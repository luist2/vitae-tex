# VitaeTex

VitaeTex es una aplicación web para crear y mantener currículums estructurados y generar documentos LaTeX y PDF bajo demanda.

La base actual utiliza Laravel 12, PHP 8.4, Vue 3, Inertia, TypeScript, PostgreSQL 18.4 y Tectonic 0.16.9. El entorno local se ejecuta con Docker Compose para conservar las mismas versiones entre máquinas.

## Requisitos

- Docker con Docker Compose.
- Los puertos `8000`, `5173` y `5432` disponibles, o valores alternativos configurados en `.env`.

Node y PHP no son necesarios en el host para el flujo canónico. Si se usan herramientas frontend directamente en el host, la versión fijada de Node es `24.18.0` y también está declarada en `.nvmrc`.

## Instalación

Desde la raíz del repositorio:

```sh
cp .env.example .env
docker compose build app
docker compose run --rm app composer install
docker compose run --rm node npm ci
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
```

Las credenciales PostgreSQL incluidas son exclusivamente locales y no deben reutilizarse en otros entornos. Si el UID o GID del usuario del host no es `1000`, ajusta `LOCAL_UID` y `LOCAL_GID` en `.env` antes de instalar dependencias.

## Desarrollo

Inicia FrankenPHP, PostgreSQL y Vite:

```sh
docker compose up
```

La aplicación queda disponible en <http://localhost:8000>. Para detenerla:

```sh
docker compose down
```

Ejecuta migraciones pendientes con:

```sh
docker compose run --rm app php artisan migrate
```

## Imagen de producción

El target `production` construye un artefacto autocontenido: instala únicamente las dependencias PHP de producción, compila los assets con Node e incorpora el código, Tectonic y su cache precalentada. El runtime usa `php.ini-production`, escribe logs en `stderr` y ejecuta FrankenPHP y sus procesos hijos como el usuario sin privilegios `10001:10001`.

Construye la imagen desde la raíz del repositorio:

```sh
docker build --target production --tag vitaetex:production .
```

Para comprobar localmente el puerto dinámico y el health check sin bind mounts:

```sh
docker run --detach --rm \
    --name vitaetex-production-smoke \
    --env PORT=18080 \
    --env APP_URL=http://localhost:18080 \
    --env TRUSTED_HOSTS=localhost \
    --publish 127.0.0.1:18080:18080 \
    vitaetex:production
curl --fail http://localhost:18080/up
docker stop vitaetex-production-smoke
```

Esta comprobación valida el empaquetado y el proceso web, no configura un deployment completo. Para utilizar los flujos de aplicación se deben proporcionar mediante entorno una `APP_KEY` segura y la conexión PostgreSQL, además del resto de valores de producción descritos más abajo. La imagen no ejecuta migraciones ni el scheduler automáticamente.

El job `container` de CI reconstruye este target, comprueba que no contenga dependencias de desarrollo, compila el fixture de Tectonic sin red y ejecuta el mismo health check HTTP antes de aceptar el artefacto.

## Calidad y pruebas

Backend con PHPUnit:

```sh
docker compose run --rm app composer test
```

Comprobaciones frontend —Prettier, ESLint, TypeScript, tests y build—:

```sh
docker compose run --rm node npm run check
```

Ejecutar únicamente los tests frontend con Vitest:

```sh
docker compose run --rm node npm run test
```

Los recorridos completos del editor en Chromium comprueban el comportamiento responsive en viewports móvil y escritorio. Utilizan exclusivamente `vitaetex_test`, recompilan los assets y falsean la respuesta PDF en el límite HTTP; la compilación real de Tectonic permanece cubierta por la prueba de integración backend.

```sh
./scripts/run-browser-tests.sh
```

El script inicia servicios Docker aislados bajo el perfil `e2e`, recrea el esquema de `vitaetex_test` y retira los contenedores al terminar. Para depurar con Playwright UI desde un host que tenga PHP, Node y PostgreSQL disponibles, ejecuta `npm run test:e2e:ui`; `E2E_DB_HOST`, `E2E_DB_PORT`, `E2E_DB_USERNAME`, `E2E_DB_PASSWORD` y `E2E_DB_SSLMODE` permiten ajustar la conexión, pero el nombre destructivo permanece limitado a `vitaetex_test`.

