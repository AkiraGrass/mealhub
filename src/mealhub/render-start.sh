#!/usr/bin/env bash
set -euo pipefail

# 1) 確保 .env 存在
if [ ! -f .env ]; then
  cp .env.example .env
fi

# 2) 如果 APP_URL 未指定，從 Render 內建變數帶入
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost}}"

# 3) 支援 sqlite：若使用 sqlite，確保檔案存在
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  mkdir -p database
  if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
  fi
fi

# 4) 生成 APP_KEY（優先使用環境變數；若未提供且 .env 沒有才生成）
if [ -z "${APP_KEY:-}" ]; then
  if ! grep -q '^APP_KEY=' .env || grep -q '^APP_KEY=$' .env; then
    php artisan key:generate --force
  fi
fi

# 5) 建立 storage link（已存在則忽略）
php artisan storage:link || true

# 6) 執行資料庫遷移
php artisan migrate --force || true

# 7) 快取設定、路由與視圖
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8) 啟動 PHP 內建伺服器於 Render 指定埠
php -S 0.0.0.0:"${PORT:-8080}" -t public public/index.php
