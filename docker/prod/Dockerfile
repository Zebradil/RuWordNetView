# syntax=docker/dockerfile:experimental
FROM php:8.3.4-fpm-bullseye

# Generate en_US.UTF-8 and ru_RU.UTF-8 locales
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        locales \
 && sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen \
 && sed -i -e 's/# ru_RU.UTF-8 UTF-8/ru_RU.UTF-8 UTF-8/' /etc/locale.gen \
 && locale-gen

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        libzip-dev \
 && docker-php-ext-install -j$(nproc) zip \
 && apt-get install -y --no-install-recommends \
        libicu-dev \
 && docker-php-ext-install -j$(nproc) intl \
 && apt-get install -y --no-install-recommends \
        libpq-dev \
 && docker-php-ext-install -j$(nproc) pdo_pgsql \
 && docker-php-ext-enable opcache

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .



