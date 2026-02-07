#!/usr/bin/env bash
set -euo pipefail

# 如果未提供 PORT，則使用預設值 8080 (Render 會設定 $PORT)
export PORT="${PORT:-8080}"

cd /var/www/html

# 1) 確保 .env 檔案存在
if [ ! -f .env ]; then
  cp .env.example .env || true
fi

# 2) 如果未提供 APP_URL，則預設為 http://localhost:$PORT
export APP_URL="${APP_URL:-http://localhost:${PORT}}"

# 2.5) 預設將日誌輸出到 stderr，以便顯示在容器 stdout/stderr 中 (適用於 Render Logs)
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
export LOG_LEVEL="${LOG_LEVEL:-info}"
# 如果有提供 JSON formatter，Laravel 將通過 config/logging.php 使用它
export LOG_STDERR_FORMATTER="${LOG_STDERR_FORMATTER:-Monolog\\Formatter\\JsonFormatter}"

# 2.6) 確保 storage 和 cache 目錄可寫入
mkdir -p storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# 3) 確保 DB 設定
# 如果使用的是 pgsql，接受 DB_URL 或分開的變數 (DB_HOST/DB_DATABASE/DB_USERNAME)。
# 只有在兩者都未提供時才回退到 sqlite，以避免在使用分開變數時出現錯誤警告。
if [ "${DB_CONNECTION:-}" = "pgsql" ]; then
  if [ -z "${DB_URL:-}" ] && { [ -z "${DB_HOST:-}" ] || [ -z "${DB_DATABASE:-}" ] || [ -z "${DB_USERNAME:-}" ]; }; then
    echo "[entrypoint] WARN: 未提供 DB_URL 且分開變數不完整；回退到 sqlite 資料庫" >&2
    export DB_CONNECTION=sqlite
  else
    echo "[entrypoint] INFO: 使用 Postgres 透過 ${DB_URL:+DB_URL}${DB_URL:+' '}${DB_URL:+'(url)'}${DB_URL:+' '}${DB_URL:+'configured'}${DB_URL:+' '}${DB_URL:+'value'}${DB_URL:+' '}$( [ -z "${DB_URL:-}" ] && echo 'split vars' )" >&2
  fi
fi
if [ "${DB_CONNECTION:-}" = "sqlite" ]; then
  mkdir -p database
  [ -f database/database.sqlite ] || touch database/database.sqlite
  # 確保 php-fpm (www-data) 可寫入
  chown -R www-data:www-data database || true
  chmod 775 database || true
  chmod 664 database/database.sqlite || true
fi

# 4) 只有在任何地方都未提供 APP_KEY 時才生成
if [ -z "${APP_KEY:-}" ]; then
  if ! grep -q '^APP_KEY=' .env || grep -q '^APP_KEY=$' .env; then
    php artisan key:generate --force
  fi
fi

# 5) 建立 Storage link (如果已存在則忽略)
php artisan storage:link || true

# 6) 快取設定與執行遷移
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan migrate --force || true

# 7) 使用當前 $PORT 從模板渲染 Nginx 設定
if [ -f /etc/nginx/nginx.conf.template ]; then
  envsubst < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
fi

# 強制刪除任何舊的 default.conf，確保只使用我們的模板
rm -f /etc/nginx/conf.d/default.conf

# 如果未設定 App_Host，欲設為 127.0.0.1 (單一容器內的本地 php-fpm)
export App_Host="${App_Host:-127.0.0.1}"

if [ -f /etc/nginx/conf.d/default.conf.template ]; then
  envsubst '$PORT $App_Host' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf
fi

# 提早驗證 Nginx 設定以便獲得更清晰的錯誤訊息
if command -v nginx >/dev/null 2>&1; then
  if ! nginx -t; then
    echo "Nginx 設定測試失敗。輸出渲染後的設定：" >&2
    echo "----- /etc/nginx/conf.d/default.conf -----" >&2
    cat /etc/nginx/conf.d/default.conf >&2 || true
    exit 1
  fi
fi

exec "$@"
