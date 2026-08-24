#!/bin/sh

set -eu

postgres_image="${POSTGRES_CLIENT_IMAGE:-postgres:18.4-bookworm@sha256:882236b897e39051d2368c5ccc6cda944904723506b2dfc97f2a8f5bc9afa382}"
runtime_url="${NEON_RUNTIME_DATABASE_URL:-}"
database_name="${NEON_DATABASE_NAME:-vitaetex}"
runtime_role="${NEON_RUNTIME_ROLE:-vitaetex_app}"
script_directory="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)"
verification_script="${script_directory}/../database/operations/verify-neon-runtime-privileges.sql"

if [ -z "${runtime_url}" ]; then
    echo 'NEON_RUNTIME_DATABASE_URL must contain the direct runtime connection string.' >&2
    exit 2
fi

case "${runtime_url}" in
    *-pooler.*)
        echo 'Initial runtime verification requires the direct Neon endpoint.' >&2
        exit 3
        ;;
esac

case "${runtime_url}" in
    *sslmode=disable* | *sslmode=allow* | *sslmode=prefer*)
        echo 'The runtime connection contains an insecure sslmode.' >&2
        exit 3
        ;;
    *sslmode=require* | *sslmode=verify-ca* | *sslmode=verify-full*) ;;
    *)
        echo 'The runtime connection must explicitly require TLS in its query string.' >&2
        exit 3
        ;;
esac

export NEON_RUNTIME_URL="${runtime_url}"

docker run --rm \
    --env NEON_RUNTIME_URL \
    --mount "type=bind,source=${verification_script},target=/verify.sql,readonly" \
    --entrypoint sh \
    "${postgres_image}" \
    -c 'exec psql "$NEON_RUNTIME_URL" \
        --set="database_name=$1" \
        --set="runtime_role=$2" \
        --file=/verify.sql' \
    verify-neon-runtime-role \
    "${database_name}" \
    "${runtime_role}"
