# Plan de implementación

Este plan ordena el trabajo desde la validación del problema hasta una primera operación segura. El scaffold Laravel 12, modelos base, contexto tenant, migraciones iniciales y la base de frontend Inertia + React + TypeScript ya están escritos; el esquema se ejecutó en un PostgreSQL local de desarrollo, mientras la infraestructura de despliegue sigue pendiente. Instalar Composer o ejecutar `composer setup` no debe migrar ni conectarse a PostgreSQL: primero se crea/edita `.env` con credenciales explícitas y se prepara/verifica PostgreSQL; la migración es un paso manual posterior.

## Fases y dependencias

### 0. Validación de producto y decisiones de alcance

**Depende de:** nada.

**Trabajo y entregables:**
- Entrevistar iglesias y responsables pastorales/financieros en Venezuela y Colombia; documentar casos de uso, conectividad, moneda de registro, procesos de diezmos/ofrendas y cumplimiento aplicable.
- Priorizar flujos de personas, seguimiento pastoral, eventos, asistencia, servicios y finanzas; separar MVP de objetivos posteriores.
- Validar disposición a pagar, límites por tier, quién configura la organización y qué reportes necesita.
- Registrar decisiones y preguntas abiertas en la documentación.

**Criterios de aceptación:** cada flujo prioritario tiene actor, problema, recorrido esperado y criterio de éxito; alcance inicial y exclusiones están aprobados por responsables de producto; precios, medios de pago e integraciones siguen identificados como hipótesis hasta obtener evidencia.

### 1. Diseño de dominio, tenancy y seguridad

**Depende de:** fase 0.

**Trabajo y entregables:**
- Aprobar el modelo de tenant, identidad, membresías, roles y permisos, ciclo de vida de una iglesia, monedas admitidas, fondos y política de acceso/borrado de datos.
- Definir límites de aislamiento para consultas, trabajos en cola, archivos, logs, reportes y futuras integraciones.
- Especificar requisitos de privacidad, auditoría, retención, copias de seguridad y respuesta a incidentes para los países objetivo, con revisión competente cuando corresponda.
- Convertir el modelo propuesto en un diseño lógico y un catálogo de invariantes.

**Criterios de aceptación:** toda operación sobre datos de iglesia tiene una ruta de autorización definida; existen casos de abuso y pruebas negativas previstas; se han resuelto o asignado las decisiones críticas de tenancy y permisos.

### 2. Preparación técnica y prueba de PostgreSQL

**Depende de:** fases 0 y 1.

**Trabajo y entregables:**
- Definir versiones soportadas de PHP, Laravel 12, PostgreSQL y dependencias; preparar configuración local, de prueba y despliegue sin secretos en el repositorio.
- Crear un entorno PostgreSQL desechable para desarrollo/CI antes de ejecutar migraciones o validar persistencia.
- Probar en PostgreSQL real las capacidades elegidas, incluidas JSONB, índices GIN, restricciones e índices compuestos previstos.
- Definir logging, colas, almacenamiento, correo, monitoreo, backup y restauración iniciales.

**Criterios de aceptación:** aplicación y entorno de prueba se conectan únicamente después de configurar credenciales seguras; migraciones y consultas críticas se ejecutan en PostgreSQL; se documentan versiones y procedimientos reproducibles. SQLite puede usarse para pruebas aisladas que no dependan de comportamiento PostgreSQL, pero no como validación de JSONB/GIN.

### 3. Fundamentos de aplicación y acceso

**Depende de:** fase 1 para diseño. La fase 2 es precondición para validar persistencia e integración contra la base objetivo, no para diseñar ni escribir código/migraciones.

**Trabajo y entregables:**
- Autenticación web por sesión, dashboard, selección de iglesia, verificación de membresía por petición, onboarding autoservicio de iglesia (membresía `admin` y monedas), fase 1 del backoffice de plataforma (`/admin`, identidad exclusiva, estados, metadatos y auditoría), fase 2 de planes, límites, uso, entitlements y overrides, y facturación manual con purga auditada ya están implementados sobre Inertia + React + TypeScript; quedan invitaciones, recuperación de contraseña, roles/permisos formales, MFA y la aplicación de límites en los módulos tenant aún no construidos.
- Mantener el contrato de props Inertia: identidad mínima, iglesia resuelta y flash; cada controlador mapea solo los campos necesarios. Ver [Frontend](architecture/frontend.md) y [`AGENTS.md`](../AGENTS.md).
- Extender la resolución de tenant por petición, contexto explícito en procesos asíncronos y autorización fail-closed.
- Incorporar políticas/gates, auditoría para acciones sensibles y pruebas de aislamiento entre iglesias.

**Criterios de aceptación:** petición sin tenant válido, usuario sin membresía o contexto ambiguo no accede a datos tenant-owned; pruebas intentan leer, modificar y borrar registros de otra iglesia y son rechazadas; jobs no dependen de contexto implícito de la petición; las páginas Inertia declaran componente y props en pruebas y `npm run typecheck`/`npm run build` pasan.

### 4. MVP pastoral y operación de iglesia

**Depende de:** fase 3.

