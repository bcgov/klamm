# Use PHP official image with Apache
FROM php:8.2-apache

# Install system dependencies for PHP extensions and other utilities
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    curl \
    postgresql-client \
    nodejs \
    npm \
    && docker-php-ext-install pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd intl zip \
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

# Install Node.js dependencies and build assets
RUN npm install \
    && npm run build

# Set correct permissions for storage, database and logs
RUN chown -R $(whoami):$(whoami) /var/www/storage /var/www/bootstrap/cache /var/www/database \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/database /var/www/storage/logs

# Copy custom Apache configuration
COPY ports.conf /etc/apache2/ports.conf
COPY apache2.conf /etc/apache2/apache2.conf
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Generate APP_KEY
RUN echo "APP_KEY=" > .env
RUN php artisan key:generate

# Expose ports
EXPOSE 8080 443

# Start Apache server
CMD ["apache2-foreground"]