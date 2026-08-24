# Deployment y operación de datos

Este documento define el procedimiento reproducible para preparar PostgreSQL en Neon y conectar la demo de VitaeTex sin publicar credenciales. Los comandos se ejecutan desde la raíz del repositorio.

## Contrato actual

- Proveedor: Neon mediante PostgreSQL estándar, sin SDK ni API en la aplicación.
- Región: AWS US East 2 (Ohio).
- Branch principal: `Production`.
- Base: `vitaetex`.
- Propietario y rol de migraciones: `neondb_owner`.
- Rol restringido de la aplicación: `vitaetex_app`.
- PostgreSQL: 18.
- Conexiones administrativas y de verificación: endpoint directo.
- TLS: `sslmode=require` como mínimo.

La cadena de `neondb_owner` se utiliza únicamente desde una estación administrativa para migraciones y cambios de privilegios. No debe configurarse en Render. El Web Service utiliza exclusivamente `vitaetex_app`.

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

## Variables del Web Service

El deployment configurará como secretos `APP_KEY`, `DB_URL`, la credencial del mailer y cualquier otro token. Para PostgreSQL:

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
- [ ] URL administrativa ausente de Render.
- [ ] Snapshot manual con antigüedad inferior a siete días.
- [ ] Simulacro de restauración completado y documentado.
- [ ] `PRIVACY_CONTACT_EMAIL` visible en `/privacidad`.
- [ ] Recuperación de contraseña entregada por el proveedor transaccional elegido.
- [ ] Smoke test completo ejecutado en Render.
