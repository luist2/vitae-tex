#!/bin/sh

set -eu

production_image="${VITAETEX_PRODUCTION_IMAGE:-vitaetex:production}"
migration_url="${NEON_MIGRATION_DATABASE_URL:-}"

if [ -z "${migration_url}" ]; then
    echo 'NEON_MIGRATION_DATABASE_URL must contain the direct owner connection string.' >&2
    exit 2
fi

case "${migration_url}" in
    postgres://* | postgresql://*) ;;
    *)
        echo 'The migration connection must use a PostgreSQL URL.' >&2
        exit 3
        ;;
esac

case "${migration_url}" in
    *-pooler.*)
        echo 'Migrations require the direct Neon endpoint, not the pooled endpoint.' >&2
        exit 4
        ;;
esac

case "${migration_url}" in
    *sslmode=disable* | *sslmode=allow* | *sslmode=prefer*)
        echo 'The migration connection contains an insecure sslmode.' >&2
        exit 5
        ;;
    *sslmode=require* | *sslmode=verify-ca* | *sslmode=verify-full*) ;;
    *)
        echo 'The migration connection must explicitly require TLS in its query string.' >&2
        exit 5
        ;;
esac

case "${migration_url}" in
    */vitaetex | */vitaetex\?*) ;;
    *)
        echo 'The migration connection must target the vitaetex database.' >&2
        exit 6
        ;;
esac

export DB_URL="${migration_url}"

docker run --rm \
    --env APP_ENV=production \
    --env APP_DEBUG=false \
    --env DB_CONNECTION=pgsql \
    --env DB_SSLMODE=require \
    --env DB_URL \
    --env LOG_CHANNEL=stderr \
    --env LOG_LEVEL=warning \
    --entrypoint php \
    "${production_image}" \
    artisan migrate --force
