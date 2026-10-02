<?php

declare(strict_types=1);

/**
 * Audit des doublons : fonctions et méthodes PHP, fonctions JS.
 *
 * Le dépôt tient la règle « une règle = une implémentation » ; ce script la rend
 * vérifiable. Il relève deux choses :
 *
 *  1. les **corps identiques** — le même code écrit deux fois (copie-colle). Les
 *     commentaires, les blancs et l'indentation sont ignorés : deux copies qui ne
 *     diffèrent que par la mise en forme sont bien signalées.
 *  2. les **noms homonymes** — une fonction globale déclarée dans deux fichiers PHP
 *     (chargés ensemble, la seconde est une erreur fatale) ou une fonction JS
 *     globale définie deux fois (la seconde écrase silencieusement la première).
 *
 * Usage :
 *   php tools/audit-doublons.php [racine] [seuil]
 *
 * La racine vaut le dépôt par défaut et le seuil 160 caractères : en dessous, deux
 * accesseurs qui se ressemblent ne sont pas un doublon. Un corps plus court n'est
 * donc pas signalé.
 *
 * Code de sortie : 1 si un doublon a été trouvé, 0 sinon — de quoi l'enchaîner
 * dans une vérification.
 */

// Jamais par le serveur web : .htaccess refuse déjà le dossier, c'est une seconde
// barrière.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'execute en ligne de commande.\n");
}

$racine = rtrim(str_replace('\\', '/', $argv[1] ?? dirname(__DIR__)), '/');
$seuil = (int) ($argv[2] ?? 160);

if (!is_dir($racine)) {
    fwrite(STDERR, 'Dossier introuvable : ' . $racine . PHP_EOL);
    exit(2);
}

// Dépendances tierces, archives, sauvegardes et tests : hors du champ (le code des
// modules, lui, est audité — c'est là que les copies reviennent).
const DOSSIERS_EXCLUS = array('/vendor/', '/node_modules/', '/.git/', '/.archive/', '/backups/', '/tests/');

/** Fichiers d'une extension donnée, sous la racine, hors dossiers exclus. */
function fichiers(string $racine, string $extension): array
{
    $liste = array();
    $parcours = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS));

    foreach ($parcours as $fichier) {
        if (!$fichier->isFile()) {
            continue;
        }

        $chemin = str_replace('\\', '/', $fichier->getPathname());

        if (!str_ends_with($chemin, $extension)) {
            continue;
        }

        foreach (DOSSIERS_EXCLUS as $exclu) {
            if (str_contains($chemin, $exclu)) {
                continue 2;
            }
        }

        $liste[] = $chemin;
    }

    sort($liste);

    return $liste;
}

/**
 * Corps normalisé : les jetons utiles collés, sans blanc ni commentaire.
 *
 * @param list<array{0: int, 1: string, 2: int}|string> $jetons
 */
