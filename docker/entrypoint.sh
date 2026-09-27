#!/bin/sh
set -e

cd /var/www/html

# Carpetas escribibles por www-data (storage va en un volumen persistente).
mkdir -p \
    storage/app/public storage/app/private \
    storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/framework/views/livewire storage/framework/views/blaze \
    storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

run_as_app_user() {
    su -s /bin/sh www-data -c "$1"
}

# Enlace público de storage (idempotente).
php artisan storage:link >/dev/null 2>&1 || true

if [ -z "${APP_KEY:-}" ]; then
    echo "AVISO: APP_KEY no está definido. Genérelo con 'php artisan key:generate --show' y póngalo en el .env del servidor." >&2
fi

# Migraciones: solo el servicio que lo pida (evita carreras entre contenedores).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

# Cachés de producción.
if [ "${APP_ENV:-production}" = "production" ]; then
    run_as_app_user 'php artisan config:cache'
    run_as_app_user 'php artisan route:cache'
    run_as_app_user 'php artisan view:cache'
fi

# El FPM atiende como www-data. Si esto falla, el contenedor no inicia en un
# estado que más tarde produciría errores 500 en componentes Livewire.
run_as_app_user 'test -w storage/framework/views/livewire && test -w storage/framework/views/blaze && test -w bootstrap/cache'

exec "$@"
