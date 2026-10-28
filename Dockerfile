FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql

COPY config/php/seguridad.ini /usr/local/etc/php/conf.d/seguridad.ini
COPY public/ /var/www/html/