Aplicar formato PHP:

```sh
docker compose run --rm app composer format
```

Aplicar formato y correcciones automáticas frontend:

```sh
docker compose run --rm node npm run format
docker compose run --rm node npm run lint
```

Construir únicamente los assets:

```sh
docker compose run --rm node npm run build
```

## Integración de Tectonic

La imagen precalienta durante el build los recursos requeridos por el fixture. La integración se puede verificar sin red y sin conservar el PDF:

```sh
docker compose run --rm --no-deps app /usr/local/bin/verify-fixture /tmp/tectonic-output
```

Este comando comprueba la compatibilidad del entorno con el fixture estático. El compilador de la aplicación cuenta además con una prueba de integración que renderiza y compila offline un CV completo, aplica límites y elimina sus temporales. La aplicación permite descargar la fuente `.tex` y generar explícitamente un PDF mediante un endpoint `POST` autenticado, privado y limitado por usuario. El editor muestra el PDF como un Blob temporal, conserva un preview anterior como referencia cuando el CV cambia y exige regenerarlo antes de descargar exactamente ese mismo PDF visible.

El límite inicial de generación es de tres PDFs por minuto y usuario. Puede ajustarse mediante `CV_PDF_RATE_LIMIT_PER_MINUTE` después de medir el runtime desplegado.

El cleanup normal elimina los archivos de cada compilación antes de responder. Como defensa adicional ante una terminación abrupta, el scheduler registra una limpieza horaria de directorios temporales abandonados con más de 60 minutos. La antigüedad se configura mediante `CV_PDF_TEMPORARY_MAX_AGE_MINUTES` y debe ser mayor que `CV_PDF_TIMEOUT_SECONDS`.

La limpieza también puede ejecutarse manualmente:

```sh
docker compose run --rm app php artisan cv:prune-pdf-temporaries
```

## Seguridad HTTP

La aplicación añade una política CSP con nonce, headers contra framing y MIME sniffing, una política de referrer y permisos restrictivos. Las respuestas dinámicas, incluidas las descargas y los errores, se marcan como privadas y no almacenables. La interfaz utiliza fuentes del sistema y no solicita tipografías a servicios externos.

El guardado completo del editor admite inicialmente hasta 1 MiB por petición, configurable mediante `CV_EDITOR_MAXIMUM_PAYLOAD_BYTES`. El límite se comprueba antes de normalizar o validar el formulario y deja margen sobre el mayor payload válido cubierto por el contrato del MVP. Una petición mayor recibe una respuesta `413` genérica sin reflejar su contenido.

El entorno local mantiene CSP y HSTS desactivados para admitir el servidor Vite por HTTP. En un deployment HTTPS se deben configurar explícitamente al menos estos valores:

```dotenv
APP_ENV=production
APP_URL=https://vitaetex.example
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
TRUSTED_HOSTS=vitaetex.example
TRUSTED_PROXIES=*
SECURITY_CSP_ENABLED=true
SECURITY_HSTS_MAX_AGE=31536000
```

`TRUSTED_HOSTS` acepta una lista separada por comas cuando el servicio responde mediante más de un dominio. `TRUSTED_PROXIES` acepta IPs o rangos CIDR separados por comas. El valor `*` solo debe usarse cuando el runtime no sea accesible sin atravesar el proxy controlado del proveedor. Ese es el modelo de Render: su balanceador termina TLS y reenvía la petición al único puerto interno del Web Service, que [no es accesible directamente desde Internet](https://render.com/docs/web-services#port-binding). La aplicación confía únicamente en `X-Forwarded-For` y `X-Forwarded-Proto`.

En otro proveedor se deben declarar sus proxies concretos o dejar `TRUSTED_PROXIES` vacío. HSTS solo se emite cuando Laravel reconoce la petición original como HTTPS y `SECURITY_HSTS_MAX_AGE` es mayor que cero.

## Licencias de terceros

La atribución y licencia de la plantilla LaTeX adaptada están disponibles en [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
