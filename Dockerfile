FROM php:8.2-fpm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    git unzip curl xfonts-75dpi xfonts-base \
    libpq-dev libzip-dev libjpeg-dev libpng-dev libfreetype6-dev \
    libonig-dev libxml2-dev libmagickwand-dev ghostscript \
    tesseract-ocr tesseract-ocr-eng imagemagick postgresql-client \
    && curl -L -o /tmp/wkhtmltox.deb https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && apt-get install -y /tmp/wkhtmltox.deb \
    && rm /tmp/wkhtmltox.deb \
    && docker-php-ext-install pdo pdo_pgsql mbstring xml zip gd bcmath intl \
    && apt-get clean


# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure ImageMagick to allow PDF processing
# Update ImageMagick policy to allow PDF processing
RUN POLICY_FILE=$(find /etc -type f -name policy.xml 2>/dev/null | head -n 1) \
    && if [ -n "$POLICY_FILE" ]; then \
        echo "Found policy file at $POLICY_FILE"; \
        sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/' "$POLICY_FILE"; \
    else \
        echo "Warning: No ImageMagick policy.xml found. Skipping PDF rights patch."; \
    fi


# Configure PHP for large file uploads
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini

# Set working directory
WORKDIR /var/www

# Copy app code
COPY . .

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader

# Set permissions
RUN mkdir -p /var/www/storage/app/temp \
    /var/www/storage/app/private \
    /var/www/storage/app/public \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/logs \
    /var/www/bootstrap/cache \
 && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
 && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

COPY setup-production.sh /usr/local/bin/setup-production.sh
RUN chmod +x /usr/local/bin/setup-production.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/setup-production.sh"]
