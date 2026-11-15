# TUVIO production image for Render (free plan: 512 MB RAM, 0.1 CPU).
# Build: PHP dependencies -> frontend assets -> slim PHP 8.4 + Apache runtime.

# --- PHP dependencies (no dev packages) ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# --- Frontend assets (Vite) ---
# Same Node major (and npm 11) that wrote package-lock.json; npm 10 resolves peers differently.
FROM node:24-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
# tsconfig maps "ziggy-js" to vendor/tightenco/ziggy, so the build needs the PHP vendor folder.
COPY --from=vendor /app/vendor ./vendor
RUN npx vite build

# --- Runtime ---
FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql gd intl opcache \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-tuvio.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
# The site config listens on ${PORT}; drop the image's default "Listen 80".
RUN : > /etc/apache2/ports.conf
COPY docker/mpm.conf /etc/apache2/mods-available/mpm_prefork.conf

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN rm -rf public/hot public/hot-ticketflow bootstrap/cache/*.php storage/logs/*.log \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/tuvio-entrypoint
RUN chmod +x /usr/local/bin/tuvio-entrypoint

# Render sends traffic to $PORT (10000 by default).
ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["tuvio-entrypoint"]
CMD ["apache2-foreground"]
