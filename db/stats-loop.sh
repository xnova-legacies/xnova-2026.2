#!/bin/sh
# Mise à jour régulière du classement — service `stats`.
#
# Le conteneur ne fait que cela : il lance `db/stats.php`, attend l'intervalle,
# recommence. La boucle est séquentielle, donc deux recalculs ne se chevauchent
# jamais, même si un passage dépasse l'intervalle (un cron, lui, empilerait les
# processus).
#
# STATS_INTERVAL : secondes entre deux passages (300 = cinq minutes par défaut).
set -u

interval="${STATS_INTERVAL:-300}"
case "$interval" in
    ''|*[!0-9]*) interval=300 ;;
esac
if [ "$interval" -lt 30 ]; then
    interval=30
fi

echo "Classement : recalcul toutes les ${interval} s."

while true; do
    moment=$(date '+%d/%m/%Y %H:%M:%S')
    if php /var/www/html/db/stats.php; then
        echo "[${moment}] classement recalcule."
    else
        # Un échec ne doit pas arrêter le planificateur : l'erreur part dans les
        # journaux du conteneur, et le passage suivant réessaie.
        echo "[${moment}] ECHEC du recalcul (classement inchange, nouvel essai a la prochaine echeance)." >&2
    fi
    sleep "$interval"
done
