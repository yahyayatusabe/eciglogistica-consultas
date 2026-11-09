FROM php:8.3-apache

# Extensión para conectar con MySQL mediante PDO (curl ya viene incluida)
RUN docker-php-ext-install pdo_mysql

# Solo la carpeta public/ es accesible desde el navegador; src/ queda fuera
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY . /var/www/html/
