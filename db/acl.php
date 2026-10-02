<?php

declare(strict_types=1);

/**
 * Installe les rôles du système d'ACL et rattache les comptes existants.
 *
 * L'installateur l'appelle juste après les migrations ; sur une base déjà en
 * service, on relance la commande après avoir déployé le code :
 *
 *   docker compose exec -T app php /var/www/html/db/acl.php
 *
 * Le semis est **idempotent** (`App\Services\AclService::seed()`) : un rôle déjà
 * présent garde son libellé, sa description et ses permissions — seules les
 * permissions d'un rôle encore vide sont complétées — et un compte qui a déjà un
 * rôle n'est jamais retouché. Le compte d'installation (identifiant 1) est laissé
 * tel quel : il est super administrateur par définition.
 */

// Jamais par le serveur web (le script écrit des rôles) : .htaccess le refuse
// déjà, c'est une seconde barrière.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande.\n");
}

define('INSIDE', true);
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('PHPEXT', 'php');

require ROOT_PATH . 'app/bootstrap.php';

use App\Services\AclService;

$report = (new AclService())->seed();

echo 'Roles installes : ' . (int) ($report['roles'] ?? 0) . "\n";

foreach ($report as $name => $value) {
    if ($name === 'roles' || $name === 'assignes') {
        continue;
    }

    echo '  - ' . $name . ' (#' . (int) $value . ")\n";
}

echo 'Comptes rattaches a un role : ' . (int) ($report['assignes'] ?? 0) . "\n";
echo "Le compte #1 est super administrateur par definition (aucun role a poser).\n";
