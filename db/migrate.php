<?php

declare(strict_types=1);

/**
 * Migrations en ligne de commande.
 *
 *   php db/migrate.php status            état des migrations
 *   php db/migrate.php migrate           applique les migrations en attente
 *   php db/migrate.php migrate --dry-run liste ce qui serait joué, sans rien exécuter
 *   php db/migrate.php rollback [n]      liste ce qui serait annulé
 *   php db/migrate.php rollback [n] --force   exécute l'annulation
 *   php db/migrate.php baseline          marque tout comme appliqué (base existante)
 *
 * Depuis l'hôte, dans le conteneur applicatif :
 *   docker compose exec -T app php /var/www/html/db/migrate.php status
 */

// Jamais par le serveur web : .htaccess le refuse déjà, c'est une seconde barrière.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande.\n");
}

define('INSIDE', true);
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require ROOT_PATH . 'app/bootstrap.php';

use App\Database\Connection;
use App\Database\Migrator;

// Pas de chargement de includes/db.php : sans connexion legacy, Connection
// construit la sienne depuis la configuration, qui lit les variables DB_* de
// l'environnement (pratique pour viser une base de test).
$command = $argv[1] ?? 'status';
$arguments = array_slice($argv, 2);
$dryRun = in_array('--dry-run', $arguments, true);
$force = in_array('--force', $arguments, true);
$steps = 0;

foreach ($arguments as $argument) {
    if (ctype_digit($argument)) {
        $steps = (int) $argument;
    }
}

$migrator = new Migrator();

switch ($command) {
    case 'migrate':
        $done = $migrator->run($dryRun);

        if ($done === array()) {
            echo 'Aucune migration en attente.' . PHP_EOL;
            break;
        }

        foreach ($done as $name) {
            echo ($dryRun ? 'a appliquer : ' : 'application : ') . $name . PHP_EOL;
        }

        foreach ($migrator->tolerated() as $ignored) {
            echo 'ignore (deja applique) : ' . $ignored . PHP_EOL;
        }

        break;

    case 'rollback':
        try {
            $targets = $migrator->rollback($steps > 0 ? $steps : 1, $force);
        } catch (RuntimeException $exception) {
            echo 'annulation impossible : ' . $exception->getMessage() . PHP_EOL;
            exit(1);
        }

        foreach ($targets as $name) {
            echo ($force ? 'annulee : ' : 'a annuler : ') . $name . PHP_EOL;
        }

        if (!$force) {
            echo 'Relancer avec --force pour executer (les donnees des tables supprimees sont perdues).' . PHP_EOL;
        }

        break;

    case 'baseline':
        $marked = $migrator->baseline();

        foreach ($marked as $name) {
            echo 'marquee comme appliquee : ' . $name . PHP_EOL;
        }

        if ($marked === array()) {
            echo 'Rien a marquer.' . PHP_EOL;
        }

        break;

    case 'status':
        $applied = $migrator->applied();

        foreach ($migrator->available() as $name => $file) {
            echo (in_array($name, $applied, true) ? '[x] ' : '[ ] ') . $name . PHP_EOL;
        }

        echo 'base : ' . Connection::table(Migrator::TABLE) . ' — prefixe « ' . Connection::prefix() . ' »' . PHP_EOL;
        break;

    default:
        echo 'Usage : php db/migrate.php status|migrate [--dry-run]|rollback [n] [--force]|baseline' . PHP_EOL;
        exit(1);
}
