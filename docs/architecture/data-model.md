# Modelo de datos propuesto

## Alcance y convenciones

Las migraciones Laravel iniciales están escritas para las entidades descritas abajo y se ejecutaron en un PostgreSQL local de desarrollo. Se pueden revisar y evolucionar como código; **no ejecutar migraciones contra un entorno compartido o desplegado hasta configurar y verificar su PostgreSQL**. Es un esquema MVP inicial, no un modelo de dominio final aprobado.

La propuesta parte de PostgreSQL con base/esquema compartidos y aislamiento lógico por iglesia. Cada tabla tenant-owned debe tener `church_id` obligatorio e indexado. En general, usar índices compuestos que comiencen por `church_id` para accesos frecuentes y restricciones únicas compuestas (`church_id`, clave de negocio). La forma final de cada índice depende de consultas medidas.

Convenciones a validar antes de implementar:

- Identificadores consistentes (UUID o bigint por decidir); claves foráneas y restricciones explícitas.
- Timestamps de creación/actualización; UTC para instantes y zona IANA configurable en la iglesia para presentación y planificación.
- Soft delete solo cuando el dominio requiera recuperación; definir retención y borrado real por separado.
- Estados mediante valores controlados e invariantes validados en servidor; guardar actor y motivo en cambios sensibles.
- `church_id` no se acepta como prueba de autorización. Consultas, constraints y políticas deben prevenir cruces de tenant.
- Los modelos tenant-owned exigen contexto coincidente para crear, actualizar y borrar; el borrado verifica el `church_id` original y no permite mutar la pertenencia.
- Nombres sugeridos, no definitivos. Evitar duplicar datos calculables sin estrategia de consistencia.

## Entidades y relaciones

### Identidad y tenant

- **Church:** iglesia/tenant; nombre, slug único, país, zona horaria, moneda primaria, locale (por defecto `es`), settings JSONB y estado. `settings` debe ser un objeto JSON y `status` está acotado a `active`, `inactive`, `suspended`, `archived`. También guarda `status_reason`, `status_changed_at`, `status_changed_by_platform_admin_id` y `archived_at` para la administración de plataforma. País, zona horaria, locale y monedas requieren validación de reglas locales. El slug es inmutable desde el backoffice.
- **`church_currencies`:** códigos de moneda admitidos por iglesia, marca `is_default` y unicidad por iglesia/código. Un índice único parcial garantiza como máximo un default por iglesia. `churches.currency_code` sigue siendo una invariante de aplicación y no una FK, para evitar una dependencia circular entre ambas tablas.
- **User:** identidad autenticable; puede ser miembro de más de una iglesia si producto lo requiere.
- **PlatformAdmin:** identidad exclusiva del backoffice de plataforma; nombre, correo único, contraseña, estado activo y último acceso. Vive fuera de `users`, usa el guard `platform` y no tiene membresías de iglesia. Su correo debe ser distinto de los correos de `users`.
- **ChurchMembership:** relación user–church, estado y rol asignado. El rol está acotado a `admin`, `pastor`, `finance`, `leader`, `volunteer` o `member`; los permisos se derivan de la matriz de `RolePermissions`. No confundir pertenencia con rol global.
- **ChurchInvitation:** iglesia, correo normalizado, rol, hash SHA-256 del token, estado (`pending`, `accepted`, `revoked`, `expired`), actor que invita, actor que acepta y fechas. Un índice único parcial impide dos invitaciones pendientes para el mismo correo en una iglesia. No usa Global Scope para poder resolver el token antes de establecer contexto; los controladores siempre filtran por iglesia.
- Excepciones sin `church_id`: `users`, `platform_admins` y `churches` son globales; `church_memberships` es tabla de acceso/control global, tiene `church_id` indexado y unicidad por iglesia/usuario, y no tiene scope de tenant para permitir la comprobación previa a establecer el contexto.
- **Role/Permission:** roles fijos y permisos definidos en código (`App\Support\Auth`), resueltos por membresía. No hay roles personalizados por iglesia en esta fase.
- **PlatformAuditEntry:** actor de plataforma, `church_id` nullable sin FK para conservar historial tras una purga, acción, referencia al objeto, metadatos JSONB en forma de objeto, IP, user agent e instante. Registra cambios de metadatos, estados, suscripciones y overrides; no debe copiar secretos ni contenido pastoral.
- **ChurchAuditEntry:** auditoría de dominio tenant-owned con `church_id` FK en cascada, actor de usuario/rol, acción, sujeto polimórfico por tipo/id, metadata JSONB saneada, IP, user agent y `created_at`. No copia notas pastorales, contraseñas, tokens ni montos sensibles. Se elimina con la iglesia; la auditoría de plataforma conserva la purga.
- **Plan / PlanLimit / PlanFeature:** catálogo global de planes. `plans.key` es único y guarda nombre, descripción, precio mensual en unidad menor, moneda, intervalo y orden. `plan_limits` define un valor por recurso y plan; `limit_value NULL` significa ilimitado. `plan_features` enumera las funciones incluidas. La matriz inicial se carga desde `PlanMatrix` y se puede reaplicar con `plans:sync`.
- **ChurchSubscription:** plan vigente de una iglesia, uno por iglesia (`church_id` único), con estado `active`, `past_due` o `cancelled`, fechas de inicio/cambio/fin, notas y actor de plataforma. No usa scope tenant para que el backoffice pueda consultarla; la autorización se aplica en controladores y servicios.
- **ChurchLimitOverride / ChurchFeatureOverride:** ajustes por iglesia que reemplazan el valor del plan. Únicos por `(church_id, resource)` y `(church_id, feature)`. Un límite `NULL` es ilimitado; una función puede forzarse habilitada o deshabilitada sin borrar datos. Registran actor y nota.

