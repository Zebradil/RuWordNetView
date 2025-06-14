# syntax=docker/dockerfile:experimental

FROM php:7.4.33-cli-buster

# Ensure cache for apt packages is preserved in mounted volume
RUN rm -f /etc/apt/apt.conf.d/docker-clean \
 && echo 'Binary::apt::APT::Keep-Downloaded-Packages "true";' > /etc/apt/apt.conf.d/keep-cache

# Install basic tools
RUN --mount=type=cache,target=/var/cache/apt --mount=type=cache,target=/var/lib/apt \
    apt-get update \
 && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gosu \
        sudo \
        unzip \
        wget

# Install PHP extensions and their dependencies
RUN --mount=type=cache,target=/var/cache/apt --mount=type=cache,target=/var/lib/apt \
    apt-get update \
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

COPY --from=composer:2.6.3 /usr/bin/composer /usr/local/bin/composer

RUN useradd --create-home --shell /bin/bash developer \
 && echo "developer ALL = NOPASSWD : ALL" > /etc/sudoers.d/developer

COPY docker/dev/scripts/entrypoint /entrypoint

WORKDIR /opt/app

EXPOSE 8000

ENTRYPOINT [ "/bin/bash", "/entrypoint"]

CMD [ "php", "-S", "0.0.0.0:8000", "-t", "web" ]
