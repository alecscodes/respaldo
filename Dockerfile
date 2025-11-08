# PHP-FPM application
FROM php:8.4-fpm-alpine

ARG USER_ID=82
ARG GROUP_ID=82
ENV USER_ID=${USER_ID} GROUP_ID=${GROUP_ID}

RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    linux-headers \
    oniguruma-dev \
    libzip-dev \
    sqlite-dev \
    && apk add --no-cache \
    oniguruma \
    libzip \
    sqlite \
    unzip \
    curl \
    git \
    nodejs \
    npm \
    && docker-php-ext-install mbstring zip pdo pdo_sqlite sockets \
    && apk del .build-deps

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

COPY docker/entrypoint.sh docker/scheduler-entrypoint.sh docker/web-entrypoint.sh /
RUN chmod +x /entrypoint.sh /scheduler-entrypoint.sh /web-entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

