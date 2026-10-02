<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

// Les fichiers legacy (includes/vars.php, app/Core/Legacy/*.php) sont protégés par
// les constantes INSIDE / ROOT_PATH / PHPEXT. On reproduit l'environnement attendu
// pour pouvoir les charger hors requête HTTP (voir includes/todofleetcontrol.php).
if (!defined('INSIDE')) {
    define('INSIDE', true);
}
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', APP_ROOT . DIRECTORY_SEPARATOR);
}
if (!defined('PHPEXT')) {
    define('PHPEXT', 'php');
}

// Fonctions legacy réparties par domaine, chargées comme en production.
require_once APP_ROOT . '/app/Core/Legacy/UserFunctions.php';
require_once APP_ROOT . '/app/Core/Legacy/BuildFunctions.php';
require_once APP_ROOT . '/app/Core/Legacy/GalaxyFunctions.php';
require_once APP_ROOT . '/app/Core/Legacy/MiscFunctions.php';
require_once APP_ROOT . '/app/Core/Legacy/FormatFunctions.php';
require_once APP_ROOT . '/app/Core/Legacy/PageFunctions.php';

// Coeur d'application legacy global (CalculateMaxPlanetFields, renderDisplay, helpers Bootstrap...),
// chargé comme le fait common.php.
require_once APP_ROOT . '/includes/functions.php';