### `people`, CRM y consolidación pastoral

- **`people`:** registro de contacto/persona de una iglesia: datos de contacto, estado y preferencias necesarias, más `custom_fields JSONB` para valores flexibles. `custom_fields` debe ser un objeto JSON y `status` está acotado a `active`, `inactive`, `archived`. La política de duplicados y consentimiento queda abierta.
- **PersonRelationship** (opcional): relaciones familiares u otras, tipadas y limitadas por permisos; no inferir parentescos.
- **PastoralNote/FollowUp:** seguimiento, responsable, estado y fechas; notas con acceso más restrictivo que el perfil general. Retención y visibilidad por definir.
- **`custom_field_definitions`:** nombre interno estable, etiqueta visible, tipo, configuración, obligatoriedad y opciones de una definición personalizada por iglesia. `type` está acotado inicialmente a `text`, `textarea`, `number`, `date`, `boolean`, `select`, `multiselect`; `options` debe ser `NULL`, arreglo u objeto. Validar los valores JSONB en aplicación contra las definiciones vigentes y definir cómo evolucionan o se retiran campos.

El diseño requiere un índice GIN en `people.custom_fields` para filtros JSONB. La clase/opciones del índice se seleccionará según los operadores y consultas previstos y se probará con datos representativos en PostgreSQL real. Este índice no sustituye columnas tipadas para atributos centrales, relaciones o datos sujetos a restricciones fuertes.

### `segments`

- **`segments`:** nombre único por iglesia, descripción, tipo (`dynamic` o `static`), estado y reglas. Los dinámicos exigen `rules` JSONB y los estáticos lo prohíben; una restricción CHECK mantiene esa coherencia. El tipo no se convierte después de crear el segmento.
- **Reglas dinámicas:** objeto JSON versionado `{version, match, conditions[]}` con `match` en `all`/`any`. Cada condición referencia un campo permitido (`person` o `custom`), un operador válido para su tipo y un valor normalizado. No se ejecuta SQL libre: el servidor valida estructura, claves, operadores y valores contra un catálogo por iglesia. El formato admite grupos anidados en el futuro sin cambiar los datos existentes.
- **`segment_members`:** membresía explícita para segmentos estáticos: `church_id`, `segment_id`, `person_id`, `added_by` y timestamps, con unicidad por iglesia/segmento/persona y FKs compuestas que impiden cruces de tenant. Las personas archivadas se conservan en el segmento y se muestran con advertencia.
- **Evaluación:** los dinámicos se calculan al leer (sin materialización) y excluyen siempre a las personas archivadas. Los estáticos solo usan `segment_members`; no se recalculan. `event_segments` puede asociar segmentos de ambos tipos.
- **Reglas y rendimiento:** los filtros de campos personalizados usan contención JSONB (`custom_fields @> ?::jsonb`) cuando aplica y comparaciones tipadas con guardas de `jsonb_typeof`; las consultas concretas deben medirse contra PostgreSQL.

### `events`, `event_segments`, RSVP y asistencia

