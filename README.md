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

## Calidad y pruebas

Backend con PHPUnit:

```sh
docker compose run --rm app composer test
```

Comprobaciones frontend —Prettier, ESLint, TypeScript y build—:

```sh
docker compose run --rm node npm run check
```

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

Este comando comprueba la compatibilidad del entorno con el fixture estático. El compilador de la aplicación cuenta además con una prueba de integración que renderiza y compila offline un CV completo, aplica límites y elimina sus temporales. La aplicación permite descargar la fuente `.tex` y expone una generación PDF explícita mediante un endpoint `POST` autenticado, privado y limitado por usuario. La integración del PDF en el panel de preview pertenece al siguiente bloque.

El límite inicial de generación es de tres PDFs por minuto y usuario. Puede ajustarse mediante `CV_PDF_RATE_LIMIT_PER_MINUTE` después de medir el runtime desplegado.

## Licencias de terceros

La atribución y licencia de la plantilla LaTeX adaptada están disponibles en [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
