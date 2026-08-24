#!/bin/sh

set -eu

postgres_image="${POSTGRES_CLIENT_IMAGE:-postgres:18.4-bookworm@sha256:882236b897e39051d2368c5ccc6cda944904723506b2dfc97f2a8f5bc9afa382}"
owner_url="${NEON_MIGRATION_DATABASE_URL:-}"
database_name="${NEON_DATABASE_NAME:-vitaetex}"
owner_role="${NEON_MIGRATION_ROLE:-neondb_owner}"
runtime_role="${NEON_RUNTIME_ROLE:-vitaetex_app}"
script_directory="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd -P)"
operations_directory="${script_directory}/../database/operations"

if [ -z "${owner_url}" ]; then
    echo 'NEON_MIGRATION_DATABASE_URL must contain the direct owner connection string.' >&2
    exit 2
fi

case "${owner_url}" in
    *-pooler.*)
        echo 'Role configuration requires the direct Neon endpoint.' >&2
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

case "${database_name}:${owner_role}:${runtime_role}" in
    *[!a-zA-Z0-9_:]*)
        echo 'Database and role names may contain only letters, numbers, and underscores.' >&2
        exit 4
        ;;
esac

export NEON_OWNER_URL="${owner_url}"

docker run --rm --interactive --tty \
    --env NEON_OWNER_URL \
    --mount "type=bind,source=${operations_directory},target=/operations,readonly" \
    --entrypoint sh \
    "${postgres_image}" \
    -c 'set -eu

        cleanup_password() {
            stty echo 2>/dev/null || true
            unset NEON_RUNTIME_PASSWORD runtime_password_confirmation
        }

        trap cleanup_password EXIT HUP INT TERM
        stty -echo

        printf "Contraseña nueva y exclusiva para %s: " "$3" >&2
        IFS= read -r NEON_RUNTIME_PASSWORD
        printf "\nRepita la contraseña: " >&2
        IFS= read -r runtime_password_confirmation
        stty echo
        printf "\n" >&2

        if [ -z "$NEON_RUNTIME_PASSWORD" ]; then
            echo "La contraseña de runtime no puede estar vacía." >&2
            exit 5
        fi

        if [ "$NEON_RUNTIME_PASSWORD" != "$runtime_password_confirmation" ]; then
            echo "Las contraseñas no coinciden." >&2
            exit 5
        fi

        unset runtime_password_confirmation
        export NEON_RUNTIME_PASSWORD

        psql "$NEON_OWNER_URL" \
            --set="database_name=$1" \
            --set="owner_role=$2" \
            --set="runtime_role=$3" \
            --file=/operations/create-neon-runtime-role.sql

        unset NEON_RUNTIME_PASSWORD

        psql "$NEON_OWNER_URL" \
            --set="database_name=$1" \
            --set="owner_role=$2" \
            --set="runtime_role=$3" \
            --file=/operations/grant-neon-runtime-privileges.sql' \
    configure-neon-runtime-role \
    "${database_name}" \
    "${owner_role}" \
    "${runtime_role}"
