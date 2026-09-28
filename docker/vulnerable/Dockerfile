# VULNERABLE DOCKERFILE - AEGISDOCK Security Training Lab
# This intentionally creates a Docker image with a secret leaked in an old layer.

FROM php:8.3-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# VULNERABILITY: DOCKER-01
# Layer N: Copy deployment archive with crypto keys to old path
COPY deploy/archive/terminal-gateway.env.bak /opt/legacy/terminal-gateway.env.bak

WORKDIR /var/www/html

# Install Laravel dependencies
COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy application source
COPY api/ ./

# VULNERABILITY: DOCKER-02
# Layer N+1: Remove the file (but old layer still contains it!)
RUN rm /opt/legacy/terminal-gateway.env.bak

# Expose port
EXPOSE 80

CMD ["apache2-foreground"]