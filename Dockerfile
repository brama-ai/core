FROM php:8.5-apache

RUN apt-get update && apt-get install -y \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo_pgsql zip pcntl \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY src/ /var/www/html/

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p var/cache var/log \
    && chmod -R 777 var/

RUN a2enmod rewrite \
    && sed -ri \
        -e 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf \
        /etc/apache2/apache2.conf \
    && sed -ri \
        -e 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf
