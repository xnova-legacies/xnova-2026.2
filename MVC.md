# Migration MVC

Le projet est migre en MVC. Les pages accessibles depuis le web sont des
controllers ; les URLs existantes sont conservees (le front controller route
`overview.php` vers `App\Controllers\OverviewController`, etc.).

## Structure

```text
app/
  bootstrap.php        # autoloader PSR-4 (App\ -> app/)
  Core/
    Kernel.php         # resout l'URL, retourne route controller ou fichier legacy
    Router.php         # table de routage : 'overview.php' => [Controller, action]
    Request.php        # wrapper $_GET/$_POST/$_SERVER
    Response.php       # HTML / JSON brut / redirection
    AbstractController.php # pont legacy ($user, $lang, renderPage, renderMessage...)
  Controllers/
    Front/             # pages publiques (login, banned, changelog, credit, contact...)
    Game/              # pages de jeu du socle (overview, fleet, profil, alliance...)
    Back/              # administration
  Services/            # logique metier (AuthService, ResourceService, ...)
  Repositories/        # TOUTES les requetes SQL (Connection -> doquery)
  Entities/            # entites hybrides (proprieties typed + fromRow/toArray)
  View/OpenGame/       # gabarits du jeu (template engine {marqueur})
  Database/
    Connection.php     # encapsule doquery(), requetes identiques au legacy
modules/               # paquets : une fonctionnalite = un dossier
  <nom>/package.json   # manifeste : page, adresses, permission, ordre
  <nom>/{core,controllers,entities,repositories,services,view,language}/
                       # meme decoupage que app/ ; namespace Modules\<Nom>\...
common.php             # bootstrap legacy (session, auth, flottes) - inclus au scope global
index.php              # front controller (constants + common.php + dispatch)
.htaccess              # statiques directs, *.php -> index.php, app|modules bloques
```

## Fusion de controllers

- FleetController : indexAction, backAction, shortcutAction, quickfleetAction,
  floten1-3Action, flotenajaxAction (ex Fleet*, Floten*).
- ProfilController : techtreeAction, techdetailsAction, optionsAction,
  messagesAction, imperiumAction.
- AllianceController : indexAction, infoAction (module `modules/alliance`).

## Modules

Une fonctionnalite (marchand, annonces, chat, notes, officier, records, alliance) est un module
sous `modules/` : son manifeste declare ses adresses avec leur controleur
(`"game/chat": "ChatController@indexAction"`), et `Router` interroge le registre
(`App\Core\Modules::controllerFor()`) avant ses propres tables. Le module apporte
aussi ses gabarits (`TemplateEngine::load()`) et ses libelles
(`Language::include()`), resolus par le Coeur d'application quand le jeu n'a pas le fichier.

## Routage dynamique

/game/{controller}/{action} appelle {action}Action du controller :
- /game/overview => Game\\OverviewController::indexAction
- /game/profil/imperium => Game\\ProfilController::imperiumAction
- /game/fleet/floten1 => Game\\FleetController::floten1Action
Les anciennes URLs .php restent servies (table de compatibilite).

- `.htaccess` : les fichiers statiques (css, js, images, `public/`) sont servis
  directement ; tous les `*.php` sont rewrites vers `index.php`.
- `index.php` inclut `app/bootstrap.php`, cree le Kernel et appelle
  `resolve()`. Trois cas :
  1. route connue : les constantes du controller (`legacyConstants()`) sont
     definies, `common.php` est inclus **au scope global** (les fonctions
     legacy utilisent `global`), puis l'action du controller est appelee ;
  2. fichier legacy existant (admin/, install/) :
     inclu au scope global apres restauration de `$_SERVER['SCRIPT_NAME']`
     et `$_SERVER['PHP_SELF']` pour compatibilite ;
  3. sinon : 404.
- `common.php`, `extension.inc` et le dossier `configs/` (fichiers d'environnement,
  `config.php`) sont bloques en HTTP
  (inclus internes uniquement).

## Regles respectees pendant la migration

