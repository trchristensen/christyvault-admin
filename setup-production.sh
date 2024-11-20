#!/bin/bash

# Check if we're in production environment
if [ "$APP_ENV" != "production" ]; then
    echo "This script is for production environment only"
    exit 1
fi

# Install wkhtmltopdf and dependencies if not present
if ! command -v /usr/local/bin/wkhtmltopdf &> /dev/null; then
    echo "Installing wkhtmltopdf..."
    sudo apt-get update
    sudo apt-get install -y xfonts-75dpi xfonts-base
    wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-2/wkhtmltox_0.12.6.1-2.jammy_amd64.deb
    sudo dpkg -i wkhtmltox_0.12.6.1-2.jammy_amd64.deb
    sudo apt-get install -f
    sudo ln -s /usr/bin/wkhtmltopdf /usr/local/bin/wkhtmltopdf
    sudo ln -s /usr/bin/wkhtmltoimage /usr/local/bin/wkhtmltoimage
    rm wkhtmltox_0.12.6.1-2.jammy_amd64.deb
    echo "wkhtmltopdf installed successfully"
else
    echo "wkhtmltopdf is already installed"
fi

# Install PHP dependencies for production
composer install --no-dev --optimize-autoloader

# Clear and cache routes, config, and views
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

echo "Production setup completed!"
