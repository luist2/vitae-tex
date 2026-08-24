#!/bin/sh

set -eu

postgres_image="${POSTGRES_CLIENT_IMAGE:-postgres:18.4-bookworm@sha256:882236b897e39051d2368c5ccc6cda944904723506b2dfc97f2a8f5bc9afa382}"
owner_url="${NEON_MIGRATION_DATABASE_URL:-}"
database_name="${NEON_DATABASE_NAME:-vitaetex}"
owner_role="${NEON_MIGRATION_ROLE:-neondb_owner}"
runtime_role="${NEON_RUNTIME_ROLE:-vitaetex_app}"
script_directory="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)"
grant_script="${script_directory}/../database/operations/grant-neon-runtime-privileges.sql"

if [ -z "${owner_url}" ]; then
    echo 'NEON_MIGRATION_DATABASE_URL must contain the direct owner connection string.' >&2
    exit 2
fi

case "${owner_url}" in
    *-pooler.*)
        echo 'Privilege configuration requires the direct Neon endpoint.' >&2
        exit 3
        ;;
esac

case "${owner_url}" in
    *sslmode=disable* | *sslmode=allow* | *sslmode=prefer*)
        echo 'The owner connection contains an insecure sslmode.' >&2
        exit 3
        ;;
    *sslmode=require* | *sslmode=verify-ca* | *sslmode=verify-full*) ;;
    *)
        echo 'The owner connection must explicitly require TLS in its query string.' >&2
        exit 3
        ;;
esac

export NEON_OWNER_URL="${owner_url}"

docker run --rm \
    --env NEON_OWNER_URL \
    --mount "type=bind,source=${grant_script},target=/grant.sql,readonly" \
    --entrypoint sh \
    "${postgres_image}" \
    -c 'exec psql "$NEON_OWNER_URL" \
        --set="database_name=$1" \
        --set="owner_role=$2" \
        --set="runtime_role=$3" \
        --file=/grant.sql' \
    grant-neon-runtime-privileges \
    "${database_name}" \
    "${owner_role}" \
    "${runtime_role}"
