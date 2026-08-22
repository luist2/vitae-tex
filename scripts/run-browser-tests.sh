#!/bin/sh

set -eu

cleanup() {
    docker compose --profile e2e rm --force --stop app-e2e browser-e2e >/dev/null 2>&1 || true
}

trap cleanup EXIT INT TERM

docker compose --profile e2e up \
    --abort-on-container-exit \
    --exit-code-from browser-e2e \
    browser-e2e
