# --- Stage 1: Base commune ---
FROM dunglas/frankenphp:php8.4 AS frankenphp_base

WORKDIR /app

RUN apt-get update && apt-get install -y \
    nodejs \
    npm \
    && npm install --global yarn \
    && rm -rf /var/lib/apt/lists/*

# 2. Tes extensions PHP existantes
RUN install-php-extensions \
    pdo_mysql \
    gd \
    intl \
    zip \
    amqp \
    opcache \
    exif

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

FROM frankenphp_base AS frankenphp_dev

ENV SERVER_NAME=:80

RUN pecl install xdebug && docker-php-ext-enable xdebug

# Copy custom php.ini if exists
COPY docker/php/conf.d/php.ini /usr/local/etc/php/conf.d/docker-php-custom.ini

COPY . .
RUN composer install --prefer-dist --no-scripts --no-progress

RUN chmod -R 777 var/

FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

# Copy custom php.ini for prod if exists
COPY docker/php/conf.d/php.ini /usr/local/etc/php/conf.d/docker-php-custom.ini

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-scripts --no-progress

RUN rm -rf docker/
