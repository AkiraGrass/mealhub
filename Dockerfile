############################
# Builder: Composer 依賴（僅建置期）
# - 在獨立階段安裝 vendor，避免最終映像過大
############################
FROM php:8.3-cli-alpine AS composer_deps
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN apk add --no-cache git zip unzip \
  && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
WORKDIR /app
# Copy full app to ensure Composer scripts (artisan) can run
COPY src/mealhub/ ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-ansi --optimize-autoloader

############################
# Runtime: Nginx + PHP-FPM + Supervisor
# - Alpine 為精簡版 Linux，適合雲端環境
############################
FROM php:8.3-fpm-alpine AS runtime

# 安裝系統相依與常用 PHP 擴充
RUN apk add --no-cache \
  bash curl nginx supervisor gettext \
  icu-dev oniguruma-dev libzip-dev zlib-dev postgresql-dev \
  $PHPIZE_DEPS \
  && docker-php-ext-install \
  pdo pdo_pgsql mbstring intl zip opcache

WORKDIR /var/www/html

# 複製應用程式原始碼
COPY src/mealhub/ ./

# Safety: avoid baking local .env into the image
RUN rm -f .env

# Use production package manifest (no-dev) to avoid loading dev providers
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
COPY --from=composer_deps /app/bootstrap/cache ./bootstrap/cache

# 複製 Composer 依賴（來自上方 builder）
COPY --from=composer_deps /app/vendor ./vendor

#（已移除）複製前端資產：純 API 無需 public/build

# 複製 Nginx/Supervisor 設定樣板
# 先清空 nginx 預設配置，避免與我們的模板衝突
RUN rm -f /etc/nginx/conf.d/default.conf
COPY docker/nginx/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/nginx/default.conf.template /etc/nginx/conf.d/default.conf.template
COPY docker/supervisord.conf /etc/supervisord.conf

# 入口腳本：啟動前初始化環境
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# 權限處理：確保 Laravel 可寫入 storage 與 bootstrap/cache
# - 使用 www-data（php-fpm 預設帳號）避免寫檔權限問題
RUN adduser -D -H -u 1000 appuser \
  && mkdir -p storage/logs bootstrap/cache \
  && chown -R www-data:www-data storage bootstrap/cache \
  && chmod -R ug+rwX storage bootstrap/cache \
  && mkdir -p /run/nginx /var/log/nginx /var/cache/nginx /var/log/supervisor

ENV PORT=8080 APP_ENV=production APP_DEBUG=false

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
