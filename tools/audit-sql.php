<?php

declare(strict_types=1);

/**
 * Audit SQL : où le jeu construit-il une requête par concaténation, et où une valeur
 * venue de l'extérieur entre-t-elle dans une requête ?
 *
 * Usage :
 *   php tools/audit-sql.php [racine] [--strict] [--strict-values]
 *
 * `--strict` sort avec le code 1 dès qu'un site **rouge** est trouvé (une valeur
 * d'entrée — `$_GET`, `$_POST`… — dans une requête) ; sans lui, le rapport s'affiche et
 * le code de sortie reste 0, pour que l'outil puisse tourner en intégration continue le
 * temps que le legacy soit repris. `--strict-values` va plus loin et échoue aussi sur une
 * **valeur concaténée** : c'est le mode du chantier « requêtes préparées », pas celui de
 * l'intégration continue (le legacy en porte encore).
 *
 * Comment il lit le code : par jetons (`token_get_all`), donc les requêtes citées dans
 * un commentaire ou une chaîne ne comptent pas. Il suit les variables assemblées par
 * concaténation (`$Qry  = 'SELECT …';` puis `$Qry .= " AND x = $y";`) — la tournure du
 * code historique — et analyse le texte de la requête au moment de l'appel.
 *
 * Cinq verdicts, dont un seul dit « à corriger ». La requête est jugée sur **son texte**
 * (premier argument de l'appel) : le tableau de paramètres qui suit n'entre pas dans le
 * verdict, sinon tout appel préparé passerait pour une concaténation.
 *   ROUGE    une valeur d'entrée (`$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER`,
 *            `$_FILES`) est présente dans la requête : c'est une injection ;
 *   VALEUR   une **valeur** est concaténée (`= '" . $x . "'`, `LIMIT " . $n`) : à
 *            corriger — un paramètre lié, ou un `(int)` pour une borne ;
 *   IDENT    un **identifiant** est concaténé (nom de colonne ou de table entre accents
 *            graves, tri `ORDER BY`) : il ne peut pas être un paramètre lié, donc une
 *            liste blanche ou une forme vérifiée doit le garantir. Les helpers du projet
 *            qui ne rendent qu'un nom ou une liste de colonnes en relèvent
 *            (`$this->botColumn()`, `$this->extraUserColumns()`, `$this->sortColumn()`, et
 *            la variable qui les reçoit) : l'instrument ne lit pas leur corps, c'est la
 *            relecture qui juge la garde — un helper qui porte des **valeurs** s'appelle
 *            `*Condition` et **lie** ses paramètres, il reste donc signalé ici ;
 *   GABARIT  la concaténation ne porte qu'une liste de `?` assemblée à l'exécution
 *            (`IN (…)` construit par `implode`, `array_fill`) ou un fragment qui porte ses
 *            propres paramètres (`[$sql, $params] = self::stateFilter(…)`) : la requête
 *            reste paramétrée ;
 *   VERT     requête littérale ou paramétrée (`?`), sans concaténation.
 *
 * Il ne remplace pas une analyse de flux : c'est une **rendu** du terrain, pour savoir
 * où regarder et mesurer ce qui recule.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Ce script s'execute en ligne de commande.\n";
    exit(1);
}

/** Fonctions et méthodes qui portent une requête. */
const APPELS_SQL = array(
    'query',
    'fetchone',
    'fetchall',
    'fetchobject',
    'preparedfetchone',
    'preparedfetchall',
    'preparedexecute',
    'preparedinsertid',
    'doquery',
    // La couche base est passée à PDO : `query()` (déjà listé plus haut) et
    // `->exec(` portent les requêtes. Les anciens noms `mysql_query`/
    // `mysqli_query`/`mysqli_prepare` ont disparu avec la migration.
    '->exec(',
);

/** Entrées extérieures : leur présence dans une requête est une injection. */
const SOURCES = array('$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER', '$_FILES', 'php://input');

/** Bornage d'une valeur concaténée : un cast, ou une fonction de nettoyage connue. */
const BORNAGE = '/\((?:int|float|bool|string)\)|\b(?:intval|floatval|doubleval|number_format|abs|max|min|count|escape|addslashes|htmlspecialchars|htmlentities|md5|sha1|sprintf|str_pad)\s*\(/i';

