#!/bin/sh
set -e

# `local` et `review` sont des postes jetables : leur base vit dans un `tmpfs` et la
# connexion est remise a zero a chaque demarrage, donc l'installateur reprend la main.
case "${APP_ENV:-local}" in
local | review)
    : > /var/www/html/configs/config.php
    chown www-data:www-data /var/www/html/configs/config.php
    # le mapping 9p de Docker Desktop Windows rend le chown cosmetique :
    # on supprime/recree le fichier EN tant que www-data pour que le
    # proprietaire reel soit correct (sinon fopen(..., "w") echoue).
    rm -f /var/www/html/configs/config.php
    touch /var/www/html/configs/config.php
    chown www-data:www-data /var/www/html/configs/config.php
    chmod 664 /var/www/html/configs/config.php
    ;;
esac

exec "$@"
