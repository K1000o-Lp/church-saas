# Church SaaS

## Contexto

Church SaaS es un scaffold Laravel 12 para una plataforma SaaS de gestión pastoral y operativa para iglesias, con foco inicial en Venezuela y Colombia. El código y las migraciones iniciales cubren tenancy, personas, segmentos, eventos, RSVP/asistencia, contribuciones, seguimiento pastoral, equipos/servicios y persistencia base de workflows.

Las capacidades, integraciones, límites y precios descritos aquí son objetivos de producto por validar. No representan funcionalidades disponibles ni compromisos comerciales.

## Estado actual

El scaffold Laravel 12 y el primer código de modelos, contexto de tenancy y migraciones ya están escritos. El esquema se ejecutó en un PostgreSQL local de desarrollo y las pruebas de esquema usan una base PostgreSQL separada. Existe autenticación web por sesión con login/logout, selección de iglesia por membresía y resolución de tenant por petición; no hay frontend de producto, invitaciones, recuperación de contraseña, runner de workflows, pagos ni integraciones productivas.

**PostgreSQL es obligatorio para validar el esquema.** JSONB, el índice GIN en `people.custom_fields`, el `CHECK` monetario y el trigger de inmutabilidad no existen en SQLite; no uses SQLite para ejecutar estas migraciones ni para pruebas de persistencia.

`composer setup` instala dependencias, prepara `.env` desde `.env.example` si falta, genera la clave y construye frontend. La conexión empieza como `DB_CONNECTION=unconfigured`; configura la instancia y credenciales PostgreSQL en `.env` y selecciona explícitamente `pgsql` cuando el servicio esté listo. **Ni `composer setup` ni `composer install` ejecutan migraciones automáticamente**; ejecútalas solo como paso deliberado tras configurar y verificar PostgreSQL. Sesión y cache locales usan archivos, y la cola usa `sync`; cambia la cola a un driver durable al desplegar.

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

`tests/Feature/PostgresSchemaTest.php` verifica el índice GIN, los CHECK monetarios y temporales, la identidad de RSVP/asistencia, la coincidencia persona-RSVP, las unicidades, la integridad entre iglesias, las FKs de membresía, los catálogos de estado, la forma de los JSONB y la inmutabilidad/cascada de versiones de workflow.

## Navegación

- [Plan de implementación](docs/implementation-plan.md): fases, dependencias, entregables y criterios para avanzar.
- [Visión de arquitectura](docs/architecture/overview.md): alcance, tenancy, seguridad, operación y decisiones pendientes.
- [Modelo de datos](docs/architecture/data-model.md): entidades y relaciones persistidas por las migraciones iniciales, con decisiones todavía abiertas.
- [Workflows](docs/architecture/workflows.md): diseño propuesto para automatizaciones durables y ejecutadas por nodos.

## Cómo usar esta documentación

Tratar las decisiones marcadas como abiertas como preguntas, no como defaults aprobados. Validar requisitos con iglesias de Venezuela y Colombia y configurar un entorno PostgreSQL comprobable antes de ejecutar o validar las migraciones. Tiers, precios e integraciones siguen siendo objetivos por validar, no capacidades disponibles.
