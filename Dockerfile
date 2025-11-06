# Stage 1: Build assets (PHP + Node.js for Wayfinder)
FROM php:8.4-cli-alpine AS build-assets

# Install Node.js and npm
RUN apk add --no-cache nodejs npm

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    oniguruma-dev \
    libzip-dev \
    sqlite-dev \
    git \
    && docker-php-ext-install \
    mbstring \
    zip \
    pdo \
    pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy all application files needed for build
COPY . .

# Install Composer dependencies (needed for Wayfinder)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-req=ext-sockets

# Copy package files (already copied with COPY . ., but explicit for clarity)
# Package files are needed for npm install

# Install Node.js dependencies and build assets
RUN npm ci && npm run build

# Stage 2: PHP-FPM application
FROM php:8.4-fpm-alpine AS app

ARG USER_ID=82
ARG GROUP_ID=82
ENV USER_ID=${USER_ID} GROUP_ID=${GROUP_ID}

RUN apk add --no-cache \
    oniguruma-dev \
    libzip-dev \
    unzip \
    sqlite \
    sqlite-dev \
    curl \
    git \
    nodejs \
    npm \
    && docker-php-ext-install mbstring zip pdo pdo_sqlite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .
COPY --from=build-assets /app/public/build ./public/build
COPY --from=build-assets /app/node_modules ./node_modules

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-req=ext-sockets \
    && chown -R ${USER_ID}:${GROUP_ID} storage bootstrap/cache database node_modules \
    && chmod -R 775 storage bootstrap/cache database

COPY docker/entrypoint.sh docker/scheduler-entrypoint.sh /
RUN chmod +x /entrypoint.sh /scheduler-entrypoint.sh

# Default entrypoint
ENTRYPOINT ["/entrypoint.sh"]

# Stage 3: Nginx web server
FROM nginx:alpine AS web

# Copy built public directory from assets build
COPY --from=build-assets /app/public /usr/share/nginx/html/public

# Copy nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf

