#!/bin/sh
set -e

echo "Starting Laravel application initialization..."

# Wait for database to be ready
echo "Waiting for database connection..."
until php artisan migrate:status > /dev/null 2>&1; do
    echo "Database not ready, waiting..."
    sleep 2
done

# Generate application key if not set
if [ "$APP_KEY" = "base64:GENERATE_NEW_KEY_WHEN_BUILDING" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache configuration
echo "Optimizing Laravel application..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Generate Swagger documentation
echo "Generating API documentation..."
php artisan l5-swagger:generate

# Create storage link if it doesn't exist
if [ ! -L /var/www/html/public/storage ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi

# Set proper permissions
echo "Setting file permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create log directories
mkdir -p /var/log/supervisor /var/log/nginx /var/log/php

echo "Laravel application initialized successfully!"

# Execute the main command
exec "$@"