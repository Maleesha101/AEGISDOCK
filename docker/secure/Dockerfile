# SECURE DOCKERFILE - AEGISDOCK Security Training Lab
# This version uses runtime secret injection and never stores secrets in image layers.

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

WORKDIR /var/www/html

# Install Laravel dependencies
COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy application source - NO deployment secrets ever copied
COPY api/ ./

# SECURE: Create directories for runtime secrets (mounted at runtime)
RUN mkdir -p /run/secrets/credentials

# SECURE: Never hardcode secrets in Docker image.
# Secrets MUST be injected at runtime via:
# - Docker secrets (Swarm/K8s)
# - Environment variables with -e flags
# - Volume mounts with secret files
# - Kubernetes secret objects
# - Docker run --secret flag
#
# The CRYPTO_KEY and CRYPTO_IV must NEVER be baked into the image.
# They are expected to be available at runtime via environment.

# Expose port
EXPOSE 80

# Use environment variables for secrets, injected at runtime
CMD ["apache2-foreground"]