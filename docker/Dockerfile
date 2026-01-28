# Ultra-minimal Dockerfile for Laravel application
FROM php:8.3-fpm

ARG USER_ID=1000
ARG GROUP_ID=1000

# Set user and group
RUN usermod -u ${USER_ID} www-data && groupmod -g ${GROUP_ID} www-data

# Install essential dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql zip xml \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory to the Laravel project
WORKDIR /var/www

# Copy application files (including composer.json and artisan)
COPY --chown=www-data:www-data ./laravel .

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader

# Set permissions for Laravel
RUN chown -R www-data:www-data storage bootstrap/cache

# Create ll alias
RUN echo "alias ll='ls -l'" >> /root/.bashrc

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
