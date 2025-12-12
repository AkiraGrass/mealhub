FROM php:8.3-fpm-alpine

# 與 app 保持相同 PHP/extension，避免版本或套件不一致
RUN apk add --no-cache bash curl libpq-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql opcache

WORKDIR /var/www/html

# 每 60 秒執行一次 Laravel 排程
CMD ["sh", "-c", "while true; do php artisan schedule:run --no-interaction --verbose; sleep 60; done"]