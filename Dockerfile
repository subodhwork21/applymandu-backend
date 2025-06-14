# Base image
FROM php:8.4-fpm

# Set working directory
WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    libzip-dev zip unzip gnupg \
    libxrender1 libfontconfig1 wkhtmltopdf xvfb \
    nodejs npm netcat-openbsd \
    libbrotli-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Swoole extension
RUN pecl install swoole \
    && docker-php-ext-enable swoole

# Install additional PHP extensions that might be needed for Laravel Passport
RUN apt-get update && apt-get install -y \
    libssl-dev \
    && docker-php-ext-install opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy app files
COPY . .

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-scripts

# Ensure permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 9000
EXPOSE 9000

# Set the entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]

# Start Laravel server - this will use Octane with Swoole if available,
# otherwise fall back to the standard artisan serve
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=9000", "--workers=4", "--task-workers=2"]
