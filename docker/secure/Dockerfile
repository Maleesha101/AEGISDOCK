# Secure API image: no deployment archive or cryptographic key is copied into this image.
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libonig-dev libxml2-dev libpq-dev zip unzip git curl \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd xml \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY api/ ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Runtime-only secret files are mounted by docker-compose.secure.yml.
# The root .dockerignore excludes secrets/ and deploy/archive/ from this build.
EXPOSE 80
CMD ["apache2-foreground"]
