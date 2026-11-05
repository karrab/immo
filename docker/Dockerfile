FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libpng-dev libfreetype6-dev unzip git \
    && docker-php-ext-configure gd --with-freetype \
    && docker-php-ext-install -j$(nproc) pdo_mysql opcache gd zip \
    && pecl install apcu && docker-php-ext-enable apcu \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-gestimmo.ini

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage
