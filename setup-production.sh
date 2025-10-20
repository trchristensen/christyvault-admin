#!/bin/bash

set -e

echo "🚀 Running production setup..."

# Ensure vendor folder is present
if [ ! -f vendor/autoload.php ]; then
    echo "📦 Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Laravel application key
if [ ! -f .env ]; then
    echo "⚠️  .env file is missing! Please create it before running the container."
    exit 1
fi

echo "⏳ Waiting for database to be ready..."
until php artisan migrate:status > /dev/null 2>&1; do
    sleep 3
    echo "Waiting for database..."
done


echo "🗄️ Running migrations and setting up DB tables..."
php artisan migrate --force

# Create cache and session tables if they don't exist (these commands generate migrations)
php artisan cache:table
php artisan session:table
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan key:generate --force

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "✅ Laravel production setup completed!"

# Start PHP-FPM
exec php-fpm
