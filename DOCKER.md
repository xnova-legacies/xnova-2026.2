# Lancer XNova avec Docker

## Prerequis

- Docker Desktop avec Compose v2

## Demarrage

Depuis la racine du projet, `.env` sélectionne l'environnement actif avec `APP_ENV=local` :
c'est le fichier de Docker Compose, il reste donc à la racine. Tous les autres
fichiers de configuration vivent dans `configs/` (non servi en HTTP).
Utiliser le script de déploiement :

```text
.\deploy.ps1
```

Pour changer d'environnement, modifier `APP_ENV` dans `.env` (`local`, `dev`, `prod` ou `review`), puis relancer le script.

| Environnement | Base de données | Au déploiement |
|---|---|---|
| `local` | `tmpfs` (rien sur le disque) | base neuve et `configs/config.php` vidé à chaque démarrage du conteneur |
| `review` | `tmpfs`, comme `local` | idem `local` : c'est un poste de travail jetable, pas une intégration |
| `dev` | volume `xnova_db_dev` | **base neuve** : le volume est supprimé, puis `configs/config.php` est vidé |
| `prod` | volume `xnova_db_prod` | rien n'est touché, les données sont conservées |

`dev` est le seul environnement dont `deploy.ps1` supprime le volume avant de relancer
(`docker compose down --volumes`) : la base repart vierge et `configs/config.php` est vidé,
donc l'application redirige vers l'installateur. C'est ce qui garantit qu'un schéma remanié
est bien repris tel quel, sans migration de confort. `configs/config.php` est suivi par git
avec les valeurs de développement : après l'installation il redevient identique, l'arbre de
travail est donc propre à nouveau.

`local` et `review` n'ont pas besoin de ce geste : leur base vit dans un `tmpfs` — rien
n'est écrit sur le disque — et `docker-entrypoint.sh` remet `configs/config.php` à zéro
à chaque démarrage du conteneur. Les deux affichent aussi les erreurs PHP, comme `dev`.

Docker Compose charge ensuite le fichier correspondant : `configs/.env.local`,
`configs/.env.dev`, `configs/.env.review` ou `configs/.env.prod` (voir `configs/.env.example`).
Ce fichier n'est **pas versionné** : sur une machine neuve, le copier avant le premier
déploiement, sinon le script s'arrête en disant lequel manque. Il contient aussi les valeurs
par défaut de l'administrateur utilisées par l'installateur, et les réglages `CONFIG_…` que
l'installation écrit dans la table `config`.

Ouvrir ensuite <http://localhost:8080/>. L'application redirige vers l'installateur tant que `configs/config.php` est vide.

Dans le formulaire de connexion MySQL, utiliser :

- Hote : `db`
- Utilisateur : `xnova`
- Mot de passe : `xnova_password`
- Base de donnees : `xnova`
- Prefixe des tables : laisser vide (ou choisir un prefixe)

Les données MySQL sont donc conservées uniquement en intégration et en production.

## Arreter les conteneurs

```text
docker compose down
```

Pour supprimer aussi la base de donnees :

```text
docker compose down -v
```
