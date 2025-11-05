# Use PHP official image with Apache
FROM php:8.3-apache

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

# Overwrite default 128mb memory limit to 1gb
RUN echo "memory_limit = 1024M" > /usr/local/etc/php/conf.d/memory-limit.ini

# Set working directory
WORKDIR /var/www

# Set Apache DocumentRoot to public directory
ENV APACHE_DOCUMENT_ROOT /var/www/public

# Configure Apache to serve the correct directory
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application directory contents
COPY . /var/www

# Install Node.js dependencies if package.json exists
RUN if [ -f package.json ]; then npm install; fi

# 🛠 NEW: Build frontend assets using Vite (required for Filament Monaco etc.)
RUN npm run build

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Create the storage directory if it doesn't exist as part of the image
RUN mkdir -p /var/www/storage

# Set group ownership to nonroot and make it group-writable
RUN chown -R www-data:www-data /var/www/storage \
    && chmod -R 775 /var/www/storage \
    && chmod g+s /var/www/storage

# Set correct permissions for storage, database and logs
RUN chown -R www-data:www-data /var/www/bootstrap/cache /var/www/database \
    && chmod -R 775 /var/www/bootstrap/cache /var/www/database

# Copy custom Apache configuration
COPY ports.conf /etc/apache2/ports.conf
COPY apache2.conf /etc/apache2/apache2.conf
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Generate APP_KEY
RUN echo "APP_KEY=" > .env
RUN php artisan key:generate

# Switch to non-root user
USER www-data

# Expose ports
EXPOSE 8080 443 6001

# Create entrypoint script
# RUN echo '#!/bin/bash\n\

#     if [ "$CONTAINER_ROLE" = "worker" ]; then\n\
#     echo "Running as Reverb worker..."\n\
#     exec php artisan reverb:start --host=0.0.0.0 --port=6001\n\
#     else\n\
#     echo "Running as web server..."\n\
#     exec apache2-foreground\n\
#     fi' > /var/www/entrypoint.sh && chmod +x /var/www/entrypoint.sh

CMD ["apache2-foreground"]

# Start with entrypoint script
# CMD ["/var/www/entrypoint.sh"]
