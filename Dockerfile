FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

# Force Ubuntu ARM repositories to HTTPS
RUN sed -i \
        's|http://ports.ubuntu.com/ubuntu-ports|https://ports.ubuntu.com/ubuntu-ports|g' \
        /etc/apt/sources.list 2>/dev/null || true; \
    find /etc/apt/sources.list.d \
        -type f \
        \( -name '*.list' -o -name '*.sources' \) \
        -exec sed -i \
            's|http://ports.ubuntu.com/ubuntu-ports|https://ports.ubuntu.com/ubuntu-ports|g' \
            {} +

# Bootstrap CA certificates.
# TLS peer verification is disabled ONLY for this bootstrap step.
RUN apt-get update \
        -o Acquire::https::Verify-Peer=false \
    && apt-get install -y \
        -o Acquire::https::Verify-Peer=false \
        ca-certificates \
    && update-ca-certificates \
    && rm -rf /var/lib/apt/lists/*
# Install dependencies
RUN apt-get update && \
    apt-get install -y software-properties-common && \
    add-apt-repository ppa:ondrej/php -y && \
    apt-get update && \
    apt-get install -y \
    php8.4 php8.4-cli php8.4-fpm php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath \
    php8.4-intl php8.4-sqlite3 php8.4-pdo php8.4-imagick \
    tesseract-ocr tesseract-ocr-eng imagemagick ghostscript \
    git unzip curl wkhtmltopdf \
    && update-alternatives --set php /usr/bin/php8.4 \
    && apt-get clean

RUN php -v && php -r 'exit(PHP_VERSION_ID >= 80401 ? 0 : 1);'


RUN ln -s /usr/bin/wkhtmltopdf /usr/local/bin/wkhtmltopdf

# Configure ImageMagick to allow PDF processing
RUN sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/g' /etc/ImageMagick-6/policy.xml

# Configure PHP for large file uploads
RUN echo "upload_max_filesize = 50M" >> /etc/php/8.4/cli/conf.d/99-uploads.ini \
    && echo "post_max_size = 50M" >> /etc/php/8.4/cli/conf.d/99-uploads.ini \
    && echo "max_execution_time = 300" >> /etc/php/8.4/cli/conf.d/99-uploads.ini \
    && echo "memory_limit = 512M" >> /etc/php/8.4/cli/conf.d/99-uploads.ini \
    && cp /etc/php/8.4/cli/conf.d/99-uploads.ini /etc/php/8.4/fpm/conf.d/99-uploads.ini

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