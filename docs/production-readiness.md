# Preparación de producción

Este documento registra el estado de preparación para un piloto cerrado con pocas iglesias y
las carencias que deben trabajarse antes de una producción abierta. No sustituye las decisiones
de producto, seguridad ni cumplimiento legal.

## Criterio actual

El piloto es viable con soporte cercano si se completan las precondiciones de despliegue y se
aceptan las limitaciones funcionales documentadas abajo. La aplicación **no** debe presentarse
como producción abierta mientras existan carencias `P0` sin resolver.

## Carencias registradas

| ID | Área | Carencia | Prioridad | Criterio de cierre |
| --- | --- | --- | --- | --- |
| OPS-01 | Despliegue | No existe CI/CD, Dockerfile ni configuración de despliegue versionada | P0 | Despliegue reproducible documentado; build y deploy verificados en un entorno remoto |
| OPS-02 | Almacenamiento | Los discos `local` y `public` son efímeros en Laravel Cloud | P0 | Adaptador S3 instalado; faltan dos buckets de Object Storage (`local` privado, `public` público) |
| OPS-03 | Correo | Sin un mailer real no se completa la verificación de correo ni el onboarding | P0 | Dominio verificado en Resend y envío probado en producción |
| OPS-04 | Backups | No se ha ensayado una restauración de PostgreSQL ni de archivos | P0 | Restauración a una base/entorno de prueba verificada y procedimiento escrito |
| OPS-05 | Monitoreo | No hay error tracking ni alertas de disponibilidad o errores | P1 | Nightwatch o Sentry conectado, alertas configuradas y responsable asignado |
| OPS-06 | Cola | La cola usa `sync`; no hay worker ni visibilidad de jobs fallidos | P1 | Cola durable si se encolan jobs; workers y jobs fallidos monitoreados |
| OPS-07 | Tareas programadas | No hay scheduler activo ni tareas recurrentes | P2 | Activar Scheduler cuando existan recordatorios, morosidad o purgas automáticas |
| SEC-01 | Autenticación | MFA pendiente para cuentas de plataforma | P0 | MFA obligatorio para `platform_admins` o acceso restringido y compensado con controles equivalentes |
| SEC-02 | Cabeceras | No hay Content-Security-Policy | P1 | CSP compatible con Inertia y Vite, probada sin romper la aplicación |
| SEC-03 | Rate limiting | El límite usa el cache configurado; con `file` no es consistente entre réplicas | P0 | `CACHE_STORE=database` o Valkey en el entorno desplegado |
| SEC-04 | Legal y privacidad | Sin términos, aviso de privacidad, retención ni acuerdo de tratamiento con iglesias | P0 | Documentos mínimos revisados y aceptados para el piloto |
| PROD-01 | Finanzas | Contribuciones y fondos tienen esquema y modelos, pero no rutas ni UI | P1 | Flujo de diezmos/ofrendas usable, auditado y probado, o exclusión explícita del piloto |
| PROD-02 | Workflows | Existen tablas y modelos, pero no hay runner ni ejecución de nodos | P2 | Motor de workflows con idempotencia, reintentos y observabilidad |
| PROD-03 | Reportes | Sin reportes operativos ni exportación/importación controlada | P2 | Reportes y exportación definidos con requisitos validados |
| QA-01 | Frontend | Sin tests de componente, e2e ni lint de JavaScript | P1 | Suite de frontend y lint en CI |
| QA-02 | Accesibilidad | Sin auditoría formal de accesibilidad | P1 | Revisión con lector de pantalla y teclado en flujos críticos |
| PRODUCT-01 | Validación | Precios, límites y pasarelas siguen sin validación de mercado | P0 | Hipótesis validadas y comunicadas como tales durante el piloto |

## Precondiciones de código

Antes de desplegar en Laravel Cloud:

1. Confirmar que el adaptador S3 de Flysystem está instalado. Ya figura en `composer.json`; si
   se recrea el proyecto desde cero, instalarlo con:

   ```bash
   composer require league/flysystem-aws-s3-v3 "^3.0" --with-all-dependencies
   ```

   Este paquete también instala `aws/aws-sdk-php`, requerido por las colas administradas.

