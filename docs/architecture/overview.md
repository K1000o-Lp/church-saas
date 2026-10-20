# Visión de arquitectura

## Estado y alcance

Existe scaffold Laravel 12 con modelos Eloquent, contexto/middleware de tenancy y migraciones iniciales escritas. Hay autenticación web por sesión, selección de iglesia, autorización de membresía por petición, onboarding autoservicio de iglesia con membresía y monedas, backoffice exclusivo de plataforma en `/admin` con auditoría y administración de estados, planes/límites/funciones con entitlements por iglesia, y una base de frontend Inertia + React + TypeScript con login, panel de iglesias y contexto de iglesia. Faltan invitaciones, recuperación de contraseña, políticas de rol/permiso, facturación, MFA, infraestructura desplegada e integraciones productivas. No ejecutar migraciones ni intentar conexiones hasta configurar explícitamente PostgreSQL.

La plataforma objetivo es un SaaS para iglesias, con foco inicial en Venezuela y Colombia. El alcance por validar incluye gestión de personas y campos personalizados, segmentos, eventos con RSVP y asistencia, diezmos/ofrendas, consolidación pastoral, servidores y servicios/cultos, y eventualmente automatizaciones e integraciones de pago.

El documento expresa dirección y restricciones propuestas, no decisiones irreversibles. Las decisiones abiertas deben resolverse con validación de producto, seguridad y operación antes de implementarse.

## Stack objetivo

- Backend: Laravel 12 y versión de PHP compatible que se confirme al preparar el entorno.
- Base relacional: PostgreSQL.
- Frontend: Inertia v3, React 19, TypeScript estricto, Vite 7, Tailwind CSS v4 y primitivas shadcn/ui sobre Radix. El servidor conserva rutas, sesión, validación y autorización; el detalle está en [Frontend](frontend.md).
- Colas, cache, almacenamiento y correo: por seleccionar conforme a necesidades operativas y presupuesto.
- JSONB y GIN son específicos de PostgreSQL en este diseño. Migraciones, operadores, índices y consultas que los usen deben probarse contra PostgreSQL; SQLite no valida su semántica ni rendimiento. No se promete portabilidad a motores alternativos.

## Tenancy: base compartida y datos aislados lógicamente

Dirección inicial propuesta: una base compartida con esquema compartido y `church_id` en cada tabla propiedad de una iglesia (tenant-owned). Esto reduce la carga operativa para el inicio, pero exige controles estrictos de aislamiento. No es una decisión final frente a esquemas o bases por tenant; evaluar costos, volumen, requisitos de residencia y aislamiento antes de comprometerse.

Entidades globales, por ejemplo una cuenta de identidad, pueden existir fuera del tenant. La pertenencia a una iglesia debe representarse explícitamente mediante membresías y permisos. Ningún dato tenant-owned se vuelve global solo por ser visible en una interfaz compartida.

## Resolución y propagación del tenant

La resolución de iglesia para una petición debe proceder de una fuente validada (por ejemplo, membresía seleccionada y contexto de ruta/subdominio). No confiar por sí solos en un `church_id` proporcionado por el cliente. Verificar que la identidad está autenticada, que la iglesia existe, está habilitada y que la identidad pertenece a ella y tiene el permiso requerido.

La base implementada usa `CurrentChurch`, registrado como binding scoped de Laravel, y el middleware `church` (`SetCurrentChurch`). Este exige usuario autenticado y resuelve la iglesia desde la ruta (slug) o, si no hay una en la ruta, desde `current_church_id` guardado en la sesión tras una selección validada. En ambos casos vuelve a comprobar que la iglesia exista, esté `active` y que exista una membership `active` en `church_memberships`; esa tabla no usa Global Scope porque debe validar acceso antes de establecer tenant. El middleware limpia el contexto al entrar y en `finally` al terminar. Los modelos tenant-owned usan `BelongsToChurch`: sin contexto el SQL agrega condición falsa, y creación/actualización/eliminación exige contexto coincidente y bloquea cambios de `church_id` mediante denegación de autorización. El login, logout, dashboard y vista de iglesia usan sesión web y CSRF; las rutas de iglesia aplican `auth` y `church`.

