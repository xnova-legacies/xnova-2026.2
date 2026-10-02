<?php
/**
 * Tis file is part of XNova:Legacies
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @see http://www.xnova-ng.org/
 *
 * Copyright (c) 2009-Present, XNova Support Team <http://www.xnova-ng.org>
 * All rights reserved.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *                                --> NOTICE <--
 *  This file is part of the core development branch, changing its contents will
 * make you unable to use the automatic updates manager. Please refer to the
 * documentation for further information about customizing XNova.
 *
 */

$lang['Version']     = 'Version';
$lang['Description'] = 'D&eacute;scription';
$lang['changelog']   = array(

'<span class="text-success">1.0.1</span>' => 'PHP 8.5',
'<span class="text-success">1.0.0</span>' => 'Les officiers deviennent un module, et le Coeur de l\'application ne les connaît plus
- MOD : L\'officier n\'est plus une notion du Coeur de l\'application : ses tables de jeu (601 à 615), ses coefficients (`OfficerBonus::EFFECTS`, une seule copie pour tout le module), ses pages, sa langue et ses migrations vivent dans `modules/officier/`. Le Coeur de l\'application garde ses **points de surcharge** — `durationBonus()`, `shipCount()`, `constructionRewards()`, `storageBonus()`, `productionBonus()`, `productionFactor()`, `BattleEngine::bonus()`, `UserRepository::rewardRaid()`, `TechTreeService::extraSections()` —, vides ou neutres sans module et remplis par lui.
- MOD : Plus aucun code du Coeur de l\'application ne nomme une colonne d\'officier : elles naissent dans la migration du module (`users.rpg_*`, `xpminier`, `xpraid`, `lvl_minier`, `lvl_raid`), la file de construction les écrit par `saveUserFields()` — qui reçoit les noms de l\'appelant —, et la fiche d\'information comme la vue des ressources lisent la règle du module.
- FIX : L\'officier amiral n\'avait **aucun effet** au combat : la fonction d\'attaque construisait le dépôt du compte à la main, donc le bonus de combat n\'arrivait jamais au moteur. Le dépôt est désormais résolu, et trois niveaux d\'amiral donnent bien 0,15.
- FIX : La page des bâtiments tombait dès qu\'un chantier était ajouté ou retiré : un relais legacy écrivait encore la colonne d\'expérience du compte. Il est supprimé — l\'expérience ne bouge qu\'à la **fin** d\'un chantier, et c\'est là qu\'elle s\'écrit.
- MOD : Le moteur de combat parle entièrement anglais : ses variables (`atakujacy_zlom_poczatek` devient `attackerValueStart`, `wrog_moc` devient `defenderPower`, …) comme les clés qu\'il rend (`atakujacy` devient `attacker`, `wrog` devient `defender`, `wygrana` devient `victory`, `dane_do_rw` devient `rounds`, `zlom` devient `debris`, `obrona` devient `armour`, `tarcza` devient `shield`, `atak` devient `attack`). Les rapports et le module des extracteurs lisent ces clés.
- MOD : Les derniers noms allemands et polonais du code s\'en vont : les neuf colonnes de menu du compte (`users.mnl_*`, dont `mnl_joueur` qui mélangeait les deux langues) deviennent `menu_*`, l\'alias `ilosc` de la requête des flottes devient `fleet_count`, la variable des colonies du contrôleur des flottes et celle du combat abandonnent leur nom d\'origine. Une installation neuve naît avec les noms définitifs : le drapeau « un camp anéanti au deuxième tour » du rapport (`game_rw.a_zestrzelona`) devient `struck`, et les variables polonaises du rapport (`$a_zestrzelona`, `$zniszczony`) partent avec lui.
- MOD : La section des officiers de l\'arbre des technologies vient du module, et l\'arbre n\'affiche plus une section vide sans lui. Une installation neuve sans le module est complète : rien n\'est créé pour une fonctionnalité absente.
Une maniere de numeroter les versions, et une seule regle pour les comparer
- MOD : Le projet adopte une numerotation a **trois nombres** : le premier change pour une **migration** (une base a reprendre), le deuxieme pour une **nouvelle fonctionnalite**, le troisieme pour une **correction**. Autrement dit `1.0.1` pour une retouche, `1.1.0` pour un lot visible, `2.0.1` pour un changement de schema. La gamme millesimee (`2026.37`) s\'arrete ici et reste dans l\'historique du journal.
- MOD : La comparaison des versions connait cette succession : un **millesime est toujours plus ancien qu\'un numero**. Sans cela, `2026.37` aurait paru plus recent que `2.0.1` — l\'onglet de mise a jour aurait propose une gamme close, et les neuf modules, qui declarent `core` en `>= 2026.6`, auraient tous ete juges incompatibles d\'un coup. La regle vit a un seul endroit (`Modules::coreSatisfies()`), donc les dependances des modules et la mise a jour en heritent ensemble.
- ADD : Le rang de gamme est pose devant chaque version avant la comparaison segment par segment (`2026.37` devient `[0, 2026, 37]`, `1.0.0` devient `[1, 0, 0]`) : le reste du comparateur, avec ses operateurs `>`, `>=`, `<`, `<=` et `=`, n\'a pas bouge.',
'<span class="text-success">2026.37</span>' => 'Une nouvelle identite, et un README qui va droit au but
- ADD : XNova a un nouveau logo : un badge carre — fond nuit, anneau orbital, planete, un X eclatant — avec sa favicon (16, 32 et 48 dans un vrai ICO) et un wordmark qui ecrit le nom lettre a lettre. Les pages d\'inscription et le generique l\'affichent, et le menu lateral du jeu en tete.
- MOD : Le logo est dessine par `tools/logo.php`, sans dependance : le conteneur n\'a pas GD, donc le PNG est ecrit a la main (IHDR/IDAT/IEND, gzcompress + crc32) et les formes sont des fonctions de distance signee. Rien n\'est tire au hasard, donc deux executions donnent le meme fichier — et le nom se recompose de traits et d\'un anneau, faute de police.
- MOD : Le README se lit en trois minutes : ce qu\'il faut, le demarrage avec Docker, la voie sans Docker, les commandes, les fonctionnalites, le temps reel WebSocket, l\'architecture et les verifications. La documentation detaillee — chaque chantier, ses decisions et ses pieges — vit desormais dans `docs/README-complet.md`.
- FIX : Une poussee sur `develop` ou sur une branche de travail ne publie plus d\'image : les paquets du depot ne gardent qu\'une etiquette de version. Les deux travaux concernes construisent pour verifier que la construction tient, puis s\'arretent, et perdent au passage leur droit d\'ecriture sur les paquets.',

'<span class="text-success">2026.36</span>' => 'Nettoyage : le code et la base ne portent plus de noms allemands
- MOD : Les colonnes du compte abandonnent leurs noms allemands : `urlaubs_modus` devient `vacation_mode`, `urlaubs_until` devient `vacation_until`, `spio_anz` devient `spy_count` et `db_deaktjava` devient `no_javascript`. Le réglage `urlaubs_modus_erz` devient `vacation_mode_enforced` (variable `CONFIG_VACATION_MODE_ENFORCED`).
- MOD : Les colonnes de la planète suivent : `mondbasis` devient `moon_base`, `sprungtor` devient `jump_gate`, et l\'image d\'une lune passe de `mond` à `moon`. Les attaques groupées prennent `participants`, `fleets`, `arrival` et `invited`.
- MOD : Les champs de formulaire parlent anglais : `passwort` devient `password` (installateur), `fmenge` devient `amounts` (hangar, méthode `ShipyardService::amounts()` comprise), `kolonieloeschen` devient `delete_colony` et `exit_modus` devient `exit_vacation`.
- MOD : Les fichiers et les adresses suivent : `gebaeude/` devient `buildings/` dans les deux skins, `scripts/flotten.js` devient `scripts/fleet.js`, `VerbandController` devient `AcsController` avec l\'adresse `/game/acs` — l\'ancienne redirige —, et le tir de missiles répond à `/game/missile-attack`.
- MOD : Le calcul des missiles et le moteur de combat perdent leurs identifiants allemands et polonais : les clés rendues sont désormais `remaining`, `destroyed`, `lost_metal`, `lost_crystal` et `lost_deuterium`.
- ADD : Une installation neuve naît donc entièrement en anglais : le fichier unique du schéma porte les noms définitifs, aucune migration de renommage n\'est nécessaire.
- FIX : Les trois libellés espagnols de la bulle d\'alliance de la galaxie sont en français.',

'<span class="text-success">2026.35</span>' => 'Mise à jour : la base est sauvegardée en SQL avec les fichiers
- ADD : Une mise à jour écrit **deux** sauvegardes avant de toucher au disque : l\'arbre en tar (`UpdateService::backup()`) et la base en SQL (`DumpService::dump()`). Un échec du vidage arrête tout — `apply()` rend `error = dump` —, car mieux vaut une mise à jour refusée qu\'une base sans copie.
- ADD : `App\Services\DumpService` vide la base **sans outil externe** : `mysqldump` vit dans le conteneur `db`, pas dans l\'image de l\'application — `php:8.3-apache` n\'installe que `unzip` et `pdo_mysql` — et la mise à jour part de l\'application. Le vidage passe donc par la connexion du jeu (`Connection::open()`), ce qui le fait fonctionner partout, Docker ou non, et n\'alourdit pas l\'image pour un seul geste.
- MOD : Le fichier se rejoue tel quel : `DROP TABLE IF EXISTS`, la définition `SHOW CREATE TABLE`, puis des `INSERT` groupés — 200 lignes ou 256 Ko par instruction, pour ne pas dépasser le paquet maximal de MySQL. Les tables sont triées : deux vidages se comparent.
- MOD : Les fonctions qui composent le fichier (`tuple()`, `insert()`, `header()`, `fileName()`) sont pures et testées sans base : l\'échappement leur est **injecté**. Le fichier s\'écrit `<racine>/backups/xnova-<version>-<horodatage>.sql`, l\'étiquette étant nettoyée — jamais un chemin.',

'<span class="text-success">2026.34</span>' => 'Installateur : la vérification du serveur passe avant la base de données
- ADD : La première étape de l\'installation vérifie ce que le jeu **utilise** avant de demander quoi que ce soit : version de PHP (8.3 minimum), extensions (`pdo`, `pdo_mysql`, `mbstring`, `phar`), dépendances installées (`vendor/autoload.php`), et l\'écriture dans `configs/`, `modules/` et `backups/`. Une installation Docker apporte tout cela, mais l\'installateur ne le suppose plus.
- ADD : `App\Core\Requirements` porte la règle — fonctions pures, clés de langue, aucun texte — et `RequirementsTest` l\'éprouve : chaque prérequis a sa clé et son détail, le plancher PHP est celui déclaré dans `composer.json`, et `satisfied()` devient faux dès qu\'un seul manque.
- MOD : Tant qu\'un prérequis manque, le bouton « Continuer » est **masqué** et la liste dit ce qui bloque : l\'étape suivante échouerait de toute façon, sur un `config.php` non inscriptible par exemple. Les étapes glissent d\'un cran — la base passe en 2, le compte en 3 et 4 — le bouton « suivant » suivant déjà `page + 1`, donc aucun gabarit n\'a bougé.
- MOD : Les libellés des contrôles vivent avec les autres dans `language/fr/install/install.mo`, et deux phrases disent l\'enjeu.',

'<span class="text-success">2026.33</span>' => 'Mise à jour : la sauvegarde complète en tar, avant de remplacer quoi que ce soit
- ADD : `UpdateService::backup()` archive **tout l\'arbre** en tar dans `backups/` avant une mise à jour : le code est ce que l\'archive du dépôt remplace, et sans cette copie on ne peut pas revenir. `configs/` et `modules/` y sont — ce sont eux qu\'on ne peut pas reconstruire.
- MOD : Trois chemins seulement sont écartés (`isNotBackedUp()`, fonction pure) : `backups/`, qui contiendrait l\'archive en train de s\'écrire, `.git/`, que l\'archive du dépôt ne touche jamais, et `vendor/`, absent de l\'archive et refait par `composer install`.
- MOD : Le tar s\'écrit avec `PharData`, **sans** l\'extension `zip` (absente de l\'image), et `phar.readonly` ne le gêne pas : il ne garde que les archives exécutables. `apply()` prend donc sa sauvegarde avant de télécharger quoi que ce soit.',

'<span class="text-success">2026.32</span>' => 'Mise à jour : l\'application d\'une version, et ses garde-fous
- ADD : `UpdateService::apply()` applique une version : sauvegarde de `configs/` **d\'abord**, téléchargement de l\'archive, extraction en **quarantaine**, puis recopie du code à la racine. `configs/`, `modules/`, `vendor/`, `backups/` et `.git/` ne sont **jamais** remplacés (`isProtected()`, fonction pure et testée), et chaque entrée passe aussi par la garde de chemin de `ModuleScan` : une archive détournée ne peut pas écrire dans la connexion à la base.
- ADD : `tools/update-check.php` liste les versions plus récentes et applique celle qu\'on lui donne (`--apply v2026.32`), puis rejoue les migrations. Il refuse une étiquette qui ne figure pas dans la liste proposée : on n\'applique que ce qui vient d\'être lu, et jamais une version plus ancienne. C\'est aussi le moyen de tester un jeton sans passer par une page.
- MOD : Le service ne rejoue pas les migrations lui-même : c\'est une étape distincte, visible dans le rapport, que l\'appelant enchaîne — l\'outil aujourd\'hui, la page de l\'installateur ensuite.',

'<span class="text-success">2026.31</span>' => 'Mise à jour : les versions disponibles se lisent dans les étiquettes du dépôt
- ADD : `App\Services\UpdateService` lit la liste des versions publiées dans les **étiquettes** du dépôt (`v2026.30`) et ne garde que celles qui sont plus récentes que la version courante, la plus récente en tête. La comparaison est celle du jeu (`Modules::coreSatisfies()`) : elle est numérique, `2026.10` est donc plus ancienne que `2026.28`.
- MOD : Le dépôt du projet est privé : la lecture de l\'API GitHub demande un jeton, posé avec le dépôt dans `configs/.env.<env>` (`UPDATE_TOKEN`, `UPDATE_REPOSITORY`), un fichier que git ignore. `fromEnvironment()` les lit et `hasToken()` dit si un jeton est en place — sans lui, la page de mise à jour ne pourra rien proposer.
- MOD : L\'appel réseau est isolé dans une seule méthode : tout le reste du service est pur, et `UpdateServiceTest` l\'éprouve sur le **texte** d\'une réponse (une liste, une erreur 404, du bruit) sans jamais sortir de la machine.
- MOD : La page de mise à jour de l\'installateur viendra dans le lot suivant : le service est la fondation, et les étiquettes sont la seule source qui donne à une version son commit, donc son archive.',

'<span class="text-success">2026.30</span>' => 'Installateur : l\'onglet de transfert UGamela disparait
- MOD : Le troisième onglet de l\'installateur proposait de transformer une base **UGamela** en base XNova — une époque où l\'on venait d\'ailleurs. Il part avec son code (`case \'goto\'`), ses trois gabarits et ses libellés : il ne reste que trois onglets, introduction, installation et quitter.
- FIX : Le quatrième onglet annonçait « Mise à Jour » mais ne menait à rien : le routeur de l\'installateur n\'avait pas de `case \'upg\'`, et cliquer dessus ramenait à l\'introduction. Il est retiré ; la vraie page de mise à jour viendra avec les étiquettes de version.
- MOD : Le dossier `install/` ne prononce plus le nom d\'UGamela, ni dans son code, ni dans ses gabarits, ni dans ses libellés.',

'<span class="text-success">2026.29</span>' => 'Les travaux qui touchent la base écrivent leur fichier d\'environnement
- MOD : Le bloc de connexion des workflows existait en double, une fois par travail. Les valeurs vivent maintenant une seule fois (`env` en tête du fichier), et chaque travail écrit `configs/.env.<APP_ENV>` en une ligne à partir de ces variables — le nom du fichier se déduit de `APP_ENV`, donc rien à répéter.
- FIX : Ces travaux écrivaient l\'hôte dans `configs/config.php`, un fichier **vide** dans le dépôt puisque c\'est l\'installateur qui l\'écrit : rien n\'était remplacé, la connexion retombait sur `localhost` — donc la socket Unix, et « SQLSTATE[HY000] [2002] No such file or directory ». La connexion vient du fichier d\'environnement, comme le fait déjà le travail de fumée.
- FIX : Le manifeste du projet porte du **texte** pour `label` et `description` : le Coeur d\'application n\'a qu\'une langue, là où un module donne des clés de langue parce qu\'il voyage avec ses traductions. `ProjectTest` vérifie les deux valeurs au lieu d\'exiger des clés.',

'<span class="text-success">2026.28</span>' => 'Le projet se décrit dans son manifeste, et la version n\'est plus un `define`
- ADD : `package.json` à la racine décrit le projet comme un module (`name`, `label`, `description`, `version`, `author`) : le Coeur d\'application a son manifeste, lu par `App\Core\Project`.
- MOD : La version ne vit plus dans une constante : `common.php` n\'en pose plus, et le menu latéral, l\'en-tête du panneau, le courriel de mot de passe perdu et la comparaison de la dépendance `core` d\'un module lisent tous `Project::version()`. Une seule valeur à faire avancer, et elle ne peut plus dériver : elle annonçait 2026.25 quand le journal était à 2026.27.
- ADD : `ProjectTest` tient les deux règles : les cinq clés existent (et `label`/`description` sont des clés de langue, définies dans `language/fr/system.mo`), et la version du manifeste est **égale** à la plus récente entrée de `$lang[\'changelog\']`.
- MOD : L\'intégration continue lit la version dans `package.json` pour étiqueter l\'image, au lieu de l\'extraire de `common.php`.
- MOD : Les textes des neuf modules déposés suivent le vocabulaire arrêté (146 remplacements, 51 fichiers).',

'<span class="text-success">2026.27</span>' => 'Vocabulaire : le texte dit « module », « Coeur d\'application » et « planète »
- MOD : Le vocabulaire arrêté est appliqué partout (52 remplacements, 21 fichiers) : le texte parle désormais de module, de Coeur d\'application, de champ de débris, de stationnement, d\'existant, de rendu, de tableau et de réponse.
- MOD : Deux classes du Coeur d\'application portaient encore l\'ancien mot : `App\\Core\\PackageScan` analyse l\'archive d\'un module, elle devient `ModuleScan` (fichier, test et 41 références comprises), et `PackageInstallService` devient `ModuleInstallService`. Le nom des méthodes reste en anglais : c\'est du code, pas du texte.
- FIX : Le message du tir de missiles restait écrit à la main dans le service (« Aucun planète existant à ces coordonnées ») : il parle maintenant de planète existante.
- MOD : L\'entrée d\'une version passée garde le nom qu\'elle portait : un compte rendu ne se réécrit pas, même quand la classe a changé de nom.',

'<span class="text-success">2026.26</span>' => 'Couche base : le jeu écrit par PDO
- MOD : `pdo_mysql` remplace `mysqli` dans l\'image et dans le manifeste, et `Connection` porte toute la couche base. Son API ne change pas : les dépôts, les services et le code legacy appellent les mêmes méthodes.
- FIX : Il n\'existe plus qu\'**une** connexion pour tout le jeu : `Connection::open()` est le seul endroit qui l\'ouvre, et le shim historique (`db/mysql.php`) l\'appelle. Deux créateurs de connexion, c\'était la cause du double encodage corrigé plus tôt dans ce chantier ; le jeu de caractères part désormais dans la source (DSN).
- FIX : Le **type** d\'un paramètre décide de la liaison, comme les `i`/`d`/`s` de mysqli. PDO citait la valeur d\'un `LIMIT ?` — il partait en `LIMIT \'2\'`, que MySQL refuse : un entier, un booléen et un nul sont maintenant liés pour ce qu\'ils sont. Le défaut a été vu par `tools/diagnostic-base.php` avant la mise en service, et `ConnectionBindingTest` le tient.
- MOD : `rowCount()` remplace `affected_rows` (même sémantique : les lignes changées), `lastInsertId()` remplace `insert_id`, l\'échappement passe par la source de PDO. Le verrou des flottes porte sur la connexion des requêtes préparées, puisque c\'est la même.
- ADD : `tools/diagnostic-base.php` établit ce que les tests unitaires ne peuvent pas dire (ils ne touchent jamais MySQL) : jeu de caractères annoncé, `LIMIT ?` lié, écriture préparée sous `LOCK TABLES`, chemin legacy `doquery()`.',
'<span class="text-success">2026.25</span>' => 'Requêtes préparées : les dépôts lient leurs valeurs, et l\'audit SQL dit enfin la vérité
- MOD : Les dépôts du Coeur d\'application passent leurs valeurs en **paramètres liés** (les marqueurs `?`) au lieu de les concaténer : les comptes et leurs réglages, les planètes, leurs coordonnées et leurs lunes, la porte de saut, les missiles, les ressources et les pourcentages de production. Un **identifiant** ne peut pas être un paramètre lié : sa forme est vérifiée (`BaseRepository::isColumnName()`, la règle partagée de `setColumn`, `updateSettingsFull`, `incrementUnread`, `setField` et `decrementField`) ou sa valeur bornée par un entier.
- MOD : `UserRepository::updateUserSettings()` disparaît : c\'était une **deuxième** écriture des réglages, ses 19 colonnes recopiées et concaténées, alors qu\'`updateSettingsFull()` écrit exactement les mêmes en vérifiant leur nom. `updateHomePlanet()` lie ses cinq entiers.
- FIX : La porte de saut concaténait dans son SQL la quantité de vaisseaux **saisie** : une apostrophe dans la saisie cassait la requête et une saisie forgée pouvait la détourner. Les quantités sont maintenant bornées par un entier et liées en paramètres, dans un seul `UPDATE`.
- FIX : Les pourcentages de production passaient par un fragment SQL construit depuis la page : les colonnes sont vérifiées et les valeurs liées, côté jeu comme côté API JSON.
- MOD : Le drapeau d\'état (`flags`) est une **valeur** comme une autre : il part en paramètre lié (`(flags & ?) = 0`) au lieu d\'être écrit en clair dans la condition. Et comme une requête préparée ne rend que des clés **associatives**, les appelants qui lisaient un index numérique (`countOnlineSince()`) lisent maintenant le nom de la colonne (`online`).
- MOD : `findCurrentByIdSemicolon()` et `findCurrentByIdWord()` écrivaient la **même** requête que `findCurrentById()` (sept pages les appelaient) : il ne reste qu\'une lecture, et `RepositoryConsolidationTest` refuse le retour des copies.
- MOD : L\'audit SQL (`tools/audit-sql.php`) juge le **texte de la requête** et non l\'appel entier : un appel préparé n\'est plus compté comme une concaténation, chaque site n\'apparaît qu\'une fois, et le rapport nomme la classe, la méthode et le morceau fautif (`UserRepository::setColumn — $column`). Cinq verdicts remplacent les trois anciens : `ROUGE` (une entrée), `VALEUR` (une valeur concaténée, le seul cas à corriger), `IDENT` (un identifiant, à garantir par une liste blanche), `GABARIT` (une liste de marqueurs assemblée à l\'exécution) et `VERT`. `--strict-values` est le mode du chantier, `--strict` reste celui de l\'intégration continue.',

'<span class="text-success">2026.24</span>' => 'Core nu : les neuf modules sont archivés, et les colonnes que le moteur lit reviennent au schéma
- ADD : `modules/README.md` porte la réponse du dossier — ce qu\'un module est, les clés de son manifeste dans l\'ordre, ses dossiers, la surcharge d\'une classe du Core, l\'archivage — et l\'adresse des deux archives où les modules retirés se reposent (dans le dossier parent du dépôt, donc hors de l\'historique).
- MOD : Les neuf modules livrés (`marchand`, `annonces`, `chat`, `notes`, `officier`, `records`, `alliance`, `extracteurs`, `bot`) sont **archivés** : `modules/` ne contient plus que son réponse, et le jeu tourne ainsi — un module absent ne casse rien, ses pages, ses routes JSON, ses surcharges de classes et ses migrations disparaissent avec lui, et le Core dit l\'absence. Leurs tests partent avec eux.
- FIX : Le schéma du Core reçoit les colonnes que **son** moteur lit : la famille des officiers (`users.rpg_*`, `xpminier`, `xpraid`, `lvl_minier`, `lvl_raid`) et l\'appartenance d\'alliance qu\'il affiche (`users.ally_id`, `users.ally_name`). Sans elles, une installation neuve levait « Undefined array key » sur la production, le hangar, l\'infos, la galaxie et le combat. Un module qui les recrée plus tard est toléré (colonne déjà présente).
- MOD : La page `/back/robots` garde sa **porte** dans le Core et prend une permission du Core (`admin.robots`, libellé `adm_robots`) : elle existe sans le module et annonce son absence, au lieu d\'exiger une permission qu\'aucun manifeste ne déclare.
- MOD : `modules/.archive/` est ignoré par git — l\'archive d\'un module désinstallé est un état d\'installation, pas du code. Le brouillon d\'installation qui s\'y trouvait versionné est retiré.',

'<span class="text-success">2026.23</span>' => 'Les colonnes des robots sortent du Coeur d\'application : le module en dérive les classes
- MOD : Le Coeur d\'application ne nomme plus une seule colonne d\'un module dans ses requêtes. `users.bot` était lue par l\'historique des connexions, le journal des actions et les chiffres de l\'univers ; `users.settings_bots` était écrit par la page Options. Les trois dépôts exposent désormais un seul point de surcharge — le **nom** de la colonne, vide au Coeur d\'application — et composent leurs requêtes avec lui, tandis que le module des robots le renvoie. Un jeu sans le module tourne donc sans ces colonnes : les listes se remplissent, les robots sont simplement absents.
- MOD : Les dépôts concernés (`SessionRepository`, `ActionRepository`, `StatsRepository`) ne sont plus `final`, et le Coeur d\'application les **résout** là où il les construit (`SessionService`, `ActionService`, le tableau de bord du panneau) au lieu de nommer leur classe — sinon c\'est la classe du jeu qui répondrait, surcharge ignorée.
- MOD : L\'enregistrement des réglages du compte passe par les **données** : `UserRepository::updateSettingsFull()` écrit les colonnes qu\'on lui donne (forme du nom vérifiée, valeurs liées en paramètres), `UserSettingsService::settings()` réunit les réglages du Coeur d\'application et ceux qu\'un module ajoute, et la page Options comme l\'API JSON résolvent le service. La case « activité des robots », sa colonne et ses libellés vivent dans le module, avec sa page `ProfilController` dérivée.
- FIX : L\'écriture des réglages échappait mal les valeurs saisies (apostrophe dans un avatar ou un skin → requête cassée) : tout passe maintenant en paramètres liés.
- ADD : Deux garde-fous tiennent la règle : plus aucune requête du Coeur d\'application ne nomme une colonne de module (`RepositoryConsolidationTest`), et chaque classe que le module des robots reprend est bien surchargée et dérivable (`BotOverrideTest`).',

'<span class="text-success">2026.22</span>' => 'Surcharge de classe : c\'est le module qui écrit ce que lui seul connaît
- MOD : Le module `officier` reprend la page de la vue générale (`/game/overview`) : le bloc des niveaux RPG, ses seuils (5 000 points par niveau de mineur, 10 par niveau de raid, plafond à 100 niveaux cumulés) et le message de passage de niveau vivent désormais dans `modules/officier/` — contrôleur dérivé, gabarit et langue dans le module. La page du Coeur d\'application ne pose plus que les marqueurs « vides » du bloc, donc un jeu sans le module affiche la vue générale comme avant, sans trou ni erreur.
- FIX : Sans le module `officier`, la page de la vue générale tombait en erreur fatale (« Unknown column \'lvl_minier\' ») : le contrôleur du Coeur d\'application lisait des colonnes que lui seul module crée. Le Coeur d\'application ne nomme plus aucune donnée d\'officier — ni dans ses requêtes, ni dans son gabarit.
- MOD : Les dépôts, entités et services suivent la même règle que les pages : `Modules\\Officier\\Repositories\\UserRepository` dérive `App\\Repositories\\UserRepository` (déclaré `class`, et non plus `final`) et porte `levelUpMinier()`, `levelUpRaid()`, `resetOfficierPoints()` et `recruitOfficier()`. Le renommage `use … as CoreUserRepository` rend explicite ce qui reste au Coeur d\'application. Une classe du Coeur d\'application qu\'un module reprend n\'est plus jamais `final`.
- MOD : Le schéma du Coeur d\'application ne connaît plus les colonnes d\'expérience RPG (`xpminier`, `xpraid`) : elles naissent dans la migration du module, avec le reste de ses colonnes. Une installation neuve les obtient du module, et le rejeu reste inoffensif.
- MOD : Deux tests disent maintenant la règle : le routeur envoie `/game/overview` au module quand il est déposé, et `levelUp()` n\'a qu\'une implémentation — celle du module (le Coeur d\'application n\'en garde aucune trace).',

'<span class="text-success">2026.21</span>' => 'Base de données : un seul fichier de schéma, et le schéma de chaque module chez lui
- MOD : Le dossier des migrations du Coeur d\'application ne contient plus qu\'un fichier. Les migrations 002 à 024 ont été fusionnées dans `001_initial_schema.php`, qui décrit **tout** le Coeur d\'application d\'un coup : chaque table naît avec ses colonnes définitives, plus un seul `ALTER TABLE`, et le schéma se crée directement en InnoDB / utf8mb3 — la conversion depuis MyISAM/latin1 n\'avait plus d\'objet. Le fichier reste réversible (un `down` explicite retire ses 18 tables).
- MOD : Le schéma d\'un **module** vit dans son module (`modules/<nom>/db/migrations/`) : `alliance`, `annonce`, `chat`, `notes`, `market_ticks`, `market_positions`, les colonnes `users.ally_*`, `users.rpg_*`, `users.bot*` et `planets.extractor` sortent du fichier du Coeur d\'application. Un jeu sans le module se contente de ne pas avoir la fonctionnalité ; le module posé plus tard crée ce qui manque (`IF NOT EXISTS`, `ADD COLUMN`), et le rejeu reste inoffensif.
- FIX : Sur une installation neuve, la table des notes naissait avec son drapeau à 1 — donc dans l\'état « supprimé » de `Flags` — au lieu du défaut de la classe : les notes auraient été invisibles. L\'écart de type des migrations de modules, qui déclaraient « text » là où les tables en service portent « mediumtext » (alliance, tchat, marché, notes), est corrigé du même coup.
- MOD : La table `errors` (journal des erreurs applicatives) disparaît : sa page d\'administration avait déjà été retirée et plus rien ne la lisait. Elle part avec `ErrorsRepository`, l\'entité `GameError`, sa place dans le verrou du traitement des flottes et la purge de la remise à zéro.
- MOD : La documentation de travail décrit le nouveau réponse — un seul fichier pour le Coeur d\'application, le schéma d\'un module chez lui, et une colonne nouvelle écrite dans sa table plutôt que rattrapée par un `ALTER`.',

'<span class="text-success">2026.20</span>' => 'Modules : téléversement analysé, et un squelette de référence
- ADD : La page des modules accepte une **archive** (zip, tar, tar.gz) : elle est extraite en quarantaine (`modules/.archive/.upload/`), analysée, et le rapport s\'affiche sur la même page — refus en rouge, alertes en orange, empreinte SHA-256, nombre de fichiers. L\'installation reste un geste **distinct** : un refus masque le bouton, une alerte demande la case « installer malgré les alertes ». Seul le **nom** du module revient du client : le dossier de quarantaine est reconstruit côté serveur et son contenu réanalysé avant tout déplacement.
- ADD : `App\Core\PackageScan` analyse un module sans jamais l\'exécuter — manifeste (clés connues, nom = dossier, permission réservée), chemin d\'une entrée (zip-slip), lien symbolique, fichier que le serveur exécute ou lit tout seul (`.htaccess`, `.phtml`), syntaxe PHP (compilée sans exécution), fonctions d\'exécution de code, code obfusqué, bornes de taille.
- MOD : **Une alerte n\'empêche jamais l\'installation.** Seuls les refus « par construction » bloquent : manifeste illisible, entrée hors du module, fichier exécutable côté serveur, bornes. Tout le reste — appel à `eval`, syntaxe cassée, clé inconnue au manifeste — s\'affiche et laisse l\'administrateur décider en connaissance de cause. L\'analyse est un filtre, pas un certificat, et l\'interface le dit.
- ADD : `tools/module-template/helloworld` : le squelette de référence d\'un module — tous les dossiers qu\'un module peut porter (`core`, `controllers`, `entities`, `repositories`, `services`, `view`, `language`, `db`, `cli`), un fichier utile dans chacun, et un README qui est le rendu, clé de manifeste par clé de manifeste. Il vit sous `tools/`, refusé en HTTP : sans effet sur le jeu tant qu\'on ne le copie pas.
- MOD : **Un module possède son schéma.** Un module reste une extension optionnelle : le dépôt ne versionne de lui que son manifeste, ses migrations, ses tables de jeu et ses langues (`modules/*/*` avec ses exceptions), et ses nouvelles tables naissent dans son propre `db/migrations/` — jamais dans celui du Coeur d\'application.
- MOD : Le panneau d\'administration prend toute la largeur de la page : le rendu du panneau porte la même classe que celles du jeu, bornée à 900 px et centrée. La borne est neutralisée **dans le panneau seulement**, pour ne pas élargir les rendus du jeu ni les fenêtres à part.',

'<span class="text-success">2026.19</span>' => 'Vue galaxie : les raccourcis de flotte fonctionnent enfin
- ADD : Coloniser une position libre se fait en un clic : la cellule « Actions » d\'une position vide propose « Coloniser », qui ouvre la page d\'envoi avec une sonde de colonisation déjà saisie et la mission de colonisation choisie (sur une position libre, c\'est la seule proposée). Le lien ne s\'affiche ni sur une position occupée, ni sur une colonie abandonnée dont les coordonnées restent réservées, ni quand le joueur n\'a pas de sonde de colonisation.
- FIX : Les raccourcis de la galaxie ne partaient pas. Leurs liens d\'infobulle portaient une entité HTML incomplète (`&#039javascript:` : le point-virgule manquait), et l\'action JSON qui les exécute refusait toute mission autre que l\'espionnage (6) et le recyclage (8) — la colonisation comprise, pourtant déjà prévue par le code du raccourci.
- FIX : Le départ d\'une flotte depuis la galaxie ne s\'affichait nulle part : la réponse de l\'action était écrite dans des éléments qui n\'existent que sur la page d\'envoi. Le pied de la galaxie porte déjà le tableau de l\'état des flottes ; il est maintenant rempli, et une bulle (le Coeur d\'application `XNova.notify`) annonce l\'envoi ou le refus. Le statut affiché vient du code renvoyé par le serveur — 600 = envoi accepté, les 6xx sont les refus — là où la ligne affichait le mot « done » quelle que soit la réponse.
- ADD : L\'extraction rejoint le champ de débris : à côté du recyclage, l\'infobulle propose « Extraction » quand le joueur possède un extracteur. Le Coeur d\'application ne connaît pas ce vaisseau : le module `extracteurs` le déclare (`debris` au manifeste de sa mission 12), comme il déclare déjà son ajout de deutérium au champ de débris — le raccourci est donc découvert, jamais codé en dur.
- ADD : Chaque action du champ de débris annonce les vaisseaux **nécessaires**, calculés sur la capacité de soute du vaisseau — le recyclage sur le métal et le cristal, une mission de module sur tout le champ, ressource ajoutée comprise : le joueur voit d\'un coup d\'œil s\'il en a assez. Quand il en a moins, le libellé le dit (« tout part ») et le raccourci envoie alors tout ce que la planète porte, jamais plus : ce qui est annoncé est ce qui part.
- MOD : La règle des raccourcis vit dans le Coeur d\'application (`FleetDispatchService::galaxyMissionIds()`), partagée par la page et par l\'action JSON qui les exécute : une mission nouvelle n\'a rien à recopier. La quantité demandée par un lien est désormais lue aussi dans l\'adresse (`$_GET`), sinon un lien prérempli ne l\'était que pour la forme.
- ADD : `GalaxyMissionShortcutTest` tient la déclaration : le Coeur d\'application connaît ses trois gestes, l\'expédition n\'est pas une mission de champ de débris, et la mission du module vient bien de son manifeste.',
'<span class="text-success">2026.18</span>' => 'Arbre des technologies : l\'unité d\'un module rejoint sa section
- FIX : L\'arbre parcourait les libellés dans l\'ordre du tableau de langue — or ceux d\'un module y sont fusionnés **avant** ceux du jeu : l\'extracteur s\'affichait donc en toute première ligne, devant la mine de métal, au lieu de figurer parmi les vaisseaux, ce qui donnait l\'impression qu\'il n\'y était pas. Les sections viennent maintenant des **catégories du jeu** (`$reslist`), que les modules complètent : chaque unité apparaît dans sa section, à sa place, quelle que soit la langue.
- ADD : `TechTreeServiceTest` tient la règle : une unité déclarée en tête du tableau de langue rejoint bien sa section, et un élément que deux catégories listent n\'est rendu qu\'une fois.',
'<span class="text-success">2026.17</span>' => 'Fiche d\'information : le vaisseau d\'un module a la sienne
- FIX : La fiche d\'information choisissait son gabarit par **plages d\'identifiants** : l\'extracteur (vaisseau 216, ajouté par le module) ne tombait dans aucune et la page finissait en erreur fatale — `/game/infos?gid=216`. Le choix se fait désormais par les **catégories du jeu** (`fleet`, `defense`, `officier`), que les modules complètent ; les missiles restent traités avant la défense, dont ils font partie, et un identifiant qu\'aucune catégorie ne réclame obtient la fiche générique au lieu d\'une page blanche.
- ADD : L\'extracteur a maintenant sa fiche, nom et description comprises. Elle compte : le hangar masque un vaisseau dont on n\'a pas les prérequis, donc la fiche est la page où le joueur lit ce que celui-ci **fait** — et ce qu\'il lui faut : un chantier spatial de niveau 12 et une technologie de bouclier de niveau 5 (l\'arbre des technologies, lui, annonçait déjà le vaisseau avec ses prérequis).
- ADD : `ExtractionTest` vérifie que le vaisseau du module porte bien son nom et sa fiche, et le crawl HTTP couvre `/game/infos?gid=216`.',
'<span class="text-success">2026.16</span>' => 'Attaques : le verrou couvre le registre des modules
- FIX : Une attaque se terminait par une erreur fatale (« Table \'game_modules\' was not locked with LOCK TABLES »). Le moteur de combat est un point de surcharge : à l\'arrivée de la flotte, le jeu demande au registre des modules lequel répond, et cette lecture tombait dans le verrou que le traitement des flottes pose sur ses tables — où MySQL refuse tout ce qui n\'est pas listé, **en lecture comme en écriture**. La table `modules` y est désormais déclarée en lecture ; un verrou d\'écriture aurait bloqué la lecture des autres requêtes, chaque page passant par ce traitement.
- ADD : `tests/Unit/Legacy/FleetLockTest` tient la liste du verrou : une table lue pendant le traitement d\'un vol doit y figurer, sinon la suite de tests échoue.',
'<span class="text-success">2026.15</span>' => 'Marché : le dernier relevé n\'est plus relu dix fois
- MOD : La page du marché relisait le relevé de cotation à chaque question qu\'elle posait — cotes, indices, valeur des actifs, portefeuille : jusqu\'à onze fois la même ligne pour un seul affichage, et d\'autant plus souvent que le joueur a de positions ouvertes. Le relevé est maintenant retenu le temps de la requête, et oublié dès qu\'un nouveau relevé est écrit, comme la vue galaxie le fait pour un système.
- MOD : Le compte à rebours de la liste des flottes (`scripts/ocnt.js`) portait le nom `t()`, comme celui des bâtiments (`scripts/cnt.js`) : deux fonctions globales homonymes s\'écrasent silencieusement dès que les deux fichiers se croisent. Il a désormais son propre nom.
- ADD : `tools/audit-doublons.php` relève les corps identiques (copie-colle) et les noms homonymes du code, en PHP comme en JavaScript : la règle « une règle = une implémentation » devient vérifiable, et le script sort en erreur s\'il trouve un doublon.',
'<span class="text-success">2026.14</span>' => 'Doublons : une seule implémentation par règle
- MOD : Le filtre d\'état des tables à drapeau (messages, notes, vols, rôles) existait quatre fois — une copie par dépôt — et elles avaient fini par diverger : il vit maintenant dans `BaseRepository::stateFilter()`, partagé par les quatre. De même, toutes les lectures « planète existant à ces coordonnées » se ramènent à `PlanetRepository::findByCoords()` : le dépôt des missions (`findPlanetAt()`), celui des flottes (`findTargetRow()`) et la variante `findByCoordsType()` du dépôt des planètes n\'en sont plus des copies — une copie qui oublie le drapeau rend une planète abandonnée attaquable.
- MOD : La lecture de la sélection multiple (`sele`) d\'un tableau du panneau vit dans `AdminController::selection()`, à côté du filtre d\'état : les pages de liste en héritent.
- MOD : Cinq méthodes sans aucun appelant sont parties : `findByCoordsTypeRaw()` (qui ignorait son paramètre de type), `findListByCoords()`, `findUpdatedSince()`, `findPlanetAtNoType()` et la copie de `findMoonsByOwner()` du dépôt de la galaxie.
- MOD : Le dossier `scripts/` perd quinze fichiers que rien ne chargeait, soit 63 Ko : des restes du jeu d\'origine (`jquery.js`, `thickbox.js`, `win.js`, `utilities.js`…), deux bibliothèques d\'infobulles inutilisées et le gabarit `galaxy_body.tpl`, remplacé par `galaxy_table.tpl`.
- ADD : Deux garde-fous tiennent la règle : `RepositoryConsolidationTest` refuse le retour d\'une copie du filtre d\'état ou d\'une lecture par coordonnées, et `AdminStateFilterTest` vérifie par réflexion que la sélection multiple est bien héritée du Coeur d\'application.',
'<span class="text-success">2026.13</span>' => 'L\'outillage n\'est plus servi par le site
- FIX : Le dossier `tools/` (générateur des images de la skin) répondait en HTTP, et ses scripts s\'exécutaient : `render.php` lancé sans argument prenait sa cible par défaut — « tout » — et réécrivait les cent quarante-deux images de la skin sur une simple visite. Le dossier est désormais refusé comme `app/` et `db/` (`.htaccess`), et chaque script vérifie son mode d\'exécution (`PHP_SAPI`) avant de travailler : les scripts d\'outillage s\'exécutent en ligne de commande, jamais depuis le site.',
'<span class="text-success">2026.12</span>' => 'Le classement se met à jour tout seul
- ADD : Un service `stats` — une image PHP en ligne de commande, sans serveur web — relance le recalcul du classement à intervalle régulier : il lance `db/stats.php`, attend, recommence. La boucle est séquentielle, donc deux recalculs ne se chevauchent jamais, même si un passage dépasse l\'intervalle (ce que ferait un cron, en empilant les processus).
- ADD : Le service démarre par défaut avec `deploy.ps1` (profil compose `stats`) ; `STATS_ENABLED=0` dans `configs/.env.<env>` l\'arrête et `STATS_INTERVAL` règle la cadence — trois cents secondes, soit cinq minutes, par défaut. Ses passages se lisent avec `docker compose logs -f stats`.
- MOD : Le classement n\'a donc plus à être planifié sur l\'hôte : la tâche voyage avec le jeu, dans la même pile.',
'<span class="text-success">2026.11</span>' => 'Vue galaxie : beaucoup moins de requêtes
- MOD : La vue galaxie lisait la base position par position — la ligne `galaxy`, puis la planète, son compte, sa lune, ses points et son alliance : soixante et onze requêtes pour un système. Chaque table est désormais lue **une seule fois** (`GalaxyRepository::prefetchSystem()`, puis préchargement des points de classement et des alliances), et la page en demande trente-neuf — un nombre qui ne dépend plus du nombre de planètes affichés.
- MOD : Les points du classement n\'étaient plus demandés deux fois par ligne : une requête couvre tout le système, et un compte déjà lu n\'est pas relu.
- FIX : La colonne « Alliance » comparait le lecteur à une variable non définie (`$GalaxyRowPlayer`) : la marque « membre de l\'alliance » ne s\'affichait donc jamais pour un membre. Elle compare maintenant au bon compte.
- FIX : Le `config.php` de la racine (vestige vide, recréé au démarrage du conteneur) est de nouveau ignoré par Git — le code ne lit que `configs/config.php`.',
'<span class="text-success">2026.10</span>' => 'Skin : images allégées sans être redessinées
- MOD : Les 61 images de `gebaeude/` sont ré-encodées à palette et pixels **inchangés** : le dossier passe de 3 559 Ko à 674 Ko, soit sept fois moins. L\'ancien encodage GIF était simplement très inefficace — chaque image a été comparée à l\'originale, pas un pixel ne bouge.
- MOD : La skin complète passe ainsi de 4,65 Mo à 1,31 Mo, sans perdre une seule image.
- ADD : `tools/skin_modern/slim.php` mesure les réglages possibles (`--essai`), ré-encode un dossier (`--reencodage`, palette conservée) et vérifie les pixels contre un dossier témoin (`--verifier`).',
'<span class="text-success">2026.9</span>' => 'Nouvelles images de la skin moderne
- MOD : Les 121 planètes de la skin (61 portraits de 200 px et leurs 60 vignettes) et les 21 glyphes d\'interface sont régénérés : planètes calculées — sphère bruitée, éclairage à termininator, nuages, atmosphère, tourbillons, champ de débris — et icônes redessinées. Le dossier des planètes passe de 1 180 Ko à 638 Ko.
- ADD : Le générateur vit dans `tools/skin_modern` (PHP et GD, sans dépendance) : `render.php` produit les images, `sheet.php` et `board.php` en font des planches de contrôle. Les noms et les tailles sont relevés sur les fichiers en place, et la graine vient du nom : la même commande redonne exactement les mêmes images.
- MOD : Les 61 images de `gebaeude/` sont conservées : ce sont des rendus 3D, qu\'aucune génération procédurale ne remplacerait à qualité égale.',
'<span class="text-success">2026.8</span>' => 'Files d\'attente visibles sur toutes les pages
- ADD : Les files d\'attente sont visibles sur **toutes** les pages de jeu, dans une colonne à droite en vis-à-vis du menu de gauche : quatre listes repliables — bâtiments, recherche, vaisseaux, défenses — avec le rang de chaque élément dans la file et son échéance, décomptée à la seconde. Le panneau n\'apparaît que si quelque chose est en chantier, et la page reprend alors toute sa largeur.
- ADD : Skin `public/xnova_modern/` : copie complète de la skin historique, allégée de ses fonds matriciels que personne ne référençait (`background1.jpg`, `background2.jpg`, `bg1.gif` — 520 Ko au total) et augmentée d\'un jeu d\'images vectorielles écrites à la main : emblème, bannière, fond étoilé répétable sans couture et quatre planètes. Le pack tient en moins de 12 Ko, là où le seul fond retiré en pesait 384.
- MOD : Le panneau réutilise le rendu des files (QueueService et QueueRenderer) : une seule règle, une seule ligne. Il hérite donc des actions — « Interrompre », « Retirer » — et du glisser-déposer, et les vaisseaux comme les défenses, qui partagent la même file, y gardent leur rang réel : un déplacement y est valide pour le jeu.
- MOD : Les listes des pages d\'origine (bâtiments, chantier spatial, laboratoire) sont masquées, jamais supprimées : le balisage reste dans les gabarits, porte toujours les actions et le décompte, et retirer la classe `xnova-queue-source` rétablit l\'affichage sur place. L\'alerte du hangar garde son temps restant total, celle du laboratoire le nom de la technologie.
- FIX : Une action qui change une file (lancer, interrompre, retirer) se voit maintenant **tout de suite** dans le panneau : le rafraîchissement à chaud, qui ne reprenait que le contenu de la page, remplace aussi la colonne de droite — et recharge la page entière quand le panneau apparaît ou disparaît, la largeur de la colonne principale changeant avec lui.
- FIX : Le domaine d\'une commande de file se lit désormais dans l\'adresse du lien, et non dans la page courante : depuis le panneau, une commande du laboratoire partait au contrôleur des bâtiments, avec l\'identifiant d\'une technologie pour un bâtiment.
- MOD : À l\'échéance d\'une file, la page se recharge : c\'est le chargement qui fait avancer les files, et sans cela le panneau aurait affiché un chantier terminé encore en cours.
- MOD : Le thème choisi par le compte pilote enfin **ses feuilles de style** : `default.css` et `formate.css` étaient chargées depuis la skin par défaut, si bien qu\'un thème n\'avait d\'effet que sur les images. Elles suivent maintenant le thème du compte, la skin par défaut restant le repli, et la liste « Skins » de la page Options propose les chemins en absolu — le chemin relatif qu\'elle offrait ne résolvait pas depuis une page profonde.',
'<span class="text-success">2026.7</span>' => 'File de recherche limitée, modules Extracteurs et Robots, stationnement entre mes planètes
- ADD : La file du laboratoire a une limite réglable (`MAX_TECHNOLOGIE_QUEUE`, 10 par défaut) : la page et l\'API refusent l\'ajout au-delà, et le bouton se désactive quand la file est pleine.
- ADD : Un module peut désormais **surcharger** une classe du jeu au lieu d\'en copier le code : il suffit de déposer la classe de même nom dans la même couche (`core/`, `services/`, `controllers/`…), le Coeur d\'application détecte la surcharge et résout la classe effective — celle du jeu reste la base. Un module dépose aussi ses tables (vaisseau, prix, prérequis, capacités), ses migrations (`modules/<nom>/db/`) et ses libellés — sans modifier `app/` ni `language/`.
- ADD : Module « Extracteurs » : le deutérium des vaisseaux détruits rejoint le champ de débris, aux mêmes pourcentages que le métal et le cristal. Un vaisseau extracteur le ramasse par la mission « Extraction », proposée pour un champ de débris seulement et refusée partout ailleurs.
- FIX : Les libellés d\'un module écrasaient ceux du jeu (les fichiers de langue étaient fusionnés sans profondeur) : les noms de vaisseaux et les libellés de missions disparaissaient des pages. La fusion est maintenant profonde, et un module peut surcharger une clé précise.
- FIX : « Stationner » et « Transporter » étaient proposés vers la planète d\'où partait la flotte. Ces deux missions ne visent plus la position de départ, mais restent ouvertes de ma planète vers ma lune — et l\'inverse.
- MOD : Les robots autonomes forment un **module** (`modules/bot/`) : service, dépôt, page, langues, migrations et point d\'entrée en ligne de commande y vivent. Le Coeur d\'application n\'en garde qu\'une réponse vide, résolu au point d\'appel : module éteint, aucun tour n\'est joué, aucun compte n\'est créé, et les marques « bot » disparaissent partout (galaxie, historique des connexions, statistiques du panneau, journal des actions).
- ADD : Nouvelle page du panneau « Robots autonomes » (`/back/robots`, permission `module.bot`) : état du réglage d\'univers, population, cadence, création des comptes manquants et liste des robots. L\'entrée de menu est apportée par le module lui-même (`/back/modules` gagne un libellé), et le module `bot` **dérive** la page du Coeur d\'application — le dossier `Back/` d\'un module désigne désormais le panneau, un fichier à plat une page du jeu.
- MOD : Le point d\'entrée des robots suit son module : `php /var/www/html/modules/bot/cli/bots.php` remplace `db/bots.php`.
- ADD : Un module peut être **désinstallé** depuis la page des modules : il est **archivé** dans `modules/.archive/`, sans qu\'aucun fichier ne soit supprimé. Le jeu le voit alors comme s\'il n\'avait jamais été déposé — pages, routes et surcharges disparaissent avec lui, et les modules qui en dépendaient sont suspendus — et le bouton « Réinstaller » le remet en place avec ses réglages.
- ADD : La liste des modules du panneau gagne ce que les autres listes ont déjà : un filtre Installés / Archivés / Tous, une recherche libre (nom, description, permission, adresse) et un classement par colonne, dix lignes par défaut et 10/25/50/100 au choix. Un module archivé se range dans la même liste que les autres, avec son badge et son bouton « Réinstaller » : une seule ligne de tableau sert les deux états.
- FIX : Le Coeur d\'application ne charge plus aucune classe d\'un module avant d\'avoir vérifié que le module est déposé : les pages du panneau qui s\'appuient sur un dépôt de module (notes, tchat) tombaient en erreur fatale quand il manquait, et annoncent maintenant l\'absence. Le jeu tourne avec `modules/` vide (vérifié page par page) — seules les adresses des modules disparus répondent 404.',
'<span class="text-success">2026.6</span>' => 'Éléments par onglets, déplacement d\'une planète
- ADD : La gestion des éléments de la fiche joueur se fait par onglets : bâtiments, recherches, vaisseaux et défenses. Chaque tableau affiche le nom, la valeur actuelle et une case de variation.
- ADD : Une seule validation par onglet : une variation négative retire, une variation positive ajoute, une case vide ou à zéro ne change rien. Les niveaux de recherche se modifient aussi, sauf celui d\'une recherche en cours (la file porte le niveau visé).
- ADD : La fiche joueur déplace une planète — et sa lune — vers une autre position : la lune, le champ de débris et toutes les flottes en vol suivent. Le déplacement est refusé si une attaque vise la position, si une flotte y est posée ou si la position est déjà occupée.
- FIX : « Vider le champ de débris » ne faisait rien : la page lisait des colonnes qui n\'existent pas (le champ de débris tient dans `metal` et `crystal`).
- FIX : La destruction d\'une lune ne la détruisait qu\'à moitié : la ligne de la lune, son entrée au registre et le lien de la galaxie partent maintenant ensemble, et la création comme la destruction se fient à la table des planètes.
- MOD : Le panneau d\'administration perd les listes de planètes, de planètes actives et de lunes, ainsi que le changement de mot de passe d\'un joueur : leurs adresses ne répondent plus. La fiche joueur dit désormais tout des planètes et des lunes.
- ADD : Chaque liste longue de l\'administration **se trie en cliquant sur l\'en-tête d\'une colonne** et la **taille de page se choisit** (10, 25, 50 ou 100 lignes) : journal des actions, liste des joueurs, historique des connexions, flottes en vol, liste des messages et tchat. Filtres, tri et taille suivent d\'une page à l\'autre.
- ADD : Sur la fiche joueur, la table des éléments suit la **planète choisie**, et un onglet « Gestion supplémentaire » réunit la lune et le déplacement de planète ; les onglets gardent la même hauteur, même quand une liste est vide.
- MOD : La taille de page par défaut passe à **dix lignes**, et le choix (10, 25, 50 ou 100) se fait désormais **au-dessus du tableau**, avec l\'intervalle affiché ; la navigation reste en dessous et disparaît quand il n\'y a qu\'une page.
- FIX : Le journal des actions affichait toujours cent lignes, quelle que soit la taille choisie : le service bornait la requête à sa propre limite, que le résumé de la barre ignorait. La liste suit maintenant la taille de page demandée.
- FIX : Le **retrait** de ressources ne faisait rien : le dépôt ramenait chaque montant à zéro avant de l\'appliquer, si bien qu\'un montant négatif était ignoré en silence. La soustraction se fait maintenant dans la requête, avec un plancher à zéro.
- MOD : Les libellés de le rendu « Ressources et champs » disent ce que chaque champ change : les ressources se saisissent en variation, les champs maximum, le diamètre et le nom ne changent que s\'ils sont remplis (« inchangé » quand ils restent vides). « Vider le champ de débris » devient un bouton, à côté de la remise à zéro des ressources.
- ADD : Sur la fiche joueur, les boutons de validation (« Appliquer », « Déplacer », « Bannir »…) se trouvent à droite du titre de leur rendu au lieu du pied de rendu.
- ADD : Le jeu pagine ses grands tableaux : la boîte de messages (dont « Voir tous les Messages »), les résultats de la recherche et le classement des joueurs comme des alliances — dix lignes par défaut, 10/25/50/100 au choix, intervalle affiché. La recherche garde son terme et son type d\'une page à l\'autre, la boîte de messages sa catégorie.
- MOD : La barre de pagination est désormais la même partout (`language/fr/system.mo`, `templates/OpenGame/pagination*.tpl`) : le jeu et le panneau d\'administration partagent les libellés et les gabarits, et le classement abandonne sa liste de cent lignes (« range ») au profit de cette barre.
- ADD : Une **case à cocher globale** ouvre les tableaux à sélection multiple (boîte de messages du jeu, notes, liste des messages du panneau) : elle coche ou décoche toutes les lignes de la page, se met à jour quand une ligne change, et reste désactivée quand le tableau est vide. Sans JavaScript, elle ne fait rien : la sélection ligne par ligne continue de fonctionner.
- ADD : Le tri par colonne et le choix de la taille de page arrivent aussi sur les listes de multi-comptes et d\'IP collectives déclarées (`/back/multi`).
- MOD : « Remettre les ressources à zéro » se déclenche par un bouton, comme « Vider le champ de débris » : plus de case à cocher à ne pas oublier avant de valider.
- ADD : Un mécanisme de **drapeaux** par enregistrement (`App\Core\Flags`, migration 014) : la colonne `flags` des tables `messages`, `planets`, `lunas` et `users` porte un bit par état — `DELETED` pour la suppression **logique** (la ligne reste en base) et `ENABLED` pour un élément actif, valeur par défaut. Chaque écran pourra ainsi adopter la suppression logique sans changer le schéma.
- MOD : La suppression d\'un message devient **logique** : il quitte la boîte du joueur (elle, ses compteurs et la page de modération ne lisent que les messages existants) mais reste en base. La liste des messages de l\'administration gagne un filtre « Suppr&eacute;s » et une action « R&eacute;tablir », à la ligne comme à la sélection — plus rien n\'est perdu par erreur.
- MOD : La page des multi-comptes se trie en cliquant sur l\'en-tête d\'une colonne et se pagine comme les autres listes, sur ses deux onglets.
- MOD : « Remettre les ressources à zéro » devient un bouton, à côté de « Vider le champ de débris » : le rendu des ressources ne porte plus de case à cocher.
- MOD : La suppression d\'une note devient **logique** : elle quitte la liste du joueur mais reste en base (colonne `flags`, migration 015). Le joueur croit l\'avoir effacée, l\'administration peut la rétablir.
- ADD : Nouvelle page « Notes des joueurs » dans le panneau d\'administration (`/back/notes`) : filtre « Suppr&eacute;s », tri, pagination, sélection multiple, et action « R&eacute;tablir » à la ligne comme à la sélection. La page ne montre que le titre et la taille des notes, jamais leur texte.
- FIX : La liste des messages du panneau (`/back/messagelist`) ne répondait que sur un système de fichiers insensible à la casse : le nom de classe déduit de l\'adresse ne correspondait pas à celui du fichier. Une table d\'alias le corrige, et un test compare désormais la casse des deux.
- MOD : La suppression d\'un **compte** suit la même règle : son état est le drapeau `DELETED` (migration 016), la colonne `deleted` disparaît et `deleted_time` ne garde que la date. Un compte supprimé ne se connecte plus (même page que le bannissement, avec son propre texte), ses robots cessent de jouer, ses planètes, son alliance et ses messages restent en base, et le panneau le rétablit d\'un clic. Une seule vérité par état, comme pour les messages et les notes.
- MOD : Un vol supprimé depuis la fiche joueur l\'est **logiquement** (drapeau `DELETED`, migration 017) : il n\'arrive plus — le moteur l\'ignore, le bandeau des flottes et les listes aussi — mais la fiche garde la liste des « Vols supprimés » et permet de le r\'etablir, après quoi il repart en mission.
- MOD : La **destruction d\'une lune** devient elle aussi logique : la lune garde sa ligne (ses bâtiments, ses vaisseaux, ses ressources), disparaît de la galaxie et n\'est plus ciblable, et la fiche joueur la propose au rétablissement — à condition qu\'une nouvelle lune n\'ait pas repris la position. Cela vaut pour les trois chemins : attaque (mission de destruction), abandon d\'une colonie et destruction depuis le panneau.
- MOD : L\'**abandon d\'une colonie** suit la même règle : la ligne `planets` reste en base (drapeau `DELETED`, propriétaire conservé), la position est détachée de la galaxie et le compte revient sur sa planète mère. La planète n\'existe plus pour le jeu : ni comptage, ni sélection, ni cible.
- ADD : Les coordonnées d\'une colonie abandonnée restent **réservées 24 h** (`ABANDONED_POSITION_DELAY`, réglable par l\'environnement) : la galaxie annonce « Planète détruite (colonisable dans X h) », la colonisation est refusée avec un message qui dit pourquoi, et aucune flotte — attaque, transport, stationnement, espionnage — ne peut s\'y rendre.
- ADD : La fiche joueur rétablit une colonie abandonnée (onglet « Gestion supplémentaire ») : refusé si une planète existant occupe la position, sinon la colonie revient chez son ancien propriétaire, sa position est réannoncée dans la galaxie et sa production repart de l\'instant du rétablissement. Une lune ne se rétablit pas avant la colonie qui la portait.
- FIX : Le formulaire d\'abandon d\'une planète partage la page du renommage : le bouton « Abandonner la colonie » partait dans l\'appel AJAX du renommage au lieu d\'ouvrir la confirmation. Il sort désormais du formulaire (`data-ajax-skip`), la confirmation parle de « Supprimer la planète », et le libellé du bouton n\'est plus défini deux fois.
- FIX : Les lectures de planètes par coordonnées (cible de flotte, missiles, phalange, position occupée) et le changement de planète ignoraient le drapeau : une colonie abandonnée restait ciblable et sélectionnable. Elles filtrent maintenant comme les autres.
- FIX : Une colonie abandonnée bloquait la colonisation de sa position, et la nouvelle colonie se faisait annoncer par l\'ancienne ligne de galaxie : les deux lectures de `CreateOnePlanetRecord()` filtrent le drapeau.
- FIX : L\'annonce « colonisable dans X h » manquait dans la galaxie : la condition était accrochée à l\'absence de ligne `galaxy`, alors que l\'abandon détache le lien **sans** supprimer la ligne. La position est maintenant annoncée dès qu\'elle est libre, ligne présente ou non.
- FIX : Le message de fin d\'abandon dit désormais où l\'on en est — « La position [1:8:1] sera colonisable à nouveau dans 24 heures » — et les libellés d\'abandon ont retrouvé leurs accents.
- FIX : Un missile, un scan de phalange ou un laboratoire intergalactique pouvaient encore viser — ou compter — une colonie abandonnée. Les cibles de missiles, la cible d\'une phalange (refusée **avant** tout débit de deutérium) et la somme des laboratoires intergalactiques filtrent désormais le drapeau.
- MOD : La table des tirs de missiles, `iraks`, devient `missiles` (migration 018, réversible) : le nom venait de l\'allemand et ne disait rien. Le code suit — un seul dépôt pour la table (`MissileRepository`, qui réunit le tir et l\'arrivée), l\'entité `Missile`, et plus aucune trace du mot dans `app/`.
- MOD : Un tir de missiles devient une **mission de flotte** (mission 11, migration 019) : le silo est débité au lancement, la salve traverse l\'espace, frappe à son échéance puis disparaît. Elle apparaît au bandeau des flottes comme les autres vols, sans bouton de rappel — un tir ne rentre pas — et ne peut plus viser qu\'une planète existant. La table `missiles`, son dépôt et son entité partent avec (la migration 019 reporte les salves en cours).
- MOD : La règle des missiles n\'a plus qu\'une implémentation : `App\\Core\\Combat\\MissileStrike` (calcul d\'origine repris tel quel, table des cibles comprise) appelée par `MissileService`, qui porte le lancement, la validation et l\'impact. L\'ancien `raketenangriff()` (fichier inclus à chaque requête), le `rak.php` qui le déclenchait et l\'ancien calcul `MipAttack()` du moteur de combat — jamais appelé depuis des années — sont supprimés.
- MOD : Le formulaire de tir de la galaxie s\'appelle maintenant `MissileLaunchController` : l\'adresse publique `/game/raketenangriff` reste valide (les liens en place continuent de fonctionner), mais le fichier ne porte plus son nom allemand.
- MOD : `calc.php`, calculateur orphelin resté à la racine (aucun lien, aucun include), est supprimé.
- MOD : Le menu latéral perd son dernier fichier à la racine : l\'tableau `ShowLeftMenu()` rejoint les autres fonctions de page dans `app/Core/Legacy/LeftMenuFunctions.php`, et `leftmenu.php` est supprimé. Le rendu reste dans `App\\Core\\LeftMenu` — seule implémentation — et les deux `renderDisplay()` chargent l\'tableau de là.
- ADD : Les rôles de l\'administration ont un **ordre** : deux flèches montent ou descendent un rôle d\'une place, sans JavaScript, et la liste suit la hiérarchie (du plus haut au plus bas). Le rôle composé naît en bas de l\'échelle, à vous de le placer.
- ADD : Un rôle ne gère que les rôles placés **sous lui** : il ne modifie ni le sien — il s\'octroierait les droits qu\'il veut — ni ceux au-dessus. Les rôles hors de portée s\'affichent en lecture seule (cases et champs désactivés, boutons masqués) et toute écriture forcée est refusée. Un rôle qui porte tous les droits gère tout le monde, et le compte d\'installation aussi.
- MOD : Les gabarits quittent la racine : ils vivent désormais dans `app/View/`, au côté des contrôleurs et des services (le dossier `templates/` disparaît). Les assets du thème passent de `skins/` à `public/` : les anciens liens `/skins/...` — messages et rapports déjà enregistrés, caches des navigateurs — sont redirigés vers `/public/...`, et le thème de chaque compte est reporté en base (migration 023).
- FIX : Une salve frappait dès le premier affichage de page suivant le tir, sans attendre son heure d\'arrivée (le moteur de missions sélectionne un vol dont le **départ** est passé) : elle attend désormais son échéance, comme les autres vols.
- FIX : Une salve en vol n\'a plus de bouton « Rappeler » dans le bandeau, et le rappel d\'un tir est refusé côté serveur.
- ADD : Le panneau d\'administration passe à un système de **rôles et permissions** (migration 020) : une permission par page (`App\\Core\\Acl`), un rôle est une liste de permissions cochées, et un compte porte un rôle (`users.role_id`). L\'installation crée quatre rôles par défaut — Opérateur, Modérateur, Administrateur, Super administrateur — et rattache chaque compte à celui de son niveau : personne ne perd d\'accès au passage.
- ADD : Nouvelle page « Rôles et permissions » (`/back/roles`) : créer un rôle, cocher les pages qu\'il ouvre, le renommer, le supprimer. La suppression est **logique** (le rôle quitte la liste et ne donne plus aucun droit, il se rétablit ensuite), un rôle par défaut ou encore porté par des comptes ne se supprime pas.
- MOD : La fiche joueur attribue un rôle (rendu « Rôle et permissions ») et la liste des joueurs affiche celui de chaque compte.
- MOD : Le menu du panneau ne montre que les pages ouvertes par le rôle du compte : les autres entrées sont masquées, et une section dont tout est fermé disparaît avec son titre.
- MOD : Le compte d\'installation (identifiant 1) est **super administrateur par définition** : tous les droits, non supprimable, non bannissable, et son rôle ne se change pas — depuis la fiche joueur comme depuis la liste des joueurs.
- MOD : `php db/acl.php` installe ou complète les rôles par défaut sur une base déjà en service (l\'installateur le fait pour un univers neuf) ; la commande est idempotente et ne réécrit jamais un rôle que l\'administration a ajusté.
- MOD : Les boutons de filtre d\'état des listes (messages, notes, rôles) n\'existent plus qu\'à un seul endroit (`AdminController::stateButtons()`), au lieu d\'être recopiés dans chaque page.
- ADD : Une fonctionnalité est un **module** : un dossier `modules/<nom>/` découvert par son manifeste `package.json`, qui déclare sa page, ses adresses (page, JSON et adresse historique) et sa permission. Nouvelle page « Modules » (`/back/modules`) : chaque module s\'allume ou s\'éteint, et le tout est semé par `php db/modules.php` (idempotent).
- ADD : Six modules livrés : **Marchand** (échange et marché spéculatif), **Annonces**, **Tchat**, **Notes**, **Officiers** et **Records**. Chacun apporte son code, ses gabarits, ses libellés (`modules/<nom>/language/`), et suit le découpage de `app/` (`core/`, `controllers/`, `entities/`, `repositories/`, `services/`, `view/`, `language/`). Un module peut offrir une page de jeu, une route JSON, et dériver un contrôleur du Coeur d\'application plutôt que de le recopier.
- ADD : Les permissions de module (`module.<nom>`) rejoignent le catalogue des rôles, dans un groupe « Modules » : un rôle qui la porte **ouvre** le module, un rôle qui ne la porte pas le **ferme** à ses comptes, et un compte sans rôle garde l\'accès d\'avant.
- MOD : Éteint, un module disparaît partout pour les joueurs — page, sous-pages, routes JSON (`module_disabled`), lien du menu — sauf pour le compte d\'installation et les rôles qui peuvent administrer les modules, qui doivent pouvoir le rallumer. Un module neuf naît allumé, les réglages `enable_marchand` et `enable_announces` sont repris.
- ADD : Nouvelle page « Modules » dans le menu du panneau (`/back/modules`) : allumer ou éteindre, voir la version, l\'auteur, la permission et les **dépendances** de chaque module.
- ADD : Un module **déclare ce qu\'il lui faut** : la version du Coeur d\'application et les autres modules (`dependencies` du manifeste). Ce qui manque s\'affiche (Coeur d\'application trop ancien, module à déposer, module à rallumer), un module privé d\'une dépendance ne s\'ouvre plus — seul un administrateur des modules le voit encore, pour réparer — et on ne peut pas éteindre un module dont un autre, allumé, dépend.
- ADD : Le jeu peut se passer d\'**alliances** : la fonctionnalité est un module comme les autres. Éteint, il ferme ses pages et son API, son entrée de menu disparaît, et les affichages du Coeur d\'application qui parlaient d\'une alliance s\'effacent avec lui — la colonne d\'alliance de la galaxie, la recherche d\'alliance et le classement des alliances.
- FIX : La **déconnexion** ramène à la page de connexion, `/front/login` : la redirection visait `login.php` **en relatif**, que le navigateur résolvait en `/game/login.php` — une adresse qui n\'existe pas — dès qu\'on venait d\'une page profonde. Elle est maintenant absolue et immédiate (plus de page de confirmation à attendre), et le panneau avait le même défaut avec `/game/login`.
- ADD : Un bouton « **Retour à la connexion** » sur la page d\'inscription et sur celle du mot de passe perdu — sur cette dernière, le bouton existait mais son libellé ne venait d\'aucun fichier de langue et s\'affichait vide.',

'<span class="text-success">2026.5</span>' => 'Pagination et fiche joueur enrichie
- ADD : Tableau de bord de l\'univers en tête de la fiche joueur : nombre de comptes, de robots, de bannis et de comptes supprimés, comptes actifs, inactifs et connectés, planètes et lunes, champs, flottes et ressources cumulées.
- ADD : La fiche joueur gère aussi les paramètres d\'une planète : diamètre, renommage, nombre de champs et remise à zéro des ressources en un clic.
- ADD : Toutes les listes de l\'administration sont paginées (cinquante lignes par page) : journal des actions, liste des joueurs, multi-comptes et IP collectives déclarées. Les filtres et le tri suivent d\'une page à l\'autre, et la barre indique l\'intervalle affiché.',

'<span class="text-success">2026.4</span>' => 'Fiche joueur, comptes supprimés et pages réunies
- ADD : Fiche joueur dans le panneau d\'administration (`/back/player`) : trois onglets. État du compte (bannir avec motif, durée et message au joueur, débannir, supprimer logiquement le compte, le rétablir), planètes et lunes (ajouter ou retirer des ressources, fixer les champs, vider un champ de débris, ajouter ou retirer des bâtiments, vaisseaux et défenses, poser ou détruire une lune) et flottes (rappeler un vol, lui retirer des vaisseaux, le supprimer).
- ADD : La suppression d\'un compte est désormais **logique** : le compte ne peut plus se connecter — le jeu le lui dit, comme pour un bannissement — mais ses planètes, son alliance et ses messages restent en base, et un administrateur peut le rétablir. Un robot supprimé cesse de jouer.
- ADD : La liste des joueurs mène à la fiche de chacun (« Gérer »).
- MOD : La liste des multi-comptes et celle des IP collectives déclarées ne font plus qu\'une page, à onglets (`/back/multi` ; `/back/declarelist` et `admin/declare_list.php` y conduisent).
- MOD : L\'ajout d\'une lune rejoint la fiche joueur : la page `/back/add-moon` disparaît, son adresse ne répond plus.
- MOD : Le bannissement se pose et se lève depuis la fiche du joueur, avec le message envoyé au compte : les pages « bannir », « débannir » et « remise à niveau » ne reviennent pas.',

'<span class="text-success">2026.3</span>' => 'Historique des connexions, base en InnoDB
- ADD : Chaque connexion est enregistrée : quand elle commence, quand le joueur a été vu pour la dernière fois, combien de temps elle a duré et comment elle s\'est terminée (déconnexion, ou inactivité après quinze minutes sans actualisation). Les robots y figurent aussi — leur tour de jeu vaut connexion — et sont marqués « bot ».
- ADD : La vue générale du panneau d\'administration affiche cet historique : une ligne par connexion, courbes de fréquentation et de temps de connexion sur l\'heure, le jour, la semaine, le mois et l\'année, et le temps de connexion total par joueur.
- MOD : L\'ancienne vue générale (liste des joueurs connectés depuis moins de quinze minutes) est supprimée : l\'historique dit la même chose en mieux. L\'adresse `/back/sessions` redirige vers la vue générale, filtres compris.
- MOD : Le compte se filtre en saisissant son nom (liste de propositions à la frappe) au lieu de le choisir dans une liste déroulante : avec un millier de comptes, la liste déroulante était inutilisable. Un nom qui ne correspond à aucun compte vide les tableaux au lieu de tout afficher.
- MOD : Toute la base passe en InnoDB et en utf8mb3_general_ci (migration 010) : les écritures deviennent transactionnelles, un arrêt brutal ne laisse plus de table à réparer, et les colonnes de texte acceptent les caractères accentués sans passer par des entités.
- FIX : La table des réglages du jeu n\'avait ni clé primaire ni index : un même réglage pouvait y exister en double (le doublon de `enable_bot` est supprimé par la même migration).
- ADD : Les robots apparaissent dans les sessions comme les joueurs : leur tour de jeu ouvre une connexion, l\'inactivité la referme.
- ADD : Statistique par jour de la semaine dans la vue générale : chaque jour, du lundi au dimanche, cumule les connexions et le temps de connexion des quatre dernières semaines. On voit enfin quels jours l\'univers est fréquenté.
- ADD : Journal des actions des joueurs (`/back/actions`) : chaque page ouverte et chaque écriture passée par l\'API JSON est enregistrée (date, compte, action, méthode, adresse), avec les filtres période, compte et nature (pages ou appels API). Les robots n\'y figurent pas — leur activité se lit dans leurs connexions — et les sondages qui rafraîchissent une page ne sont pas des actions. Le journal est borné à 90 jours.
- ADD : Le journal retient aussi le **contenu** de la demande : quel bâtiment ou quelle recherche est lancée (avec son nom), quels vaisseaux et en quelles quantités, quel échange au marchand, quels pourcentages de production. Un mot de passe ou un jeton n\'y est jamais écrit : ces champs sont masqués.
- ADD : Les adresses inconnues (page qui n\'existe pas) sont journalisées comme telles, et filtrables depuis la page : de quoi repérer un compte qui sonde des adresses qui n\'existent pas.
- ADD : Le changement de pseudo d\'un joueur est tracé : le journal enregistre l\'ancien et le nouveau nom, et l\'action porte son propre libellé.
- MOD : Le panneau d\'administration perd les pages de bannissement (bannir, débannir, remise à niveau), les erreurs répertoriées, l\'outil de cryptage MD5, l\'exécution d\'une commande SQL, l\'ajout de ressources et la recherche d\'un joueur (fiche de compte). Leur adresse ne répond plus — elle est désormais journalisée comme adresse inconnue — et la liste des joueurs garde une seule action, la suppression du compte.',

'<span class="text-success">2026.2</span>' => 'Marché : cotation par rareté, cote horaire et robots
- MOD : Tous les fichiers de configuration du serveur sont regroupés dans `configs/` (fichiers d\'environnement et `config.php`), un dossier jamais servi en HTTP ; seul `.env`, le fichier de Docker Compose qui choisit l\'environnement, reste à la racine. L\'interrupteur des robots devient le réglage `enable_bot` de la configuration (`CONFIG_ENABLE_BOT` à l\'installation) et le drapeau de debug devient `CONFIG_DEBUG` : les robots ne dépendent plus d\'une variable à part, et le debug se règle avec les autres réglages.
- FIX : L\'installateur ne peut plus mourir sur « Cannot redeclare xnova_load_env() » : le fichier d\'environnement était chargé deux fois dans la même requête (une fois par la barre de debug, une fois par `install/index.php`). Son chargement est désormais sans effet s\'il a déjà eu lieu.
- MOD : Les trois vitesses du panneau d\'administration se saisissent en multiplicateur (1 = normal, 1000 = mille fois plus rapide) : plus besoin de multiplier par 2500 soi-même. La vitesse de production l\'était déjà ; celles du jeu et des flottes sont converties à l\'enregistrement, et le menu latéral comme la fiche des officiers affichent enfin le multiplicateur réel (un univers normal annonçait ×2500).
- ADD : Chaque réglage de la table `config` peut être fixé par l\'environnement (variable `CONFIG_` suivie du nom du réglage en majuscules, par exemple `CONFIG_INITIAL_FIELDS=200`) : l\'installateur écrit ces valeurs au moment de l\'installation, les autres réglages gardant les défauts du jeu. Une fois le serveur démarré, c\'est la table `config` qui fait foi — le panneau d\'administration reste le seul à modifier un univers en service. La planète mère créée à l\'installation suit désormais `initial_fields` au lieu d\'une taille écrite en dur, et les 48 réglages sont listés dans `.env.example`.
- MOD : La cotation du marché suit désormais la rareté : un actif que l\'univers accumule voit ses parts perdre de la valeur, un actif que l\'univers consomme les voit monter. C\'est l\'inverse de l\'ancien indice, qui montait avec la croissance.
- ADD : Le métal, le cristal et le deutérium sont recotés chaque heure (MARKET_RATE_AMPLITUDE) : la page du marché affiche la cote du jour, le cours de l\'heure passée et l\'heure du prochain changement. Le métal est l\'unité de compte du marché, donc sa cote bouge avec ce qu\'il achète.
- ADD : Les relevés gardent la mémoire du marché (moyenne lissée, MARKET_EMA_ALPHA) ; c\'est elle qui sert de référence à la cote de rareté, dont l\'écart est plafonné (MARKET_SCARCITY_CAP).
- ADD : Les robots misent au marché comme des joueurs : mêmes cotes, mêmes frais, et leurs mises comptent dans la demande. Ils encaissent leurs gains, coupent leurs pertes et misent sur l\'actif le moins cher, un tour sur trois. Réglage BOTS_MARKET.
- ADD : Panneau « Activité des robots » sur la page du marché, alimenté par leurs vraies positions.
- ADD : Nouvelle option de compte « Voir l\'activité des robots » dans la page Options, doublée d\'un interrupteur d\'univers (`BOTS_INTERACTION`) : l\'activité des robots ne s\'affiche que si l\'univers et le joueur sont d\'accord, sans jamais changer le comportement des robots.
- MOD : Cotes, prix d\'une part, parts et montants du marché s\'affichent avec leurs décimales (virgule française, trois chiffres après la virgule au maximum) : plus d\'indice ni de gain arrondis à l\'unité. Les positions ouvertes avant le changement de modèle sont remises à la cote du jour, une fois, pour que la nouvelle règle ne se transforme pas en perte.
- MOD : Le rapport de destruction lunaire est habillé par le rapport de combat lui-même (même rendu, même en-tête, même résumé des pertes et du champ de débris) : la destruction n\'est plus une page à part.
- FIX : La phalange de capteur ne scanne plus n\'importe quel système : sa portée suit son niveau (système ± niveau, même galaxie), un scan hors portée est refusé avec le rappel de la zone couverte, et le deutérium n\'est débité que pour un scan réellement effectué (il l\'était même pour une demande refusée).
- ADD : Le classement des joueurs et des alliances se rafraîchit désormais en ligne de commande (`php db/stats.php`, à planifier) au lieu d\'être recalculé à l\'ouverture d\'une page du panneau : mêmes formules, mais plus de requête bloquée par un calcul complet de l\'univers. Les rangs des alliances, qui restaient à zéro, sont posés comme ceux des joueurs. La page `admin/statbuilder.php` et son fichier de fonctions sont supprimés : le dossier `admin/` ne contient plus de PHP.
- FIX : Le classement affichait les pseudos et les noms d\'alliance accentués en caractères illisibles (`Boréal` s\'écrivait `Bor?al`) : ils sont maintenant décodés à l\'affichage, comme partout ailleurs.
- ADD : Panneau d\'administration : les réglages du jeu, les copyrights étendus, le changement de code d\'un joueur, l\'outil de cryptage, l\'exécution d\'une commande SQL, le message à tous les joueurs, la réparation des files du hangar et la remise à zéro de l\'univers passent en MVC (`/back/settings`, `/back/credit`, `/back/changepass`, `/back/md5`, `/back/query`, `/back/message-all`, `/back/queue-fix`, `/back/reset` ; les adresses `admin/*.php` correspondantes redirigent). Les pages mortes sont supprimées (`variables.php` qui exposait phpinfo, `mass_message.php`, `deletuser.php`, `changelog.php` doublon de la page publique).
- FIX : Activer le réglage « debug » réduisait toutes les pages au journal de debug : ce journal terminait par `die()`. La barre de debug moderne (`DEBUG_BAR=1`) le remplace.
- FIX : L\'exécution d\'une commande SQL du panneau ne fonctionnait plus du tout (elle appelait `mysql_query()`, retirée depuis PHP 7) ; elle exige maintenant une case de confirmation en plus du jeton.
- MOD : Le message à tous les joueurs passe par le composeur du jeu (sujet borné, BBCode selon le réglage de l\'univers) au lieu de fabriquer son HTML.
- ADD : Le panneau d\'administration passe au thème Bootstrap comme le jeu : chaque page est un rendu (en-tête titré, tableau ou formulaire, pied), le menu latéral est une liste de liens groupés par section, et les tableaux deviennent responsives.
- ADD : Panneau d\'administration : les bannissements (bannir, débannir, remise à niveau des sanctions échues), les erreurs répertoriées, la modération du tchat, la liste des messages, l\'ajout de ressources, l\'ajout de lunes et les flottes en vol passent en MVC (`/back/banned`, `/back/unbanned`, `/back/autounban`, `/back/errors`, `/back/chat`, `/back/messagelist`, `/back/add-money`, `/back/add-moon`, `/back/flying-fleets` ; les adresses `admin/*.php` correspondantes redirigent).
- FIX : Les suppressions du panneau se faisaient par des liens (`?delete=N`, `?deleteall=yes`) lus avec `extract($_GET)` : elles exigent désormais un formulaire POST signé du jeton CSRF (erreurs, tchat, messages, comptes, bannissements).
- FIX : La modération du tchat affichait les messages des joueurs tels quels : un joueur pouvait faire exécuter du script dans le navigateur de l\'administrateur. Les messages sont échappés.
- FIX : La remise à niveau des bannissements ne faisait rien : sa requête écrivait un nom de colonne contenant une espace, donc MySQL la refusait. Elle lève maintenant les sanctions arrivées à échéance et annonce combien.
- FIX : La liste des messages du panneau sautait le premier message de la première page (le décalage de pagination partait de 1) et la suppression d\'une sélection effaçait tout ce qui était coché (`=` au lieu de `==` dans le test de la valeur).
- FIX : L\'ajout de lune créait une lune sur des coordonnées vides lorsque l\'identifiant de planète était inconnu, et un nom trop long échouait sur une erreur fatale (la colonne accepte onze caractères). L\'ajout de ressources, lui, annonçait un succès sans rien écrire pour une planète inconnue : les deux vérifient maintenant la planète et bornent la saisie.
- FIX : Les noms de planètes et de lunes s\'affichaient en « BorÃ©al » dans les pages du panneau : ces noms sont écrits en UTF-8 dans une colonne latin1, la connexion moderne les ré-encode. Ils sont relus comme le faisaient les pages historiques.
- FIX : Les libellés stockés en entités (« Tour de contr&ocirc;le ») s\'affichaient tels quels, ou vides pour ceux qui portaient un accent : le décodage produisait des octets que l\'échappement refusait. Ils sont désormais décodés puis échappés une seule fois, les octets latin1 étant réparés au passage.
- MOD : « Flottes en vol » du panneau liste les flottes de tout l\'univers avec la composition de chacune (lue par la même règle que le bandeau du bas), au lieu de la mécanique d\'événements de la vue générale.
- MOD : La page « Ajouter une flotte » du panneau est supprimée : son gabarit n\'existait plus et son écriture en base était invalide depuis toujours.
- ADD : Panneau d\'administration : la liste des joueurs, la recherche d\'un joueur, les multi-comptes déclarés et les IP collectives passent en MVC (`/back/userlist`, `/back/paneladmina`, `/back/multi`, `/back/declarelist` ; `admin/userlist.php`, `admin/paneladmina.php`, `admin/multi.php` et `admin/declare_list.php` y redirigent). La suppression d\'un compte passe par un formulaire POST protégé par le jeton CSRF (elle se faisait par un lien), et le changement de niveau d\'accès aussi.
- MOD : Le panneau d\'administration a désormais son menu latéral sur chaque page : les pages ne s\'ouvrent plus dans un cadre, les liens sont absolus et les entrées déjà migrées pointent vers les nouvelles adresses.
- FIX : La page des multi-comptes du panneau n\'affichait rien : elle lisait une colonne `text` qui n\'existe pas. Elle montre maintenant le déclarant, le compte déclaré et le motif, en résolvant les pseudos.
- FIX : La recherche par IP du panneau interrogeait une variable vide (elle ne renvoyait donc jamais rien) : elle lit l\'IP saisie. La fiche d\'un joueur affiche ses colonies et ses niveaux de recherche, comme prévu.
- FIX : `game/mipattack` (et les nouvelles adresses d\'administration en deux mots) ne se résolvaient que sur un système de fichiers insensible à la casse : la correspondance entre un segment d\'URL et sa classe de contrôleur est désormais explicite, ce qui la rend indépendante du système.
- ADD : Panneau d\'administration : le menu (hub) et les listes (planètes, lunes, planètes actives) passent en MVC, avec les mêmes gabarits et des lignes rendues par gabarit au lieu d\'être écrites dans le PHP. Les adresses historiques (`admin/leftmenu.php`, `admin/planetlist.php`, `admin/moonlist.php`, `admin/activeplanet.php`) redirigent vers les nouvelles.
- ADD : Le panneau d\'administration passe en MVC, comme le jeu : un contrôleur par page (préparatoire), les gabarits pour le balisage, une garde d\'accès unique (`App\Core\AdminAccess`, niveaux Opérateur / Modérateur / Administrateur) et les dépôts pour les requêtes. Première page migrée : « Joueurs connectés » (`/back/overview`, l\'ancienne adresse `admin/overview.php` y redirige).
- MOD : Les lignes « Heure » et « Membres en ligne » sont retirées de la vue générale ; le nombre de joueurs en ligne s\'affiche en haut, en icône avec son compteur (infobulle « joueurs en ligne »).
- MOD : Le rapport de destruction lunaire est présenté comme un rendu : cible, probabilité de destruction, ligne des Étoiles de la mort et issue, au lieu de lignes à la suite.
- ADD : Le rapport de combat annonce la formation d\'une lune sur une ligne à part, mise en évidence, au lieu de la coller à la ligne de probabilité.
- FIX : Une erreur fatale frappait toutes les pages lorsque la planète courante était une lune (la ligne galaxie se cherchait par identifiant de planète, introuvable pour une lune) : la recherche se fait désormais par coordonnées, comme la vue galaxie.
- ADD : Défense groupée : les flottes en stationnement chez un défenseur (mission « Garder ») combattent désormais à ses côtés, et les survivants sont répartis entre la planète et chaque flotte (au prorata de ce que chacun a engagé).
- MOD : Le rapport de combat détaille les forces par participant (attaquants d\'une attaque groupée, propriétaire de la planète et flottes en stationnement) : flotte engagée et pertes de chacun, à la place d\'un total global.
- MOD : Le rapport d\'attaque groupée nomme chaque attaquant avec ses coordonnées et la flotte qu\'il engage (les tableaux des tours montrent la force réunie), et le raid compte pour chacun des participants.
- FIX : « Raids Perdus » de la vue générale restait à zéro : un raid perdu écrivait son compteur dans la colonne des raids gagnés, si bien que « Raids Gagnés » montait à chaque défaite.
- ADD : Bouton « Rappeler » dans le bandeau « Flottes en vol » : une flotte en vol, en stationnement chez un allié ou en orbite rentre d\'un clic, sans JavaScript (le formulaire POST classique reste la seule mécanique).
- FIX : Le rappel accepte une flotte en stationnement chez un allié (elle ne rentrait jamais : la page exigeait la marque « en vol ») et son temps de retour est le temps de l\'aller, jamais le temps passé sur place.
- MOD : « Stationner chez un allié » n\'est plus possible que chez un ami accepté ou un membre de la même alliance ; l\'ancienne condition demandait un dépôt d\'allié sur la planète visée.
- FIX : Les noms et objets des messages s\'affichent enfin correctement (« Tour de contrôle » et non « Tour de contr&ocirc;le ») : les entités HTML étaient échappées une seconde fois à l\'affichage.
- FIX : L\'attaque groupée déclenche enfin un combat. La mission « Attaque groupée » supprimait la flotte sans rien faire (les deux traitements la jetaient) ; les flottes du même groupe sont maintenant fusionnées en une seule bataille, à l\'arrivée du dernier participant, puis le groupe est nettoyé. Les survivants rentrent avec la flotte du meneur et le rapport part au meneur et au défenseur.
- ADD : Détail du RapidFire dans le rapport de combat : pour chaque camp, les unités qui détruisent plusieurs unités à chaque tour et sur quelles unités du combat (le RapidFire est rejoué à chaque tour, tant que la cible est présente).
- ADD : Bandeau d\'alerte « Attaque en approche » en haut de chaque page dès qu\'une flotte hostile vise une de vos positions : mission, trajet et décompte à la seconde ; quand l\'échéance tombe, la page se recharge pour que le serveur traite l\'arrivée.
- FIX : Le tableau des équivalences du marché n\'est plus figé dans le code, il vient des cotes de l\'heure.
- MOD : Le rapport d\'espionnage est devenu un vrai rapport visuel : rendu d\'en-tête avec la cible et ses coordonnées, un bloc par catégorie (ressources, flotte, défenses, bâtiments, recherches), l\'issue de l\'espionnage et le raccourci « Attaquer ». Les règles d\'espionnage et le décompte des éléments sont inchangés.
- FIX : Stationner chez un allié (mission « Garder ») n\'inonde plus le courrier et rentre enfin à la fin de son maintien. Le traitement repassait à chaque affichage de page et renvoyait la notification d\'arrivée à chaque fois : le message partait en boucle et la fin du maintien n\'était jamais atteinte (la flotte restait sur place indéfiniment).
- FIX : Les boucliers ne peuvent plus être commandés en plusieurs exemplaires. La page Défense testait toujours le petit bouclier pour décider du grand (2 000 grands boucliers avaient pu être mis en file, puis refusés par MySQL à l\'enregistrement de la production : « Data truncated for column big_protection_shield ») ; la règle est désormais unique, partagée entre la commande, la file et l\'écriture, et une file en excès se répare d\'elle-même.',
'<span class="text-success">2026.1</span>' => 'Temps réel et confort de jeu
- ADD : Canal temps réel WebSocket (service « ws ») : l\'état de la planète est poussé au navigateur, plus de sondage en boucle ; option WS_ENABLED et repli automatique sur l\'API JSON.
- ADD : Bandeau « Flottes en vol » en bas de chaque page (décompte, stationnement, retour) à la place de la liste de la vue générale.
- ADD : Tchat diffusé par le canal, sans rechargement de page.
- FIX : Les ressources gagnées hors production s\'affichent enfin : pillage encaissé, prime du marchand, attaque subie. Le stockage ne plafonne plus que la production affichée.
- FIX : Énergie, débit de production et compteur de messages déclenchent aussi la mise à jour de la barre de navigation.
- MOD : Page Défense alignée sur le Chantier spatial : mêmes rendus d\'unité, même saisie, bouton « Nombre max ».
- ADD : Boutons « Retour » aux étapes d\'envoi de flotte : vaisseaux, cible, vitesse et mission déjà saisis sont conservés.
- ADD : Accélérateur de particules : nouveau bâtiment (usine de nanites 5 requise) qui divise par deux la durée des recherches à chaque niveau.
- ADD : Nouvelle mission « Mise en orbite » : une flotte se place autour de sa planète de départ, invisible aux espions et hors de portée des attaques, jusqu\'à son rappel.
- ADD : Détail des vaisseaux d\'un vol dans le bandeau « Flottes en vol » : la composition s\'ouvre d\'un clic, vaisseau par vaisseau.
- MOD : Les pages « Ressources », « Empire » et « Technologies » sont devenues des onglets de la vue générale ; leurs anciennes adresses y redirigent et les formulaires continuent de fonctionner sans JavaScript.
- ADD : Panneau « Bonus en cours » avec la page des ressources : niveau et effet réel de chaque officier (production, énergie, stockage, durées, combat) et réglages de l\'univers.
- ADD : Raccourcis « Notes » et « Liste d\'amis » dans la barre de navigation : ils s\'ouvrent en fenêtre à part, sans menu latéral ni barre de ressources.
- MOD : La déclaration de multi-comptes devient un onglet de la page Options ; l\'ancienne adresse `/game/add-declare` y redirige et le formulaire continue de fonctionner sans JavaScript.
- MOD : Les pages Règles et Options affichent la barre de ressources, comme les autres pages de jeu ; « Ressources » quitte le menu latéral (l\'onglet de la vue générale le remplace) et « Piloris » y est retiré pour l\'instant, la page restant accessible par son adresse.
- ADD : Notifications dans la barre de navigation : badge des messages non lus et des demandes d\'ami en attente, affiché en direct par le canal temps réel.
- MOD : Les icônes (messages, notes, liste d\'amis, thème) sont regroupées au-dessus du choix de la planète ; le compteur « Message » quitte la ligne des ressources.
- MOD : La fenêtre de la liste d\'amis ne construit plus son HTML dans le code : son balisage vit dans des gabarits, et un contrôle de tests empêche d\'y revenir.
- MOD : Même migration pour les pages Crédits, Contact, Bannis, Rapport de guerre, Recherche, Galaxie et Inscription (balisage déplacé en gabarits).
- MOD : Rapport de combat entièrement redessiné : un bloc par tour, les deux camps côte à côte, un tableau par type de vaisseau, verdict coloré puis résumé des pertes, du butin et du champ de débris. Le balisage vient des gabarits (le moteur de combat n\'est pas touché) et le rapport suit le thème clair ou sombre du joueur.
- FIX : Caractères corrompus corrigés (renommage d\'alliance, suppression de compte, messages d\'erreur, fichiers de langue).
',

'0.9c' => 'Files d\'attente, hangar et flottes
- FIX : Annuler le premier élément d\'une file recalcule les heures de fin des suivants (bâtiments, laboratoire).
- FIX : Une recherche lancée depuis une colonie est bien enregistrée dans la file du joueur et peut être annulée (remboursement sur la planète qui a payé).
- FIX : Annulation d\'un bâtiment en cours et réordonnancement de la file (toute la ligne sert de cible au glisser-déposer).
- FIX : File du hangar débloquée : les unités sont livrées même quand la durée de construction est nulle (partie accélérée).
- FIX : Envoi d\'expédition : plus de vaisseaux perdus ; technologie 124 et nombre de vols simultanés contrôlés comme sur la page.
- FIX : Retour de flotte (expédition, recyclage, colonisation) : plus d\'erreur qui bloquait toutes les pages.
- FIX : Création d\'une colonie (colonne de file sans valeur par défaut).
- MOD : Plus d\'action « Interrompre » sur les lignes du hangar : elle annulait en réalité un chantier de bâtiment.
',

'0.9b' => 'Refonte MVC et API JSON
- MOD : Architecture MVC maison : contrôleurs (Front, Game, Back), services métier, dépôts en requêtes préparées, entités, Coeur d\'application (routeur, requête, réponse, gabarits).
- MOD : Plus de SQL dans les pages et plus de doublons : une règle = une implémentation (BBCode, files, capacités de stockage, temps de construction...).
- MOD : 99 fonctions legacy regroupées par domaine dans app/Core/Legacy.
- ADD : API JSON /game/api/* : tableau ok/data/state/messages, jeton CSRF, erreurs normalisées sans détail technique.
- ADD : Amélioration progressive : chaque formulaire garde son POST classique et fonctionne sans JavaScript ; l\'AJAX prend le relais quand il est disponible.
- MOD : Interface réécrite en Bootstrap 5 (thème clair/sombre, affichage mobile).
- MOD : Schéma de base versionné (db/migrations : status, migrate, rollback, baseline).
- MOD : Constantes et tables de jeu centralisées, surchargeables par l\'environnement (.env) ; vitesse de jeu réglable par l\'administration.
- MOD : Installation et déploiement revus (assistant, images Docker dev/local/prod, deploy.ps1).
',

'0.9a' => 'Sécurité, contenu et outillage
- FIX : Sécurité : injections SQL, XSS réfléchi et données sérialisées non filtrées corrigés ; accès aux sources (dotfiles, app/, db/, tests/, includes/, templates/) bloqué.
- ADD : Marché : indices calculés sur l\'économie réelle (ressources, flottes, défenses), mises, revente, historique, effet de demande plafonné.
- ADD : Robots de test : tours bornés déclenchés à la consultation, suivi en ligne de commande.
- ADD : Barre de debug (requêtes SQL, temps d\'exécution) activable par l\'environnement, jamais injectée dans les réponses JSON.
- ADD : Suite de tests automatisés (près de 400 tests) et vérification HTTP des pages (test_game.ps1).
- MOD : Compatibilité PHP 8.3 (variables non définies, accès sur null, requêtes only_full_group_by).
- MOD : Code et commentaires en français, fichiers en LF sans BOM.
',

'<span class="text-success">0.8e</span>' => '- ADD : Fonction SecureArray() pour les variables POST et GET (Bono)
- ADD : Les administrateurs choisissent desormais le fond de la baniere... (Bono)
- ADD : Mode vacances + Production a 0 + Interdiction de construire(Prethorian)
',

'<span class="text-success">0.8d</span>' => '- ADD : Les administrateurs voient d&eacute;sormais quelle page est consult&eacute;e par quel joueur (Bono)
- ADD : Bot antimulticompte, personnalisation et activation/d&eacute;sactivation a volont&eacute;...  (Bono)
- ADD : Politique de customisation du serveur entam&eacute;e...  (Bono)
- ADD : Possibilit&eacute; d\'activer/personnaliser/customiser un lien personnalis&eacute; (Bono)
- ADD : Possibilit&eacute; d\'afficher/desactiver des liens dans le menu (Bono)
- FIX : Lien vers les alliances corrig&eacute;
- NEW: Destruction des lunes (juju67)
- NEW: Stationnement chez un alli&eacute; (juju67)
- FIX: Lien vers la galaxie du joueur dans la fonction de recherche',



'0.8c' => 'Modules et corrections (e-Zobar)
- NEW: Fonction copyright &eacute;tendus
- NEW: G&eacute;n&eacute;rateur de banni&egrave;re-profil (signatures pour forum) dans \'Vue g&eacute;n&eacute;rale\' (d&eacute;sactivable)
- ADD: D&eacute;claration des multi-comptes
- FIX: Nombreuses erreurs visuelles: admin chat, r&egrave;gles
- FIX: Variable root_path sur toutes les pages
- FIX: S&eacute;curit&eacute; panneau d\'administration
- FIX: Illustrations officiers manquantes
- ADD: Message d\'accueil &agrave; l\'inscription (Tom1991)
- ADD: Affichage points raids (Tom1991)',

'0.8b' => 'Correction de bugs (Chlorel)
- ADD: Fonction de remise &agrave; z&eacute;ro du joueur qui triche
- FIX: Liste des plan&egrave;tes tri&eacute;es dans la vue empire
- FIX: Liste des plan&egrave;tes tri&eacute;es dans la vue g&eacute;n&eacute;rale aussi
- FIX: Mise &agrave; de toutes les plan&egrave;tes au passage par la vue g&eacute;n&eacute;rale et la vue empire',

'0.8a' => 'Correction de bugs (Chlorel)
- FIX: message.php ne fait plus d\'erreurs SQL quand y pas de message
- FIX: Correction page records pour pouvoir prendre en compte ou pas les admins
- NEW: phalange version recod&eacute;e ... a tester sous toutes les coutures
- FIX: Plus de possibilit&eacute; d\'espionner sans sondes
- MOD: Mise en forme des chiffres dans les rapports de combat (avec des .)
- MOD: Modification du template de login pour qu\'il passe par display avec 1 seul <body>
- FIX: Suppression d\'une cause possible d\'erreurs MySQL
- FIX: Extraction des dernieres chaines de la vue generale
- FIX: Surprise pour les cheater au marchand !
- FIX: Fonction DeleSelectedUser efface aussi les planetes maintenant
- ADD: Page des r&egrave;gles (XxmangaxX)',

'0.8' => 'Infos (Chlorel)
- FIX: Skin sur nouvel installeur
- DIV: Travaux esthetique sur l\'ensemble des fichiers
- FIX: Oublie de modification d\'appel sur quelques functions nouvellement modifiees',

'0.7m' => 'Correction de bugs (Chlorel)
- ADD: Interface d\'activation de protection des plan&egrave;tes
- FIX: Les lunes vont a nouveau au bon joueur et pas a "un" joueur quand elles sont crees depuis l\'administration
- FIX: Overview Evenements de flottes (les personnelles pour le moment) utilisent a present le css (default.css)
- MOD: Adaption de diverses fonctions a l\'utilisation du css
- FIX: Chat interne (divers ajustements) (e-Zobar)',

'0.7k' => 'Correction de bugs (Chlorel)
- FIX: Retour de flotte en transport
- ADD: Protection des planetes d\'administration
- MOD: Liste des joueurs dans la section admin liens sur les ent&ecirc;tes pour tri
- MOD: Page g&eacute;n&eacute;rale section admin avec liens sur les ent&ecirc;tes pour tri
- FIX: Lors de l\'utilisation d\'un skin autre que celui d\'XNova, il s\'applique aussi en section admin
- FIX: Ajout du lune dans le panneau d\'administration (e-Zobar)
- ADD: Mode transf&egrave;re dans l\'installateur (e-Zobar)',

'0.7j' => 'Correction de bugs (Chlorel)
- FIX: On peut a nouveau retirer une construction de la queue de fabrication
- FIX: On peut a nouveau envoyer une flotte en transport entre deux planetes
- FIX: La liste des raccourcis dans la selection de la cible fonctionne a nouveau
- FIX: On ne peut plus detruire un batiment que l\'on ne possede pas
- ADD: Tout beau tout nouveau installeur (e-Zobar)
- FIX: Rarcellage de hieroglyphes (e-Zobar)',

'0.7i' => 'Correction de bugs (Chlorel)
- Suppression cheat +1
- Ajustement des dur&eacute;e de vols / consommation des flottes entre le code PHP et le code JAVA
- Tri des colonies par le joueur dans options
- Preparation du multiskin dans options
- Divers amenagements dans le code pour les Administrateurs (Liste de messages, Liste de Joueurs)
- Travaux sur le skin (e-Zobar)
- Travaux sur l\'installeur (e-Zobar)',

'0.7h' => 'Correction de bugs (Chlorel)
- Interface Officier refaite
- Ajout blocage des "refresh meta"
- Ajustement de divers Bugs
- Correction de divers textes (flousedid)
- Correction de defauts visuels (e-Zobar)',

'0.7g' => 'Correction diverses (Chlorel)
- Modification de l\'ordre du traitement de la liste de construction de batiments
- Mise en conformit&eacute; du code pour une seule commande "echo"
- Quelques modules de r&eacute;&eacute;crits
- Correction bug de d&eacute;doublement de flotte
- Mise &agrave; jour dynamique de la taille des silos, production des mines et de l\'&eacute;nergie
- Divers adaptations dans la section admin (e-Zobar)
- Modification lourde du style XNova (e-Zobar)',

'0.7f' => 'Informations et porte de saut: (Chlorel)
- Nouvelle page d\'information completement repens&eacute;e
- Nouvelle interface porte de saut int&eacute,gr&eacute;e a la page d\'information
- Nouvelle gestion de l\'affichage des rapid fire dans la page d\'information
- Multitude de correction faites par e-Zobar',

'0.7e' => 'Partout et nulle part : (Chlorel)
- Nouvelle page registration (mise au standard)
- Nouvelle page records (mise en conformit&eacute; avec le site)
- Modif kernel (y en a pas mal mais pas possible de toutes les expliquer l&agrave; et de toutes maniere pas
  grand planète ne serait capable de les comprendre',

'0.7d' => 'Partie admin : (e-Zobar)
- menage dans pas mal de modules
- alignement du menu au style de fonctionnement du site
- traduction complete de ce qui n\'etait pas encore en francais',

'0.7c' => 'Statistiques : (Chlorel)
- Suppression des appels base de donn&eacute;es de l\ancien systeme de Statistiques
- Bug Impossibilit&eacute; de fabriquer des defenses ou des elements de flotte n\'utilisant pas de metal
- Bug Comme certains petits rigolos s\'amusent a lancer des quantit&eacute;es enormes de vaisseau dans
  une meme ligne de la queue de construction vaisseau, nous en sommes arriv&eacute;s a limiter le nombre
  d\'element fabriquable par ligne donc maximum 1000 vaisseaux ou defenses a la fois !!
- Bug erreur lors de la selection planete par la combo
- Mise a jour de l\'installeur',

'0.7b' => 'Statistiques : (Chlorel)
- Reecriture de la page de Statistique (appell&eacute;e par l\'utilisateur)
- Les stat alliance s\'affichent !
- Ecriture du generateur admin des stats
- Separation des stats de l\'enregistrement utilisateur (les stats on leur propre base de donn&eacute;es)',

'0.7a' => 'Divers : (Chlorel)
- Bug Technologies (la duree de recherche apparait a nouveau quand on revient dans le laboratoire
- Bug Missiles (mis a plat de la port&eacute;e des missiles interplanetaires, et mise en place de la limite de fabrication par rapport a la taille du silo)
- Bug Port&eacute;e des phalange corrig&eacute; (on ne peut plus phalanger toute la galaxie)
- Bug Correction de la conssomation de deuterium quand on passe par le menu galaxie',

'0.7' => 'Building :
- Reecriture de la page
- Modularisation
- Correction bugs de statistiques
- Debugage de la liste de construction batiments
- Diverses retouches (Chlorel)
- Divers debug (au fil de l\'eau) (e-Zobar)
- Ajout de fonction sur la vue principale (Tom1991)',

'0.6b' => 'Divers :
- Correction & Ajouts de fonctions pour les officiers (Tom1991)
- Menage dans les scripts java inclus (Chlorel)
- Correction divers bug (Chlorel)
- Mise en place version 0.5 de la liste de construction batiments (Chlorel)',

'0.6a' => 'Graphisme :
- Ajout Skin XNova (e-Zobar)
- Correction d\'effets nefastes (e-Zobar)
- Ajout de bugs involotaires (Chlorel)',

'0.6' => 'Galaxy (suite): (by Chlorel)
- Modification et reecriture de flottenajax.php
- Modification des routine javascript et ajax pour permettre les modification dynamiques de la galaxie
- Corrections bug dans certains liens des popups
- Definition nouveau protocole d\'appel, dorenavant meme sur une lune, la galaxie s\'affiche a partir de la bonne position
- Correction des appels de recyclage
- Ajout module "Officier" (by Tom1991)',

'0.5' => 'Galaxy: (by Chlorel)
- Decoupage ancien module
- Modification systeme de generation des popup dans la vue de la galaxie
- Modularisation de la generation de page',

'0.4' => 'Overview: (by Chlorel)
- Mise en forme ancien module
- Gestion de l\'affichage des flotte personnelle 100%
- Modification affichage des lunes quand presentes
- Correction bug renommer les lunes (pour qu\'elles soient effectivement renomm&eacute;es)',

'0.3' => 'Gestion de flottes: (by Chlorel)
- Modification / modularisation / documentation de la boucle de gestion des vols 100%
- Modification Mission d\'espionnage 100%
- Modification Mission de Colonisation 100%
- Modification Mission Transport 100%
- Modification Mission Stationnement 100%
- Modification Mission Recyclage 100%',

'0.2' => 'Corrections
- Ajouts de la version 0.5 des Exploration (by Tom1991)
- Modification de la boucle de controle des flottes 10% (by Chlorel)',

'0.1' => 'Merge des version flotte:
- Mise en place de la strat&eacute;gie de developpement
- Mise en place de nouvelles pages de gestion de flotte',

'0.0' => 'Version de depart:
- Base du repack a Tom1991',
);

$lang['changelog_title'] = 'Journal des modifications';

?>
