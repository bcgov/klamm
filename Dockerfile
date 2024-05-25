# Use PHP official image with Apache
FROM php:8.2-apache

# Install system dependencies for PHP extensions and other utilities
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www

# Set Apache DocumentRoot to public directory
ENV APACHE_DOCUMENT_ROOT /var/www/public

# Configure Apache to serve the correct directory
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Create necessary directories
RUN mkdir -p /var/www/storage/logs \
    /var/www/storage/framework/{cache,sessions,views,testing} \
    /var/www/bootstrap/cache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application directory contents
COPY . /var/www

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Create SQLite database file and set permissions
RUN touch /var/www/database/database.sqlite \
    && chown -R $(whoami):$(whoami) /var/www/database \
    && chmod 775 /var/www/database/database.sqlite /var/www/database

# Set correct permissions for storage, and logs
RUN chown -R $(whoami):$(whoami) /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage/logs

# Copy custom Apache configuration
COPY ports.conf /etc/apache2/ports.conf
COPY apache2.conf /etc/apache2/apache2.conf
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Generate APP_KEY
RUN echo "APP_KEY=" > .env
RUN php artisan key:generate

# Run the migrations and seeders
# todo -- delete once postgres and IDIR auth have been deployed
RUN php artisan migrate --force \
    && php artisan db:seed --force

# Clear caches
RUN php artisan cache:clear \
    && php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

# Cache configurations
RUN php artisan config:cache \
    && php artisan event:cache \
    && php artisan route:cache \
    && php artisan view:cache 

# Expose ports
EXPOSE 8080 443

# Start Apache server
CMD ["apache2-foreground"]