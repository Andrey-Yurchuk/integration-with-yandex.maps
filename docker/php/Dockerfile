FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        git \
        icu-dev \
        libzip-dev \
        linux-headers \
        postgresql-dev \
        unzip \
        $PHPIZE_DEPS \
    && docker-php-ext-configure zip \
    && docker-php-ext-install \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/docker-entrypoint.sh /usr/local/bin/laravel-entrypoint.sh

RUN chmod +x /usr/local/bin/laravel-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["laravel-entrypoint.sh"]

CMD ["php-fpm"]
