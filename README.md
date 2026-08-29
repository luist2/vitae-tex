# VitaeTex

VitaeTex es una aplicación web para crear y mantener currículums estructurados y generar documentos LaTeX y PDF bajo demanda.

Está construida como un monolito con Laravel 12, PHP 8.4, Vue 3, Inertia, TypeScript y PostgreSQL. Los documentos se renderizan con una adaptación en español y A4 de Jake's Resume y los PDFs se compilan temporalmente con Tectonic.

## Demo

La demo pública está disponible en [vitaetex.onrender.com](https://vitaetex.onrender.com/).

> Al ejecutarse en el plan gratuito de Render, el primer acceso puede tardar mientras el servicio arranca desde reposo.

## Funcionalidades

- Registro, autenticación y recuperación de contraseña.
- Administración de múltiples CVs independientes.
- Editor responsive con secciones ordenadas y datos de ejemplo.
- Guardado explícito y preview PDF bajo demanda.
- Descarga de la fuente `.tex` y del PDF generado.
- Protección de datos por usuario y eliminación permanente de la cuenta y sus CVs.

## Requisitos

- Docker con Docker Compose.
- Los puertos `8000`, `5173` y `5432` disponibles, o valores alternativos configurados en `.env`.

PHP y Node no son necesarios en el host. El entorno Docker fija PostgreSQL 18.4, Node 24.18.0 y Tectonic 0.16.9.

## Instalación local

Desde la raíz del repositorio:

```sh
cp .env.example .env
docker compose build app
docker compose run --rm app composer install
docker compose run --rm node npm ci
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
```

Las credenciales incluidas son exclusivamente locales. Si el UID o GID del host no es `1000`, ajusta `LOCAL_UID` y `LOCAL_GID` en `.env` antes de instalar dependencias.

## Desarrollo

```sh
docker compose up
```

La aplicación estará disponible en <http://localhost:8000>. Para detener el entorno o ejecutar migraciones pendientes:

```sh
docker compose down
docker compose run --rm app php artisan migrate
```

## Calidad y pruebas

```sh
# Backend
docker compose run --rm app composer test

# Formato, lint, tipos, tests y build frontend
docker compose run --rm node npm run check

# Tests de navegador en Chromium y Firefox
./scripts/run-browser-tests.sh
```

Los comandos para aplicar formato o ejecutar comprobaciones individuales son:

```sh
docker compose run --rm app composer format
docker compose run --rm node npm run format
docker compose run --rm node npm run lint
docker compose run --rm node npm run test
docker compose run --rm node npm run build
```

## Imagen de producción

El target `production` genera una imagen autocontenida con las dependencias PHP, los assets frontend, Tectonic y su caché precalentada. El contenedor se ejecuta como usuario sin privilegios y no aplica migraciones ni inicia el scheduler automáticamente.

```sh
docker build --target production --tag vitaetex:production .
docker run --detach --rm \
    --cap-drop ALL \
    --security-opt no-new-privileges:true \
    --name vitaetex-production-smoke \
    --env PORT=18080 \
    --env APP_URL=http://localhost:18080 \
    --env TRUSTED_HOSTS=localhost \
    --publish 127.0.0.1:18080:18080 \
    vitaetex:production
curl --fail http://localhost:18080/up
docker stop vitaetex-production-smoke
```

Este smoke solo comprueba el empaquetado y el proceso web. El despliegue completo en Render con PostgreSQL administrado por Neon, roles de base de datos, secretos, migraciones y mantenimiento está documentado en [DEPLOYMENT.md](DEPLOYMENT.md).

## Generación de documentos

La aplicación genera la fuente LaTeX desde datos persistidos y controlados, y compila el PDF bajo demanda con Tectonic en modo no confiable y sin red. Las fuentes, PDFs y archivos auxiliares se mantienen en directorios temporales privados y se eliminan después de cada petición.

La integración offline puede comprobarse con:

```sh
docker compose run --rm --no-deps app /usr/local/bin/verify-fixture /tmp/tectonic-output
```

La limpieza defensiva de temporales abandonados se puede ejecutar manualmente:

```sh
docker compose run --rm app php artisan cv:prune-pdf-temporaries
```

## Smoke remoto

Existe un recorrido opt-in para verificar un deployment autorizado. Crea y elimina datos ficticios, por lo que requiere confirmación explícita:

```sh
PLAYWRIGHT_REMOTE_SMOKE_CONFIRM=1 \
PLAYWRIGHT_BASE_URL=https://vitaetex.example \
npm run test:smoke:render
```

Consulta [DEPLOYMENT.md](DEPLOYMENT.md) antes de ejecutarlo. No debe apuntarse a un entorno ajeno ni a uno con datos reales que no estés autorizado a modificar.

## Seguridad y privacidad

Las operaciones sobre CVs requieren autenticación y ownership en backend. El contenido del usuario se trata como texto no confiable, se escapa según su contexto LaTeX y nunca controla plantillas, comandos, paquetes ni rutas. Las respuestas dinámicas y documentos se marcan como privados y no almacenables.

Los secretos y credenciales de producción deben proporcionarse exclusivamente mediante variables del entorno de despliegue. No deben copiarse a `.env.example`, archivos versionados, comandos ni logs. Consulta [DEPLOYMENT.md](DEPLOYMENT.md) para conocer el contrato completo de configuración.

## Licencias de terceros

La atribución y licencia de la plantilla LaTeX adaptada están disponibles en [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