- **`events`:** iglesia, título, descripción, lugar/modo, inicio/fin, zona horaria operativa y estado. `status` está acotado a `draft`, `published`, `cancelled`, `completed`; si ambos instantes existen, `ends_at >= starts_at`.
- **`event_segments`:** pivote tenant-owned que asocia un evento con un segmento para invitación/RSVP focalizado. Incluir `church_id`; las claves foráneas compuestas impiden asociaciones entre iglesias distintas.
- **`event_registrations` / RSVP:** persona o invitado, respuesta/estado, cantidad de acompañantes y timestamps de cambios. Exige persona o `guest_name` no vacío, `guest_count >= 0` y `status` acotado a `registered`, `confirmed`, `declined`, `waitlisted`, `cancelled`. La clave `(church_id,event_id,id,person_id)` permite verificar la persona del RSVP desde asistencia.
- **`attendance_records`:** evento, persona/inscripción opcional, estado de asistencia, origen, hora y actor. Exige persona o RSVP; `status` está acotado a `present`, `absent`, `late`, `excused` y `source` a `manual`, `import`, `device`. La FK compuesta `(church_id,event_id,event_registration_id,person_id)` exige que, cuando ambos IDs están presentes, la persona coincida con la del RSVP. La FK de tres columnas sigue validando iglesia/evento incluso con persona `NULL` (`MATCH SIMPLE`). Hay unicidad parcial por evento/persona y por evento/RSVP. `recorded_by` debe ser miembro de la iglesia.
- **EventSession** (opcional): sesiones o turnos de un evento; relación con asistencias cuando el caso de uso lo necesite.

Queda abierta la política para invitados no registrados (la unicidad con `person_id NULL` no deduplica invitados) y el tratamiento de cambios de horario, cancelación y acceso restringido a listas.

### Diezmos, ofrendas y registros financieros

- **`funds`:** fondos tenant-owned con nombre y estado, creados antes de contribuciones y con claves únicas `(church_id,id)` y `(church_id,name)`. `status` está acotado a `active`, `archived`.
- **`contributions`:** registro financiero de diezmo/ofrenda, persona y fondo opcionales, fecha efectiva, estado, referencia de origen, importe no negativo y código de moneda explícito. La FK compuesta del fondo impide asociar un fondo de otra iglesia; `(church_id,currency_code)` debe existir en `church_currencies`; `amount_minor >= 0` está declarado explícitamente para PostgreSQL (no se confía en `unsignedBigInteger`). `status` está acotado a `recorded`, `voided`, `reconciled` y `method` a `cash`, `transfer`, `card`, `mobile_payment`, `other`.
- **Futuro PaymentAttempt de proveedor** (si se integra cobro automático): proveedor, identificador externo, estado, monto solicitado/recibido y eventos de proveedor; no guardar datos de tarjeta que no se deban custodiar.
- **ExchangeRate/ConversionRecord** (solo si se habilita conversión): monedas de origen/destino, tasa, fuente, instante, regla de redondeo y vínculo con montos originales/liquidados.
- **Reconciliation/Settlement** (según operación validada): agrupación y diferencia de registros con liquidaciones del proveedor o extractos.

### Facturación manual de plataforma

- **`invoices`:** factura de plataforma ligada a una iglesia, con `church_id` nullable y snapshot `church_name`/`church_slug`. `number` es único y se genera al emitir; `status` está acotado a `issued`, `paid`, `void`, `overdue`; `currency_code` es USD y `amount_minor > 0`. Guarda concepto, notas, emisión, vencimiento, pago, anulación, motivo y actor de plataforma. La FK `church_id` usa `nullOnDelete` para conservar historia tras una purga.
- **`payments`:** pago manual de una factura con `amount_minor > 0`, USD, método (`cash`, `transfer`, `card`, `mobile_payment`, `other`), referencia, notas, fecha y actor. `invoice_id` cascadea con la factura; `church_id` usa `nullOnDelete`. El total pagado se suma en servidor y nunca se acepta un pago por encima del saldo.
- **Estados:** una factura emitida con vencimiento pasado pasa a `overdue` al consultar facturación. Admite pagos parciales; al cubrir el total pasa a `paid`. `paid` no se anula y `void` es terminal en esta fase. La transición y el registro del pago se ejecutan con bloqueo de la factura.
- **Purga:** `churches:purge --date=YYYY-MM-DD [--dry-run]` elimina datos tenant y la iglesia; `platform_audit_entries` no tiene FK y se conserva, mientras facturas y pagos quedan sin `church_id` pero mantienen el snapshot y la relación entre ambos. El comando registra `church.purged` con conteos antes del borrado.

