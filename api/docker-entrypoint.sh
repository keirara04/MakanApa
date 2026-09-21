#!/bin/sh
set -e

php artisan config:clear

php artisan migrate --force

# Idempotent — RestaurantPhoto::publicUrl() needs public/storage to exist. The link itself
# doesn't survive a container replacement, only the underlying disk config does (see
# .env.staging.example's RESTAURANT_PHOTOS_PUBLIC_DISK note for why local disk isn't enough
# for production photo persistence).
[ -L /var/www/html/public/storage ] || php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
