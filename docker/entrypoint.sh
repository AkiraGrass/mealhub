#!/usr/bin/env bash
set -euo pipefail

# Default PORT if not provided (Render sets $PORT)
export PORT="${PORT:-8080}"

cd /var/www/html

# 1) Ensure .env exists
if [ ! -f .env ]; then
  cp .env.example .env || true
fi

# 2) Derive APP_URL from Render if not provided
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"

# 2.5) Default logging to stderr so logs show in container stdout/stderr (Render Logs)
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
export LOG_LEVEL="${LOG_LEVEL:-info}"
# Use JSON formatter if provided; Laravel will pick it up via config/logging.php
export LOG_STDERR_FORMATTER="${LOG_STDERR_FORMATTER:-Monolog\\Formatter\\JsonFormatter}"

# 2.6) Ensure writable storage and cache directories
mkdir -p storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# 3) Ensure DB config
# If using pgsql, accept either DB_URL or split vars (DB_HOST/DB_DATABASE/DB_USERNAME). Only fallback to sqlite
# when neither form is provided, to avoid false warnings when split vars are used.
if [ "${DB_CONNECTION:-}" = "pgsql" ]; then
  if [ -z "${DB_URL:-}" ] && { [ -z "${DB_HOST:-}" ] || [ -z "${DB_DATABASE:-}" ] || [ -z "${DB_USERNAME:-}" ]; }; then
    echo "[entrypoint] WARN: No DB_URL and incomplete split vars; falling back to sqlite database" >&2
    export DB_CONNECTION=sqlite
  else
    echo "[entrypoint] INFO: Using Postgres via ${DB_URL:+DB_URL}${DB_URL:+' '}${DB_URL:+'(url)'}${DB_URL:+' '}${DB_URL:+'configured'}${DB_URL:+' '}${DB_URL:+'value'}${DB_URL:+' '}$( [ -z "${DB_URL:-}" ] && echo 'split vars' )" >&2
  fi
fi
if [ "${DB_CONNECTION:-}" = "sqlite" ]; then
  mkdir -p database
  [ -f database/database.sqlite ] || touch database/database.sqlite
  # ensure writable for php-fpm (www-data)
  chown -R www-data:www-data database || true
  chmod 775 database || true
  chmod 664 database/database.sqlite || true
fi

# 4) Generate APP_KEY only if not provided anywhere
if [ -z "${APP_KEY:-}" ]; then
  if ! grep -q '^APP_KEY=' .env || grep -q '^APP_KEY=$' .env; then
    php artisan key:generate --force
  fi
fi

# 5) Storage link (ignore if exists)
php artisan storage:link || true

# 6) Cache and migrate
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan migrate --force || true

# 7) Render Nginx config from template with current $PORT
if [ -f /etc/nginx/nginx.conf.template ]; then
  envsubst < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
fi

if [ -f /etc/nginx/conf.d/default.conf.template ]; then
  envsubst '$PORT' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf
fi

# Validate Nginx configuration early for clearer errors
if command -v nginx >/dev/null 2>&1; then
  if ! nginx -t; then
    echo "Nginx configuration test failed. Dumping rendered config:" >&2
    echo "----- /etc/nginx/conf.d/default.conf -----" >&2
    cat /etc/nginx/conf.d/default.conf >&2 || true
    exit 1
  fi
fi

exec "$@"