Colas, tareas programadas, webhooks y comandos no heredan una petición confiable. Cada mensaje debe incluir referencias mínimas al tenant y sujeto de autorización requeridos; al ejecutarse, volver a comprobar existencia, estado y autorización conforme al caso. Para jobs se debe establecer y limpiar contexto explícitamente dentro del ciclo del job; el middleware HTTP no resuelve ese caso. Archivos, cache keys, eventos, exportaciones, notificaciones, auditoría y métricas deben quedar particionados o etiquetados por tenant. No recuperar contexto de tenant desde una variable global residual.

## Onboarding de iglesias

La creación de iglesias es **autoservicio para el usuario autenticado**; el backoffice de
plataforma administra tenants existentes, pero no es el mecanismo de alta. El usuario con
sesión inicia el onboarding en `/onboarding/church` y, en una sola transacción, se crean la
iglesia, su membresía `admin` activa, la suscripción al plan Comunidad y el catálogo de monedas
(principal como default y adicionales opcionales dentro del límite del plan inicial). Después se
guarda `current_church_id` en sesión y se redirige a la iglesia; a partir de ahí aplican las
mismas comprobaciones de membresía que en cualquier otra ruta.

Decisiones asociadas:

- El creador recibe el rol `admin` únicamente en la iglesia creada. La administración de
  plataforma usa una identidad separada, no un rol dentro de `users`.
- Un usuario con membresías activas puede crear iglesias adicionales y queda como `admin` de
  cada una. Esto evita bloquear casos de líderes con más de una iglesia.
- El país se limita inicialmente a Venezuela y Colombia; la zona horaria se valida como IANA.
  El catálogo inicial de monedas es USD, VES, COP y EUR.
- La creación es transaccional: si falla la membresía o una moneda, no queda iglesia parcial.
- Invitaciones, recuperación de contraseña, transferencia de propiedad, facturación y MFA
  quedan pendientes y no se simulan en la interfaz. Los planes se asignan automáticamente al
  crear la iglesia y los límites del plan inicial se validan también durante el onboarding.

## Backoffice de plataforma

La administración de tenants vive en `/admin` y usa una identidad **exclusiva** en
`platform_admins`, con su propio guard `platform`, login, layout y ciclo de sesión. Esa cuenta
no existe en `users`, no puede autenticarse en `/dashboard` y no tiene membresías de iglesia.
Los correos de plataforma deben ser distintos de los correos de usuarios de iglesia. Las
cuentas se crean y desactivan por comando Artisan; no hay registro público.

Alcance implementado:

- Listado, búsqueda, filtro y detalle de iglesias con metadatos y conteos agregados. No se
  exponen personas, correos, notas pastorales ni montos.
- Edición de nombre, país, zona horaria, locale y monedas. El slug es inmutable. Una moneda con
  contribuciones asociadas no se puede retirar.
- Estados `active`, `suspended`, `inactive` y `archived`, con motivo, actor y fecha. Cualquier
  estado distinto de `active` bloquea el acceso en la siguiente petición. Los miembros ven una
  página de cuenta suspendida/inactiva/archivada; los no miembros reciben 404.
- Las iglesias archivadas son de solo lectura hasta su reactivación y quedan fuera del listado
  por defecto.
- Cada cambio de metadatos o estado se registra en `platform_audit_entries` con actor, IP,
  valores anteriores y nuevos.
- No hay impersonación ni borrado físico desde el backoffice.

Provisionamiento:

```bash
php artisan platform:admin:create admin@example.com --name="Admin Plataforma"
php artisan platform:admin:deactivate admin@example.com
```

Pendiente para fases posteriores: facturación manual y purga auditada de iglesias archivadas.
MFA queda explícitamente para una fase de endurecimiento antes de producción.

## Planes, límites y funciones

La matriz aprobada se sincroniza desde `App\Support\Plans\PlanMatrix` y vive en `plans`,
`plan_limits` y `plan_features`. Cada iglesia tiene una fila en `church_subscriptions`; al crear
una iglesia recibe automáticamente **Comunidad**, y la migración de datos asigna ese plan a las
iglesias existentes. El comando `php artisan plans:sync` vuelve a aplicar la matriz de forma
idempotente y completa suscripciones faltantes.

