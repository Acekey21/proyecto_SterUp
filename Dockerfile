# Usamos una imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instalamos las extensiones necesarias para MySQL/MariaDB
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitamos el módulo de reescritura de Apache (útil para rutas limpias)
RUN a2enmod rewrite

# Copiamos todos los archivos de tu proyecto al servidor
COPY . /var/www/html/

# Configuramos Apache Document Root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/nova
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Exponemos el puerto 80
EXPOSE 80
