<?php

declare(strict_types=1);

/**
 * Mise à jour du jeu : ce qui est disponible, et son application.
 *
 *   php tools/update-check.php                      liste les versions plus récentes
 *   php tools/update-check.php --apply v2026.32     applique celle-ci
 *
 * Le dépôt du projet est **privé** : la lecture de l'API GitHub demande un jeton, posé
 * dans `configs/.env.<env>` (`UPDATE_TOKEN`, jamais versionné). Sans jeton, la liste est
 * vide — c'est la réponse de l'API, pas une erreur du script.
 *
 * Ce qu'une mise à jour ne remplace **jamais** : `configs/`, `modules/`, `vendor/`,
 * `backups/`, `.git/`. `configs/` est sauvegardé avant, dans `backups/` ; les migrations
 * sont rejouées après. La liste des versions vient des **étiquettes** du dépôt (`v2026.30`) :
 * une étiquette est la seule chose qui donne à une version son commit, donc son archive.
 *
 * Jamais par le serveur web : `.htaccess` refuse déjà le dossier, c'est une seconde barrière.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/db/mysql.php';

use App\Core\Project;
use App\Database\Migrator;
use App\Services\UpdateService;

$arguments = array_slice($argv, 1);
$tag = '';

foreach ($arguments as $index => $argument) {
    if ($argument === '--apply') {
        $tag = (string) ($arguments[$index + 1] ?? '');
    }
}

$root = dirname(__DIR__);
$service = UpdateService::fromEnvironment();

echo 'Version installée : ', Project::version(), PHP_EOL;
echo 'Dépôt lu          : ', $service->repository(), $service->hasToken() ? ' (jeton posé)' : ' (aucun jeton)', PHP_EOL;

$available = $service->upgradable($service->fetchVersions(), Project::version());

if ($available === array()) {
    echo 'Aucune version plus récente à proposer.', PHP_EOL;
    exit(0);
}

echo 'Versions disponibles :', PHP_EOL;

foreach ($available as $entry) {
    echo '  ', $entry['tag'], '  (', $entry['version'], ')', PHP_EOL;
}

if ($tag === '') {
    echo PHP_EOL, 'Pour appliquer : php tools/update-check.php --apply <étiquette>', PHP_EOL;
    exit(0);
}

// On n'applique que ce qui vient d'être proposé : une étiquette forgée à la main
// (ou plus ancienne que celle installée) n'a rien à faire ici.
if (!in_array($tag, array_column($available, 'tag'), true)) {
    echo 'Refus : ', $tag, " n'est pas une des versions proposées ci-dessus.", PHP_EOL;
    exit(1);
}

$report = $service->apply($tag, $root, $root . '/backups/update');

if (!$report['ok']) {
    echo 'Échec de la mise à jour (', $report['error'], '). Rien n\'a été remplacé.', PHP_EOL;
    echo $report['backup'] === '' ? '' : 'Sauvegarde de configs/ : ' . $report['backup'] . PHP_EOL;
    exit(1);
}

echo PHP_EOL, 'Fichiers écrits : ', $report['files'], ' (ignorés : ', $report['skipped'], ')', PHP_EOL;

if ($report['backup'] !== '') {
    echo 'Sauvegarde de configs/ : ', $report['backup'], PHP_EOL;
}

// Le schéma suit le code : c'est le même geste que l'installateur, en ligne de commande.
$applied = (new Migrator())->run();

echo $applied === array()
    ? 'Aucune migration en attente.' . PHP_EOL
    : 'Migrations appliquées : ' . implode(', ', $applied) . PHP_EOL;

echo 'Mise à jour terminée vers ', $tag, PHP_EOL;
