# syntax=docker/dockerfile:1

FROM php:8.5-cli-bookworm

# Ensure apt cache is preserved in mounted volumes
RUN rm -f /etc/apt/apt.conf.d/docker-clean \
 && echo 'Binary::apt::APT::Keep-Downloaded-Packages "true";' > /etc/apt/apt.conf.d/keep-cache

# Install PHP extensions and their native dependencies
RUN --mount=type=cache,target=/var/cache/apt --mount=type=cache,target=/var/lib/apt \
    apt-get update \
 && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gosu \
        sudo \
        unzip \
        wget \
        libzip-dev \
        libicu-dev \
        libpq-dev \
 && docker-php-ext-install -j$(nproc) zip intl pdo_pgsql \
 && docker-php-ext-enable opcache

COPY --from=composer:2.9.7 /usr/bin/composer /usr/local/bin/composer

RUN useradd --create-home --shell /bin/bash developer \
 && echo "developer ALL = NOPASSWD : ALL" > /etc/sudoers.d/developer

COPY docker/dev/scripts/entrypoint /entrypoint

WORKDIR /opt/app

EXPOSE 8000

ENTRYPOINT [ "/bin/bash", "/entrypoint"]

CMD [ "php", "-S", "0.0.0.0:8000", "-t", "web" ]
