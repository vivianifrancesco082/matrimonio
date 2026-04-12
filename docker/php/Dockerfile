FROM php:8.3-apache

# Installiamo le estensioni per MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Abilitiamo il modulo rewrite di Apache (utile per framework o routing smart)
RUN a2enmod rewrite

# Impostiamo i permessi corretti per Apple Silicon
RUN chown -R www-data:www-data /var/www/html