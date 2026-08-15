FROM php:8.3-cli-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" dom exif gd opcache pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html

FROM php-base AS build

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY composer.json composer.lock package.json package-lock.json ./

RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist \
    && npm ci

COPY . .

RUN composer dump-autoload --no-dev --no-interaction --optimize \
    && npm run build \
    && rm -rf node_modules /root/.cache /root/.npm

FROM php:8.3-cli-bookworm AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        libfreetype6 \
        libjpeg62-turbo \
        libpng16-16 \
        libwebp7 \
        libxml2 \
        libzip4 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=php-base /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=php-base /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info

WORKDIR /var/www/html

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY --chown=root:root docker/entrypoint.sh /usr/local/bin/lineweb-social

RUN chmod 0755 /usr/local/bin/lineweb-social \
    && mkdir -p storage/app/private/media storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8000

ENTRYPOINT ["lineweb-social"]
CMD ["serve"]
