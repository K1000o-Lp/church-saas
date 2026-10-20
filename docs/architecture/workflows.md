# Workflows y automatizaciones

## Propósito y estado

Diseño objetivo para automatizaciones. Hay modelos Eloquent y migraciones de persistencia iniciales para metadatos, versiones publicadas, runs y ejecuciones por nodo; el esquema se ejecutó en un PostgreSQL local de desarrollo, pero no existe runner, scheduling, jobs ni infraestructura. Instalar dependencias o ejecutar `composer setup` no debe ejecutar migraciones ni conectarse; prepara/edita `.env`, configura y verifica PostgreSQL, y solo después ejecuta migraciones manualmente. El formato y alcance aquí descritos deben validarse antes de construir el runner.

Un workflow expresa una automatización versionada como grafo dirigido de nodos y transiciones. Los primeros casos deberían ser internos y limitados; acciones externas, cobros o mensajes requieren aprobación de producto, seguridad y operación.

## Definición: grafo JSON versionado

Los metadatos y el contenido publicado se separan: `workflows` guarda nombre, tipo de trigger y estado activo; `workflow_versions` almacena el documento JSONB inmutable y su número de versión. Una edición/publicación produce otra fila en `workflow_versions`; no modifica silenciosamente las ejecuciones ya iniciadas. Un trigger PostgreSQL rechaza `UPDATE` y `DELETE` directos sobre versiones, también por query builder o SQL raw; permite el `DELETE` anidado de la cascada al borrar el workflow o la iglesia. Esa eliminación también borra sus runs y ejecuciones por nodo, por lo que elimina el historial asociado. La base exige unicidad tenant/workflow/versión y las FKs compuestas evitan versiones de otra iglesia o workflow. `TRUNCATE` y la alteración/desactivación del trigger mediante privilegios de DBA no son operaciones de aplicación y quedan fuera de esta protección.

Forma conceptual —no contrato final—:

```json
{
  "schema_version": 1,
  "trigger": { "type": "event.created", "config": {} },
  "nodes": [
    { "id": "start", "type": "condition", "config": {} },
    { "id": "follow-up", "type": "create_follow_up", "config": {} }
  ],
  "edges": [
    { "from": "start", "to": "follow-up", "when": "match" }
  ]
}
```

La validación al guardar/publicar debe comprobar versión conocida, límites de tamaño/profundidad, identificadores únicos, tipos de nodo registrados, configuración válida, referencias y estructura permitida. Rechazar nodos o expresiones desconocidas. No evaluar código arbitrario, plantillas ejecutables, SQL ni expresiones sin sandbox. Decidir si se permiten ciclos; opción inicial conservadora: prohibir ciclos y exigir límite de nodos/transiciones.

El documento de definición no contiene credenciales. Configuración de integraciones refiere a credenciales gestionadas por servidor y acotadas al tenant.

## Runs y checkpoints

- **WorkflowVersion (`workflow_versions`):** iglesia, workflow, número de versión y definición JSONB publicada inmutable; la protección contra actualización y borrado individual se aplica en PostgreSQL.
- **WorkflowRun (`workflow_runs`):** tenant y referencia compuesta a la versión exacta del workflow, trigger/evento, sujeto, estado, cursor persistido `current_node_index` (para recorridos lineales; referencias a nodos elegibles/checkpoints si hay ramas), clave de correlación, tiempos, resultado y motivo de terminación.
- **NodeExecution/Checkpoint:** un registro por ejecución lógica de nodo, estado (pendiente, en cola, ejecutando, esperando, completado, fallido, cancelado), número de intentos, tiempos, resultado mínimo y error normalizado.
- **Transición:** el estado persistido del checkpoint determina los siguientes nodos elegibles. El run solo finaliza cuando sus ramas terminan, fallan según política o se cancela.

Persistir checkpoint antes de despachar el trabajo siguiente. Manejar publicación y persistencia de jobs de forma fiable; evaluar patrón outbox si una transacción y el broker no pueden coordinarse. Al recuperar tras caída, reanudar desde nodos durables no completados en vez de repetir todos los efectos a ciegas.

## Un job por nodo e idempotencia

Cada nodo ejecutable se despacha como trabajo acotado, con referencia a run, ejecución de nodo, tenant, definición/version y contexto mínimo. Al terminar un nodo correctamente, se persiste el checkpoint/cursor y se auto-despacha otro job para el siguiente nodo elegible. Si el job falla, reintentar desde el checkpoint del nodo fallido, no reiniciar el grafo. El worker vuelve a comprobar que los registros existen, siguen habilitados y pertenecen al tenant indicado. No depender de sesión HTTP ni estado estático para reconstruir contexto.

La entrega de jobs puede ser al menos una vez. Los handlers deben tolerar duplicados: generar clave de idempotencia estable a partir de run, nodo lógico y operación; persistir resultado/efecto y transición de forma transaccional cuando sea posible. Para proveedores externos, enviar clave si la API lo soporta y reconciliar el estado si el resultado de red es ambiguo. La idempotencia reduce duplicados, pero no equivale a una garantía global de “exactamente una vez”.

Definir por nodo límite de intentos, backoff con jitter, errores reintentables/no reintentables y expiración. No reintentar indefinidamente errores permanentes, rechazos de autorización o datos inválidos. Evitar que cada reintento duplique notificaciones, registros financieros u otros efectos.

## Delays y temporizadores

