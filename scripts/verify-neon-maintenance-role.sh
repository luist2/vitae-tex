#!/bin/sh

set -eu

postgres_image="${POSTGRES_CLIENT_IMAGE:-postgres:18.4-bookworm@sha256:882236b897e39051d2368c5ccc6cda944904723506b2dfc97f2a8f5bc9afa382}"
maintenance_url="${NEON_MAINTENANCE_DATABASE_URL:-}"
database_name="${NEON_DATABASE_NAME:-vitaetex}"
maintenance_role="${NEON_MAINTENANCE_ROLE:-vitaetex_maintenance}"
script_directory="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)"
verification_script="${script_directory}/../database/operations/verify-neon-maintenance-privileges.sql"

if [ -z "${maintenance_url}" ]; then
    echo 'NEON_MAINTENANCE_DATABASE_URL must contain the direct maintenance connection string.' >&2
    exit 2
fi

case "${maintenance_url}" in
    *-pooler.*)
        echo 'Maintenance verification requires the direct Neon endpoint.' >&2
        exit 3
        ;;
esac

case "${maintenance_url}" in
    *sslmode=disable* | *sslmode=allow* | *sslmode=prefer*)
        echo 'The maintenance connection contains an insecure sslmode.' >&2
        exit 3
        ;;
    *sslmode=require* | *sslmode=verify-ca* | *sslmode=verify-full*) ;;
    *)
        echo 'The maintenance connection must explicitly require TLS in its query string.' >&2
        exit 3
        ;;
esac

export NEON_MAINTENANCE_URL="${maintenance_url}"

docker run --rm \
    --env NEON_MAINTENANCE_URL \
    --mount "type=bind,source=${verification_script},target=/verify.sql,readonly" \
    --entrypoint sh \
    "${postgres_image}" \
    -c 'exec psql "$NEON_MAINTENANCE_URL" \
        --set="database_name=$1" \
        --set="maintenance_role=$2" \
        --file=/verify.sql' \
    verify-neon-maintenance-role \
    "${database_name}" \
    "${maintenance_role}"
