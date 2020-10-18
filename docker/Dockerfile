FROM php:7.4.11-cli-buster

# Install basic tools
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gosu \
        sudo \
        unzip \
        wget \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

# Install composer and symfony
WORKDIR /tmp/installers
COPY docker/bin/* ./
RUN bash ./install_composer \
 && mv composer.phar /usr/local/bin/composer

# Install PHP extensions and their dependencies
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
 && docker-php-ext-enable opcache \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

RUN useradd --create-home --shell /bin/bash developer \
 && echo "developer ALL = NOPASSWD : ALL" > /etc/sudoers.d/developer

COPY docker/scripts/entrypoint /entrypoint

WORKDIR /opt/app

EXPOSE 8000

ENTRYPOINT [ "/bin/bash", "/entrypoint"]

CMD [ "php", "-S", "0.0.0.0:8000", "-t", "web" ]
