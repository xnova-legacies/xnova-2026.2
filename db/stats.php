<?php

declare(strict_types=1);

/**
 * Recalcule le classement des joueurs et des alliances.
 *
 * Reprend le travail de `admin/statbuilder.php` : la page n'était ouverte que
 * lorsqu'un administrateur y pensait, et elle bloquait sa requête pendant tout le
 * calcul. Ici, la tâche se planifie (cron) :
 *
 *   docker compose exec -T app php /var/www/html/db/stats.php
 *
 * Les formules sont celles du jeu (`App\Services\StatsService`), rien de plus.
 */

// Jamais par le serveur web (le script réécrit tout le classement) : .htaccess le
// refuse déjà, c'est une seconde barrière.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande.\n");
}

define('INSIDE', true);
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('PHPEXT', 'php');

require ROOT_PATH . 'app/bootstrap.php';

use App\Core\GameConfig;
use App\Core\GameData;
use App\Services\StatsService;

// Coeur d'application legacy, comme common.php : les tables de données du jeu (prix, listes
// d'éléments) doivent être recopiées dans les globales avant tout calcul.
require_once ROOT_PATH . 'includes/constants.php';
require_once ROOT_PATH . 'includes/functions.php';
require_once ROOT_PATH . 'includes/db.php';

GameData::load();
$GLOBALS['game_config'] = GameConfig::load();

$result = (new StatsService())->rebuild();

echo 'Classement recalcule : '
    . $result['players'] . ' joueur(s), '
    . $result['alliances'] . ' alliance(s).' . PHP_EOL;