- SQL deplace a l'identique dans les repositories (securisation par requetes
  preparees : passe ulterieure).
- Templates conserves (parsetemplate/gettemplate/display). `display()` et
  `message()` echo + die ; les controllers utilisent `renderDisplay()` et
  `renderMessage()` (meme rendu, retourne en string).
- Comportements legacy conserves a l'identique, y compris les cas limites
  (annonce.php?action=5, neusuw/multi `$mode` non defini, typos `$lan`, etc.).

## Pages restant en fallback legacy

- `admin/` : migration reportee.
- `install/` : non concerne.

## Bugs legacy corriges au passage

- `CreateOnePlanetRecord` : `global $game_config` manquant (inscription
  cassedepuis le passage en MySQL strict).
- `SendSimpleMessage` : `message_sender` recevait une chaine ('Admin') sur
  une colonne int (cast int ajoute).
- `fleet.php` : `{$user[id]}` (constante indefinie, fatal PHP 8) - corrige
  dans FleetController.
- `alliance.php` : lecture de `$_GET` avant `common.php` cassait la session
  en mode dev (warnings avant `session_start`) - corrige dans
  AllianceController.

## Couche metier (Phase 6)

```text
app/
  Entities/          # Planet, Fleet, Message, Alliance, User (hybrides : fromRow + toArray)
  Services/          # FleetService, MessageService, RegistrationService, AuthService,
                     #   ResourceService (le marchand vit dans son paquet)
```

## Requetes preparees

`Connection` porte la couche base en PDO (`pdo()`, `prepare()`, `preparedFetchAll()`,
`preparedFetchOne()`, `preparedExecute()`, `preparedInsertId()`). Les repositories
migrants utilisent des placeholders `?` (MessageRepository, FleetRepository,
SearchRepository, UserRepository pour login/emails, AllianceRepository LIKE,
PlanetRepository recherche par coordonnees, ConfigRepository).

Les SQL concatenes restants (updates de files de construction, module
galaxie, combat...) sont des candidats pour une passe suivante.

## Bugs legacy corriges (phase 6)

- `GetFleetMaxSpeed("", $Ship, ...)` : en PHP 8, une string n'est pas
  implicitement convertie en array => `$speedalls` jamais remplie =>
  `speedallsmin` vide dans le formulaire de flotte => refus d'envoi
  ("tricherie sur la vitesse") puis DivisionByZero dans
  `GetFleetConsumption`. Corrige (cast array + garde-fous).
## Phase 7 : URLs /game/... dans tout le jeu

- `Game/InfosController` cree (ex infos.php, 331 lignes) : routes
  `/game/infos` + `infos.php` (compat).
- Mapping central `Router::LEGACY_TO_NEW` : toutes les URLs legacy .php ont
  un equivalent `/game/...`.
- Templates, controllers, includes legacy et scripts JS utilises par le jeu
  pointent désormais vers les routes /game/... (~260 remplacements).
- `leftmenu.php` (menu gauche legacy) converti (annonce, marchand).
- Le formulaire de notes pointe vers /game/profil/notes (PHP_SELF).
- Redirection 301 dans le Kernel pour toute URL `xxx.php` non servie par la
  table statique ayant un equivalent dans le mapping (filet de securite).
- Les seules URLs .php restantes : templates admin/ et install/ (hors
  perimetre), includes internes (common, config) et script asset scripts/createbanner.php.
- BUG corrige au passage : le remplacement automatique avait casse
  l'include interne `require ROOT_PATH . 'leftmenu.php'` (converti en URL
  /game/leftmenu) => fatal sur overview. Restauré ; le wrapper racine a depuis
  été supprimé, son tableau `ShowLeftMenu()` vit dans
  `app/Core/Legacy/LeftMenuFunctions.php` et les deux `renderDisplay()` la chargent.
## Phase 8 : Classes Core + Services/Repositories avec SQL préparé