/**
 * Appel à un helper du projet qui ne rend qu'un nom de colonne ou une liste de colonnes.
 *
 * Ce qui est concaténé est alors un **identifiant** (`isColumnName`, liste blanche, ou colonne
 * posée par un module), jamais une valeur. La convention porte le nom du helper : ce qui porte
 * des valeurs s'appelle `*Condition` et lie ses paramètres, donc le signalement y reste.
 */
const HELPER_COLONNE = '/\$this->\w*Column[s]?\s*\(/';

/** Ordre d'affichage (et de gravité) des verdicts. */
const GRAVITE = array('ROUGE' => 0, 'VALEUR' => 1, 'IDENT' => 2, 'GABARIT' => 3);

$arguments = array_slice($argv, 1);
$strict = in_array('--strict', $arguments, true);
$strictValeurs = in_array('--strict-values', $arguments, true);
$cheminRacine = '';

foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--') === false) {
        $cheminRacine = $argument;
        break;
    }
}

$racine = rtrim(str_replace('\\', '/', $cheminRacine !== '' ? $cheminRacine : dirname(__DIR__)), '/');

/**
 * Analyse du texte d'une requête : rouge, valeur, identifiant, gabarit ou vert.
 *
 * @param array<string, string> $variables texte assemblé de chaque variable du fichier
 * @param array<string, bool>   $fragments variables qui portent un fragment **et** ses
 *                                         paramètres (`stateFilter()`)
 * @return array{verdict: string, motif: string}
 */
function analyse(string $requete, array $variables, array $fragments = array()): array
{
    foreach (SOURCES as $source) {
        if (str_contains($requete, $source)) {
            return array('verdict' => 'ROUGE', 'motif' => $source);
        }
    }

    if (preg_match('/\$[A-Za-z_]/', $requete) !== 1) {
        return array('verdict' => 'VERT', 'motif' => str_contains($requete, '?') ? 'parametree' : 'litterale');
    }

    // La requête est jugée sur ses **morceaux concaténés** : chacun est classé, et le verdict
    // est le plus grave de tous.
    $verdict = 'VERT';
    $motif = '';

    foreach (pieces($requete) as $piece) {
        $classe = classePiece($piece['texte'], $requete, $piece['debut'], $variables, $fragments);

        if ($classe === 'valeur' && $verdict !== 'VALEUR') {
            $verdict = 'VALEUR';
            $motif = 'valeur concatenee : ' . resume($piece['texte']);
            continue;
        }

        if ($classe === 'identifiant' && $verdict === 'VERT') {
            $verdict = 'IDENT';
            $motif = 'identifiant concatene : ' . resume($piece['texte']);
            continue;
        }

        if ($classe === 'gabarit' && $verdict === 'VERT') {
            $verdict = 'GABARIT';
            $motif = 'liste de ? assemblee a l\'execution : ' . resume($piece['texte']);
        }
    }

    return array('verdict' => $verdict, 'motif' => $motif !== '' ? $motif : 'concatenation');
}

/**
 * Morceaux d'une requête, séparés par ses concaténations de premier niveau.
 *
 * Les points d'une chaîne littérale (une décimale, un nom de table qualifié) coupent aussi :
 * le morceau obtenu ne porte alors aucune variable, donc il ne change pas le verdict.
 *
 * @return list<array{texte: string, debut: int}>
 */
function pieces(string $requete): array
{
    $morceaux = array();

    foreach (preg_split('/\s*\.\s*/', $requete, -1, PREG_SPLIT_OFFSET_CAPTURE) as $partie) {
        $morceaux[] = array('texte' => $partie[0], 'debut' => $partie[1]);
    }

    return $morceaux;
}

/**
 * Classe d'un morceau de requête.
 *
 * @param array<string, string> $variables
 * @param array<string, bool>   $fragments
 */
