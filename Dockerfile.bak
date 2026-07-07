FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies
RUN apt-get update && \
    apt-get install -y software-properties-common && \
    add-apt-repository ppa:ondrej/php -y && \
    apt-get update && \
    apt-get install -y \
    php8.2 php8.2-cli php8.2-fpm php8.2-pgsql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath \
    php8.2-intl php8.2-sqlite3 php8.2-pdo php8.2-imagick \
    tesseract-ocr tesseract-ocr-eng imagemagick ghostscript \
    git unzip curl wkhtmltopdf \
    && apt-get clean


RUN ln -s /usr/bin/wkhtmltopdf /usr/local/bin/wkhtmltopdf

# Configure ImageMagick to allow PDF processing
RUN sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/g' /etc/ImageMagick-6/policy.xml

# Configure PHP for large file uploads
RUN echo "upload_max_filesize = 50M" >> /etc/php/8.2/cli/conf.d/99-uploads.ini \
    && echo "post_max_size = 50M" >> /etc/php/8.2/cli/conf.d/99-uploads.ini \
    && echo "max_execution_time = 300" >> /etc/php/8.2/cli/conf.d/99-uploads.ini \
    && echo "memory_limit = 512M" >> /etc/php/8.2/cli/conf.d/99-uploads.ini \
    && cp /etc/php/8.2/cli/conf.d/99-uploads.ini /etc/php/8.2/fpm/conf.d/99-uploads.ini

# Set working directory
WORKDIR /var/www

# Copy existing application directory contents
COPY . /var/www

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies
RUN composer install

# Create required directories and set permissions
RUN mkdir -p /var/www/storage/app/temp \
    && mkdir -p /var/www/storage/app/private \
    && mkdir -p /var/www/storage/app/public \
    && mkdir -p /var/www/storage/framework/cache \
    && mkdir -p /var/www/storage/framework/sessions \
    && mkdir -p /var/www/storage/framework/views \
    && mkdir -p /var/www/storage/logs \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage

# Expose port 8000 for Laravel dev server
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]