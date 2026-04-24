# syntax=docker/dockerfile:1

FROM php:8.5-fpm-bookworm

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
        libicu-dev \
        libpq-dev \
 && docker-php-ext-install -j$(nproc) zip intl pdo_pgsql \
 && docker-php-ext-enable opcache

COPY --from=composer:2.9.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

RUN composer install --no-dev --no-interaction --optimize-autoloader \
 && mkdir -p var/log var/cache/twig \
 && chown -R www-data:www-data var/
