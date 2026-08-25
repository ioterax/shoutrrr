# syntax=docker/dockerfile:1.20

ARG FRANKENPHP_BUILDER_IMAGE=dunglas/frankenphp@sha256:862bce4c7efe77337d8f8170a262b639e1787141ffe70ae0edd9ec6ef96c6a99
ARG FRANKENPHP_RUNTIME_IMAGE=dunglas/frankenphp@sha256:214b541fe30aeed2717d54f74149b71b2bdb0089718f1fbb8b43bc170ab00939
ARG COMPOSER_IMAGE=composer@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040
ARG BUN_IMAGE=oven/bun@sha256:5ff609364c049b54eb0ff560ec96319729a972078ef2c755d758f0c6ef89c2d6

# Rebuild FrankenPHP 1.12.7 with the security-fixed transitive versions that
# are not yet present in the upstream 1.12.7 binary. The final image scan
# verifies the rebuilt Go dependency graph instead of relying on this intent.
FROM ${FRANKENPHP_BUILDER_IMAGE} AS frankenphp-builder

WORKDIR /go/src/app/caddy
RUN go get github.com/getkin/kin-openapi@v0.144.0 \
        google.golang.org/grpc@v1.82.1 \
    && go mod tidy \
    && go mod verify

WORKDIR /go/src/app/caddy/frankenphp
RUN GOBIN=/usr/local/bin \
    ../../go.sh install \
      -ldflags "-w -s -X 'github.com/caddyserver/caddy/v2.CustomVersion=FrankenPHP v1.12.7 PHP ${PHP_VERSION} Caddy' -X 'github.com/caddyserver/caddy/v2.CustomBinaryName=frankenphp' -X 'github.com/caddyserver/caddy/v2/modules/caddyhttp.ServerHeader=FrankenPHP Caddy'" \
      -buildvcs=false \
    && go version -m /usr/local/bin/frankenphp | grep -E 'github.com/getkin/kin-openapi[[:space:]]+v0\.144\.0' \
    && go version -m /usr/local/bin/frankenphp | grep -E 'google.golang.org/grpc[[:space:]]+v1\.82\.1'

# Build the common production PHP runtime once. Build dependencies installed by
# install-php-extensions are removed by that tool before this stage is copied.
FROM ${FRANKENPHP_RUNTIME_IMAGE} AS php-runtime

USER root
COPY --from=frankenphp-builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp
RUN apk upgrade --no-cache \
    && apk add --no-cache ffmpeg \
    && install-php-extensions bcmath exif gd pcntl pdo_pgsql redis-6.3.0 \
    && rm -f /usr/local/bin/install-php-extensions \
    && frankenphp version \
    && php -m | grep -Fx bcmath \
    && php -m | grep -Fx exif \
    && php -m | grep -Fx gd \
    && php -m | grep -Fx pcntl \
    && php -m | grep -Fx pdo_pgsql \
    && php -m | grep -Fx redis

# Composer is copied from an immutable Composer 2.10.2 manifest. It never
# enters the final image.
FROM ${COMPOSER_IMAGE} AS composer-bin

FROM php-runtime AS vendor

USER root
WORKDIR /var/www/html
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY storage ./storage

RUN composer install \
      --no-interaction \
      --no-plugins \
      --no-scripts \
      --prefer-dist \
    && mkdir -p \
      storage/framework/cache/data \
      storage/framework/sessions \
      storage/framework/views \
    && php artisan wayfinder:generate --with-form \
    && composer install \
      --no-dev \
      --no-interaction \
      --no-plugins \
      --no-scripts \
      --prefer-dist \
      --classmap-authoritative \
    && composer check-platform-reqs --no-dev

# Bun 1.4.0 is restricted to this native build-platform stage. Only static
# client assets leave the stage; SSR and node_modules are intentionally absent.
FROM --platform=$BUILDPLATFORM ${BUN_IMAGE} AS assets

WORKDIR /app
COPY package.json bun.lock vite.config.ts tsconfig.json ./
RUN bun install --frozen-lockfile

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=vendor /var/www/html/resources/js/actions ./resources/js/actions
COPY --from=vendor /var/www/html/resources/js/routes ./resources/js/routes
COPY --from=vendor /var/www/html/resources/js/wayfinder ./resources/js/wayfinder

ENV SKIP_WAYFINDER_GENERATE=true
ARG APP_VERSION
ENV APP_VERSION=${APP_VERSION}
RUN bun run build

# The final runtime contains PHP, the patched FrankenPHP binary, ffmpeg,
# production Composer dependencies, application code, and static assets only.
FROM php-runtime AS app

ARG APP_VERSION
ARG BUILD_DATE
ARG VCS_REF

LABEL org.opencontainers.image.title="iot.EraX Shout" \
      org.opencontainers.image.description="Hardened iot.EraX social publishing runtime derived from Shoutrrr" \
      org.opencontainers.image.url="https://shout.ioterax.app" \
      org.opencontainers.image.source="https://github.com/ioterax/shoutrrr" \
      org.opencontainers.image.documentation="https://github.com/ioterax/shoutrrr/blob/develop/README.md" \
      org.opencontainers.image.licenses="Apache-2.0" \
      org.opencontainers.image.vendor="iot.EraX LLC" \
      org.opencontainers.image.version="${APP_VERSION}" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.revision="${VCS_REF}"

ENV APP_BASE_DIR=/var/www/html \
    APP_VERSION=${APP_VERSION} \
    HEALTHCHECK_PATH=/up \
    INERTIA_SSR_ENABLED=false \
    OCTANE_SERVER=frankenphp \
    SERVER_NAME=:8080 \
    XDG_CONFIG_HOME=/var/www/html/storage/framework/frankenphp/config \
    XDG_DATA_HOME=/var/www/html/storage/framework/frankenphp/data

WORKDIR /var/www/html
USER root

COPY --chown=www-data:www-data artisan composer.json composer.lock LICENSE ./
COPY --chown=www-data:www-data app ./app
COPY --chown=www-data:www-data bootstrap ./bootstrap
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data resources/views ./resources/views
COPY --chown=www-data:www-data routes ./routes
COPY --chown=www-data:www-data storage ./storage
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY --from=assets --chown=www-data:www-data /app/public/emoji ./public/emoji

RUN mkdir -p \
      bootstrap/cache \
      storage/app/public \
      storage/framework/cache/data \
      storage/framework/frankenphp/config \
      storage/framework/frankenphp/data \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
    && ln -s ../storage/app/public public/storage \
    && chown -R www-data:www-data bootstrap/cache storage

USER www-data
RUN php artisan package:discover --ansi \
    && test ! -e node_modules \
    && ! command -v bun \
    && ! command -v node \
    && ! command -v yarn \
    && ! command -v composer \
    && ! command -v git

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD wget -q -O /dev/null http://127.0.0.1:8080/up || exit 1

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8080"]
