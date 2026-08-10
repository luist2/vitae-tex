#!/bin/sh

set -eu

umask 077

output_directory="${1:-/tmp/tectonic-output}"
input_path="/opt/tectonic-spike/resume.tex"
pdf_path="${output_directory}/resume.pdf"

mkdir --parents "${output_directory}"

if [ "${TECTONIC_ONLY_CACHED:-0}" = "1" ]; then
    set -- --only-cached
else
    set --
fi

tectonic -X compile \
    --untrusted \
    "$@" \
    --outdir "${output_directory}" \
    "${input_path}"

test -s "${pdf_path}"

pdf_header="$(dd if="${pdf_path}" bs=5 count=1 2>/dev/null)"
test "${pdf_header}" = "%PDF-"

pdf_size="$(wc -c < "${pdf_path}")"
test "${pdf_size}" -ge 1000
test "${pdf_size}" -le 5242880

printf 'PDF validado: %s bytes en %s\n' "${pdf_size}" "${pdf_path}"
