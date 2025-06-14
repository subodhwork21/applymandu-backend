#!/bin/bash
set -e

# Copy environment file if it doesn't exist
cp -n .env.example .env || true
# Generate application key if not set
php artisan key:generate --no-interaction --force

# Wait for the database to be ready (if you're using a database)
if [ "$DB_CONNECTION" = "mysql" ]; then
  until nc -z -v -w30 $DB_HOST $DB_PORT; do
    echo "Waiting for database connection..."
    sleep 2
  done
fi

# Clear configuration cache
php artisan config:clear

# Check if Laravel Octane is installed and configure Swoole
if [ -f "vendor/bin/octane" ]; then
  # Set Swoole specific php.ini settings
  echo "
  [swoole]
  swoole.use_shortname = Off
  memory_limit = 512M
  " > /usr/local/etc/php/conf.d/swoole.ini
  
  # Make sure the session directory exists and is writable
  mkdir -p /var/www/html/storage/framework/sessions
  chmod -R 775 /var/www/html/storage/framework/sessions
  
  # Optimize for Octane if using Laravel Octane
  php artisan octane:install --server=swoole
fi

# Run migrations if the database is available
php artisan migrate --force || echo "Could not run migrations. Database may not be available yet."

# Set up Passport
php artisan passport:keys --force || echo "Could not generate Passport keys"

# Create storage link
php artisan storage:link || echo "Could not create storage link"

# Seed roles and permissions
php artisan db:seed RolesAndPermissionsSeeder || echo "Could not seed roles and permissions"

# Clear optimization
php artisan optimize:clear

# Cache configuration for production
php artisan config:cache

# Optimize for production
if [ "$APP_ENV" = "production" ]; then
  php artisan optimize
  php artisan event:cache
  php artisan view:cache
  php artisan route:cache
fi

# Execute the main command (CMD from Dockerfile)
exec "$@"