**Invariantes monetarias:** almacenar montos como enteros en la unidad menor de la moneda (por ejemplo, centavos cuando corresponda), junto a código de moneda explícito. No usar `float`/punto flotante para dinero. Confirmar exponente/unidad menor por moneda. Sumas y comparaciones se hacen dentro de la misma moneda; conversión siempre explícita, preservando importe original y registro de tasa, fuente, fecha y redondeo cuando aplique. No inferir tasa de cambio ni mezclar monedas en un total sin conversión documentada.

Los pagos locales —Pago Móvil, Zelle, Wompi, PayU, PSE u otros— son candidatos por validar. La entrada manual puede ser distinta de un cobro procesado y debe quedar identificada como tal. Reglas fiscales, recibos, anulaciones y conciliación dependen de validación local y contable.

### Pipeline pastoral, voluntarios y servicios

- **PipelineStage:** etapas configurables por iglesia para seguimiento de personas/procesos pastorales; nombre único por iglesia, `position >= 0` y transiciones por definir.
- **PipelineRecord/Opportunity:** sujeto/persona, etapa, responsable, fechas, fuente y resultado; definir uso de términos CRM evitando implicar ventas si el dominio es pastoral.
- **VolunteerProfile** (o atributos/permisos de Person): disponibilidad, habilidades y preferencias estrictamente necesarias.
- **Service:** servicio/culto programado, hora, ubicación y estado. `service_plans` acota `status` a `draft`, `published`, `completed`, `cancelled` y exige `ends_at >= starts_at` cuando ambos existen.
- **ServiceTeam/MinistryTeam:** equipo ministerial o función con ámbito de iglesia, nombre único por iglesia.
- **ServiceAssignment:** persona/voluntario, servicio, rol/equipo, estado de confirmación y restricciones de concurrencia. `service_items.position >= 0`; `volunteer_assignments.status` se acota a `invited`, `confirmed`, `declined`, `cancelled`.

Validar si pipeline, voluntariado y equipo son módulos separados o extensiones de persona/servicio. Las asignaciones deben soportar cancelación, suplencia y disponibilidad sin exponer información innecesaria.

### Workflows

- **`workflows`:** metadatos tenant-owned (nombre, disparador, estado activo); no contiene definición publicada.
- **`workflow_versions`:** definición JSONB publicada e inmutable con número de versión, FK tenant/workflow y unicidad `(church_id,workflow_id,version)`. `version >= 1` y `definition` debe ser un objeto JSON. Un trigger PostgreSQL impide `UPDATE` y `DELETE` directos, incluidos query builder y SQL raw; permite borrar en cascada al eliminar workflow/iglesia. `TRUNCATE` y privilegios de DBA para alterar o desactivar el trigger no son operaciones de aplicación y no están cubiertos.
- **`workflow_runs`:** referencia compuesta a la versión exacta del mismo workflow y tenant, estado, cursor `current_node_index`, inicio/fin y datos de correlación. La FK impide asignar una versión perteneciente a otro workflow o iglesia. `current_node_index >= 0` y `status` se acota inicialmente a `pending`, `running`, `completed`, `failed`, `cancelled`.
- **WorkflowNodeExecution/Checkpoint:** ejecución por nodo, intentos, estado, entrada/salida acotada, error normalizado, tiempos y clave de idempotencia. `attempts >= 0` y `status` se acota a `pending`, `queued`, `running`, `waiting`, `completed`, `failed`, `cancelled`.
- **WorkflowEvent/Outbox** (opcional según arquitectura de entrega): eventos de transición para coordinación durable y publicación fiable de jobs.

No duplicar payloads sensibles en historial; definir expiración y acceso a ejecuciones. Especificación operativa en [Workflows](workflows.md).

## JSONB, GIN e índices

JSONB puede servir para settings, valores personalizados, reglas de segmentos y definiciones versionadas de workflows, siempre con validación de esquema y límites de tamaño/profundidad. GIN puede acelerar operadores/consultas compatibles sobre ciertos documentos, pero no es automático: definir consultas concretas, medir tamaño y costo de escritura, y crear índices selectivos.

