#!/bin/sh

set -eu

export SERVER_NAME="${SERVER_NAME:-:${PORT:-8000}}"

exec docker-php-entrypoint "$@"
