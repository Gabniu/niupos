FROM composer:2.9.3 AS composer_source

FROM php:8.5.3-cli-bookworm

RUN apt-get update \
    && apt-get install --yes --no-install-recommends \
        git \
        libonig-dev \
        libsqlite3-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_sqlite xml zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer_source /usr/bin/composer /usr/local/bin/composer

WORKDIR /workspace

CMD ["php", "--version"]
