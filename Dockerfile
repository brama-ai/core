# Stage 1: Install Composer dependencies
FROM composer:2 AS vendor

WORKDIR /app

COPY src/composer.json src/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist --ignore-platform-reqs

COPY src/ .
RUN composer dump-autoload --optimize --no-dev

# Stage 2: Runtime (Alpine + PHP-FPM + Caddy)
FROM php:8.5-fpm-alpine

# Install runtime libs + build extensions + Caddy
RUN apk add --no-cache \
        caddy \
        libpq \
        icu-libs \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        icu-dev \
        libzip-dev \
    && docker-php-ext-install pdo_pgsql zip pcntl \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Caddy config: reverse proxy to PHP-FPM
RUN printf '{\n\tservers {\n\t\ttrusted_proxies static 0.0.0.0/0 ::/0\n\t}\n}\n\n:80 {\n\troot * /var/www/html/public\n\tphp_fastcgi 127.0.0.1:9000\n\tfile_server\n\tencode gzip\n}\n' > /etc/caddy/Caddyfile

WORKDIR /var/www/html

# Copy only built app from vendor stage
COPY --from=vendor /app /var/www/html/

RUN mkdir -p var/cache var/log \
    && chmod -R 777 var/

# Startup script: run both PHP-FPM and Caddy
RUN echo '#!/bin/sh' > /usr/local/bin/start.sh \
    && echo 'php-fpm -D' >> /usr/local/bin/start.sh \
    && echo 'exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile' >> /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
