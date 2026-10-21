# Church SaaS

## Contexto

Church SaaS es un scaffold Laravel 12 para una plataforma SaaS de gestión pastoral y operativa para iglesias, con foco inicial en Venezuela y Colombia. El código y las migraciones iniciales cubren tenancy, personas, segmentos, eventos, RSVP/asistencia, contribuciones, seguimiento pastoral, equipos/servicios y persistencia base de workflows.

Las capacidades, integraciones, límites y precios descritos aquí son objetivos de producto por validar. No representan funcionalidades disponibles ni compromisos comerciales.

## Estado actual

El scaffold Laravel 12 y el primer código de modelos, contexto de tenancy y migraciones ya están escritos. El esquema se ejecutó en un PostgreSQL local de desarrollo y las pruebas de esquema usan una base PostgreSQL separada. Existe autenticación web por sesión con login/logout, registro público con verificación de correo y onboarding posterior, recuperación de contraseña, selección de iglesia por membresía, resolución de tenant por petición, onboarding autoservicio que crea iglesia, membresía `admin` y monedas en una transacción, RBAC formal con roles y permisos, invitaciones por correo, gestión de miembros, backoffice exclusivo de plataforma en `/admin` con auditoría, administración de estados y metadatos, planes/límites/funciones con suscripción y overrides por iglesia, cambio de plan autoservicio para administradores de iglesia, facturación manual en USD con pagos y estados, purga auditada de iglesias archivadas, auditoría de dominio, contexto tenant explícito para jobs/comandos, y un módulo de personas con campos personalizados. La interfaz Inertia + React + TypeScript incluye registro, login, recuperación de contraseña, panel de iglesias, onboarding, pantalla de plan, historial de facturas, directorio de personas y configuración de miembros/campos. Faltan MFA, runner de workflows, pagos automáticos e integraciones productivas. Las decisiones de onboarding, backoffice, planes y facturación están documentadas en [`docs/architecture/overview.md`](docs/architecture/overview.md#onboarding-de-iglesias), [`docs/architecture/overview.md`](docs/architecture/overview.md#backoffice-de-plataforma), [`docs/architecture/overview.md`](docs/architecture/overview.md#planes-límites-y-funciones) y [`docs/architecture/overview.md`](docs/architecture/overview.md#facturación-manual).

La matriz de planes se aplica con la migración de datos y puede resincronizarse de forma idempotente; las iglesias sin suscripción reciben Comunidad:

```bash
php artisan plans:sync
```

Una persona puede crear su cuenta gratis en `/register`; debe verificar su correo antes de crear
la iglesia. Las invitaciones válidas también verifican el correo porque el token se entrega a ese
buzón. Tras el onboarding, la iglesia queda en el plan Comunidad. El administrador de la iglesia puede cambiarla a otro plan desde
`/churches/{church}/plan`. El cambio es inmediato y queda auditado; la facturación se sigue
emitiendo manualmente desde `/admin`.

El backoffice se provisiona por comando, nunca por registro público:

```bash
php artisan platform:admin:create admin@example.com --name="Admin Plataforma"
php artisan platform:admin:deactivate admin@example.com
```

La cuenta de plataforma es exclusiva: vive en `platform_admins`, usa el guard `platform` y no puede acceder a `/dashboard` ni a datos pastorales o financieros de las iglesias.

La purga de iglesias archivadas es explícita y auditable. La fecha de corte es obligatoria y se purgan las iglesias archivadas hasta esa fecha inclusive. Sin `--dry-run` elimina datos tenant; conserva la auditoría de plataforma y el historial de facturas/pagos:

```bash
php artisan churches:purge --date=2026-10-20 --dry-run
php artisan churches:purge --date=2026-10-20
```

La facturación es manual y en USD: el backoffice emite facturas, registra pagos con referencia y notas, y cambia estados (`issued`, `overdue`, `paid`, `void`). Los montos se guardan en centavos; no hay impuestos, PDF ni cobro automático en esta fase.

**PostgreSQL es obligatorio para validar el esquema.** JSONB, el índice GIN en `people.custom_fields`, el `CHECK` monetario y el trigger de inmutabilidad no existen en SQLite; no uses SQLite para ejecutar estas migraciones ni para pruebas de persistencia.

El frontend usa Inertia v3, React 19, TypeScript estricto, Vite 7, Tailwind CSS v4 y primitivas shadcn/ui sobre Radix. El servidor conserva rutas, sesión, validación y autorización; las páginas React no deciden tenant ni permisos. La guía de componentes y el sistema visual están en [`docs/architecture/frontend.md`](docs/architecture/frontend.md) y [`AGENTS.md`](AGENTS.md).

`composer setup` instala dependencias, prepara `.env` desde `.env.example` si falta, genera la clave y construye frontend. La conexión empieza como `DB_CONNECTION=unconfigured`; configura la instancia y credenciales PostgreSQL en `.env` y selecciona explícitamente `pgsql` cuando el servicio esté listo. **Ni `composer setup` ni `composer install` ejecutan migraciones automáticamente**; ejecútalas solo como paso deliberado tras configurar y verificar PostgreSQL. Sesión y cache locales usan archivos, y la cola usa `sync`; cambia la cola a un driver durable al desplegar. Las invitaciones y la recuperación de contraseña usan el mailer configurado; en local `MAIL_MAILER=log` escribe los enlaces en el log en lugar de enviar correo real.

El esquema define `church_currencies` para monedas admitidas, `funds` para destinos financieros y `workflow_versions` para definiciones publicadas inmutables, protegidas contra UPDATE/DELETE directos mediante trigger PostgreSQL, además de restricciones tenant/evento en RSVP y asistencia, coincidencia entre la persona del RSVP y la asistencia, unicidad parcial de asistencia, moneda de contribución existente por iglesia, máximo un default monetario, rangos temporales válidos, enteros no negativos, catálogos iniciales de estado y FKs de responsables contra membresías. Los modelos tenant-owned deniegan creación, modificación y borrado sin contexto de iglesia coincidente; el borrado también compara el `church_id` original. `TRUNCATE` y privilegios de DBA no son operaciones de aplicación ni están cubiertos por el trigger.

## Base de datos local

La configuración de desarrollo vive en `.env` y la de pruebas en `.env.testing`; ambos están fuera del control de versiones. Las pruebas apuntan a una base separada, `church_saas_test`, para no tocar datos de desarrollo.

Variables mínimas:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=church_saas
DB_USERNAME=church
DB_PASSWORD=...
DB_SSLMODE=disable
DB_TIMEZONE=UTC
```

Con PostgreSQL nativo en el puerto `5432`:

```bash
php artisan migrate
php artisan migrate --env=testing
php artisan test
```

Con Docker, `compose.yaml` levanta PostgreSQL en el puerto `5433` (para no chocar con una instancia local en `5432`); en ese caso ajusta `DB_PORT=5433` en `.env` y `.env.testing` antes de migrar:

```bash
docker compose up -d
```

`tests/Feature/PostgresSchemaTest.php` verifica el índice GIN, los CHECK monetarios y temporales, la identidad de RSVP/asistencia, la coincidencia persona-RSVP, las unicidades, la integridad entre iglesias, las FKs de membresía, los catálogos de estado, la forma de los JSONB y la inmutabilidad/cascada de versiones de workflow. Las pruebas de RBAC, invitaciones, recuperación de contraseña, personas/campos personalizados, auditoría y jobs con tenant están en `tests/Feature` y `tests/Unit`.

### Roles y permisos

Los roles viven en `App\Support\Auth\Role` y la matriz en `RolePermissions`; las rutas usan Gates/Policies y el middleware `permission:<permiso>`. `currentChurch.permissions` se comparte solo como ayuda de interfaz; la autorización real se repite en Laravel. Un rol desconocido no recibe permisos.

### Invitaciones y recuperación

Un `admin` puede invitar por correo desde la configuración de miembros. El token se guarda hasheado, vence en 7 días y se acepta una sola vez; una persona nueva puede crear su cuenta desde la invitación y una existente inicia sesión. La invitación a un correo de plataforma se rechaza, y una membresía suspendida no puede reactivarse aceptando una invitación. El envío de invitaciones y la creación de iglesias están limitados por usuario.

La recuperación de contraseña usa el broker de Laravel, responde siempre de forma genérica (sin enumerar cuentas) y elimina las sesiones abiertas al completar el cambio. `AuthenticateSession` invalida además sesiones y cookies “remember me” cuando cambia la contraseña. Los eventos `auth.login`, `auth.logout` y `auth.password_reset` se registran en `auth_events` sin correos, tokens ni contraseñas.

### Auditoría y jobs

Las acciones sensibles de dominio se registran en `church_audit_entries` con actor, iglesia, acción y metadatos mínimos. Los eventos de autenticación se registran aparte en `auth_events`. Los jobs deben extender `TenantAwareJob` y llevar IDs explícitos; el contexto se establece y limpia con `CurrentChurch::run`, y la iglesia/membresía/permiso se revalidan al ejecutar.

## Checklist de producción

- `APP_DEBUG=false` y `APP_ENV=production`.
- `SESSION_DRIVER=database` y `SESSION_SECURE_COOKIE=true` con HTTPS.
- Configurar un mailer real: sin verificación de correo los usuarios nuevos no completan el onboarding.
- Definir `TRUSTED_PROXIES` si la aplicación está detrás de un balanceador o proxy.
- Ejecutar la cola con un driver durable si se añaden jobs; las invitaciones actuales se envían de forma síncrona.
- MFA para cuentas de plataforma queda pendiente como endurecimiento separado.

## Navegación

- [Plan de implementación](docs/implementation-plan.md): fases, dependencias, entregables y criterios para avanzar.
- [Visión de arquitectura](docs/architecture/overview.md): alcance, tenancy, seguridad, operación y decisiones pendientes.
- [Frontend](docs/architecture/frontend.md): Inertia + React, contrato de props, sistema de diseño, accesibilidad y verificación.
- [Modelo de datos](docs/architecture/data-model.md): entidades y relaciones persistidas por las migraciones iniciales, con decisiones todavía abiertas.
- [Workflows](docs/architecture/workflows.md): diseño propuesto para automatizaciones durables y ejecutadas por nodos.

## Cómo usar esta documentación

Tratar las decisiones marcadas como abiertas como preguntas, no como defaults aprobados. Validar requisitos con iglesias de Venezuela y Colombia y configurar un entorno PostgreSQL comprobable antes de ejecutar o validar las migraciones. Tiers, precios e integraciones siguen siendo objetivos por validar, no capacidades disponibles.