function normaliser(array $jetons): string
{
    $texte = '';

    foreach ($jetons as $jeton) {
        if (is_array($jeton)) {
            if (in_array($jeton[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }

            $texte .= $jeton[1];
        } else {
            $texte .= $jeton;
        }
    }

    return $texte;
}

/**
 * Fonctions et méthodes PHP : nom, corps normalisé, fichier, ligne.
 *
 * @return list<array{nom: string, corps: string, fichier: string, ligne: int, contexte: string}>
 */
function fonctionsPhp(string $racine): array
{
    $trouvees = array();

    foreach (fichiers($racine, '.php') as $chemin) {
        $jetons = token_get_all((string) file_get_contents($chemin));
        $total = count($jetons);
        $classe = '';

        for ($i = 0; $i < $total; $i++) {
            $jeton = $jetons[$i];

            if (!is_array($jeton)) {
                continue;
            }

            if (in_array($jeton[0], array(T_CLASS, T_TRAIT, T_INTERFACE), true)) {
                for ($j = $i + 1; $j < $total; $j++) {
                    if (is_array($jetons[$j]) && $jetons[$j][0] === T_STRING) {
                        $classe = $jetons[$j][1];
                        break;
                    }

                    if ($jetons[$j] === '{') {
                        break;
                    }
                }
            }

            if ($jeton[0] !== T_FUNCTION) {
                continue;
            }

            // Nom de la fonction ou de la méthode : absent pour une fermeture, qui
            // n'est donc pas auditable ici.
            $k = $i + 1;

            while ($k < $total && is_array($jetons[$k]) && $jetons[$k][0] === T_WHITESPACE) {
                $k++;
            }

            if ($k >= $total || !is_array($jetons[$k]) || $jetons[$k][0] !== T_STRING) {
                continue;
            }

            // Corps : de la première accolade à la sienne, fermante.
            $imbriquees = 0;
            $debut = -1;

            for ($j = $k; $j < $total; $j++) {
                if ($jetons[$j] === '{') {
                    if ($imbriquees === 0) {
                        $debut = $j + 1;
                    }

                    $imbriquees++;
                } elseif ($jetons[$j] === '}') {
                    $imbriquees--;

                    if ($imbriquees === 0) {
                        $trouvees[] = array(
                            'nom' => $jetons[$k][1],
                            'corps' => normaliser(array_slice($jetons, $debut, $j - $debut)),
                            'fichier' => substr($chemin, strlen($racine) + 1),
                            'ligne' => $jeton[2],
                            'contexte' => $classe !== '' ? $classe : '(global)',
                        );
                        $i = $j;
                        break;
                    }
                }
            }
        }
    }

    return $trouvees;
}

/**
 * Fonctions JS **globales** : une définition indentée vit dans une fermeture
 * (`(function () { … })()`), donc elle est locale — la signaler comme homonyme
 * serait un faux positif.
 *
 * @return array<string, list<array{fichier: string, ligne: int}>>
 */
function fonctionsJs(string $racine): array
{
    $motifs = array(
        '/^function\s+([A-Za-z_$][\w$]*)\s*\(/m',
        '/^(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*function\s*\(/m',
        '/^(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*(?:\([^)]*\)|[A-Za-z_$][\w$]*)\s*=>/m',
    );
    $trouvees = array();

    foreach (fichiers($racine, '.js') as $chemin) {
        $source = (string) file_get_contents($chemin);
        $relatif = substr($chemin, strlen($racine) + 1);

        foreach ($motifs as $motif) {
            if (!preg_match_all($motif, $source, $trouveesLigne, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($trouveesLigne[1] as $index => $capture) {
                $decalage = $trouveesLigne[0][$index][1];
                $ligne = 1 + substr_count(substr($source, 0, $decalage), "\n");
                $trouvees[$capture[0]][] = array('fichier' => $relatif, 'ligne' => $ligne);
            }
        }
    }

    return $trouvees;
}

$doublons = 0;

// ---------------------------------------------------------------- PHP
$fonctions = fonctionsPhp($racine);
$parCorps = array();

foreach ($fonctions as $fonction) {
    if (strlen($fonction['corps']) >= $seuil) {
        $parCorps[md5($fonction['corps'])][] = $fonction;
    }
}

$groupes = array_values(array_filter($parCorps, static fn (array $groupe): bool => count($groupe) > 1));
usort($groupes, static fn (array $a, array $b): int => strlen($b[0]['corps']) <=> strlen($a[0]['corps']));

echo '===== PHP : ' . count($fonctions) . ' fonctions et methodes analysees =====' . PHP_EOL;
echo '--- corps identiques (' . count($groupes) . ' groupe(s) de ' . $seuil . ' caracteres et plus) ---' . PHP_EOL;

foreach ($groupes as $groupe) {
    $doublons++;
    $noms = array_unique(array_map(
        static fn (array $item): string => $item['contexte'] . '::' . $item['nom'],
        $groupe
    ));
    echo ' * ' . strlen($groupe[0]['corps']) . ' caracteres x ' . count($groupe) . ' : ' . implode(' | ', $noms) . PHP_EOL;

    foreach ($groupe as $item) {
        echo '     ' . $item['fichier'] . ':' . $item['ligne'] . PHP_EOL;
    }
}

$globaux = array();

foreach ($fonctions as $fonction) {
    if ($fonction['contexte'] === '(global)') {
        $globaux[$fonction['nom']][] = $fonction['fichier'];
    }
}

echo '--- fonctions globales declarees dans plusieurs fichiers ---' . PHP_EOL;

foreach ($globaux as $nom => $ou) {
    $ou = array_unique($ou);
    sort($ou);

    if (count($ou) > 1) {
        $doublons++;
        echo ' * ' . $nom . ' : ' . implode(', ', $ou) . PHP_EOL;
    }
}

// ---------------------------------------------------------------- JS
$js = fonctionsJs($racine);

echo '===== JS : ' . count($js) . ' fonctions globales analysees =====' . PHP_EOL;
echo '--- noms definis dans plusieurs fichiers ---' . PHP_EOL;

foreach ($js as $nom => $definitions) {
    $fichiers = array_unique(array_column($definitions, 'fichier'));

    if (count($fichiers) > 1) {
        $doublons++;
        echo ' * ' . $nom . ' : ' . implode(', ', $fichiers) . PHP_EOL;
    }
}

echo PHP_EOL . ($doublons === 0
    ? 'Aucun doublon signale.' . PHP_EOL
    : $doublons . ' doublon(s) a examiner.' . PHP_EOL);

exit($doublons === 0 ? 0 : 1);