2. Crear dos buckets en Laravel Cloud con estos nombres de disco exactos, porque la aplicación
   usa `Storage::disk('local')` para comprobantes y `Storage::disk('public')` para logos:

   | Bucket | Nombre de disco | Visibilidad | Uso |
   | --- | --- | --- | --- |
   | Comprobantes | `local` | Privado | Archivos de `payment_receipts` |
   | Marca | `public` | Público | Logos del portal público |

   Laravel Cloud inyecta `LARAVEL_CLOUD_DISK_CONFIG` y sobrescribe esos discos en tiempo de
   ejecución; no es necesario cambiar las rutas de `config/filesystems.php`.

3. Verificar que `config/services.php` lea `RESEND_API_KEY` (ya está configurado) y que
   `resend/resend-php` esté instalado (ya está en `composer.json`). No hace falta instalar
   `resend/resend-laravel`; Laravel 12 incluye el transporte.

4. Confirmar que la ruta de salud responde en `/up` (ya está registrada en `bootstrap/app.php`).

5. Ejecutar `php artisan test`, `vendor/bin/pint --dirty`, `npm run typecheck` y `npm run build`
   antes de publicar el commit de despliegue.

No ejecutar `php artisan storage:link` en Laravel Cloud: los archivos persistentes deben vivir en
Object Storage y los cambios de los comandos de despliegue no se conservan.

## Pasos de despliegue en Laravel Cloud

### 1. Crear la aplicación

1. Conectar GitHub, GitLab o Bitbucket y seleccionar el repositorio.
2. Crear la aplicación con un nombre estable y la región `us-east-1` (la más cercana disponible
   para Venezuela y Colombia; Laravel Cloud no tiene región en Sudamérica).
3. Usar el entorno `production` creado por defecto y elegir PHP 8.3 o 8.4.
4. Configurar el comando de build:

   ```bash
   composer install --no-dev --optimize-autoloader && npm ci --audit false && npm run build && php artisan optimize
   ```

5. Configurar el comando de despliegue:

   ```bash
   php artisan migrate --force
   ```

   Para el primer despliegue se recomienda ejecutar la migración primero en `staging` y luego
   promover el mismo commit. Las migraciones no se revierten automáticamente al hacer rollback.

### 2. Variables de entorno

Definir en el entorno de Laravel Cloud:

```ini
APP_NAME="Church SaaS"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://app.example.com
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
LOG_LEVEL=info

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database

DB_CONNECTION=pgsql
DB_TIMEZONE=UTC

QUEUE_CONNECTION=sync
```

Generar `APP_KEY` localmente con `php artisan key:generate --show` y pegarlo en Cloud. No subir
`.env`, credenciales ni secretos al repositorio. Para valores compartidos entre entornos usar
Secrets Manager.

`QUEUE_CONNECTION=sync` es correcto para el piloto porque no hay jobs en cola. Si más adelante se
encolan invitaciones, correos o procesos, crear una cola administrada; Cloud ajustará
`QUEUE_CONNECTION=cloud` automáticamente.

Cambiar variables de entorno requiere un nuevo despliegue para que surtan efecto.

### 3. Añadir PostgreSQL

1. En el canvas del entorno, elegir **Add database** y crear una base **Laravel Serverless
   Postgres** en la misma región.
2. Cloud inyecta `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` y el resto de
   la conexión. No sobrescribir esos valores.
3. Redesplegar para que la aplicación reciba las credenciales.
4. Confirmar en los logs del primer despliegue que las migraciones con JSONB, GIN, CHECK y
   triggers se ejecutaron correctamente. Laravel Cloud usa automáticamente la conexión sin pool
   para las migraciones.

### 4. Añadir Object Storage

1. Crear el bucket privado y asignarle el disco `local`.
2. Crear el bucket público y asignarle el disco `public`.
3. Verificar que el build incluyó `league/flysystem-aws-s3-v3`.
4. Probar una subida de comprobante y la descarga autorizada, y un logo visible en el portal
   público. No ejecutar `storage:link`.

