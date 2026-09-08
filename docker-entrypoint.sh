#!/bin/sh
# Runs once at container START (never at image build time), so that editing
# `.env` and running `docker compose up -d --force-recreate` always picks up
# the new values without ever needing an image rebuild.
set -e

php artisan config:cache

# Only the app (php-fpm) container runs migrations. `app` and `queue` start
# from the same image/entrypoint and come up at roughly the same time — if
# both ran `migrate --force` concurrently, they'd race to create the
# `migrations` table itself (confirmed: this crashed the queue container with
# a Postgres unique-violation on the table's own sequence). One designated
# runner avoids the race entirely rather than trying to make migrations
# safe under concurrent first-run execution.
if [ "$1" = "php-fpm" ]; then
    php artisan migrate --force

    # Only the app service serves HTTP via nginx, which needs its own copy of
    # the public/ directory (nginx and app are separate containers/
    # filesystems). Sync it into the shared `app-public` volume every start so
    # `web` always has the same build/index.php the app container is running
    # — an explicit copy avoids depending on which container happens to touch
    # the (initially empty) named volume first, which Docker doesn't guarantee.
    mkdir -p /var/www/html/public-shared
    cp -a /var/www/html/public/. /var/www/html/public-shared/
fi

exec "$@"