function classePiece(string $piece, string $requete, int $debut, array $variables, array $fragments): string
{
    $texte = trim($piece);

    if (str_contains($texte, '$') === false) {
        return 'litteral';
    }

    if (preg_match(BORNAGE, $texte) === 1) {
        return 'borne';
    }

    // Liste de marqueurs assemblée à l'exécution (`IN (…)` construit par `implode`) : la
    // variable porte alors elle-même des `?`, la requête reste paramétrée. On lit les deux
    // formes — la variable et son tableau (`$sets` d'un côté, `$sets[]` de l'autre).
    foreach (nomsVariables($texte) as $nom) {
        if (isset($fragments[$nom])) {
            return 'gabarit';
        }

        $assemblage = ($variables[$nom] ?? '') . ' ' . ($variables[$nom . '[]'] ?? '');

        if (str_contains($assemblage, '?') === true) {
            return 'gabarit';
        }

        // Variable remplie par un helper de colonnes : `$column = $this->botColumn()`.
        if (preg_match(HELPER_COLONNE, $assemblage) === 1) {
            return 'identifiant';
        }
    }

    if (preg_match(HELPER_COLONNE, $texte) === 1) {
        return 'identifiant';
    }

    // Entre accents graves, ou juste après un tri : c'est un identifiant (colonne, table).
    // La découpe a consommé le point de concaténation, on l'écarte avant de lire le voisin.
    $gauche = trim(substr($requete, 0, $debut));
    $droite = substr($requete, $debut + strlen($piece));
    $avant = dernierUtile($gauche);
    $apres = premierUtile($droite);

    if ($avant === '.') {
        $gauche = trim(substr($gauche, 0, -1));
        $avant = dernierUtile($gauche);
    }

    if ($apres === '.') {
        $apres = premierUtile(substr(ltrim($droite), 1));
    }

    $entreAccents = $avant === '`' || $apres === '`';
    $apresTri = preg_match('/(?:order|group)\s+by\s*$/i', $gauche) === 1;

    if ($entreAccents || $apresTri) {
        return 'identifiant';
    }

    return 'valeur';
}

/**
 * Noms de variables cités dans un morceau (`$this` mis de côté).
 *
 * @return list<string>
 */
function nomsVariables(string $texte): array
{
    preg_match_all('/\$[A-Za-z_]\w*/', $texte, $trouves);

    return array_values(array_diff(array_unique($trouves[0]), array('$this')));
}

/** Dernier caractère utile (hors blancs) d'un texte. */
function dernierUtile(string $texte): string
{
    $texte = rtrim($texte);

    return $texte === '' ? '' : substr($texte, -1);
}

/** Premier caractère utile (hors blancs) d'un texte. */
function premierUtile(string $texte): string
{
    $texte = ltrim($texte);

    return $texte === '' ? '' : $texte[0];
}

/** Texte court d'un morceau, pour le rapport. */
function resume(string $texte): string
{
    $texte = preg_replace('/\s+/', ' ', trim($texte)) ?? '';

    return strlen($texte) > 60 ? substr($texte, 0, 57) . '...' : $texte;
}

/** Jetons d'un fichier, avec leur texte et leur numéro de ligne. */
function jetons(string $source): array
{
    $liste = array();

    foreach (token_get_all($source) as $jeton) {
        if (is_array($jeton)) {
            $liste[] = array($jeton[0], $jeton[1], $jeton[2]);
            continue;
        }

        $liste[] = array(0, $jeton, 0);
    }

    return $liste;
}

/**
 * Index du prochain jeton utile (les blancs et les commentaires ne comptent pas).
 *
 * @param array<int, array{0: int, 1: string, 2: int}> $liste
 */
