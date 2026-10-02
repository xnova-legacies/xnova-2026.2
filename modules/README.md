# `modules/` — les modules du jeu

**Ce dossier est livré vide.** Le Core de l'application ne porte aucune fonctionnalité
optionnelle : le tchat, les notes, le marché et les robots, l'alliance, les annonces, les
records, l'officier (bonus RPG) et les extracteurs (deutérium des débris) ont été
**archivés hors du dépôt** et se reposent après coup, module par module.

Ce n'est pas une limite subie : le jeu **tourne avec `modules/` vide**, et c'est vérifié
page par page. Un module absent ne casse rien — ses pages, ses routes JSON, ses
surcharges de classes et ses migrations disparaissent avec lui, et le Core dit l'absence
là où il en avait besoin (`AdminController::moduleMissing()` ; l'interrupteur
`/back/modules` et la page des rôles se vident d'eux-mêmes).

Seul ce fichier est versionné : `.archive/` (l'archive d'un module désinstallé) est un
**état d'installation**, pas du code — ses fichiers vivent sur le disque et
`Modules::archived()` les découvre à l'exécution.

## Où sont les modules retirés

Deux archives, **dans le dossier parent du dépôt** (donc hors de git, hors de
l'historique) :

| Archive | Contenu |
|---|---|
| `../xnova-modules-2026.23.zip` | les neuf modules, migrations comprises (`modules/<nom>/**`) |
| `../xnova-tests-modules-2026.23.zip` | leurs tests (`Modules/`, `Entities/`, `Services/`) |

Reposer un module :

1. décompresser `modules/<nom>/` ici et recopier ses tests sous `tests/Unit/` ;
2. `docker compose exec -T app php /var/www/html/db/modules.php` — semis idempotent du
   registre (le module apparaît allumé dans `/back/modules`) ;
3. `docker compose exec -T app php /var/www/html/db/migrate.php migrate` — **ses**
   migrations rejoignent celles du Core, dans l'ordre (Core d'abord, un module peut donc
   compter sur les tables du jeu) ;
4. `docker compose exec -T app php /var/www/html/db/acl.php` — un module neuf apporte sa
   permission `module.<nom>` : elle doit exister pour être cochée dans un rôle.

## Écrire un module

Un module est un dossier `modules/<nom>/` découvert par son manifeste `package.json`
(`App\Core\Modules`, pur et testé ; l'état vit dans la table `modules` via
`App\Services\ModuleService`). Le nom du dossier est un **identifiant** (minuscules, sans
tiret) : c'est un segment de namespace.

Le squelette de référence est `tools/module-template/helloworld` (copier le dossier, il
contient son mode d'emploi).

### Manifeste

Mêmes clés, dans cet ordre, une section vide en `{}` ou absente, indentation de 4
espaces, retour à la ligne final :

| Clé | Rôle |
|---|---|
| `name` | identifiant = dossier = segment de namespace |
| `label`, `description` | **clés de langue** (jamais du texte : un module voyage avec ses traductions) |
| `version`, `author` | les siens |
| `native` | livré avec le jeu, donc pas proposé au retrait comme un module tiers |
| `order` | place dans le menu du panneau (**unique**) |
| `page` | l'entrée de menu, quand le module en a une |
| `routes` | adresses de pages → `Controleur@action` |
| `api` | adresses JSON, sous `game/api/` |
| `legacy` | adresse historique → adresse moderne |
| `dependencies` | `core` = version minimale du Core de l'application, `modules` = modules requis |
| `permissions` | `module.<nom>`, réservée au catalogue des rôles |
| `settings` | réglages propres, `{}` sinon |
| `tables` | le fichier de tables de données, quand le module ajoute une unité |
| `debris` | ressources ajoutées au champ de débris |
| `missions` | missions de flotte (`handler`, `refusal`, `free_target`, `debris`, `ships`) |

### Contenu

Même découpage que `app/` : `core/`, `controllers/`, `entities/`, `repositories/`,
`services/`, `view/`, `language/` (`Modules::DIRECTORIES` ; seul `core/` est propre aux
modules — une règle générale reste dans `app/Core` et le module la **réutilise**). Quatre
dossiers qui ne portent pas de classe du jeu sont acceptés en plus
(`Modules::DATA_DIRECTORIES`) : `db/` (migrations), `cli/` (points d'entrée), `assets/`
(fichiers servis au navigateur) et `tests/` (tests unitaires) — un dossier non déclaré fait
échouer `ModulesTest::testAPackageOnlyCarriesDeclaredDirectories`.

Une classe vit sous `Modules\<Nom>\…` (`Modules\Marchand\Core\Cote` →
`modules/marchand/core/Cote.php`), résolu par l'autoloader de `app/bootstrap.php` via
`Modules::classFile()`, qui refuse tout chemin hors de ces dossiers. Une classe du
**Core de l'application** s'importe toujours avec son `use` : sans lui, elle est cherchée
dans le module.

Gabarits et langue sont résolus dans le module par les mêmes appels que le reste du code
(`TemplateEngine::load()`, `Language::include()`).

### Assets (`assets/`)

C'est le **seul** dossier d'un module que le serveur web laisse lire, et il est
**découvert** : un `.js` ou un `.css` déposé dans `modules/<nom>/assets/` est chargé tout
seul par les pages du module (`Modules::assets()`, `Modules::assetTags()`), dans l'ordre
alphabétique, avec `?v=<date de modification>` — rien à déclarer au manifeste. `.htaccess`
n'ouvre qu'une liste **fermée** d'extensions statiques : un `.php` (ou un `.tpl`) déposé là
reste refusé.

Deux conséquences à connaître :

- l'asset vit dans l'**en-tête**, donc il n'est **pas** réexécuté quand une page est
  remplacée partiellement (`softReload`) : s'il lit le DOM, il doit écouter
  `xnova:reloaded` ;
- seules les pages **déclarées au manifeste** reçoivent les assets du module : une page du
  jeu que le module se contente de dériver garde les assets du jeu.

### Tests (`tests/`)

Un test unitaire du module vit dans `modules/<nom>/tests/`, sous le namespace
`Modules\<Nom>\Tests\` : il **voyage avec le module**, et la suite du Core le découvre
(`phpunit.xml` descend dans `modules/`). Le fichier s'appelle `<Sujet>Test.php` ; le
bootstrap et les conventions restent ceux du Core (`tests/bootstrap.php`).

### L'entrée du menu latéral du jeu

Un module **déclaré au manifeste** apparaît tout seul dans le menu du jeu : l'adresse vient
de `page`, le libellé de `label` (une clé `mod_<nom>`, dans sa langue). Le Core n'écrit ni
l'une ni l'autre : une entrée fermée — module absent, éteint, ou refusé au rôle — n'est
**pas écrite du tout**, elle n'est pas cachée.

### Surcharger une classe du Core

Un module **dérive** une classe du Core de l'application en déposant le même nom court
dans la même couche (`Modules::overrideFor()`, rien à déclarer) ; la classe du Core
**reste** et ne doit pas être `final`. Pour un **contrôleur**, le dossier tranche :
`controllers/Back/X.php` dérive une page du **panneau** (`App\Controllers\Back\X`), un
fichier à plat (`controllers/X.php`) une page du **jeu** (`Game`, `Api`, `Front`) — les
deux familles ne se mélangent jamais.

Une page du panneau garde donc sa **porte** dans le Core (adresse,
`requiredPermission()`, habillage) : c'est elle que le module dérive, et c'est elle qui
répond « module absent » quand il n'est pas là. Exemples en place :
`app/Controllers/Back/ChatController.php`, `NotesController.php` et `RobotsController.php`.

**Une colonne qu'un module pose dans une table du Core ne se nomme jamais dans le
Core** (une requête SQL ne se surcharge pas) : le dépôt du Core porte un point de
surcharge (ex. `SessionRepository::botColumn()`), le module le renvoie et le Core
**résout** le dépôt par `ModuleService::instance()`.

### Interrupteur, dépendances, désinstallation

- **Permission** `module.<nom>` (groupe « Modules » du catalogue des rôles) : un rôle qui
  la porte **ouvre** le module, un rôle qui ne la porte pas le **ferme** à ses comptes.
  Un module neuf naît allumé.
- **Dépendances** : `Modules::problems()` rend le verdict (`core`, `module:<nom>` absent,
  `off:<nom>` éteint) et `ModuleService::dependencyText()` le met en mots. Tant qu'une
  dépendance manque, le module ne s'ouvre pas, et on ne peut pas l'éteindre si un autre,
  allumé, en dépend.
- **Désinstaller = archiver** : `ModuleService::archive()` déplace le module dans
  `modules/.archive/<nom>/` et `restore()` le remet en place — **rien n'est jamais
  supprimé**. Un module archivé n'est plus découvert : pages, routes, surcharges et
  migrations disparaissent avec lui.

## Ce qui garde la réponse

Les tests du Core tiennent ces règles **avec ou sans module** : ils itèrent sur
`Modules::names()`, donc les gardes d'un module se réarment dès qu'on en repose un.

- `tests/Unit/Core/ModulesTest.php` — découverte des manifestes, dossiers autorisés,
  convention de surcharge, archive.
- `tests/Unit/Core/ModuleDependenciesTest.php` — dépendances déclarées et résolues, code
  d'un module qui importe le Core, **le Core qui ne charge jamais une classe de module dans
  un constructeur**, et **le menu du jeu qui ne nomme aucune page de module** (elles viennent
  du manifeste).
- `tests/Unit/Core/MethodCallsTest.php` — un appel `$this->propriété->méthode()` vise une
  méthode qui existe, dans le Core **et** dans les modules déposés.
- les tests **du module** : `modules/<nom>/tests/*Test.php` sont découverts par la suite du
  Core (`phpunit.xml`), donc un module déposé se teste avec le jeu, sans liste à tenir.
- `tests/Unit/Core/PackageScanTest.php` — analyse d'un module avant installation (sur un
  faux module en dossier temporaire).
- `tests/Unit/Services/ModuleServiceTest.php` — liste, filtre, recherche et tri du
  panneau (fonctions pures).
- `tests/Unit/Controllers/ControllerHtmlTest.php`, `Repositories/RepositoryCoverageTest.php`,
  `Repositories/RepositoryConsolidationTest.php` — les mêmes garde-fous appliqués aux
  modules quand il y en a.
