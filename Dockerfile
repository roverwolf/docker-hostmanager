FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative

FROM php:8.5-cli-alpine

RUN apk add --no-cache icu-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev \
    && docker-php-ext-install -j"$(nproc)" intl \
    && apk del .build-deps

COPY --from=vendor /app /usr/local/src/docker-hostmanager

ENV HOSTS_FILE=/hosts

ENTRYPOINT ["/usr/local/src/docker-hostmanager/bin/docker-hostmanager"]