**Trabajo y entregables:**
- ~~Creación de iglesia, membresías y monedas admitidas, con validación en servidor y pantallas Inertia.~~ Implementado como onboarding autoservicio: el creador obtiene membresía `admin` y la iglesia, membresía y monedas se guardan en una transacción. Ver [Onboarding de iglesias](architecture/overview.md#onboarding-de-iglesias).
- ~~Backoffice de plataforma fase 1.~~ Implementado en `/admin` con identidad exclusiva, listado/detalle/edición de metadatos, monedas, estados, archivado y auditoría. Ver [Backoffice de plataforma](architecture/overview.md#backoffice-de-plataforma).
- ~~Planes, límites y funciones.~~ Implementados con matriz aprobada, suscripción por iglesia, uso en servidor, resolución de overrides, límites duros de creación, downgrade sin borrado, backoffice de suscripción/overrides y pantalla de plan para `admin`/`finance`. Ver [Planes, límites y funciones](architecture/overview.md#planes-límites-y-funciones).
- ~~Facturación manual y purga auditada.~~ Implementadas facturas y pagos en USD con estados `issued`, `overdue`, `paid` y `void`, backoffice de emisión/pagos/estados, historial para `admin`/`finance` y comando `churches:purge` con fecha de corte, `--dry-run`, auditoría y conservación de historial. Ver [Facturación manual](architecture/overview.md#facturación-manual). Quedan impuestos, PDF, cobro automático y conciliación.
- Personas, contactos y campos personalizados con validación y permisos.
- Segmentos, inicialmente con alcance acotado y reglas explicables; eventos, RSVP, registro de asistencia y seguimiento pastoral.
- Servicios/cultos, equipos de servidores y asignaciones básicas según priorización.
- Importación/exportación controlada y manejo de duplicados, si se valida como necesidad del MVP.

**Criterios de aceptación:** los usuarios pueden completar los flujos validados sin mezclar datos de tenants; cambios y permisos sensibles se auditan; se prueban límites de acceso, validación, importación y comportamiento con datos representativos.

### 5. Finanzas y experiencia multi-divisa

**Depende de:** fases 3 y 4; además de validación financiera y regulatoria.

**Trabajo y entregables:**
- Registro de diezmos y ofrendas asociados opcionalmente a fondos tenant-owned, y conciliación manual inicial, con montos no negativos enteros en unidad menor y moneda explícita; configurar catálogo de monedas admitidas por iglesia.
- Reportes por período, moneda y método; correcciones mediante registros auditables, no edición opaca de historia.
- Evaluar Pago Móvil y Zelle en Venezuela, y Wompi, PayU, PSE u otros medios adecuados en Colombia. Diseñar adaptadores solo después de confirmar disponibilidad, condiciones, flujo de liquidación y requisitos de cada proveedor.

**Criterios de aceptación:** no se mezclan montos de monedas distintas sin conversión explícita y trazable; exactitud de importes y redondeo está probada; integraciones no se presentan como disponibles antes de validación, pruebas y aprobación operativa.

### 6. Automatizaciones y escalamiento de operación

**Depende de:** fases 3–5 y de una decisión explícita sobre alcance del motor de workflows.

**Trabajo y entregables:**
- Implementar gradualmente el grafo JSON versionado, validación, runs, checkpoints y jobs por nodo descritos en [Workflows](architecture/workflows.md).
- Empezar con handlers internos acotados; agregar proveedores/adaptadores únicamente con casos de uso y controles validados.
- Operar reintentos, delays, límites de concurrencia, cancelación, observabilidad y revisión de fallos.

**Criterios de aceptación:** reejecutar un nodo no duplica efectos protegidos por idempotencia; demoras no retienen workers; una definición no válida no se ejecuta; las fallas y transiciones pueden diagnosticarse sin filtrar secretos ni cruzar tenants.

### 7. Piloto, endurecimiento y lanzamiento

**Depende de:** fases 0–6 y de la aprobación de responsables.

**Trabajo y entregables:** piloto con iglesias seleccionadas; revisión de seguridad y privacidad; restauración ensayada; soporte, onboarding, términos y procedimientos de incidentes; medición de uso, rendimiento y costos.

**Criterios de aceptación:** el equipo conoce límites y soporte del producto; pruebas de aislamiento y restauración pasan; no quedan vulnerabilidades críticas abiertas; las integraciones, límites de planes y precios comunicados coinciden con capacidades verificadas.

## Definición de listo

Un cambio o fase está listo cuando:

1. Cumple los criterios de aceptación acordados y tiene revisión de producto/dominio cuando modifica flujos o datos.
2. Incluye pruebas relevantes: autorización positiva y negativa, aislamiento tenant, errores y casos límite; cambios de JSONB/GIN se validan contra PostgreSQL real; las páginas Inertia se verifican con `assertInertia`.
3. Documenta migración, despliegue, reversión o mitigación y observabilidad cuando aplica; no requiere ejecutar cambios contra un entorno sin aprobación.
4. El frontend pasa `npm run typecheck` y `npm run build`, y no introduce datos de otra iglesia ni lógica de autorización en componentes.
5. No expone datos de otra iglesia, secretos ni información sensible en respuestas, logs, jobs o métricas.
6. Las limitaciones conocidas y decisiones pendientes quedan explícitas; no se anuncia como entregada una capacidad que siga siendo objetivo.

## Dependencias y bloqueos importantes

- El scaffold Laravel 12, modelos base, migraciones, pruebas unitarias sin BD, pruebas de esquema PostgreSQL y la base de frontend Inertia + React + TypeScript están escritos; el esquema se ejecutó en un PostgreSQL local y queda pendiente la configuración de entornos desplegados. `composer install`/`composer setup` pueden preparar `.env` y clave, pero no deben conectarse ni migrar; configurar PostgreSQL explícitamente antes del paso manual de migración.
- Se puede diseñar y escribir código/migraciones sin BD. Completar la fase 2 y configurar explícitamente PostgreSQL antes de ejecutar migraciones, realizar pruebas de integración/persistencia o validar características PostgreSQL; no intentar conexiones antes de ello.
- Precios, tiers, pasarelas, obligaciones legales y comportamiento fiscal requieren validación de mercado, proveedor y asesoría pertinente.
- No iniciar automatizaciones que ejecuten acciones externas antes de aprobar límites de seguridad, autorización, idempotencia y consentimiento.
