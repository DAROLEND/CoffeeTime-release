FROM php:8.2-apache-bookworm

# System deps
RUN apt-get update && apt-get install -y \
    libgd-dev libpng-dev libjpeg-dev libwebp-dev \
    libzip-dev libonig-dev libcurl4-openssl-dev libicu-dev \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) \
        mysqli pdo pdo_mysql mbstring gd zip curl intl

# Allow .htaccess overrides, fix MPM conflict
RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
          /etc/apache2/mods-enabled/mpm_worker.load \
 && a2enmod mpm_prefork rewrite \
 && sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

COPY . /var/www/html/
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Never ship a local .env into the image
RUN rm -f /var/www/html/.env

# Writable dirs for uploads / logs
RUN mkdir -p /var/www/html/logs \
             /var/www/html/static/images/menu_items \
             /var/www/html/static/images/gallery \
             /var/www/html/static/images/items \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html

EXPOSE 80
