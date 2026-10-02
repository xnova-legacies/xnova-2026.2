# Workflows — trois environnements, un seul jeu de contrôles

Tout ce qui suit tourne sur l'offre **gratuite** de GitHub : les actions du dépôt public
sont illimitées, les images publiées dans GHCR sont gratuites quand elles sont publiques,
et il n'y a aucun service tiers payant. Les travaux sont volontairement courts (une
dizaine de minutes au total) pour rester dans les quotas d'un dépôt privé (2 000 minutes
par mois).

## Les trois environnements

| Workflow | Déclencheur | Image publiée | Environnement GitHub |
|---|---|---|---|
| `review.yml` | poussée sur `feat/**`, `fix/**`, `refactor/**`, `docs/**` (avec ou sans numéro de ticket) | `ghcr.io/<dépôt>:review-<branche>` | `review` |
| `integration.yml` | pull request **fusionnée** dans `develop` | `ghcr.io/<dépôt>:integration` (+ `integration-<version>`) | `integration` |
| `production.yml` | étiquette `v*` poussée | `ghcr.io/<dépôt>:<étiquette>` **et** `:latest` | `production` |

Les trois appellent `_quality.yml` : **même contrôle partout**, un seul endroit à
corriger. Une nouvelle règle ajoutée là vaut immédiatement pour les trois.

L'environnement de **revue** sert à essayer une fonctionnalité avant de la fusionner
(l'image porte le nom de la branche, donc elle n'écrase jamais `integration` ni une
version livrée) ; l'**intégration**, à vérifier ce qui est entré dans `develop` ; la
**production**, à livrer une étiquette précise.

## Ce qui est vérifié (`_quality.yml`)

1. **Composer** : `validate --strict`, installation des dépendances, et
   `check-platform-reqs` — c'est là que le **plancher PHP 8.3** et l'extension `pdo_mysql`
   déclarés dans `composer.json` sont confrontés à l'interpréteur.
2. **Style** : `composer cs` (PHP_CodeSniffer, **PSR-12** sur le code moderne —
   `phpcs.xml` dit ce qui est exclu et pourquoi).
3. **Schéma** : les migrations du **Core de l'application** **et** des Modules, puis le semis du registre des
   modules ; on rejoue ensuite `rollback --force` puis `migrate` pour prouver que le
   `down` fonctionne aussi.
4. **Une règle, une implémentation** : `composer doublons`
   (`tools/audit-doublons.php`, code de sortie 1 s'il trouve un doublon).
5. **Tests** : `composer test` (PHPUnit), sur une base MySQL **5.7** — la version du jeu.
6. **Fumée HTTP** : la pile Docker se monte, puis `db/demo.php --force` remet la base à
   neuf et garnit un univers de démonstration (quatre comptes, dont un compte garni, mot
   de passe `demo`). `tools/smoke.sh` vérifie ensuite que les pages répondent **sans erreur
   PHP** (un `Fatal error` sort en 200 quand `display_errors` est actif : le code seul ne
   suffit pas).
7. **Espaces** : `git diff --check` refuse les espaces en fin de ligne et les conflits
   oubliés ; `docker compose config -q` valide les fichiers compose.
8. **Les workflows eux-mêmes** : [actionlint](https://github.com/rhysd/actionlint) lit
   `.github/workflows/` (schéma GitHub + YAML), en image Docker, sans installation.
9. **PHP 8.4** : un travail **non bloquant** (`continue-on-error`) rejoue les tests sur
   la version suivante du langage — le jeu vise 8.3, mais autant le savoir tôt.

## À faire une fois dans les réglages du dépôt

- Créer les trois environnements (`Settings → Environments`) : `review`, `integration`,
  `production`. Le nom doit correspondre **exactement** à celui des workflows.
- Sur `production`, poser la règle **Required reviewers** : la publication attend alors
  une approbation manuelle.
- `Settings → Actions → General → Workflow permissions` : autoriser **Read and write**
  (les images passent par `GITHUB_TOKEN`, aucun secret à créer). Le droit d'écrire est
  d'ailleurs redemandé dans les workflows (`permissions: packages: write`).
- `Package visibility` (GHCR) : public si vous voulez des images gratuites et lisibles
  sans jeton.

## Les mêmes contrôles en local

```sh
composer cs          # style PSR-12
composer cs:fix      # corrections automatiques
composer doublons    # une règle = une implémentation
composer test        # tests
sh tools/smoke.sh http://localhost:8080   # fumée HTTP sur la pile en cours
docker compose exec -T app php db/demo.php --force   # base neuve et compte garni
```

Aucun de ces gestes n'a besoin du réseau ni d'un service externe.

## « Un SonarQube interne à GitHub, sans rien installer ? »

La réponse honnête : **pas d'équivalent complet pour PHP**. Le moteur d'analyse de GitHub
est **CodeQL** — il est gratuit sur un dépôt public et n'a rien à installer — mais il ne
connaît **pas PHP** (C/C++, C#, Go, Java/Kotlin, JavaScript/TypeScript, Python, Ruby,
Swift). SonarLint/SonarCloud visent bien PHP, mais ce sont des services **externes** à
GitHub (SonarCloud est gratuit sur un dépôt public, SonarQube Community s'auto-héberge) :
hors du périmètre demandé.

Ce qui est gratuit, interne à GitHub, et sans installation :

| Moyen | Ce qu'il apporte | Limite |
|---|---|---|
| **Ces workflows** (phpcs, tests, doublons, fumée) | style, régression, tenue des règles du projet | PHP uniquement, pas d'analyse de flux |
| **Dependabot** (`.github/dependabot.yml`) | veille composer / actions / images Docker, PR automatiques | pas d'analyse de code |
| **Secret scanning + push protection** | refuse un jeton poussé par erreur | **dépôt public** ; dépôt privé = GitHub Advanced Security (payant) |
| **CodeQL / code scanning** | analyse de flux JavaScript (`scripts/*.js`) | **pas PHP** ; dépôt privé = payant |
| **Onglet Security → Code scanning** | accepte un rapport **SARIF** de n'importe quel outil | il faut un outil qui produise du SARIF |
| **Annotations de PR** | une erreur s'affiche sur la ligne concernée : PHPStan la produit nativement (`--error-format=github`), `cs2pr` convertit un rapport `checkstyle` de phpcs | un outil de plus |

Les deux étapes suivantes, si l'envie vient : **PHPStan** (analyse de types et de bugs,
niveau 0 puis 1, avec un `baseline` pour figer l'existant — la vraie partie « Sonar » qui
manque) et **PHPMD** (code mort, complexité). Les deux s'ajoutent en `require-dev`,
s'exécutent hors ligne, et se branchent dans `_quality.yml` comme les autres.