Una espera/delay no debe dormir ni retener un worker. Persistir `available_at`/instante objetivo y estado de espera; un scheduler o mecanismo de cola vuelve a despachar el nodo cuando corresponde. Guardar instantes de forma consistente y calcular la presentación según zona de la iglesia. Definir comportamiento ante cambio de zona, cambio de horario del evento, pausa o cancelación del workflow.

Una espera externa (por ejemplo, respuesta RSVP) debería reanudarse por evento correlacionado con run/nodo y vencer en un plazo explícito, no mediante polling ilimitado. Proteger la reanudación frente a eventos duplicados o tardíos.

## Concurrencia y orden

Controlar concurrencia con locks o claims durables de checkpoint y transiciones atómicas. Definir lease/timeout para workers caídos y recuperación de locks vencidos. No permitir que dos workers completen simultáneamente una ejecución lógica y generen dos efectos.

Una definición puede admitir paralelismo entre ramas solo si se define cómo se espera y combina el resultado. Establecer límites por tenant, por workflow y globales para proteger proveedores y workers. Evitar orden global salvo que el dominio lo requiera; para límites compartidos (por ejemplo, asignaciones de voluntarios) hacer cumplir la regla en el dominio y base de datos, no confiar en el grafo.

## Errores, cancelación y observabilidad

- Registrar transiciones de estado, intentos, duración, causa normalizada y correlación, con referencias a tenant/run/nodo.
- Distinguir errores transitorios de permanentes y errores de configuración/autorización; exponer motivo comprensible a usuarios autorizados.
- Definir salida de fallo: detener rama/run, ruta de compensación permitida o intervención manual. No asumir que todo efecto externo es reversible.
- Permitir cancelación y pausa con semántica explícita; cambios no deben dejar jobs antiguos ejecutándose sin verificar estado.
- Medir runs pendientes/atascados, reintentos, tasas de error, demora y saturación por tenant y proveedor. Alertar sin incluir datos personales o secretos.
- Ofrecer inspección y reintento manual limitado, autorizado y auditado; reintentar desde checkpoint requiere conservar idempotencia y mostrar posibles efectos.
- Definir retención de payloads/historial y límites de almacenamiento; guardar en logs solo datos mínimos/redactados.

## Handlers y adapters

Separar el motor (validación, scheduling, transición y estado) de handlers tipados y pequeños. Un handler debe validar input, comprobar tenant/autorización, respetar límites temporales/de recursos y devolver resultado serializable de tamaño acotado.

Handlers internos posibles, sujetos a priorización: comprobar condición permitida, crear tarea de seguimiento, cambiar estado de una entidad autorizada o esperar evento. Cada uno debe usar casos de uso del dominio, no escribir tablas arbitrariamente.

Los adaptadores de correo, mensajería y pago encapsulan APIs, credenciales, firmas, rate limits, idempotencia y errores del proveedor. Mantener secretos en un almacén/configuración del servidor; workflows guardan referencias, no secretos. Validar firma y replay de webhooks y mapear eventos externos a un contrato interno. No interpretar una notificación de pago como liquidación definitiva sin confirmación del proveedor.

## Seguridad

- Solo usuarios con permiso específico pueden crear, publicar, activar, pausar, inspeccionar o reintentar workflows en su iglesia.
- Cada ejecución queda vinculada a iglesia y sujeto autorizados; fallo de contexto o de permiso termina en denegación, nunca en ejecución global.
- Limitar tipos de nodo, destinos de red, tamaño/frecuencia, duración y consumo. Evitar SSRF, ejecución de código y exfiltración por configuración de URL arbitraria.
- Acciones financieras, exportaciones, borrados y mensajes masivos requieren controles reforzados, consentimiento aplicable y posiblemente confirmación humana; quedan fuera del alcance inicial hasta definir política.
- No filtrar notas pastorales, datos financieros, tokens ni payloads sensibles a logs, historial de ejecución, métricas o mensajes de error.
- Al despachar, volver a comprobar estado de tenant, permisos y estado del workflow según la operación; revocaciones deben tener una semántica clara para trabajos ya encolados.

## Pruebas requeridas antes de habilitar

Validar formato/versiones y rechazo de grafos inválidos; autorización y aislamiento entre iglesias; entrega duplicada; caída entre efecto y checkpoint; reintentos y backoff; delay sin ocupación del worker; eventos tardíos/duplicados; dos workers concurrentes; cancelación; fallo/revocación del tenant; límites de tasa; redacción de secretos. Probar persistencia y locking contra PostgreSQL real una vez configurado. SQLite no valida características PostgreSQL como JSONB/GIN ni sustituye pruebas de concurrencia del motor objetivo.

## Decisiones abiertas

1. ¿Qué editor/formato de grafo y esquema de validación se usarán? ¿Cómo se versionan y migran definiciones?
2. ¿Se prohíben ciclos inicialmente y cuál será el máximo de nodos, profundidad, duración y ejecuciones concurrentes?
3. ¿Qué broker/scheduler se elige y se requiere outbox para despacho fiable?
4. ¿Qué política de reintentos, leases, retención, pausa, cancelación y compensación corresponde a cada clase de acción?
5. ¿Cuáles son los primeros casos de uso y qué acciones externas requieren consentimiento o aprobación humana?
6. ¿Qué identidad/permisos se evalúan al publicar y al ejecutar si el actor pierde acceso después?
7. ¿Qué datos de payload/checkpoint se retienen, quién puede inspeccionarlos y por cuánto tiempo?
8. ¿Qué proveedores locales se validan y qué garantías de idempotencia/webhook/liquidación ofrecen?
