#!/bin/sh
set -e

# Configure Apache port dynamically from $PORT (required by Render, Railway, etc.)
PORT_TO_USE="${PORT:-80}"
sed -i "s/Listen 80/Listen ${PORT_TO_USE}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT_TO_USE}>/g" /etc/apache2/sites-available/*.conf

# Ensure storage and bootstrap/cache directories exist and have proper permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache \
         /var/www/html/database

# If using SQLite and database file doesn't exist, create it
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
fi

# Ensure permissions for www-data
chown -R www-data:www-data /var/www/html/storage \
                           /var/www/html/bootstrap/cache \
                           /var/www/html/database
chmod -R 775 /var/www/html/storage \
             /var/www/html/bootstrap/cache \
             /var/www/html/database

# Run migrations and optional seeder if enabled or in production
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Ejecutando migraciones de base de datos..."
    php artisan migrate --force || true

    if [ "${RUN_SEEDER:-false}" = "true" ]; then
        echo "Ejecutando sembrado inicial de base de datos..."
        php artisan db:seed --force || true
    fi
fi

# Cache configuration, routes, and views if APP_KEY is set
if [ -n "$APP_KEY" ]; then
    echo "Optimizando caches de Laravel..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "Iniciando servidor web Apache en puerto ${PORT_TO_USE}..."
exec apache2-foreground
