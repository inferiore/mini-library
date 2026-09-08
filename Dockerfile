# syntax=docker/dockerfile:1.7
#
# Multi-stage build for the Mini Library Management System.
#
#   base       - shared PHP-FPM runtime + extensions, nothing app-specific.
#   dev        - used locally by docker-compose.override.yml. Full Composer
#                install (incl. dev deps), but NO application code is baked
#                in — source is bind-mounted at container start, so editing
#                a PHP file needs neither a rebuild nor a recreation.
#   build      - production-only. Has both PHP and Node because Laravel
#                Wayfinder's Vite plugin shells out to `php artisan
#                wayfinder:generate` *during* `npm run build` to emit typed
#                route/action helpers — the frontend build is not actually
#                Node-only for this starter kit, so it can't be isolated in a
#                plain node: image the way a typical Vite app could be. Local
#                dev never uses this stage; it runs Vite's own dev server
#                instead (see docker-compose.override.yml's `vite` service).
#   production - the actual runtime image. Copies only the *outputs* of
#                `build` (vendor/, public/build, route/view caches) — Node
#                itself never ships in the final image.
#
# Deliberately NOT done at build time: `php artisan config:cache`. Config
# reads env values, and this project's hard requirement is that an `.env` edit
# only ever needs `docker compose up -d --force-recreate`, never a rebuild —
# so config caching happens in docker-entrypoint.sh, at container *start*.

FROM php:8.3-fpm-alpine AS base

# postgresql-dev pulls in the headers *and* the runtime libpq needed by
# pdo_pgsql/pgsql at both compile time and run time.
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql pgsql bcmath opcache pcntl

# The base image's default CLI memory_limit (128M) is too low for PHPStan's
# parallel workers (confirmed: `composer test` crashed with "PHPStan process
# crashed because it reached configured PHP memory limit: 128M" on a clean
# container). This only affects the CLI SAPI, not php-fpm request handling.
RUN echo 'memory_limit=512M' > /usr/local/etc/php/conf.d/memory-limit.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
FROM base AS dev

# Node lives in the dev image too (not just the `build` stage) because the
# `vite` service also runs `php artisan wayfinder:generate` under the hood at
# dev-server startup — same reason `build` needs both toolchains together,
# just for `npm run dev` instead of `npm run build`. The `vite` service reuses
# this image rather than a bare node: image for exactly that reason.
RUN apk add --no-cache nodejs npm

# Only the dependency manifests are copied here so `composer install` is its
# own cached layer, independent of source changes. --no-scripts/--no-autoloader
# because there's no application code yet for package:discover to run against
# — the bind mount supplies it at container start, and Laravel regenerates its
# package manifest on demand if bootstrap/cache/packages.php isn't there.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --no-progress

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
FROM base AS build

RUN apk add --no-cache nodejs npm

# Dependency manifests first, each toolchain gets its own cached layer,
# independent of source changes.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --no-scripts --no-autoloader --no-progress

COPY package.json package-lock.json ./
RUN npm ci

# Now the full app is present — this is what `wayfinder:generate` needs to
# reflect real routes/controllers while Vite builds.
COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && npm run build \
    && php artisan route:cache \
    && php artisan view:cache

# ---------------------------------------------------------------------------
FROM base AS production

COPY . .
COPY --from=build /var/www/html/vendor ./vendor
COPY --from=build /var/www/html/public/build ./public/build
COPY --from=build /var/www/html/bootstrap/cache ./bootstrap/cache

RUN chown -R www-data:www-data storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
