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
    && docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd xml \
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
COPY api/composer.json ./
RUN composer install --no-dev --optimize-autoloader

# Copy application source
COPY api/ ./

# VULNERABILITY: DOCKER-02
# Layer N+1: Remove the file (but old layer still contains it!)
RUN rm /opt/legacy/terminal-gateway.env.bak

# SECURITY TRAINING: This demonstrates how secrets persist in Docker image
# layers even after file removal from the working directory. The previous COPY
# command (DOCKER-01) placed the file in a layer that remains in the image
# history, teaching learners to never store secrets in Docker images.

# SECURITY BEST PRACTICE: Multi-stage builds prevent secret leakage.
# In production, use .dockerignore, build-time secrets (ARG/ENV), or
# external secret management (Vault, KMS) instead of hardcoding in Dockerfiles.
# EXPOSE port
EXPOSE 80

# Multi-stage build recommendation:
# FROM php:8.3-apache AS builder
# ... build steps ...
# FROM php:8.3-apache AS production
# --copy-from builder --only needed files--
CMD ["apache2-foreground"]