function suivant(array $liste, int $index): int
{
    for ($i = $index, $n = count($liste); $i < $n; $i++) {
        if (!in_array($liste[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
            return $i;
        }
    }

    return count($liste);
}

/**
 * Index du jeton utile précédent (-1 au début du fichier).
 *
 * @param array<int, array{0: int, 1: string, 2: int}> $liste
 */
function precedent(array $liste, int $index): int
{
    for ($i = $index - 1; $i >= 0; $i--) {
        if (!in_array($liste[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
            return $i;
        }
    }

    return -1;
}

/**
 * Texte d'une expression, jusqu'à un délimiteur de premier niveau.
 *
 * Deux usages : les arguments d'un appel (jusqu'à la parenthèse fermante) et la valeur
 * affectée à une variable (jusqu'au point-virgule). Une parenthèse qui se referme alors
 * qu'aucune ne l'a ouverte arrête aussi la lecture : c'est la fin du contexte.
 *
 * @param array<int, array{0: int, 1: string, 2: int}> $liste
 * @return array{0: string, 1: int} le texte, et l'index du délimiteur
 */
function collecter(array $liste, int $debut, string $fin): array
{
    $texte = '';
    $profondeur = 0;

    for ($i = $debut, $n = count($liste); $i < $n; $i++) {
        list($type, $valeur) = $liste[$i];

        if ($type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT) {
            $texte .= ' ';
            continue;
        }

        // Un appel porte plusieurs arguments — la requête, puis les paramètres liés et le nom
        // de la table : seule la **requête** nous intéresse.
        if ($valeur === ',' && $profondeur === 0) {
            return array($texte, $i);
        }

        if ($valeur === $fin && $profondeur === 0) {
            return array($texte, $i);
        }

        if ($valeur === '(') {
            $profondeur++;
        } elseif ($valeur === ')') {
            $profondeur--;

            if ($profondeur < 0) {
                return array($texte, $i);
            }
        } elseif ($valeur === ';' && $profondeur === 0) {
            return array($texte, $i);
        }

        $texte .= $type === T_CONSTANT_ENCAPSED_STRING ? trim($valeur, "'\"") : $valeur;
    }

    return array($texte, count($liste));
}

$fichiers = array();

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine)) as $fichier) {
    $chemin = str_replace('\\', '/', (string) $fichier->getPathname());

    if (!$fichier->isFile() || $fichier->getExtension() !== 'php') {
        continue;
    }

    if (preg_match('#/(vendor|\.git|\.archive|node_modules)/#', $chemin) === 1) {
        continue;
    }

    $fichiers[] = $chemin;
}

sort($fichiers);

$sites = array();
$comptes = array('ROUGE' => 0, 'VALEUR' => 0, 'IDENT' => 0, 'GABARIT' => 0, 'VERT' => 0);

foreach ($fichiers as $chemin) {
    $source = (string) file_get_contents($chemin);
    $liste = jetons($source);
    /** @var array<string, string> $variables texte assemble pour chaque variable */
    $variables = array();
    $classe = '';
    $fonction = '';

    // `[$sql, $params] = self::stateFilter(...)` : le fragment porte ses `?` et ses paramètres
    // voyagent avec lui — le dépôt le passe tel quel à `preparedFetch*`. Sans cela, chaque
    // lecture filtrée passait pour une valeur concaténée.
    $fragments = array();

    if (preg_match_all('/\[\s*(\$[A-Za-z_]\w*)\s*,\s*\$[A-Za-z_]\w*\s*\]\s*=\s*self::stateFilter\s*\(/', $source, $trouves) > 0) {
        foreach ($trouves[1] as $nom) {
            $fragments[$nom] = true;
        }
    }

    for ($i = 0, $n = count($liste); $i < $n; $i++) {
        list($type, $valeur, $ligne) = $liste[$i];

        // Endroit courant : le rapport nomme la classe et la fonction du site, sans quoi il faut
        // relire tout le fichier pour retrouver la méthode fautive.
        if (in_array($type, array(T_CLASS, T_INTERFACE, T_TRAIT), true)) {
            $suivant = suivant($liste, $i + 1);
            $classe = $liste[$suivant][1] ?? $classe;
            $fonction = '';
            continue;
        }

        if ($type === T_FUNCTION) {
            $suivant = suivant($liste, $i + 1);
            $fonction = $liste[$suivant][1] ?? '';
            continue;
        }

        // Assemblage d'une variable : `$sql = "…"` puis `$sql .= "…"` (le legacy écrit
        // ses requêtes ainsi, en plusieurs morceaux), et `$sets[] = '…'` pour une liste.
        if ($type === T_VARIABLE) {
            $egal = suivant($liste, $i + 1);
            $cle = $valeur;

            // Ajout à un tableau : `[`, la clé éventuelle, `]`, puis `=`.
            if (($liste[$egal][1] ?? '') === '[') {
                $fermante = suivant($liste, $egal + 1);
                $egal = suivant($liste, $fermante + 1);
                $cle = $valeur . '[]';
            }

            $suite = $liste[$egal][1] ?? '';

            if ($suite === '=' || $suite === '.=') {
                list($morceau) = collecter($liste, $egal + 1, '=');
                $variables[$cle] = trim($suite === '=' ? $morceau : ($variables[$cle] ?? '') . ' ' . $morceau);
                continue;
            }
        }

        // Appel de fonction (`doquery(`), de méthode (`$this->query(`) ou de méthode
        // statique (`Connection::query(`).
        $nom = null;
        $ouvrante = 0;

        if ($type === T_STRING) {
            // Un appel de méthode a déjà été relevé depuis sa variable (`$this->query(`) : ne
            // pas compter le site deux fois. Une **définition** de fonction n'est pas un appel.
            $precedent = precedent($liste, $i);
            $avant = $precedent >= 0 ? ($liste[$precedent][1] ?? '') : '';

            if (in_array($avant, array('->', '?->', 'function'), true)) {
                continue;
            }

            $nom = strtolower($valeur);
            $ouvrante = suivant($liste, $i + 1);
        } elseif ($type === T_VARIABLE) {
            $operateur = suivant($liste, $i + 1);

            if (in_array($liste[$operateur][1] ?? '', array('->', '?->'), true)) {
                $methode = suivant($liste, $operateur + 1);

                if ($liste[$methode][0] === T_STRING) {
                    $nom = strtolower($liste[$methode][1]);
                    $ligne = $liste[$methode][2];
                    $ouvrante = suivant($liste, $methode + 1);
                }
            }
        }

        if ($nom === null || !in_array($nom, APPELS_SQL, true) || ($liste[$ouvrante][1] ?? '') !== '(') {
            continue;
        }

        list($arguments) = collecter($liste, $ouvrante + 1, ')');
        $requete = trim($arguments);

        // L'argument est une simple variable : on relit ce qui y a été assemblé.
        if (preg_match('/^\$[A-Za-z_]\w*$/', $requete) === 1) {
            $requete = $variables[$requete] ?? '';
        }

        if ($requete === '' || preg_match('/\b(select|insert|update|delete|replace)\b/i', $requete) !== 1) {
            continue;
        }

        $verdict = analyse($requete, $variables, $fragments);
        $comptes[$verdict['verdict']]++;

        if ($verdict['verdict'] !== 'VERT') {
            $sites[] = array(
                'chemin' => str_replace($racine . '/', '', $chemin),
                'ligne' => $ligne,
                'endroit' => $classe !== '' ? $classe . '::' . $fonction : $fonction,
                'verdict' => $verdict['verdict'],
                'motif' => $verdict['motif'],
            );
        }
    }
}

usort($sites, static function (array $a, array $b): int {
    return array(
        GRAVITE[$a['verdict']] ?? 9,
        $a['chemin'],
        $a['ligne'],
    ) <=> array(
        GRAVITE[$b['verdict']] ?? 9,
        $b['chemin'],
        $b['ligne'],
    );
});

$analyses = array_sum($comptes);
$echecs = $comptes['ROUGE'] + ($strictValeurs ? $comptes['VALEUR'] : 0);

echo "Audit SQL — $analyses requetes analysees dans " . count($fichiers) . " fichiers\n";
echo "  ROUGE   : {$comptes['ROUGE']} (une entree — \$_GET, \$_POST… — entre dans la requete)\n";
echo "  VALEUR  : {$comptes['VALEUR']} (une valeur concatenee : le seul cas a corriger)\n";
echo "  IDENT   : {$comptes['IDENT']} (colonne ou table concatenee : liste blanche attendue)\n";
echo "  GABARIT : {$comptes['GABARIT']} (marqueurs ? ou fragment qui porte ses parametres)\n";
echo "  VERT    : {$comptes['VERT']} (litterale ou parametree)\n";

if ($sites !== array()) {
    echo "\nSites a regarder :\n";

    foreach ($sites as $site) {
        printf(
            "  %-7s %s:%d%s — %s\n",
            $site['verdict'],
            $site['chemin'],
            $site['ligne'],
            $site['endroit'] !== '' ? ' (' . $site['endroit'] . ')' : '',
            $site['motif']
        );
    }
}

if (($strict || $strictValeurs) && $echecs > 0) {
    $details = array();

    if ($comptes['ROUGE'] > 0) {
        $details[] = "{$comptes['ROUGE']} requete(s) rouge(s)";
    }

    if ($strictValeurs && $comptes['VALEUR'] > 0) {
        $details[] = "{$comptes['VALEUR']} valeur(s) concatenee(s)";
    }

    echo "\nEchec : " . implode(' et ', $details) . ".\n";
    exit(1);
}

echo "\nFin de l'audit.\n";
