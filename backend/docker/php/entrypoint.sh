#!/bin/sh
set -e

if [ ! -f artisan ]; then
    echo "entrypoint: $(pwd) is not the application root (no artisan file)." >&2
    exit 1
fi

# Make sure the directories Laravel writes to exist.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "$(id -u)" -eq 0 ]; then
    chown -R "${APP_UID:-1000}:${APP_GID:-1000}" storage bootstrap/cache 2>/dev/null || true
fi

# In Kubernetes the configuration arrives from the environment and each pod has
# its own writable layer, so the caches are built here.
if [ "${OPTIMIZE_ON_BOOT:-0}" = "1" ]; then
    php artisan config:cache
    php artisan event:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
