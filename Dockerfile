FROM php:8.3-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite proxy proxy_http proxy_wstunnel \
    && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY apache-ws.conf /etc/apache2/conf-available/xnova-ws.conf
RUN a2enconf xnova-ws

COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/xnova-entrypoint

RUN chmod +x /usr/local/bin/xnova-entrypoint \
    && chown -R www-data:www-data /var/www/html \
    && chmod 664 /var/www/html/configs/config.php

ENTRYPOINT ["/usr/local/bin/xnova-entrypoint"]
CMD ["apache2-foreground"]

EXPOSE 80
