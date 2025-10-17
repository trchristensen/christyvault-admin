FROM php:8.2-fpm

ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies
# Install wkhtmltopdf manually
RUN apt-get update && apt-get install -y \
    git unzip curl \
    libpq-dev libzip-dev libjpeg-dev libpng-dev libfreetype6-dev \
    libonig-dev libxml2-dev libmagickwand-dev ghostscript \
    tesseract-ocr tesseract-ocr-eng imagemagick \
    && curl -L -o /tmp/wkhtmltopdf.deb https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.6/wkhtmltox_0.12.6-1.buster_amd64.deb \
    && apt-get install -y /tmp/wkhtmltopdf.deb \
    && rm /tmp/wkhtmltopdf.deb \
    && docker-php-ext-install pdo pdo_pgsql mbstring xml zip gd bcmath intl \
    && apt-get clean


# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure ImageMagick to allow PDF processing
RUN sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/g' /etc/ImageMagick-6/policy.xml

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
    && mkdir -p /var/www/storage/app/private \
    && mkdir -p /var/www/storage/app/public \
    && mkdir -p /var/www/storage/framework/cache \
    && mkdir -p /var/www/storage/framework/sessions \
    && mkdir -p /var/www/storage/framework/views \
    && mkdir -p /var/www/storage/logs \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage

EXPOSE 9000

CMD ["php-fpm"]