Límites por plan (valores aprobados, base mensual para eventos y ejecuciones):

| Recurso | Comunidad | Crecimiento | Ministerio Pro |
| --- | ---: | ---: | ---: |
| Personas | 100 | 500 | 2.500 |
| Usuarios activos | 5 | 15 | 50 |
| Monedas | 1 | 4 | 8 |
| Eventos/mes | 20 | 100 | 500 |
| Workflows | 0 | 10 | 50 |
| Ejecuciones/mes | 0 | 1.000 | 10.000 |
| Integraciones | 0 | 2 | 10 |

Funciones: Crecimiento habilita multi-moneda, voluntarios y pipeline pastoral; Ministerio Pro
habilita además white-label, automatización y pagos. Comunidad no incluye funciones opcionales.

`Entitlements` resuelve primero el override de la iglesia (`church_limit_overrides`,
`church_feature_overrides`) y, si no existe, el valor del plan. Un override de límite con valor
nulo significa ilimitado; un override de función puede habilitar o deshabilitar una capacidad.
`ChurchUsage` calcula el consumo en servidor: personas no archivadas, membresías activas,
monedas, eventos creados en el mes, workflows, ejecuciones iniciadas en el mes e integraciones
(aún sin módulo, por lo que el uso es 0).

Los modelos tenant que consumen límites (`Person`, `Event`, `Workflow`, `WorkflowRun`,
`ChurchCurrency` y `ChurchMembership`) aplican el límite al crear. La edición y lectura de
registros existentes no se bloquean. Tras un downgrade, los datos permanecen y las nuevas altas
del recurso excedido se rechazan. Las funciones fuera del plan se bloquean con
`plan.feature:<feature>`; deshabilitarlas no borra datos. El backoffice de plataforma puede
gestionar suscripción y overrides con auditoría en `platform_audit_entries`. La pantalla
`/churches/{church}/plan` está disponible para roles `admin` y `finance` con plan actual, uso,
límites, funciones y estado de suscripción.

## Fail-closed y autorización

- Si falta el tenant, hay ambigüedad, membresía inválida o no se puede comprobar autorización, denegar por defecto; nunca recurrir a un tenant por omisión.
- En Laravel, aplicar un Global Scope a los modelos tenant-owned para filtrar por `church_id`. Sin un contexto de iglesia validado, la consulta debe denegar o no devolver registros; nunca omitir el scope ni usar un tenant predeterminado. Mantener además autorización explícita y filtros tenant en operaciones sensibles.
- Aplicar autorización en servidor en cada lectura y escritura, además de cualquier filtro de interfaz. Usar políticas/permisos del dominio; no considerar conocer un identificador como autorización.
- Asegurar que rutas de carga masiva, búsquedas, reportes, exportaciones, descargas, jobs y endpoints de integración respeten los mismos límites.
- Filtrar por `church_id` explícitamente en repositorios/consultas tenant-owned. Evaluar restricciones compuestas y, si procede, Row-Level Security de PostgreSQL como defensa adicional, sin reemplazar la autorización de aplicación.
- Auditar acciones sensibles con actor, tenant, objeto, hora y resultado, limitando los datos personales y secretos almacenados en la auditoría.

**Excepciones deliberadas a `church_id`:** `users` es identidad global; `platform_admins` es identidad global de backoffice; `churches` es el registro global de tenants; `church_memberships` es la tabla global de acceso/control (`church_id` indexado y relación user–church); `platform_audit_entries` referencia `church_id` sin FK para conservar historial tras una purga. Todo dominio tenant-owned implementado sí incluye `church_id`.

## CRM y campos personalizados

Las personas usarán columnas relacionales para atributos centrales y `people.custom_fields JSONB` para valores definidos por cada iglesia. `custom_field_definitions` contiene identificador, etiqueta, tipo y opciones; la aplicación validará cada valor contra la definición vigente. La migración incluye un índice GIN PostgreSQL inicial sobre `people.custom_fields`; su utilidad para operadores/consultas concretas debe validarse con consultas reales. GIN no sustituye índices relacionales ni garantiza cualquier búsqueda rápida. Las migraciones y consultas JSONB/GIN son específicas de PostgreSQL y se probarán en ese motor.

