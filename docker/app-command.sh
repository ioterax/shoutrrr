#!/bin/sh
set -eu

# The image ships safe production defaults in php-production.ini. These knobs
# remain useful for local Docker deployments without weakening the defaults used
# by Cloud Run jobs, which invoke PHP directly.
PHP_MEMORY_LIMIT_VALUE="${PHP_MEMORY_LIMIT:-512M}"
PHP_POST_MAX_SIZE_VALUE="${PHP_POST_MAX_SIZE:-20M}"
PHP_UPLOAD_MAX_FILE_SIZE_VALUE="${PHP_UPLOAD_MAX_FILE_SIZE:-8M}"
OCTANE_WORKERS_VALUE="${OCTANE_WORKERS:-2}"
OCTANE_MAX_REQUESTS_VALUE="${OCTANE_MAX_REQUESTS:-250}"

validate_ini_size() {
    setting_name="$1"
    setting_value="$2"
    numeric_value="${setting_value%[KMGkmg]}"

    case "$numeric_value" in
        ''|*[!0-9]*)
            echo "[runtime] invalid ${setting_name}: ${setting_value}" >&2
            exit 64
            ;;
    esac

    if [ "$numeric_value" -le 0 ]; then
        echo "[runtime] invalid ${setting_name}: ${setting_value}" >&2
        exit 64
    fi
}

validate_positive_integer() {
    setting_name="$1"
    setting_value="$2"

    case "$setting_value" in
        ''|*[!0-9]*)
            echo "[runtime] invalid ${setting_name}: ${setting_value}" >&2
            exit 64
            ;;
    esac

    if [ "$setting_value" -le 0 ]; then
        echo "[runtime] invalid ${setting_name}: ${setting_value}" >&2
        exit 64
    fi
}

validate_ini_size PHP_MEMORY_LIMIT "$PHP_MEMORY_LIMIT_VALUE"
validate_ini_size PHP_POST_MAX_SIZE "$PHP_POST_MAX_SIZE_VALUE"
validate_ini_size PHP_UPLOAD_MAX_FILE_SIZE "$PHP_UPLOAD_MAX_FILE_SIZE_VALUE"
validate_positive_integer OCTANE_WORKERS "$OCTANE_WORKERS_VALUE"
validate_positive_integer OCTANE_MAX_REQUESTS "$OCTANE_MAX_REQUESTS_VALUE"

php \
    -d "memory_limit=${PHP_MEMORY_LIMIT_VALUE}" \
    -d "post_max_size=${PHP_POST_MAX_SIZE_VALUE}" \
    -d "upload_max_filesize=${PHP_UPLOAD_MAX_FILE_SIZE_VALUE}" \
    artisan optimize --no-ansi --no-interaction

exec php \
    -d "memory_limit=${PHP_MEMORY_LIMIT_VALUE}" \
    -d "post_max_size=${PHP_POST_MAX_SIZE_VALUE}" \
    -d "upload_max_filesize=${PHP_UPLOAD_MAX_FILE_SIZE_VALUE}" \
    artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=8080 \
    --workers="$OCTANE_WORKERS_VALUE" \
    --max-requests="$OCTANE_MAX_REQUESTS_VALUE" \
    --no-ansi \
    --no-interaction
