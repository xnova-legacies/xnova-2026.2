FROM php:8.5-cli

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Workerman s'appuie sur pcntl (signaux) et sockets : ces extensions ne sont pas
# compilées dans l'image CLI de base.
RUN docker-php-ext-install pcntl sockets

WORKDIR /var/www/html

# Le code de l'application est monté par docker-compose (bind mount) : l'image
# se contente de fournir le runtime PHP du serveur WebSocket.
CMD ["php", "ws/server.php", "start"]