**JSONB y GIN son específicos de PostgreSQL en las migraciones escritas.** `people.custom_fields` tiene GIN creado con SQL PostgreSQL explícito en `up/down`; SQLite no puede validar ese índice ni las migraciones/consultas JSONB. Ejecutar y probar contra PostgreSQL real después de preparar el entorno. No prometer portabilidad a SQLite u otro motor. Preferir columnas relacionales tipadas para relaciones, filtros frecuentes, autorización y restricciones de integridad.

## Reglas de integridad implementadas

- Identidad de RSVP: persona o `guest_name` no vacío; `guest_count >= 0`.
- Asistencia: persona o RSVP; coincidencia de persona con el RSVP cuando ambos IDs están presentes; unicidad parcial por evento/persona y por evento/RSVP.
- Rangos temporales no invertidos en eventos y planes de servicio.
- Enteros no negativos en posiciones, versión de workflow, cursor de run e intentos.
- Moneda de contribución existente en `church_currencies` y máximo un default por iglesia.
- Responsables (`assigned_to`, `recorded_by`) deben ser miembros de la iglesia mediante FK compuesta.
- Nombres únicos por iglesia en fondos, equipos ministeriales y etapas de pipeline.
- JSONB de configuración/definición debe ser objeto donde corresponde (`churches.settings`, `people.custom_fields`, `segments.rules`, `workflow_versions.definition`).
- Catálogos iniciales de estado para iglesias, membresías, personas, eventos, RSVP, asistencia, contribuciones, fondos, seguimiento, servicios, voluntarios y workflows. Son valores iniciales, no una decisión de producto cerrada; ampliarlos requiere una migración. `church_memberships.role` y `workflows.trigger_type` siguen libres hasta definir roles y tipos de disparador.
- No se impone unicidad de `position` en etapas/ítems de servicio: el default `0` y la reordenación harían conflictos prematuros. Queda como invariante de aplicación.
- Planes: recursos acotados (`people`, `active_users`, `currencies`, `events_per_month`, `workflows`, `executions_per_month`, `integrations`) y funciones acotadas (`multi_currency`, `volunteers`, `pastoral_pipeline`, `white_label`, `automation`, `payments`); valores de límite no negativos o `NULL` para ilimitado.
- Suscripción única por iglesia y asignación automática del plan Comunidad al crear la iglesia; la migración de datos completa iglesias previas.
- Límites duros en creación para personas, usuarios activos, monedas, eventos, workflows y ejecuciones. La edición y la lectura existentes no se bloquean; un downgrade conserva datos y bloquea nuevas altas del recurso excedido.
- Facturación: monto positivo, USD explícito, estados acotados, pagos positivos y método acotado. El saldo, las transiciones y el paso automático a `overdue` se validan en servidor; no se aceptan sobrepagos.
- Purga: solo iglesias `archived` con `archived_at` hasta la fecha de corte. Conserva auditoría, facturas y pagos; elimina membresías, personas y demás datos tenant.
- La auditoría de dominio cubre personas, campos personalizados, membresías e invitaciones. Soft delete, RLS y validación semántica profunda de JSONB siguen pendientes. La auditoría de plataforma cubre cambios de iglesia, estados, suscripciones, overrides, facturación y purga.

## Decisiones pendientes

- Tipo de ID, política de unicidad y eliminación/retención por entidad.
- Modelo para miembros con múltiples iglesias, identidad y roles/permisos.
- Esquema y reglas para evolución de `people.custom_fields` y sus definiciones; el almacenamiento objetivo de valores personalizados es JSONB.
- Segmentos: grupos anidados de reglas y materialización/caché para escala.
- Manejo de invitados, recurrencias, sesiones y zonas horarias en eventos/servicios.
- Catálogo de moneda, unidad menor, moneda base y política de conversión/conciliación.
- Convención/validación de códigos de moneda ampliados y sincronización entre `churches.currency_code` y la fila default de `church_currencies`; el máximo de un default por iglesia ya está garantizado por índice único parcial.
- Si pagos se registran manualmente, se procesan mediante proveedor o ambos; datos que cada proveedor permite conservar.
- Relación entre persona, perfil de voluntario, miembros de equipos, roles de servicio y pipeline.
- Retención, auditoría y permisos especiales de notas pastorales y datos financieros.
- Necesidad y diseño de outbox, RLS de PostgreSQL y particionamiento según escala/amenaza.