```text
app/Core/            # socle technique
  GameData.php       # charge resource/pricelist/reslist/CombatCaps/ProdGrid/messfields
                     #   (ex includes/vars.php) et les expose en globals legacy
  GameConfig.php     # game_config depuis la table config (requête préparée)
  GameConstants.php  # miroir typé des constantes de jeu
  Language.php       # includeLang + accès $lang
  TemplateEngine.php # parsetemplate/getTemplate/renderDisplay/renderMessage
  Format.php         # pretty_number/colorNumber/pretty_time
  BbCode.php         # rendu BBCode des messages (corrige sQuote manquant)
  FleetMath.php      # distances/durées/vitesses/consommations de flotte
app/Services/
  Authenticator.php  # CheckTheUser + CheckCookies (SQL préparé : corrige la
                     #   faille d'injection via cookie nova-cookie)
  ProductionService.php # PlanetResourceUpdate + HandleElementBuildingQueue
  BuildingService.php   # files, GetBuildingTime/Price, IsElementBuyable
  FleetMissionService.php # FlyingFleetHandler (SELECT préparé)
app/Repositories/
  AuthRepository.php, BuildingQueueRepository.php, MissionRepository.php,
  GalaxyRepository.php (étendu)
```

`common.php` charge le Coeur d'application MVC (app/bootstrap.php) puis GameData/GameConfig/
Language : les variables legacy ($resource, $game_config, $lang...) restent
disponibles dans $GLOBALS pour le code non migré.

### Wrappers legacy créés (fonction 1-ligne → service)
ChekUser, CheckCookies (→ Authenticator) ; PlanetResourceUpdate (→
ProductionService) ; CheckPlanetBuildingQueue, CheckPlanetUsedFields,
BuildingSavePlanetRecord, HandleElementBuildingQueue
(→ BuildingService/BuildingQueueRepository) ; ShowGalaxyRows,
GalaxyCheckFunctions, GalaxyRowAlly, GalaxyRowUser, GalaxyRowPlanet
(→ GalaxyRepository/GameConfig) ; FlyingFleetHandler (→ FleetMissionService,
SELECT préparé, LOCK conservé sur la connexion legacy) ; SendSimpleMessage
(→ MessageService).

### Bugs corrigés au passage
- CheckCookies : SQL injectable via le cookie nova-cookie → vérification en
  PHP avec hash_equals (AuthRepository::validateRememberMeCookie).
- AddBuildingToQueue : `$QueueArray = ""` (string) au lieu d'un array →
  implode() fatal en PHP 8. La construction de bâtiment refonctionne.
- GetFleetMaxSpeed (Phase 6) : même famille de bug string/array.
## Phase 9 : suppression de includes/functions/

- `includes/functions/` supprimé : toutes les fonctions sont consolidées dans
  `includes/legacy_functions.php` (généré depuis le dossier, 99 fonctions).
- `todofleetcontrol.php` charge `legacy_functions.php` au lieu du dossier.
- Les 9 missions de flotte (Spy, Transport, Stay, StayAlly, Recycling,
  Colonisation, Expedition) sont migrées vers `FleetMissionService` avec
  requêtes préparées via `MissionRepository` (75 SQL).
- Wrappers 1-ligne : MissionCaseSpy → FleetMissionService::spy, etc.
- `Connection::pdo()` réutilise la connexion legacy à chaque appel : il n'y a plus
  qu'**une** connexion pour tout le jeu, donc le `LOCK TABLES` du moteur couvre
  aussi les requêtes préparées des dépôts.
- `BuildingQueueRepository::savePlanetProduction` ne pose plus de LOCK
  (le verrou global du handler couvre déjà la transaction).
- Restent 84 requêtes dans legacy_functions.php : les moteurs de combat
  (MissionCaseAttack/Destruction, rapports complexes) et helpers divers —
  délégation via Connection::query (pont doquery).
## Phase 10 : fonctions legacy réparties par domaine

`includes/legacy_functions.php` supprimé. Les 99 fonctions legacy sont
réparties dans `app/Core/Legacy/` :

