<?php

declare(strict_types=1);

/**
 * Installe les modules du registre (`modules/<nom>/package.json`) en base.
 *
 * L'installateur l'appelle juste après les migrations ; sur une base déjà en
 * service, on relance la commande après avoir déployé le code :
 *
 *   docker compose exec -T app php /var/www/html/db/modules.php
 *
 * Le semis est **idempotent** (`App\Services\ModuleService::seed()`) : un module
 * déjà réglé garde son état et ses réglages, seules les lignes manquantes sont
 * créées. Les interrupteurs historiques de la table `config` (`enable_marchand`,
 * `enable_announces`) font foi au premier passage : une fonctionnalité éteinte
 * avant le registre le reste. Un module sans ligne en base est de toute façon
 * allumé, donc la commande n'est jamais obligatoire.
 */

// Jamais par le serveur web (le script écrit l'état des modules) : .htaccess le
// refuse déjà, c'est une seconde barrière.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande.\n");
}

define('INSIDE', true);
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('PHPEXT', 'php');

require ROOT_PATH . 'app/bootstrap.php';

use App\Core\Modules;
use App\Services\ModuleService;

$service = new ModuleService();
$created = $service->seed();

echo 'Modules decouverts : ' . count(Modules::names()) . "\n";

foreach ($service->all() as $name => $module) {
    echo '  - ' . str_pad((string) $name, 12) . ($module['active'] ? 'allume' : 'eteint')
        . '  (' . (string) $module['permission'] . ")\n";
}

echo 'Lignes creees : ' . $created . "\n";
