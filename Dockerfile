# syntax=docker/dockerfile:1.7

ARG FRANKENPHP_VERSION=1.12.7
ARG PHP_VERSION=8.4
ARG FRANKENPHP_DIGEST=sha256:df76530f51b4ccf19211b1198e297bbecb7dbdff859f327efb6e0e1d86ad0ae7

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-bookworm@${FRANKENPHP_DIGEST} AS development

ARG TARGETARCH
ARG TECTONIC_VERSION=0.16.9

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    LC_ALL=C.UTF-8 \
    TECTONIC_ONLY_CACHED=1 \
    TECTONIC_UNTRUSTED_MODE=1 \
    XDG_CACHE_HOME=/var/cache/tectonic

RUN cp "${PHP_INI_DIR}/php.ini-development" "${PHP_INI_DIR}/php.ini" \
    && install-php-extensions intl opcache pdo_pgsql zip \
    && apt-get update \
    && apt-get install --yes --no-install-recommends ca-certificates curl git poppler-utils unzip \
    && case "${TARGETARCH}" in \
        amd64) \
            tectonic_platform="x86_64-unknown-linux-musl"; \
            tectonic_sha256="60b13a0826ae7ad9ce34b4a2df06bff2cfcfa6dda8a915477c0cbb84e1a4a902" \
            ;; \
        arm64) \
            tectonic_platform="aarch64-unknown-linux-musl"; \
            tectonic_sha256="f9aa39017dbd51f111fdb93dda222178cbe51c8193508fc567b523cc74fff9c1" \
            ;; \
        *) \
            echo "Unsupported architecture: ${TARGETARCH}" >&2; \
            exit 1 \
            ;; \
       esac \
    && tectonic_archive="tectonic-${TECTONIC_VERSION}-${tectonic_platform}.tar.gz" \
    && curl --fail --location --proto '=https' --retry 3 --tlsv1.2 \
        "https://github.com/tectonic-typesetting/tectonic/releases/download/tectonic%40${TECTONIC_VERSION}/${tectonic_archive}" \
        --output "/tmp/${tectonic_archive}" \
    && printf '%s  %s\n' "${tectonic_sha256}" "/tmp/${tectonic_archive}" | sha256sum --check --strict \
    && tar --extract --gzip --file "/tmp/${tectonic_archive}" --directory /usr/local/bin tectonic \
    && chmod 0755 /usr/local/bin/tectonic \
    && rm "/tmp/${tectonic_archive}" \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --gid 10001 vitaetex \
    && useradd --uid 10001 --gid vitaetex --no-create-home --shell /usr/sbin/nologin vitaetex \
    && mkdir --parents /opt/tectonic-spike /var/cache/tectonic \
    && chown --recursive vitaetex:vitaetex /opt/tectonic-spike /var/cache/tectonic

COPY --from=composer:2.10.2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/local/bin/composer
COPY --chmod=0755 spikes/tectonic/scripts/compile-fixture.sh /usr/local/bin/compile-fixture
COPY --chmod=0755 spikes/tectonic/scripts/verify-fixture.sh /usr/local/bin/verify-fixture
COPY --chown=vitaetex:vitaetex spikes/tectonic/fixture/resume.tex /opt/tectonic-spike/resume.tex
COPY --chown=vitaetex:vitaetex spikes/tectonic/LICENSE-JAKES-RESUME /opt/tectonic-spike/LICENSE-JAKES-RESUME

USER 10001:10001

RUN prewarm_directory="$(mktemp -d /tmp/tectonic-prewarm.XXXXXX)" \
    && TECTONIC_ONLY_CACHED=0 /usr/local/bin/compile-fixture "${prewarm_directory}" \
    && rm "${prewarm_directory}/resume.pdf" \
    && rmdir "${prewarm_directory}" \
    && test -n "$(find /var/cache/tectonic -type f -print -quit)"

USER root
WORKDIR /app
