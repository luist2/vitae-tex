# syntax=docker/dockerfile:1.7

ARG FRANKENPHP_VERSION=1.12.7
ARG PHP_VERSION=8.4
ARG FRANKENPHP_DIGEST=sha256:df76530f51b4ccf19211b1198e297bbecb7dbdff859f327efb6e0e1d86ad0ae7
ARG NODE_IMAGE=node:24.18.0-bookworm-slim@sha256:6f7b03f7c2c8e2e784dcf9295400527b9b1270fd37b7e9a7285cf83b6951452d

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-bookworm@${FRANKENPHP_DIGEST} AS php-base

ENV LC_ALL=C.UTF-8 \
    TECTONIC_ONLY_CACHED=1 \
    TECTONIC_UNTRUSTED_MODE=1 \
    XDG_CACHE_HOME=/var/cache/tectonic

RUN install-php-extensions intl opcache pdo_pgsql zip \
    && apt-get update \
    && apt-get install --yes --no-install-recommends ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --gid 10001 vitaetex \
    && useradd --uid 10001 --gid vitaetex --no-create-home --shell /usr/sbin/nologin vitaetex \
    && mkdir --parents /var/cache/tectonic

FROM php-base AS tectonic-cache

ARG TARGETARCH
ARG TECTONIC_VERSION=0.16.9

RUN apt-get update \
    && apt-get install --yes --no-install-recommends curl \
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
    && mkdir --parents /opt/tectonic-spike \
    && chown --recursive vitaetex:vitaetex /opt/tectonic-spike /var/cache/tectonic

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

RUN chmod --recursive a=rX /var/cache/tectonic

FROM tectonic-cache AS development

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN cp "${PHP_INI_DIR}/php.ini-development" "${PHP_INI_DIR}/php.ini" \
    && apt-get update \
    && apt-get install --yes --no-install-recommends git poppler-utils unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.10.2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

FROM tectonic-cache AS php-dependencies

ENV APP_ENV=build \
    COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2.10.2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

RUN apt-get update \
    && apt-get install --yes --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY app app
COPY bootstrap bootstrap
COPY config config
COPY database database
COPY lang lang
COPY public public
COPY resources resources
COPY routes routes
COPY artisan composer.json composer.lock THIRD_PARTY_NOTICES.md ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

FROM ${NODE_IMAGE} AS frontend-assets

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY resources resources
COPY public public
COPY components.json tailwind.config.js tsconfig.json vite.config.ts ./
COPY --from=php-dependencies /app/vendor/tightenco/ziggy vendor/tightenco/ziggy
COPY --from=php-dependencies /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views vendor/laravel/framework/src/Illuminate/Pagination/resources/views

RUN npm run build

FROM php-base AS production

ENV APP_DEBUG=false \
    APP_ENV=production \
    DB_SSLMODE=require \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=warning \
    PORT=8000 \
    SERVER_ROOT=/app/public

WORKDIR /app

RUN cp "${PHP_INI_DIR}/php.ini-production" "${PHP_INI_DIR}/php.ini" \
    && apt-get update \
    && apt-get install --yes --no-install-recommends fontconfig-config \
    && rm -rf /var/lib/apt/lists/*

COPY --from=tectonic-cache --chmod=0755 /usr/local/bin/tectonic /usr/local/bin/tectonic
COPY --from=tectonic-cache /var/cache/tectonic /var/cache/tectonic
COPY --from=php-dependencies /app /app
COPY --from=frontend-assets /app/public/build /app/public/build
COPY --chmod=0755 docker/production/entrypoint.sh /usr/local/bin/vitaetex-entrypoint

RUN mkdir --parents \
        /config \
        /data \
        /var/cache/tectonic/fontconfig \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
    && chown --recursive vitaetex:vitaetex \
        /config \
        /data \
        /var/cache/tectonic/fontconfig \
        bootstrap/cache \
        storage \
    && chmod --recursive go-w /var/cache/tectonic \
    && chmod --recursive u+rwX bootstrap/cache storage

USER 10001:10001

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r '$port = getenv("PORT") ?: "8000"; $host = parse_url(getenv("APP_URL") ?: "http://localhost", PHP_URL_HOST) ?: "localhost"; $context = stream_context_create(["http" => ["header" => "Host: ".$host, "timeout" => 4]]); exit(@file_get_contents("http://127.0.0.1:".$port."/up", false, $context) === false ? 1 : 0);'

ENTRYPOINT ["/usr/local/bin/vitaetex-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
