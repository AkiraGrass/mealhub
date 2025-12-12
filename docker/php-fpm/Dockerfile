# ---- Composer binary stage ----
FROM composer:2 AS composer_bin

# ---- PHP-FPM base ----
FROM php:8.3-fpm-alpine

# System packages & PHP extensions commonly used by Laravel
RUN apk add --no-cache \
    bash git curl \
    libpq-dev oniguruma-dev icu-dev libzip-dev \
    $PHPIZE_DEPS \
 && docker-php-ext-install \
    pdo pdo_pgsql mbstring intl zip opcache

# Add Composer
COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

# Dev-friendly PHP settings
RUN { \
      echo "opcache.enable=1"; \
      echo "opcache.enable_cli=1"; \
      echo "opcache.jit_buffer_size=0"; \
      echo "memory_limit=512M"; \
      echo "post_max_size=50M"; \
      echo "upload_max_filesize=50M"; \
    } > /usr/local/etc/php/conf.d/zz-local.ini

WORKDIR /var/www/html