## Capas propuestas

1. **Interfaz/API:** rutas y controladores Laravel, validación de entrada, autenticación y respuestas Inertia; las páginas React son presentacionales y no contienen reglas de autorización delegadas al cliente.
2. **Aplicación:** casos de uso transaccionales, autorización, contexto tenant explícito y coordinación de trabajos.
3. **Dominio:** reglas de personas, eventos, finanzas, voluntarios, servicios y workflows; invariantes de dinero, estado e idempotencia.
4. **Persistencia e integraciones:** acceso PostgreSQL, almacenamiento, cola y adaptadores de proveedores; los adaptadores no eluden tenant ni políticas.
5. **Operación:** configuración por entorno, secretos, logs, métricas, alertas, backups y procedimientos de recuperación.

La elección concreta de patrones Laravel (por ejemplo, acciones, servicios o repositorios) queda abierta; evitar abstracciones que no resuelvan una necesidad real.

## Frontend y contrato con el servidor

El frontend usa Inertia con React y TypeScript. Las páginas viven en `resources/js/pages` y
se registran desde controladores con `Inertia::render`; no se agregan vistas Blade de
producto ni una API paralela solo para la interfaz. El root view es
`resources/views/app.blade.php`.

La fuente de verdad del estado de servidor son los props de Inertia. `HandleInertiaRequests`
comparte identidad mínima, iglesia resuelta y flash; cada controlador mapea solo los campos
que la página necesita. El cliente no envía `church_id` como prueba de acceso, no calcula
permisos ni decide montos. La validación y autorización se repiten en Laravel.

El sistema visual usa tokens semánticos en `resources/css/app.css` (verde profundo, dorado
cálido, papel cálido, claro/oscuro), Instrument Sans para UI y Fraunces para títulos. El
layout autenticado usa sidebar y topbar en escritorio, y bottom nav en móvil. Las reglas de
componentes, estado, accesibilidad y verificación están en [Frontend](frontend.md) y
[`AGENTS.md`](../../AGENTS.md).

## Operación para Venezuela y Colombia

Diseñar para datos y procesos que puedan variar por país: idioma español, zona horaria por iglesia, formatos locales, conectividad variable y monedas distintas. Guardar instantes con zona/UTC de manera coherente y mantener la zona configurada de la iglesia para presentar fechas y programar servicios. Los detalles de formatos, obligaciones de privacidad, tributación, recibos y retención requieren validación local; no asumir que una regla aplica a ambos países.

Medios candidatos por validar: Pago Móvil y Zelle para escenarios en Venezuela; Wompi, PayU y PSE para Colombia, además de otros métodos según demanda. Su disponibilidad depende de proveedor, país, titular, moneda, liquidación y requisitos contractuales/técnicos. No suponer que todos permiten cobro automatizado, reembolsos o webhook uniforme. El registro manual debe seguir siendo una opción de producto si se valida.

## Seguridad y privacidad

- Autenticación segura, sesiones y recuperación de cuenta con controles acordes al riesgo; MFA como opción a evaluar especialmente para administradores y finanzas.
- Mínimo privilegio y separación de responsabilidades para datos pastorales y financieros; definir visibilidad de notas y campos sensibles.
- Cifrar transporte y datos/respaldos según capacidades operativas; gestionar secretos fuera del control de versiones y rotarlos.
- Validar firmas, origen, replay e idempotencia de webhooks; nunca confiar en valores financieros enviados por el navegador.
- Minimizar datos personales, definir retención/eliminación, exportación y respuesta a incidentes; someter estas políticas a revisión legal local.
- Prevenir enumeración, abuso de endpoints, inyección, CSRF/XSS y exposición de archivos; limitar frecuencia y tamaño de exportaciones.

## Pruebas y calidad

