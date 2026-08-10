#!/bin/sh

set -eu

output_directory="${1:-/tmp/tectonic-output}"
pdf_path="${output_directory}/resume.pdf"
text_path="$(mktemp /tmp/tectonic-pdf-text.XXXXXX)"

cleanup() {
    rm -f "${text_path}"
}

trap cleanup EXIT HUP INT TERM

/usr/local/bin/compile-fixture "${output_directory}"

pdfinfo "${pdf_path}" | grep -Eq '^Page size:.*A4'
pdftotext -enc UTF-8 -layout "${pdf_path}" "${text_path}"

for expected_text in \
    'María José Núñez' \
    'Ingeniera de Software' \
    'Perfil profesional' \
    'Educación' \
    'Experiencia' \
    'Proyectos' \
    'Habilidades técnicas' \
    'Certificaciones'
do
    grep -Fq "${expected_text}" "${text_path}"
done

printf 'A4, Unicode español y extracción de texto verificados.\n'