### 5. Configurar Resend

1. Conectar la cuenta de Resend a la organización desde **Settings > Integrations**.
2. Añadir y verificar un dominio de envío, preferiblemente un subdominio como
   `notifications.example.com`.
3. Añadir el recurso **Emails** al entorno y elegir el dominio, remitente y una clave propia.
4. Cloud inyecta `MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS` y
   `MAIL_FROM_NAME`.
5. Redesplegar y probar registro, verificación de correo, invitación y recuperación de contraseña.

### 6. Dominio y TLS

1. Añadir el dominio personalizado en el entorno.
2. Crear los registros DNS que indique Laravel Cloud.
3. Esperar la verificación y el certificado TLS automático.
4. Actualizar `APP_URL` al dominio final y redesplegar, porque las URLs firmadas y los enlaces de
   verificación dependen de ese valor.

### 7. Verificación posterior al despliegue

- `/up` responde correctamente.
- Registro, verificación de correo y onboarding crean iglesia, membresía `admin` y monedas.
- Login, logout y recuperación de contraseña funcionan.
- Invitación por correo y aceptación funcionan.
- El portal público `/iglesias/{slug}` y el registro a eventos funcionan.
- La subida, revisión y descarga de comprobantes funciona con el bucket privado.
- El logo se ve en el portal público con el bucket público.
- La entrada QR y el check-in funcionan.
- El backoffice `/admin` funciona y una cuenta de iglesia no puede acceder a él.
- Un usuario de una iglesia no puede leer ni modificar datos de otra iglesia.
- Los logs no contienen correos, tokens, contraseñas ni notas pastorales.

### 8. Provisionar el backoffice

Crear la cuenta de plataforma por comando, nunca por registro público. Usar la pestaña
**Commands** del entorno:

```bash
php artisan platform:admin:create admin@example.com --name="Admin Plataforma"
```

Si se requiere resincronizar la matriz de planes:

```bash
php artisan plans:sync
```

## Operación del piloto

- Mantener `APP_DEBUG=false` y `LOG_LEVEL=info`.
- Configurar notificaciones de Laravel Cloud para despliegues fallidos y límites de gasto.
- Activar Nightwatch o Sentry antes de invitar a la primera iglesia.
- Confirmar la política de backups de PostgreSQL y ensayar una restauración a un entorno de
  prueba. Un backup sin restauración verificada no cuenta como recuperación.
- Usar un entorno `staging` separado con base, buckets y clave de Resend propios. Protegerlo con
  HTTP Basic Auth si el plan lo permite.
- Mantener una sola réplica al inicio para simplificar diagnóstico; usar Scale-to-Zero solo si el
  costo lo exige y aceptar el arranque en frío.
- No habilitar el Scheduler hasta que existan tareas recurrentes definidas y probadas.
- Documentar el procedimiento de rollback: desplegar el commit anterior y restaurar la base si una
  migración es incompatible. Preferir migraciones expand/contract para evitar rollbacks de esquema.

## Backlog sugerido

### Antes del piloto (`P0`)

- Buckets privado y público con Flysystem S3.
- Mailer Resend con dominio verificado.
- `APP_KEY`, `APP_URL`, `APP_ENV`, `APP_DEBUG` y sesión/cache en base de datos.
- Backups y restauración ensayada.
- MFA o control compensatorio para la cuenta de plataforma.
- Términos, privacidad y acuerdo mínimo de tratamiento de datos.
- Validar precios y límites como hipótesis, sin prometer integraciones no implementadas.

### Durante el piloto (`P1`)

- Nightwatch/Sentry y alertas.
- CSP y revisión de cabeceras.
- Tests de frontend, e2e y lint.
- Auditoría de accesibilidad de los flujos críticos.
- Decidir si finanzas entra en el alcance y, si entra, implementar contribuciones y fondos.
- Cola durable si aparecen jobs.

### Después del piloto (`P2`)

- Runner de workflows.
- Reportes, exportación e importación controlada.
- Automatización de facturación y conciliación.
- MFA para todos los roles que lo requieran.
- Retención y borrado automatizados según requisitos legales.
