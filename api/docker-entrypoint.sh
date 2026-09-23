#!/bin/sh
set -e

# One image, three roles — run it as separate services pointing at the same env:
#   CONTAINER_ROLE=web        (default) Apache serving the API + admin panel
#   CONTAINER_ROLE=worker     queue:work — halal triage / AI second-opinion jobs
#   CONTAINER_ROLE=scheduler  schedule:work — pruning, halal:lifecycle, scheduled pushes
ROLE="${CONTAINER_ROLE:-web}"

php artisan config:clear

# Only the web role migrates, so worker/scheduler replicas never race it. Set
# RUN_MIGRATIONS=false once migrations run as a separate release/pre-deploy step instead.
if [ "$ROLE" = "web" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

# Idempotent — RestaurantPhoto::publicUrl() needs public/storage to exist. The link itself
# doesn't survive a container replacement, only the underlying disk config does (see
# .env.staging.example's RESTAURANT_PHOTOS_PUBLIC_DISK note for why local disk isn't enough
# for production photo persistence).
[ -L /var/www/html/public/storage ] || php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Caches above are written as root; make sure www-data (Apache and the long-running roles
# below) can still write logs and compiled views.
chown -R www-data:www-data storage bootstrap/cache

case "$ROLE" in
    worker)
        exec runuser -u www-data -- php artisan queue:work --tries=3 --backoff=10 --max-time=3600
        ;;
    scheduler)
        exec runuser -u www-data -- php artisan schedule:work
        ;;
esac

exec "$@"