Priorizar pruebas de dominio, integración y autorización, con casos entre dos iglesias distintas. Usar PostgreSQL real en CI o un servicio efímero para validar migraciones, JSONB, GIN, restricciones, transacciones y consultas específicas. SQLite no demuestra compatibilidad con PostgreSQL ni sirve para validar índices GIN. Pruebas unitarias aisladas sí pueden ser independientes del motor si no se interpretan como prueba de persistencia.

Probar jobs bajo reintento, entrega duplicada, concurrencia, contexto tenant faltante y fallos de proveedor; probar exactitud financiera y zonas horarias. Mantener fixtures y datos de prueba sintéticos, sin información pastoral o financiera real.

En frontend, `npm run typecheck` y `npm run build` son obligatorios. Las rutas y controladores Inertia se prueban con `assertInertia` sobre componente, props y sesión, no con aserciones de HTML renderizado. Las pruebas de componentes y E2E están pendientes de adopción; al incorporarlas deben cubrir login, creación de iglesia, membresías, monedas y aislamiento entre iglesias.

## Riesgos principales

- Fuga de datos por filtro tenant ausente, estado residual en workers o URL de archivos sin control.
- Ambigüedad de identidad/membresía o crecimiento de roles y permisos sin modelo explícito.
- Inconsistencias de tipo de cambio, redondeo, conciliación o liquidación entre monedas/proveedores.
- JSONB y consultas flexibles difíciles de indexar, validar y evolucionar; GIN aumenta costo de escritura/almacenamiento y requiere medición.
- Dependencia de integraciones cuya disponibilidad y operación local aún no se han verificado.
- Automatizaciones que dupliquen efectos o ejecuten acciones sin autorización, límites o posibilidad de diagnóstico.
- Requisitos regulatorios, fiscales y de privacidad distintos entre Venezuela y Colombia.

Mitigaciones: validar tempranamente con usuarios/proveedores, mantener registros financieros explícitos e inmutables/auditables, ensayar aislamiento y recuperación, medir consultas reales y lanzar automatizaciones gradualmente.

## Decisiones y preguntas abiertas

1. ¿El aislamiento compartido es adecuado para los primeros clientes o se necesitará esquema/base por iglesia para ciertos planes o requisitos?
2. ¿Cómo se resuelven dominio/subdominio, selección de iglesia por usuario y cuentas con múltiples membresías?
3. ¿Qué matriz de roles/permisos necesitan pastores, finanzas, líderes y servidores? ¿Qué datos requieren acceso especialmente restringido?
4. ¿Cuáles son país, moneda base, zona horaria y política de conversión de cada iglesia? ¿Se registra tasa, fuente, hora y monto liquidado?
5. ¿Qué proveedor y modalidad son viables por país: registro manual, redirección, API, conciliación o solo referencia externa?
6. ¿Qué reglas locales aplican a datos pastorales, donaciones, recibos, conservación y eliminación? ¿Qué asesoría local se requiere?
7. ¿Qué alcance entra al MVP y qué límites de personas/usuarios/almacenamiento se justifican por investigación?
8. ¿Qué tecnología de cola, cache y almacenamiento se elige, y qué objetivos de disponibilidad/recuperación son necesarios? El frontend ya está decidido: Inertia + React + TypeScript; queda abierto si se habilita SSR.
9. ¿Qué modelo de campos personalizados y segmentos satisface flexibilidad sin consultas difíciles de proteger o mantener?
10. ¿Qué capacidades externas podrá ejecutar un workflow y qué consentimiento, límites, revisión y mecanismo de cancelación hacen falta?

## Tiers indicativos por validar

Estas referencias son hipótesis de empaquetado y precio, no planes habilitados ni promesas comerciales:

| Tier objetivo | Hipótesis de alcance |
| --- | --- |
| Comunidad | Hasta 100 personas y límites de usuarios por definir/validar. |
| Crecimiento | USD 29–39; multi-divisa, voluntarios y pipeline pastoral. |
| Ministerio Pro | USD 79–99; white-label, automatización y objetivos de pagos/integraciones. |

Validar moneda/facturación por país, impuestos, límites exactos, funciones, costos de proveedores y disposición a pagar antes de publicar precios. Multi-divisa, white-label, automatización y pagos son objetivos por validar, no capacidades implementadas.
