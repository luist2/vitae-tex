#!/bin/sh

set -eu

script_directory="$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)"
spike_directory="$(CDPATH='' cd -- "${script_directory}/.." && pwd)"
compiler_image="vitaetex-tectonic-spike:compiler-0.16.9"
runtime_image="vitaetex-tectonic-spike:runtime-0.16.9"
temporary_root="$(mktemp -d /tmp/vitaetex-tectonic-spike.XXXXXX)"

cleanup() {
    case "${temporary_root}" in
        /tmp/vitaetex-tectonic-spike.*)
            if docker image inspect "${compiler_image}" >/dev/null 2>&1; then
                docker run --rm \
                    --user 0:0 \
                    --network none \
                    --mount "type=bind,source=${temporary_root},target=/cleanup" \
                    --entrypoint /bin/sh \
                    "${compiler_image}" \
                    -c 'find /cleanup -mindepth 1 -delete' \
                    >/dev/null 2>&1 || true
            fi
            find "${temporary_root}" -mindepth 1 -delete 2>/dev/null || true
            rmdir "${temporary_root}" 2>/dev/null || true
            ;;
    esac
}

trap cleanup EXIT HUP INT TERM

chmod 0777 "${temporary_root}"
mkdir --parents \
    "${temporary_root}/cache" \
    "${temporary_root}/cold-output" \
    "${temporary_root}/warm-output" \
    "${temporary_root}/runtime-output"
chmod 0777 \
    "${temporary_root}/cache" \
    "${temporary_root}/cold-output" \
    "${temporary_root}/warm-output" \
    "${temporary_root}/runtime-output"

common_runtime_arguments="--rm --read-only --security-opt=no-new-privileges --cap-drop=ALL --pids-limit=128 --memory=512m --cpus=1 --tmpfs=/tmp:rw,nosuid,nodev,noexec,size=64m,mode=1777 --tmpfs=/var/cache/tectonic/fontconfig:rw,nosuid,nodev,noexec,size=8m,mode=1777"

printf 'Construyendo imagen sin cache precalentada...\n'
docker build \
    --file "${spike_directory}/Dockerfile" \
    --target compiler \
    --tag "${compiler_image}" \
    "${spike_directory}"

printf '\nCompilación fría (cache vacío, red disponible):\n'
# shellcheck disable=SC2086
docker run ${common_runtime_arguments} \
    --network bridge \
    --mount "type=bind,source=${temporary_root}/cache,target=/var/cache/tectonic" \
    --mount "type=bind,source=${temporary_root}/cold-output,target=/output" \
    --entrypoint /usr/bin/time \
    "${compiler_image}" \
    -f 'cold elapsed=%e s cpu=%P max_rss=%M KiB' \
    /usr/local/bin/compile-fixture /output

printf '\nCompilación caliente (misma cache, sin red):\n'
# shellcheck disable=SC2086
docker run ${common_runtime_arguments} \
    --network none \
    --env TECTONIC_ONLY_CACHED=1 \
    --mount "type=bind,source=${temporary_root}/cache,target=/var/cache/tectonic" \
    --mount "type=bind,source=${temporary_root}/warm-output,target=/output" \
    --entrypoint /usr/bin/time \
    "${compiler_image}" \
    -f 'warm elapsed=%e s cpu=%P max_rss=%M KiB' \
    /usr/local/bin/compile-fixture /output

printf '\nConstruyendo imagen de runtime con cache precalentada...\n'
docker build \
    --file "${spike_directory}/Dockerfile" \
    --target runtime \
    --tag "${runtime_image}" \
    "${spike_directory}"

printf '\nVerificación de la imagen final sin red:\n'
# shellcheck disable=SC2086
docker run ${common_runtime_arguments} \
    --network none \
    --mount "type=bind,source=${temporary_root}/runtime-output,target=/output" \
    --entrypoint /usr/local/bin/verify-fixture \
    "${runtime_image}" \
    /output

printf '\nSpike completado; no se conservaron PDFs ni caches de ejecución en el workspace.\n'
