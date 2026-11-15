#!/bin/sh
# Runs at every container start on Render, after the environment variables are available.
set -e

cd /var/www/html

# Cache config, routes, views and events from the live environment variables.
php artisan optimize

# Apply any new migrations to the Neon database (safe to run repeatedly).
php artisan migrate --force --no-interaction

# public/storage -> storage/app/public for uploaded files.
php artisan storage:link --no-interaction >/dev/null 2>&1 || true

exec "$@"
