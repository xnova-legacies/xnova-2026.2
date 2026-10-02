# Outillage

Ces scripts ne font pas partie du jeu : ils se lancent **en ligne de commande** et ne sont jamais
servis par le site — le dossier est refusé par `.htaccess` et chaque script porte la garde
`PHP_SAPI !== 'cli'`.

## `audit-doublons.php` — vérifier la règle « une règle = une implémentation »

```
php tools/audit-doublons.php [racine] [seuil]
```

Relève les **corps identiques** (le même code écrit deux fois ; commentaires, blancs et
indentation sont ignorés, donc deux copies qui ne diffèrent que par la mise en forme sont bien
signalées) et les **noms homonymes** : une fonction globale PHP déclarée dans deux fichiers, ou une
fonction JS globale définie deux fois — la seconde écrase alors silencieusement la première.

La racine vaut le dépôt par défaut et le seuil 160 caractères : en dessous, deux accesseurs qui se
ressemblent ne sont pas un doublon. Le script sort avec le code **1** s'il trouve un doublon, **0**
sinon.

Les fonctions JS **indentées** sont ignorées : elles vivent dans une fermeture, donc elles ne
peuvent pas se croiser. Les `scripts/xnova-*.js` sont tous écrits de cette façon.

## `audit-sql.php` — rendu des requêtes SQL

```
php tools/audit-sql.php [racine] [--strict] [--strict-values]
```

Lit le code par **jetons** (`token_get_all`) et relève, à chaque appel de requête
(`query`, `fetchOne`, `preparedFetchAll`, `doquery`…), le **texte de la requête** — son
premier argument seulement : le tableau de paramètres qui suit n'entre pas dans le verdict,
sinon tout appel préparé passerait pour une concaténation :

- **ROUGE** — une entrée (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`, `$_FILES`)
  est présente dans la requête : c'est une injection, à corriger ;
- **VALEUR** — une **valeur** est concaténée (`= '" . $x . "'`, `LIMIT " . $n`) : le seul
  cas à corriger, un paramètre lié (ou un `(int)` pour une borne) est attendu ;
- **IDENT** — un **identifiant** est concaténé (nom de colonne entre accents graves, tri
  `ORDER BY`) : il ne peut pas être un paramètre lié, donc une liste blanche ou une forme
  vérifiée doit le garantir ;
- **GABARIT** — la concaténation ne porte qu'une liste de `?` assemblée à l'exécution (un
  `IN (…)` construit par `implode`) ou un fragment qui porte ses propres paramètres
  (`[$sql, $params] = self::stateFilter(…)`) : la requête reste paramétrée ;
- **VERT** — requête littérale ou paramétrée (`?`), sans concaténation.

Il suit les variables assemblées morceau par morceau (`$sql = 'SELECT …';` puis
`$sql .= " AND x = $y";`, et les listes `$sets[] = '…'`) — la tournure du code historique —
et ignore ce qui est dans un commentaire. Le rapport nomme la **classe et la méthode** du
site (`UserRepository::setColumn`) et le morceau fautif (`$settings['email']`), et chaque
site n'apparaît qu'une fois. `--strict` sort en erreur dès qu'un site rouge est trouvé :
c'est le mode de l'intégration continue, où il tourne après les tests. `--strict-values`
échoue aussi sur une **valeur concaténée** : c'est le mode du chantier « requêtes
préparées », qui vise zéro `VALEUR` (le legacy en porte encore).

Ce qu'il ne fait **pas** : il ne suit pas la **provenance** d'une valeur (une donnée lue
dans `$_POST` puis rangée dans `$x` ressort en `VALEUR` si elle reste concaténée). C'est le
rôle de l'analyse de flux — **Psalm** en mode `--taint-analysis` sait le faire, à condition
d'annoter les tableaux maison (`Connection`, `doquery`).

## `smoke.sh` — fumée HTTP de la pile
```
sh tools/smoke.sh [base]        # défaut : http://localhost:8080
```

Vérifie ce que les tests unitaires ne peuvent pas dire : la **pile tourne**. Il interroge
quelques adresses d'un univers neuf (installateur, connexion, deux pages du jeu) et refuse
une réponse **5xx** comme un corps contenant `Fatal error`, `Parse error`, `Uncaught` ou un
avertissement PHP. Piège connu du projet : PHP sort un `Fatal error` en **200** quand
`display_errors` est actif, donc le code HTTP seul ne prouve rien.

C'est le contrôle des workflows (`.github/workflows/_quality.yml`) et le même geste en
local, avant de pousser. Il est en **POSIX** pour tourner sur un runner Linux comme dans un
Git Bash, sans installation ; `test_game.ps1` (PowerShell) reste le crawl complet côté
Windows, avec la connexion et les 40 adresses du jeu.

## `skin_modern/` — générer les images de la skin

`render.php` produit les planètes et les glyphes de `public/xnova_modern/` (noms et tailles relevés
sur les fichiers en place, graine tirée du nom : la même commande redonne les mêmes images),
`slim.php` allège des images existantes sans les redessiner, `sheet.php` et `board.php` en font des
planches de contrôle. Détail et exemples : `skin_modern/README.md`.

## `diagnostic-base.php` — vérifier la couche base pour de vrai

```
php tools/diagnostic-base.php
```

Établit ce que les tests unitaires ne peuvent pas dire (ils ne touchent jamais MySQL) : le jeu de
caractères réellement **annoncé** à MySQL, la liaison d'un `LIMIT ?` (une valeur citée est refusée
par MySQL), l'écriture préparée **sous `LOCK TABLES`** (le verrou doit porter sur la même connexion
que les requêtes préparées), le `rowCount()` d'un `preparedExecute()`, l'échappement et le chemin
legacy `doquery()`. Il a servi de filet au passage de la couche base à PDO : c'est lui qui a montré
que la valeur d'un `LIMIT ?` partait **citée**.

## `rename-vocabulaire.php` — renommer le vocabulaire du projet

```
php tools/rename-vocabulaire.php [racine] [--with-strings] [--only=<préfixe>] [--with-modules]
```

Passe le vocabulaire arrêté en revue (`Coeur d'application` → Coeur d'application, `module` → module,
`champ de débris` → champ de débris, `en stationnement` → stationnement, `existant` → existant…). Il lit le
code par **jetons** : les commentaires d'abord, les chaînes de caractères seulement avec
`--with-strings` (et jamais une **clé de tableau**, qui est un identifiant) ; `--only=<préfixe>`
restreint à un fichier ou un dossier, `--with-modules` aux dossiers des modules. Les **noms de
méthodes restent en anglais** : le renommage porte sur les textes, pas sur le code.
