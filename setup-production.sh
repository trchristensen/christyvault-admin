#!/bin/bash

set -e

echo "🚀 Running production setup..."

# Ensure vendor folder is present
if [ ! -f vendor/autoload.php ]; then
    echo "📦 Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Check for .env
if [ ! -f .env ]; then
    echo "⚠️  .env file is missing! Please create it before running the container."
    exit 1
fi

# Remove schema dump to avoid migration errors
if [ -f database/schema/pgsql-schema.sql ]; then
    echo "🧹 Removing existing schema dump to avoid conflicts..."
    rm database/schema/pgsql-schema.sql
fi

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
until php artisan migrate:status > /dev/null 2>&1; do
    sleep 3
    echo "⏳ Waiting for database..."
done

# Generate cache/session table migrations before running them
echo "🛠️ Generating cache and session table migrations..."
php artisan cache:table
php artisan session:table

# Run migrations
echo "🗄️ Running migrations and setting up DB tables..."
php artisan migrate --force

# Laravel cleanup and optimization
echo "🧼 Clearing old caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo "⚙️ Rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Generate app key if missing
echo "🔑 Generating app key..."
php artisan key:generate --force

# Set proper permissions
echo "🔐 Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "✅ Laravel production setup completed!"

# Start PHP-FPM
exec php-fpm
