#!/bin/sh
# Ce que `deploy.ps1` fait sur un poste Windows, en shell : amener un poste jetable
# jusqu'à un jeu qui répond dans le navigateur.
#
# Deux moments, un seul script, rejouable sans dégât — il ne fait que ce qui manque :
#
#   creer     premier clone : fichier d'environnement, dépendances Composer, puis
#             construction et démarrage de la pile.
#   relancer  à chaque redémarrage du poste : la machine s'arrête, la pile aussi. Le
#             disque survit, donc rien n'est à refaire — seulement à remonter.
#
# Ce qui n'est **pas** ici, volontairement : l'installation du schéma. Le poste est un
# environnement `local`, donc jetable : `docker-entrypoint.sh` vide `configs/config.php`
# à chaque démarrage du conteneur, et c'est l'installateur, dans le navigateur, qui crée
# les tables et le compte d'administration. C'est le chemin de Windows, et il est
# éprouvé. Pour un univers déjà garni, voir la fin de ce fichier.
set -eu

MODE="${1:-relancer}"

# Le script est appelé depuis la racine du dépôt, mais on ne s'y fie pas.
cd "$(dirname "$0")/.."

# L'environnement de la pile : `local`, le poste jetable — base sur `tmpfs` (voir
# `docker-compose.local.yml`), rien n'est écrit sur le disque. Les deux fichiers de
# composition sont nommés ici une seule fois, et toutes les commandes reçoivent la
# même paire : aucun risque qu'elles divergent.
ENV_FILE=configs/.env.local
COMPOSE="docker compose -f docker-compose.yml -f docker-compose.local.yml"

# Le démon Docker vit dans ce poste (voir `devcontainer.json`) et démarre avec lui :
# à notre tour, il peut n'être pas encore prêt. On l'attend, plutôt que de s'arrêter
# sur un « cannot connect to the Docker daemon ».
essais=0
while ! docker info >/dev/null 2>&1; do
    essais=$((essais + 1))
    if [ "$essais" -ge 30 ]; then
        echo 'Le demon Docker ne repond pas apres 60 s.' >&2
        exit 1
    fi
    sleep 2
done

# Le fichier d'environnement n'est pas versionné : il porte les mots de passe. Un dépôt
# fraîchement cloné n'en a donc aucun, et Compose s'arrêterait sur un « env file not
# found ». Le modèle versionné fait foi, et **les deux** — `app` et `db` — lisent le
# même fichier : ils ne peuvent pas diverger.
if [ ! -f "$ENV_FILE" ]; then
    cp configs/.env.example "$ENV_FILE"
    echo "Fichier d'environnement cree : $ENV_FILE (copie de configs/.env.example)."
fi

# Le `.env` de la racine choisit l'environnement, comme sur Windows (`deploy.ps1` le
# lit). Non versionné lui aussi : sans lui, Compose retombe sur `local` — le bon défaut
# — mais les commandes tapées à la main le liraient alors autrement. On l'écrit, pour
# que le poste ressemble exactement à un poste Windows.
if [ ! -f .env ]; then
    printf 'APP_ENV=local\n' > .env
fi

if [ "$MODE" = creer ]; then
    # `--build` : c'est la première fois, l'image du jeu n'existe pas encore. Les
    # services à profil (`ws`, `stats`) ne démarrent pas : le jeu retombe sur son API
    # JSON, et un poste de test n'a pas besoin d'un recalcul de classement en boucle.
    echo 'Construction et demarrage de la pile...'
    $COMPOSE up -d --build
else
    # Rien à construire : l'image et les conteneurs sont sur le disque du poste. Si
    # l'image a disparu (poste reconstruit), Compose la reconstruira de lui-même.
    $COMPOSE up -d
fi

# `vendor/` n'est pas versionné, et le volume masque celui que l'image porte : sans
# cette installation, le jeu s'arrête sur un `vendor/autoload.php` introuvable.
#
# Composer tourne **dans le conteneur** — c'est lui qui a les extensions exigées
# (`pdo_mysql`) — et sous l'identité du poste : autrement `vendor/` appartiendrait à
# `root` et le poste n'y écrirait plus. `COMPOSER_HOME` est déplacé parce que ce compte
# n'a pas de dossier personnel dans l'image.
if [ ! -f vendor/autoload.php ]; then
    echo 'Dependances Composer...'
    $COMPOSE exec -T -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer \
        app composer install --no-interaction --no-progress --prefer-dist
fi

# MySQL accepte-t-il le **réseau** ? Le contrôle de santé de Compose interroge
# `localhost`, donc le socket, qui répond pendant l'initialisation : l'installateur
# ouvert trop tôt échoue sur un « connection refused ». Le test passe par le migrateur
# du jeu, donc par les identifiants du fichier d'environnement — rien à réécrire ici.
essais=0
until $COMPOSE exec -T app php db/migrate.php status >/dev/null 2>&1; do
    essais=$((essais + 1))
    if [ "$essais" -ge 30 ]; then
        echo 'MySQL injoignable apres 60 s :' >&2
        $COMPOSE logs --no-color db >&2
        exit 1
    fi
    sleep 2
done
echo 'MySQL repond sur le reseau.'

# Ce que l'installateur va demander, lu **dans le fichier d'environnement** plutôt que
# recopié ici : c'est le même fichier que lit la pile, il n'y a donc pas deux vérités
# à tenir à jour.
lire() {
    sed -n "s/^$1=//p" "$ENV_FILE" | head -n 1
}

echo
echo 'Le jeu : http://localhost:8080/'
echo
echo 'Formulaire de connexion MySQL de l installateur :'
echo "  Hote          : $(lire DB_HOST)"
echo "  Utilisateur   : $(lire DB_USER)"
echo "  Mot de passe  : $(lire DB_PASSWORD)"
echo "  Base          : $(lire DB_NAME)"
echo
echo 'configs/config.php est vide : l installateur prend donc la main, comme sur'
echo 'Windows. Il cree le schema et l installation d administration, avec les reglages'
echo 'INSTALL_ADMIN_* du fichier d environnement.'

# Pour aller plus loin, dans le terminal du poste :
#
#   Univers de démonstration, garni d'un coup (la base est remise à neuf) :
#     docker compose exec -T app php db/demo.php --force
#   → le compte `Admin` et trois voisins, mot de passe `demo`. Les réglages `CONFIG_*`
#     du fichier d'environnement s'appliquent : `CONFIG_GAME_SPEED` raccourcit tout.
#
#   Le canal temps réel (service `ws`, port 8081) :
#     docker compose --profile websocket up -d
#
#   La mise à jour du classement (service `stats`) : idem, profil `stats`.
#
#   Les contrôles, exactement comme le dépôt les passe :
#     docker compose exec -T app composer test
#     docker compose exec -T app composer cs
#     docker compose exec -T app composer doublons
#     docker compose exec -T app composer sql -- . --strict
#     sh tools/smoke.sh http://localhost:8080
#
#   Un module à éprouver ici : copier ses fichiers dans `modules/<nom>/` (c'est ce que
#   fait son propre workflow), puis `docker compose exec -T app php db/modules.php`.
