# Deployment y operación de datos

Este documento define el procedimiento reproducible para preparar PostgreSQL en Neon y conectar la demo de VitaeTex sin publicar credenciales. Los comandos se ejecutan desde la raíz del repositorio.

## Contrato actual

- Proveedor: Neon mediante PostgreSQL estándar, sin SDK ni API en la aplicación.
- Región: AWS US East 2 (Ohio).
- Branch principal: `Production`.
- Base: `vitaetex`.
- Propietario y rol de migraciones: `neondb_owner`.
- Rol restringido de la aplicación: `vitaetex_app`.
- Rol restringido de mantenimiento programado: `vitaetex_maintenance`.
- PostgreSQL: 18.
- Conexiones administrativas y de verificación: endpoint directo.
- TLS: `sslmode=require` como mínimo.

La cadena de `neondb_owner` se utiliza únicamente desde una estación administrativa para migraciones y cambios de privilegios. No debe configurarse en Render ni GitHub. El Web Service utiliza exclusivamente `vitaetex_app` y el workflow programado utiliza exclusivamente `vitaetex_maintenance`.

Neon recomienda una conexión directa para migraciones ejecutadas por ORMs. Además, los roles creados desde la consola de Neon heredan `neon_superuser`; por ese motivo `vitaetex_app` se crea mediante SQL y después recibe solo permisos DML. Consulta [Connection pooling](https://neon.com/docs/connect/connection-pooling) y [Postgres compatibility](https://neon.com/docs/reference/compatibility).

## Preparar una migración

Construye exactamente el artefacto que se desplegará:

```sh
docker build --target production --tag vitaetex:production .
```

Introduce la URL directa del propietario sin escribirla en el historial del shell:

```sh
read -r -s -p "URL directa de neondb_owner: " NEON_MIGRATION_DATABASE_URL
echo
export NEON_MIGRATION_DATABASE_URL
```

La URL debe apuntar a `/vitaetex`, no contener `-pooler` y declarar `sslmode=require`, `verify-ca` o `verify-full`. El script rechaza conexiones que no cumplan ese contrato.

Antes de una migración sobre una base con datos, crea o renueva el snapshot manual descrito en la sección de backups. Después ejecuta:

```sh
./scripts/run-neon-migrations.sh
```

El contenedor recibe la credencial solo mediante entorno, ejecuta `php artisan migrate --force` y desaparece al terminar. Si la migración falla, no despliegues una versión nueva ni ejecutes `migrate:rollback` automáticamente: conserva el servicio anterior y evalúa la migración fallida y el snapshot.

## Crear el rol de runtime

Este paso se ejecuta una sola vez, después de las migraciones iniciales:

```sh
./scripts/configure-neon-runtime-role.sh
```

El comando crea `vitaetex_app` mediante SQL, comprueba que no herede `neon_superuser` y solicita interactivamente una contraseña sin mostrarla. Guárdala en el gestor de contraseñas. El wrapper la entrega temporalmente a `psql` para que Neon reciba el valor en texto plano, como exige su plano de control; no se incluye en los argumentos del proceso, los archivos ni la salida. Luego concede:

- conexión a `vitaetex`;
- uso del schema `public` sin permiso `CREATE`;
- `SELECT`, `INSERT`, `UPDATE` y `DELETE` sobre las tablas de aplicación;
- uso y lectura de secuencias;
- los mismos permisos mínimos por defecto para tablas y secuencias futuras.

El rol no recibe acceso a `public.migrations`, ownership de tablas, creación de schemas, bases o roles, replicación ni bypass de RLS.

Después, obtén en Neon una URL directa de `vitaetex_app` para la base `vitaetex`, guárdala fuera del repositorio y verifica el contrato:

```sh
read -r -s -p "URL directa de vitaetex_app: " NEON_RUNTIME_DATABASE_URL
echo
export NEON_RUNTIME_DATABASE_URL
./scripts/verify-neon-runtime-role.sh
```

Para migraciones futuras, los privilegios por defecto deberían cubrir las tablas nuevas. Aun así, vuelve a aplicar y verificar el contrato después de cada cambio de esquema:

```sh
./scripts/grant-neon-runtime-privileges.sh
./scripts/verify-neon-runtime-role.sh
```

Retira las credenciales del entorno al finalizar:

```sh
unset NEON_MIGRATION_DATABASE_URL NEON_RUNTIME_DATABASE_URL
```

## Configurar la limpieza diaria de tokens expirados

Render Free no ejecuta el scheduler de Laravel ni admite un Cron Job gratuito, como refleja la referencia de [Blueprints](https://render.com/docs/blueprint-spec). El workflow `.github/workflows/clear-expired-password-resets.yml` ejecuta diariamente `php artisan auth:clear-resets --no-interaction` a las `03:17 UTC` y también permite iniciarlo manualmente. La hora evita el comienzo exacto de la hora, cuando GitHub advierte que existe mayor probabilidad de retrasos.

El workflow no ejecuta `schedule:run`: la limpieza horaria de temporales PDF pertenece al filesystem efímero del Web Service y no tendría efecto desde un runner externo. Tampoco recibe las credenciales de migraciones o del Web Service.

Laravel requiere una `APP_KEY` incluso para construir el repositorio que elimina tokens expirados. El workflow genera una clave aleatoria efímera de 32 bytes después de preparar PHP y la conserva únicamente durante ese job mediante `GITHUB_ENV`. Esa clave no se guarda como secret, no se comparte con Render y no necesita coincidir con la clave de la aplicación desplegada porque la limpieza solo compara la fecha de creación antes de borrar filas.

Primero introduce la URL directa de `neondb_owner` como se describe en [Preparar una migración](#preparar-una-migración). Después crea `vitaetex_maintenance` y asigna una contraseña nueva y exclusiva:

```sh
./scripts/configure-neon-maintenance-role.sh
```

El script rechaza endpoints pooled y conexiones que no exijan TLS. Crea un login sin privilegios administrativos ni memberships y le concede únicamente:

- conexión a `vitaetex`;
- uso del schema `public` sin permiso `CREATE`;
- `DELETE` sobre `password_reset_tokens`;
- lectura de la columna `created_at`, necesaria para seleccionar las filas vencidas.

El rol no puede leer emails ni hashes de tokens, acceder a usuarios, CVs, sesiones, migraciones o secuencias, ni insertar o modificar tokens. Obtén o construye fuera del repositorio su URL directa hacia `vitaetex`, con `sslmode=require` o más estricto, y verifica el contrato:

```sh
read -r -s -p "URL directa de vitaetex_maintenance: " NEON_MAINTENANCE_DATABASE_URL
echo
export NEON_MAINTENANCE_DATABASE_URL
./scripts/verify-neon-maintenance-role.sh
```

En el repositorio de GitHub, crea un Repository Secret de Actions llamado exactamente `NEON_MAINTENANCE_DATABASE_URL` con esa URL. No lo configures como variable visible ni lo copies a Render. Desde la pestaña Actions, abre `Clear expired password reset tokens`, ejecuta `Run workflow` y confirma que termina correctamente antes de depender de la programación diaria.

El [evento programado de GitHub](https://docs.github.com/actions/reference/workflows-and-actions/events-that-trigger-workflows#schedule) solo se ejecuta desde la rama por defecto, puede retrasarse bajo carga y se desactiva automáticamente tras 60 días sin actividad en repositorios públicos. Un retraso no amplía la vigencia configurada de los tokens: únicamente posterga la eliminación física de filas que Laravel ya considera expiradas. Para una demo pública debe revisarse el estado del workflow después de periodos largos sin actividad.

Registro de validación, 2026-08-24 UTC:

- `vitaetex_maintenance` fue creado mediante el procedimiento versionado y su contrato de privilegios mínimos pasó la verificación remota.
- `NEON_MAINTENANCE_DATABASE_URL` fue configurado como Repository Secret de GitHub Actions.
- Una primera ejecución manual detectó que Laravel necesitaba `APP_KEY` para construir el repositorio estándar de tokens. El workflow se corrigió para generar una clave aleatoria efímera por job, sin compartir la clave de Render.
- La ejecución manual posterior terminó correctamente. No se registraron connection strings, credenciales ni contenido de tokens.

Retira las credenciales de la sesión administrativa al terminar:

```sh
unset NEON_MIGRATION_DATABASE_URL NEON_MAINTENANCE_DATABASE_URL
```

## Crear el Web Service en Render

`render.yaml` es la fuente versionada de la configuración no secreta de Render. Declara únicamente el Web Service Docker `vitaetex` en el plan Free y la región Ohio, conserva el `ENTRYPOINT` y `CMD` de la imagen y usa `/up` como health check. No crea una base de Render ni añade workers, cron jobs, discos o comandos de pre-deploy.

Antes de crear el Blueprint, construye la imagen y ejecuta las migraciones contra Neon mediante el procedimiento anterior. Después, conecta el repositorio desde `New > Blueprint` en el dashboard de Render y revisa el plan propuesto antes de aplicarlo.

Render solicitará los cinco valores con `sync: false` durante la creación inicial:

| Variable | Valor requerido |
|---|---|
| `APP_KEY` | Clave nueva y secreta generada mediante `docker run --rm --entrypoint php vitaetex:production artisan key:generate --show`. |
| `APP_URL` | URL HTTPS completa del Web Service, por ejemplo `https://vitaetex.onrender.com`. |
| `DB_URL` | URL directa de `vitaetex_app` para `vitaetex`, con `sslmode=require` o más estricto. |
| `TRUSTED_HOSTS` | Hostname exacto de `APP_URL`, sin esquema ni path. |
| `PRIVACY_CONTACT_EMAIL` | Email público de contacto para `/privacidad`. |

No introduzcas la URL de `neondb_owner`, tokens del panel de Neon ni una credencial de correo todavía. Los valores `sync: false` nuevos o modificados después de la creación inicial deben mantenerse manualmente desde el dashboard porque Render no los aplica en sincronizaciones posteriores del Blueprint.

El Blueprint utiliza provisionalmente `MAIL_MAILER=array`: las solicitudes de recuperación conservan su respuesta no reveladora, pero no entregan ni registran enlaces. Sustituye esta configuración solo dentro del bloque dedicado al proveedor transaccional y verifica la entrega antes de abrir el registro.

La imagen y el Blueprint no ejecutan migraciones ni el scheduler automáticamente. Tampoco deben añadirse a `dockerCommand`: las migraciones continúan siendo una operación administrativa explícita y la ejecución diaria de `auth:clear-resets` pertenece al workflow de GitHub documentado anteriormente.

## Variables del Web Service

El Blueprint configura el siguiente contrato para PostgreSQL, sesiones y cache; `DB_URL` se introduce como secreto en el dashboard:

```dotenv
DB_CONNECTION=pgsql
DB_URL=<URL de vitaetex_app>
DB_SSLMODE=require
SESSION_DRIVER=database
CACHE_STORE=database
```

Nunca configures la URL de `neondb_owner` en el servicio. La aplicación falla al arrancar en producción si PostgreSQL usa `disable`, `allow` o `prefer` como `DB_SSLMODE`.

La configuración HTTPS, cookies, hosts y proxies requerida se mantiene en el [README](README.md#seguridad-http). Antes de abrir el registro también debe configurarse `PRIVACY_CONTACT_EMAIL`.

## Política de backup y restauración

Esta demo adopta objetivos operativos, no garantías contractuales:

- RPO máximo objetivo: siete días mediante snapshot manual semanal; la recuperación continua de Neon puede reducir la pérdida dentro de la ventana disponible del plan.
- RTO objetivo: un día hábil para evaluar el incidente, restaurar y comprobar el flujo principal.
- Retención máxima de snapshots manuales: siete días.
- Se crea además un snapshot inmediatamente antes de cada migración o cambio destructivo planificado.
- El plan Free admite un snapshot manual; antes de reemplazarlo se comprueba su fecha y se registra el nuevo punto de recuperación.

Las capacidades y límites de Neon pueden cambiar. Revisa [Neon pricing](https://neon.com/pricing) y [Database versioning with snapshots](https://neon.com/docs/ai/ai-database-versioning) antes de cada simulacro o publicación.

### Rotación semanal

1. Comprueba que `Production` está saludable y que el snapshot existente no sigue siendo necesario para una migración en curso.
2. Elimina el snapshot que alcanzó siete días de antigüedad.
3. Crea inmediatamente un snapshot nuevo desde la branch raíz `Production` con nombre `weekly-AAAA-MM-DD`.
4. Registra fecha UTC, responsable y resultado sin copiar datos personales ni credenciales.
5. Si no puede crearse el reemplazo, suspende cambios de esquema y considera cerrar temporalmente el registro.

### Simulacro seguro

El simulacro no restaura directamente sobre `Production`:

1. Usa únicamente datos ficticios en el primer ejercicio.
2. Crea un snapshot manual de `Production`.
3. Restaura el snapshot como una branch temporal o preview, sin finalizar el restore sobre la branch activa.
4. Con una credencial administrativa temporal, comprueba migraciones, conteos esperados y lectura de un CV ficticio.
5. Elimina la branch temporal y cualquier endpoint asociado al terminar.
6. Registra tiempos, resultado y desviaciones sin guardar connection strings ni contenido del CV.

Una restauración real sobre `Production` requiere detener cambios, confirmar el snapshot elegido, registrar el estado anterior y comprobar que las operaciones de Neon hayan terminado antes de reconectar. Neon conserva la URL al finalizar una restauración sobre la branch activa, pero cambia internamente el branch ID y deja la branch anterior huérfana; revisa y elimina esa branch solo después de validar la recuperación.

## Retención y eliminación de usuarios

Eliminar un CV o una cuenta borra inmediatamente sus filas de la base activa mediante cascadas. Una copia anterior puede permanecer en recuperación continua o snapshots hasta que venza su retención. Por ello:

- ningún snapshot manual vive más de siete días;
- las branches temporales de restauración se eliminan al terminar el ejercicio;
- no se restauran registros individuales eliminados por una persona usuaria salvo incidente técnico que afecte al conjunto de la base;
- fuentes `.tex`, PDFs y auxiliares nunca forman parte de estos backups porque solo existen en temporales del Web Service.

## Checklist antes de admitir datos reales

- [ ] Migraciones ejecutadas con la imagen de producción contra `vitaetex`.
- [ ] `vitaetex_app` creado mediante SQL y verificado por el script.
- [x] `vitaetex_maintenance` creado mediante SQL y verificado por el script.
- [x] `NEON_MAINTENANCE_DATABASE_URL` guardado únicamente como Repository Secret de GitHub Actions.
- [x] Workflow de limpieza ejecutado manualmente con resultado correcto.
- [ ] URL administrativa ausente de Render.
- [ ] Snapshot manual con antigüedad inferior a siete días.
- [ ] Simulacro de restauración completado y documentado.
- [ ] `PRIVACY_CONTACT_EMAIL` visible en `/privacidad`.
- [ ] Recuperación de contraseña entregada por el proveedor transaccional elegido.
- [ ] Smoke test completo ejecutado en Render.