| Fichier | Domaine | Fonctions |
|---|---|---|
| `UserFunctions.php` | auth/utilisateur | 8 |
| `BuildFunctions.php` | bâtiments/production | 38 |
| `GalaxyFunctions.php` | galaxie | 28 |
| `FleetFunctions.php` | flottes (missions wrappées) | 15 |
| `CombatFunctions.php` | moteurs de combat (Attack/Destruction) | 2 |
| `MiscFunctions.php` | divers (JS, helpers) | 8 |

`todofleetcontrol.php` charge ces 6 fichiers. Les fonctions wrappées
(CheckTheUser, PlanetResourceUpdate, MissionCaseSpy...) délèguent aux
services MVC ; les requêtes SQL simples passent par les repositories.
## Phase 11 : routes /front/ + /back/

Le routing dynamique supporte les trois modules :
- `/front/login` → `App\Controllers\Front\LoginController::indexAction`
- `/game/overview` → `App\Controllers\Game\OverviewController::indexAction`
- `/back/...` → `App\Controllers\Back\...` (réservé)

Les URLs legacy (`login.php`) sont redirigées 301 vers `/front/...` via
`Router::LEGACY_TO_NEW`. Le login redirige vers `/game/overview` (zone jeu).
## Scripts de test

`test_game.ps1` : crawl automatique de 40 pages avec session (40 attendus OK).
Usage : `.\test_game.ps1` (default) ou `.\test_game.ps1 -BaseUrl http://host:port -User xxx -Pass yyy`
## Phase 12 : wrappers de calcul délégués (admin exclu)

`app/Core/Legacy/PageFunctions.php` et `FormatFunctions.php` ne contiennent plus
de règle de jeu : les 13 fonctions de calcul de flotte et de mise en forme sont
devenues des tableaux d'une ligne vers `App\Core\FleetMath` et
`App\Core\Format`.

| Wrapper legacy | Implémentation unique |
|---|---|
| `GetTargetDistance`, `GetMissionDuration`, `GetGameSpeedFactor`, `GetFleetMaxSpeed`, `GetShipConsumption`, `GetFleetConsumption` | `App\Core\FleetMath` |
| `pretty_time`, `pretty_time_hour`, `pretty_number`, `colorNumber`, `colorRed`, `colorGreen` | `App\Core\Format` |
| `ShowBuildTime` | `App\Core\Format::prettyTime` (balisage `<span>` legacy conservé) |

Conséquence : tous les appelants — y compris ceux de `admin/`, qui n'a pas été
modifié — exécutent le code corrigé de la phase 6 (`GetFleetMaxSpeed("")` en
PHP 8, troncature avant `%` de `pretty_time`). Les contrôleurs et services
modernes appellent directement `FleetMath` / `Format`.

Garde-fou : `tests/Unit/Legacy/LegacyFunctionSetTest::testLegacyWrappersDelegateToTheModernClasses`
compare la sortie des tableaux pures à celle des classes modernes.
## Phase 13 : suppression des doublons d'implémentation

Audit statique (tokenisation de tous les fichiers PHP) puis fusion : chaque règle
n'a plus qu'une seule implémentation.

