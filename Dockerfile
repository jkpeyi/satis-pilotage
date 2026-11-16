# syntax=docker/dockerfile:1

# One image for app, worker and scheduler; a "test" target adds dev dependencies
# for deploy.sh; a "web" target serves public/ with nginx.

# ---- PHP dependencies (production) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---- Front-end assets ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
# Tailwind scans Laravel's pagination views.
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources ./vendor/laravel/framework/src/Illuminate/Pagination/resources
RUN npm run build

# ---- Application (php-fpm) ----
FROM php:8.3-fpm-alpine AS app
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl zip gd bcmath opcache pcntl
COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN php artisan package:discover --ansi
USER www-data
CMD ["php-fpm"]

# ---- Tests (app + dev dependencies), run by deploy.sh before going live ----
FROM app AS test
USER root
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-interaction --prefer-dist && chown -R www-data:www-data vendor
USER www-data

# ---- Web server ----
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
