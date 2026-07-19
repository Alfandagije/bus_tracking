FROM php:8.2-apache

RUN apt-get update && apt-get install -y libzip-dev unzip && \
    docker-php-ext-install pdo pdo_mysql zip && \
    a2enmod rewrite && \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