| Doublon supprimé | Implémentation unique |
|---|---|
| globales `bbcode`, `image`, `sCode`, `sList`, `imagefix`, `urlfix` | `App\Core\BbCode` |
| `MissionRepository::insertMessage` / `incrementUnread` (sans appelant) | supprimées |
| `MissileRepository::insertMessage` | `MessageRepository::insertMessage` |
| `MissileRepository::incrementUnread` | `UserRepository::incrementNewMessage` |
| `MissileRepository::decrementDefence` + `PlanetRepository::decrementShips` | `PlanetRepository::decrementField` |
| `MissileRepository::zeroDefence` | `PlanetRepository::setField` |
| `BuildingQueueRepository::savePlanetRecord` | `saveBuildingQueueState` |
| `BuildingQueueRepository::saveUserRecordXp` | `saveUserFields` (colonnes données par l'appelant) |
| `StatsRepository::update{Ally,User}Rank{Only}` (4 méthodes) | `updateRank` / `updateRankOnly` (`stat_type` en paramètre) |
| SQL dupliqué de `levelUpMinier` / `levelUpRaid` | `levelUp()` privé + liste blanche de colonne |

`ProfilController` passe désormais par `MessageComposer::body()` : la chaîne
`bbcode(image(...))` y était recopiée. `MissileService` ne s'adresse plus à
`MissileRepository` pour les messages (les MIP n'incrémentaient que `new_message`,
comportement conservé dans `incrementNewMessage`).

Vérifications : `php -l`, 316 tests, `test_game.ps1` 40/40, journaux PHP vierges,
0 CRLF. Audit après fusion : 0 corps identique. Garde-fous :
`LegacyFunctionSetTest` (globales disparues) et
`tests/Unit/Repositories/RepositoryConsolidationTest` (une méthode par règle).
## Phase 14 : la file d'attente n'est plus rendue deux fois

La file était écrite deux fois — par le serveur (repli mis en forme par
`cnt.js`) puis par `scripts/xnova-queue.js` à partir de `/game/api/state`. À
chaque clic, `softReload()` réinjectait le HTML serveur : la liste de repli
réapparaissait une image, plus courte, et la page sautait.

Le serveur rend désormais les lignes (`App\Core\QueueRenderer`, balisage
identique à celui du client : `data-position`, `data-movable`, `data-end-time`)
et le client ne fait plus que décompter et déplacer :

| Avant | Après |
|---|---|
| `ShowBuildingQueue()` + `InsertBuildListScript('buildings')` | `QueueService::buildingItems()` + `QueueRenderer` |
| ticker de `buildings_script.tpl` (hangar) | `QueueService::hangarItems()` + `QueueRenderer` |
| ticker de `buildings_research_script.tpl` | `QueueService::researchItems()` + `QueueRenderer` |
| `itemHtml()` / `renderContainer()` côté client | supprimés (le client se limite à `tick()`) |

`PlanetStateService::contentRevision()` inclut la composition des trois files
(élément et quantité, pas les horodatages) : les pages hangar et recherche
portent donc elles aussi `data-xnova-autorefresh="revision"` et se rafraîchissent
sans F5. Le sélecteur `Atr` — dont la valeur n'était lue par aucun contrôleur —
et le gabarit mort `buildings_builds_queue_script.tpl` ont été retirés.
`InsertBuildListScript()` reste, pour le bloc « chantier en cours » de la vue
d'ensemble.

Vérifications : `php -l`, 321 tests, `test_game.ps1` 40/40, HTML brut de la page
bâtiments (sans JavaScript) contenant la file, `XNova.softReload()` sans
changement de géométrie.
## Phase 15 : suppression du facteur de temps global

`App\Core\Clock` (variable `GAME_TIME_MULTIPLIER`, clé `config.time_multiplier`)
est supprimé : il faisait double emploi avec les réglages natifs de la table
`config`. La vitesse ne passe plus que par ces trois valeurs :

| Réglage | Effet |
|---|---|
| `game_speed` | durées de construction, recherche, défense, vaisseaux (`GetBuildingTime`, `BuildingService::buildingTime`) |
| `fleet_speed` | durées de trajet des flottes (`FleetMath::gameSpeedFactor`) |
| `resource_multiplier` | production horaire (`ProductionService`, `ResourceService`) |

Retirés avec lui : `scaleDuration()` / `scaleElapsed()` (FleetMath,
BuildFunctions, BuildingService, ProductionService), la clé `time_factor` de
l'état (et le badge « Temps de jeu accéléré » de `app/View/OpenGame/topnav.tpl`),
le `putenv()` de `tests/bootstrap.php` et `tests/Unit/Core/ClockTest.php`. Le
libellé trompeur du menu de gauche (« Queues », qui affichait en réalité
`MAX_UNITS_PER_ROW`) devient « Unités/ordre ».

Vérifications : `php -l`, 314 tests, `test_game.ps1` 40/40, plus aucune référence
à `Clock` dans le dépôt, menu affichant « Jeu ×1000 / Flotte ×1000 /
Ressources ×1000 » avec les trois réglages portés à leur équivalent ×1000.

## Phase 16 : file d'attente de la recherche

La recherche du laboratoire suivait encore le modèle historique « une seule à la
fois » (`b_tech` / `b_tech_id` sur la planète, `b_tech_planet` sur l'utilisateur).
Elle utilise désormais la même file que les bâtiments et le hangar :

- `QueueService` gagne le domaine `research` : `researchEntries()` (lecture de
  `users.b_tech_queue`, avec repli sur le couple legacy `b_tech_id` / `b_tech`
  pour une recherche lancée avant la migration), `appendResearch()`,
  `removeResearch()` (positions ≥ 2, sans remboursement, comme pour les
  bâtiments), `reorderResearch()` et `advanceResearch()` (fin de la recherche en
  cours : la suivante démarre).
- `b_tech` / `b_tech_id` / `b_tech_planet` restent écrits par la file : ils sont
  le miroir de la première entrée, ce qui laisse `HandleTechnologieBuild()` et le
  code non migré fonctionner sans changement.
- `ResearchService::start()` ajoute à la file (validations et débit des ressources
  au lancement) et `cancel()` rembourse le niveau engagé puis démarre la suivante.
- `ResearchBuildingPage` ne porte plus d'écriture SQL : elle délègue aux services
  et affiche la file complète via `QueueRenderer` (« Interrompre » sur la première
  ligne, « Retirer » sur les suivantes). Le glisser-déposer passe par
  `POST /game/api/queues/reorder` avec `domain=research`.
- Migration `db/migrations/003_research_queue.php` : colonne `users.b_tech_queue`.

Vérifications : `php -l`, 319 tests, `test_game.ps1` 40/40, et un cycle complet en
base (recherches enchaînées aux niveaux 7/8/9, `cmd=remove&listid=2` qui retire la
bonne entrée, `cmd=cancel&tech=106` qui rembourse et démarre la suivante).

## Phase 17 : stockage réel et file de recherche multi-technologies

**Capacités de stockage.** Les colonnes `metal_max` / `crystal_max` /
`deuterium_max` de la table des planètes n'étaient jamais réécrites : elles
restaient à la valeur d'installation (`BASE_STORAGE_SIZE` = 1 000 000), alors que
la production calculait, elle, les capacités des silos. Conséquence visible :
après une action passant par l'API (clic sur « Construire »), l'état renvoyé
annonçait le plafond de la constante et l'affichage retombait dessus — d'où
l'impression que le jeu ignorait les silos construits. Le calcul vit désormais
dans une seule méthode, `ProductionService::storageCapacities()` (niveaux des
silos 22/23/24, majorés par l'officier Stockeur), utilisée par la production,
l'onglet « Ressources » de la vue générale (`/game/overview?tab=resources`) et
`PlanetStateService` ; `BuildingQueueRepository`
persiste ces valeurs à chaque écriture de production, donc la colonne redevient
fiable pour le code legacy (barre de navigation, code non migré).

**File de recherche.** Le laboratoire n'acceptait qu'une recherche à la fois :
les autres technologies affichaient « - » et il fallait attendre la fin pour
lancer la suivante. La file accepte maintenant plusieurs recherches, y compris
de technologies différentes (les ressources sont débitées à l'ajout, comme pour
les bâtiments ; les coûts sont vérifiés à chaque ajout). La page présente la
file une seule fois, en tête, et chaque ligne propose « Rechercher » (file
vide) ou « Dans la liste de construction » ; la technologie en cours porte un
badge « En travail ». Le niveau annoncé vient de
`QueueService::researchNextLevel()`, seule implémentation de cette règle.

Vérifications : `php -l`, 323 tests, `test_game.ps1` 40/40, état API renvoyant
`metal_max` = 7 593 750 pour un silo de niveau 5 (au lieu de 1 000 000), file
`106,8,…;115,3,…` après ajout d'une seconde technologie, page du laboratoire
affichant la file en tête et les boutons pour les autres technologies.

## Phase 18 : barre de debug et marché spéculatif

**Barre de debug.** `php-debugbar/php-debugbar` (dépendance de développement) est
branché sur `index.php` : `App\Core\Debug\DebugBar::boot()` démarre un tampon de
sortie qui glisse le rendu avant `</body>`, donc les contrôleurs **et** les pages
legacy en profitent. L'activation tient à `DEBUG_BAR=1` (fichier `configs/.env.<env>`) et
à la présence du module : sans lui, `enabled()` répond faux et n'appelle plus
rien. Les requêtes SQL sont mesurées aux deux points de passage réels du jeu
(`Connection::executePrepared()` et `doquery()`), via un collecteur maison
(`SqlCollector`) : les requêtes passent par deux chemins, la couche PDO et le shim
historique. Les réponses JSON ne
sont jamais touchées. Au passage, `app/bootstrap.php` charge l'autoloader Composer
(sans quoi `DebugBar\…` était introuvable côté web).

**Marché spéculatif.** Le marchand gagne un onglet (`?mode=marche`) adossé à
l'économie entière : chaque relevé agrège les ressources de toutes les planètes,
les flottes et les défenses, valorisées en équivalent métal (métal 1, cristal 2,
deutérium 4 — les taux du marchand), et en tire un indice par classe (100 au
premier relevé). Les mises ouvertes ajoutent une pression d'achat
(`MARKET_DEMAND_FACTOR`), donc la courbe bouge aussi par les joueurs :
`MarketService::buy()` convertit la mise en parts au prix courant, `sell()`
recrédite la valeur du moment, frais de courtage déduits. Les relevés se
déclenchent à la consultation (pas de tâche de fond), comme le marché n'a pas de
cron. Tables `market_ticks` / `market_positions` (migration `004_market`), routes
`GET /game/api/market`, `POST /game/api/market/{buy,sell}`, page qui fonctionne
sans JavaScript (POST classique) et courbe canvas tracée par
`scripts/xnova-market.js`. Réglages : `MARKET_ENABLED`, `MARKET_TICK_SECONDS`,
`MARKET_FEE_PERCENT`, `MARKET_HISTORY_TICKS`, `MARKET_DEMAND_FACTOR`.

Vérifications : `php -l` (+ `node --check` sur le script), 335 tests, mise de
100 000 métal créée en base, indice métal passé à 104,39 par la demande, revente
reversant 102 301,81 (gain +2 301,81 après 2 % de frais), refus
`not_enough_resources` sur une mise hors budget.

**Robots autonomes.** C'est le module `modules/bot/` : `BotService` (qui dérive la réponse
`App\Services\BotService`) entretient des comptes `users.bot = 1` (`enable_bot` de la table
`config`, `BOTS_COUNT`) et leur fait jouer un tour : production via `PlanetResourceUpdate()`,
puis un bâtiment (`BuildingQueueService::add()`) ou à défaut une recherche
(`ResearchService::start()`), donc avec les mêmes contrôles que les joueurs. Les tours se
déclenchent à l'affichage d'une page (`AbstractController::renderPage()` résout la réponse par
`ModuleService::instance()`, borné par `BOTS_TICK_SECONDS` et par trois actions par page, dans
un `try/catch` pour qu'un incident ne casse jamais le rendu) ;
`php modules/bot/cli/bots.php [--ensure|--status]` permet de les jouer à la main. Module
éteint, la réponse du Coeur d'application répond `false` à `present()` : aucun tour, aucune création, et les
marques « bot » disparaissent partout. La
création d'une planète réutilise `CreateOnePlanetRecord()` — ce qui a révélé un
bug latent : la colonne `planets.b_building_id` était `text NOT NULL` sans valeur
par défaut, ce que MySQL refuse en mode strict, si bien qu'aucune colonie ne
pouvait être créée (migration `006_planet_queue_defaults`, colonne rendue
nullable).

Vérifications : `php -l`, 339 tests, 3 comptes créés avec leur planète, un
bâtiment lancé par robot (synthétiseur, centrale solaire, usine de robots),
ressources débitées, tour déclenché par un simple affichage de page (`bot_tick`
mis à jour, page rendue en 335 ms).

## Phase 19 : sortir le balisage des contrôleurs

**La règle.** Un contrôleur prépare des données, il n'écrit pas de HTML. Un bloc
de balisage vit dans **un seul** fichier `app/View/OpenGame/*.tpl` ; s'il doit
apparaître plusieurs fois (une ligne de tableau, un bouton), c'est **le même
gabarit** qu'on remplit en boucle — jamais une copie du balisage dans le PHP, ni
un second gabarit identique.

**Les outils.** `AbstractController::partial($nom, $donnees)` (contrôleurs) et
`TemplateEngine::render($nom, $donnees)` (services, fonctions utilitaires)
chargent puis remplissent un gabarit. Ils remplacent les deux écritures qui
traînaient : `$this->parse($this->template('x'), $d)` et
`parsetemplate(gettemplate('x'), $d)`.

**Un cas concret (les amis).** `BuddyController` ne contient plus une seule
balise : `buddy_body.tpl` (la page), `buddy_row.tpl` (une ligne),
`buddy_action.tpl` (un bouton d'action, rempli une à trois fois selon le mode),
`buddy_request_body.tpl` (le formulaire) et `alliance_link.tpl` (le lien vers une
alliance, réutilisable ailleurs). Les variantes de colonnes et les blocs
facultatifs (actions de liste, pied « Retour », ligne « pas de requêtes ») sont
des **classes** passées au gabarit (`{buddy_actions_class}`), pas des blocs
dupliqués.

**Le rapport de combat.** Le rendu du rapport (ex blocs `<table border=1>` enchaînés)
vit lui aussi dans des gabarits : `combat_report.tpl` (le rendu, le verdict et le
résumé), `combat_report_round.tpl` (un tour), `combat_report_side.tpl` (un camp — le
même gabarit pour l'attaquant et le défenseur, là où le legacy dupliquait le bloc)
et `combat_report_unit.tpl` (une ligne de vaisseau). `CombatFunctions.php` ne fait
plus que remplir ces gabarits : le moteur de combat et ses calculs ne sont pas
touchés. La page (`rw_body.tpl`) charge le thème du jeu, si bien que le rapport suit
le mode clair ou sombre du joueur. Le rapport d'une attaque de **missiles** suit la
même recette : `App\Core\Combat\MissileStrike` calcule (règle d'origine reprise
telle quelle) et `MissileService` compose le message envoyé au défenseur — la salve
est une mission de flotte (11), il n'y a plus de fichier inclus par requête.

**Le garde-fou.** `tests/Unit/Controllers/ControllerHtmlTest` échoue si un
contrôleur non listé écrit du balisage, et si deux gabarits ont un contenu
identique à la mise en forme près (seul doublon historique toléré : l'en-tête jeu
et sa copie d'administration `simple_header.tpl`).

**Avancement.** Migrés : `BuddyController` (5 gabarits), `CreditController`
(`credit_body.tpl`), `ContactController` (`contact_body_rows.tpl`),
`BannedController` (`banned_row.tpl`), `RwController` (`rw_body.tpl`),
`SearchController` (`alliance_link.tpl`, `table_message_row.tpl`),
`GalaxyController` (`galaxy_table.tpl`) et `RegController` (`registry_form.tpl`).
Gabarits partagés créés au passage : `alliance_link.tpl` (lien vers une alliance,
par identifiant ou par tag, utilisé par les amis et la recherche) et
`table_message_row.tpl` (ligne « aucun résultat » d'un tableau).

Restent à migrer, listés dans le test : `AllianceController`,
`AnnonceController`, `FleetController`, `InfosController`, `OverviewController`,
`ProfilController`, `StatsController`, `AcsController`. Les classes de rendu
du noyau (`FleetBar`, `UnitCard`, `QueueRenderer`) restent en PHP :
ce sont déjà des rendus uniques, testés, injectés dans des pages dont le gabarit
n'existe pas.
