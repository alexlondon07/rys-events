# syntax=docker/dockerfile:1

########################################
# 1) PHP dependencies and runtime tools
########################################
FROM php:8.3-fpm-bookworm AS php-base

ENV DEBIAN_FRONTEND=noninteractive \
    LARAVEL_PDF_CHROME_PATH=/usr/bin/chromium \
    PUPPETEER_SKIP_DOWNLOAD=true

# Extensiones de PHP, Nginx, Node y Chromium (para el PDF con Browsershot).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip zip curl ca-certificates gnupg \
        nginx supervisor \
        chromium \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libzip-dev \
        libonig-dev libicu-dev libxml2-dev \
        fonts-liberation fonts-noto-color-emoji \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && docker-php-ext-install xml \
    && docker-php-ext-install dom \
    && docker-php-ext-install simplexml \
    && docker-php-ext-install xmlreader \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && rm -f /etc/nginx/sites-enabled/default

WORKDIR /var/www/html

COPY . .

RUN mkdir -p \
        storage/app/public storage/app/private \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs bootstrap/cache \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

########################################
# 2) Assets need vendor/livewire/flux/dist/flux.css
########################################
FROM node:22-bookworm-slim AS assets

WORKDIR /app
ENV PUPPETEER_SKIP_DOWNLOAD=true

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
COPY --from=php-base /var/www/html/vendor ./vendor
RUN npm run build

########################################
# 3) Web, queue worker and scheduler share one image
########################################
FROM php-base AS runtime

COPY --from=assets /app/public/build ./public/build
COPY --from=assets /app/node_modules ./node_modules

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
