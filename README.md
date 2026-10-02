# XNova

<img src="images/xnova-wordmark.png" alt="XNova" width="340">

[![Qualité](https://github.com/mandalorien/xnova-docker/actions/workflows/integration.yml/badge.svg)](https://github.com/mandalorien/xnova-docker/actions/workflows/integration.yml)
[![Minimum PHP](https://img.shields.io/badge/php-%3E%3D%208.3-8892BF.svg)](https://php.net/)
[![MySQL](https://img.shields.io/badge/mysql-5.7-4479A1.svg)](https://www.mysql.com/)
[![Temps réel](https://img.shields.io/badge/temps%20r%C3%A9el-WebSocket-010101.svg)](#-temps-réel)
[![Licence](https://img.shields.io/badge/licence-GPL--3.0-blue.svg)](LICENCE)

XNova est un serveur de jeu de stratégie spatiale en PHP, sans framework : des
bâtiments, des recherches, des flottes, des combats, une galaxie partagée, une
alliance, un classement — et un panneau d'administration complet.

Le jeu tourne sous **PHP 8.3** avec **MySQL 5.7**, se pilote par une **API JSON**
et pousse ses mises à jour par **WebSocket**.

## 📋 Ce qu'il faut

| | |
|---|---|
| **PHP** | 8.3 ou plus, avec `pdo_mysql`, `mbstring` et `phar` |
| **MySQL** | 5.7 ou plus (InnoDB, `utf8mb3`) |
| **Extensions PHP** | `pdo`, `pdo_mysql`, `mbstring`, `phar` — l'installateur les vérifie lui-même |
| **Composer** | pour les dépendances (`composer install`) |
| **Docker** | facultatif : le dépôt fournit un environnement complet |

L'installateur refuse de commencer tant qu'il manque quelque chose : sa première
étape contrôle la version de PHP, les extensions, les dépendances installées et
les dossiers dans lesquels le jeu doit écrire.

## 🐳 Démarrage avec Docker

Docker Desktop avec Compose v2 suffit — aucune dépendance à installer sur la
machine, pas même PHP.

```powershell
.\deploy.ps1
```

Puis ouvrir <http://localhost:8080/> : le jeu redirige vers l'installateur tant
que `configs/config.php` est vide. Dans le formulaire de connexion MySQL :

| Champ | Valeur |
|---|---|
| Hôte | `db` |
| Utilisateur | `xnova` |
| Mot de passe | `xnova_password` |
| Base de données | `xnova` |

Le script lit `APP_ENV` dans le `.env` de la racine (`local`, `dev`, `prod` ou `review`) et
démarre les services correspondants. Les autres réglages vivent dans
`configs/.env.<environnement>` — le dossier `configs/` n'est **jamais** servi en
HTTP, il contient les mots de passe.

### Les services

| Service | Rôle | Port |
|---|---|---|
| `app` | Apache + PHP : le jeu, l'API, le panneau | 8080 |
| `db` | MySQL 5.7 | interne |
| `ws` | WebSocket (Workerman) — profil `websocket`, relayé par Apache sur `/ws/` | 8085 |
| `stats` | Recalcul du classement en boucle — profil `stats` | — |

### Les commandes

```powershell
docker compose up -d                  # démarrer
docker compose down                   # arrêter
docker compose down -v                # arrêter et effacer la base
docker compose logs -f app            # suivre les journaux
docker compose exec app php db/migrate.php status     # état des migrations
docker compose exec app composer test                 # la suite de tests
.\test_game.ps1                                        # fumée HTTP (42 adresses)
```

`local` et `review` montent MySQL sur `tmpfs` : rien n'est écrit sur le disque et
l'univers repart à zéro dès que le conteneur est recréé. `dev` repart d'une base neuve à
chaque déploiement, seul `prod` conserve son volume.

## 🧩 Sans Docker

Le jeu est un site PHP ordinaire : il n'a besoin ni de framework ni d'outillage
particulier.

1. Installer **PHP 8.3** (`pdo_mysql`, `mbstring`, `phar`) et **MySQL 5.7**.
2. `composer install` à la racine du dépôt.
3. Créer une base vide et un compte MySQL qui y accède.
4. Pointer la racine du site (`DocumentRoot`) sur **la racine du dépôt** : le
   `.htaccess` fourni protège `app/`, `configs/`, `db/`, `includes/`, `modules/`,
   `tests/` et `tools/`.
5. Ouvrir le site : l'installateur écrit `configs/config.php`, crée le schéma et
   le compte d'administration.

Pour le temps réel, un service à part (PHP en ligne de commande, extensions
`pcntl` et `sockets`) sert le canal ; il peut être omis, le jeu retombe alors sur
l'API JSON :

```bash
php ws/server.php
```

Le classement se recalcule avec `php db/stats.php`, à planifier (cron ou
planificateur de tâches) ou à laisser au service `stats` de Docker.

## 🎮 Fonctionnalités

**Le jeu** — bâtiments, recherches, chantier spatial, défense, flottes et leurs
missions (transport, stationnement, attaque, attaque groupée, espionnage,
colonisation, recyclage, expédition, mise en orbite, missiles), combats avec
rapports détaillés, galaxie partagée, lunes et porte de saut, phalange, alliance,
classement, messagerie, tchat, notes et liste d'amis.

**Le panneau d'administration** — rôles et permissions (ACL hiérarchisée), fiche
joueur complète (ressources, planètes, flottes, bannissement, suppression logique),
journal des actions, historique des connexions avec courbes, gestion des modules
et des réglages de l'univers.

**Technique** — API JSON sous `/game/api/…` pour toutes les écritures, formulaires
classiques conservés (le jeu fonctionne **sans JavaScript**), journal des
modifications intégré, installateur qui vérifie son serveur avant de commencer.

**Modules** — une fonctionnalité est un module déposable dans `modules/` : pages,
routes JSON, services, gabarits, langues et migrations voyagent avec lui. Éteint
ou archivé, il disparaît sans rien casser. Neuf modules existent (marché
spéculatif, robots autonomes, annonces, tchat, notes, officier, records, alliance,
extracteurs).

**Mise à jour** — le panneau lit les étiquettes du dépôt, sauvegarde l'arbre en
`tar` et la base en SQL avant de remplacer quoi que ce soit.

## ⚡ Temps réel

Le navigateur ne sonde pas le serveur : il ouvre un **canal WebSocket** et reçoit
les changements (`event: state`) dès qu'une écriture a été appliquée. Les
ressources s'interpolent entre deux états reçus, et le hub rediffuse
immédiatement à toutes les connexions d'une même session.

L'API HTTP reste la **seule source de vérité** — le service WebSocket relaie, il
ne décide rien. Le canal est optionnel : `WS_ENABLED=0` dans
`configs/.env.<environnement>` le désactive et tout continue de fonctionner en
AJAX, puis en formulaires classiques.
Le navigateur se connecte sur le même hôte que le jeu (`/ws/`) ; Apache relaie le
canal vers le service `ws`. Cela conserve le cookie de session et évite le port
Codespaces séparé, qui impose une authentification distincte.

## 🗂️ Architecture

```
app/            le coeur de l'application (MVC maison, PSR-4 : App\ -> app/)
  Controllers/    Front, Game, Api, Back
  Services/       la logique métier, une classe par domaine
  Repositories/   l'accès aux données (requêtes préparées)
  Core/           règles pures (constantes, drapeaux, routeur, gabarits, combat)
  View/OpenGame/  les gabarits .tpl
modules/        les fonctionnalités déposables
db/migrations/  le schéma, en un seul fichier pour une installation neuve
configs/        configuration et variables d'environnement (jamais servis)
public/         les skins et leurs images
scripts/        le JavaScript du client
ws/             le service WebSocket
tools/          outillage d'audit et générateurs (ligne de commande seulement)
tests/          PHPUnit 11
```

## ✅ Tests et qualité

```bash
composer test          # la suite complète (PHPUnit 11, sans base de données)
composer cs            # le style (PSR-12)
composer cs:fix        # le style, corrigé automatiquement
composer sql -- . --strict   # les requêtes : valeurs liées ou concaténées
composer doublons      # les règles implémentées deux fois
```

Les tests unitaires ne touchent **jamais** MySQL : ils portent sur les règles
pures, les entités et les chemins de validation. Le reste se vérifie en jeu.

## 📚 Documentation

- [`docs/README-complet.md`](docs/README-complet.md) — la documentation détaillée
  du projet : chaque chantier, ses décisions et ses pièges.
- [`DOCKER.md`](DOCKER.md) — l'environnement Docker en détail.
- [`MVC.md`](MVC.md) — les conventions d'architecture et le découpage du code.
- [`modules/README.md`](modules/README.md) — écrire un module.
- [`.github/copilot-instructions.md`](.github/copilot-instructions.md) — les
  règles de travail du dépôt.

## � Versions

Trois nombres, chacun disant la nature du changement : **le premier pour une migration**
(schéma ou données à reprendre), **le deuxième pour une nouvelle fonctionnalité**, **le
troisième pour une correction** — `1.0.1`, `1.1.0`, `2.0.1`. L'étiquette du dépôt
(`v1.0.1`) déclenche le déploiement, et le journal des modifications raconte chaque
version depuis le jeu.

## �📄 Licence

GPL-3.0-or-later — voir [`LICENCE`](LICENCE).
