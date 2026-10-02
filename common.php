<?php
/**
 * This file is part of XNova:Legacies
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

session_start();
// Les fichiers de configuration vivent dans `configs/` : le Coeur d'application `includes/env.php`
// ne dépend d'aucune classe, il est donc lisible avant l'autoload du jeu.
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'env.php';
$appEnvironment = strtolower((string) getenv('APP_ENV'));
// `review` (branche en revue) est un poste jetable comme `local` : les erreurs doivent y
// etre visibles, c'est la raison d'etre de cet environnement.
$debugEnabled = in_array($appEnvironment, array('local', 'dev', 'review'), true)
    || xnova_env('CONFIG_DEBUG', '0') === '1';

error_reporting($debugEnabled ? (E_ALL & ~E_WARNING & ~E_DEPRECATED & ~E_NOTICE) : 0);
ini_set('display_errors', $debugEnabled ? '1' : '0');
ini_set('display_startup_errors', $debugEnabled ? '1' : '0');

define('ROOT_PATH', realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
define('PHPEXT', require 'extension.inc');

// Coeur d'application applicatif MVC (autoload des classes App\)
require_once ROOT_PATH . 'app/bootstrap.php';

// La version du jeu vit dans le manifeste du projet (`package.json`, racine du
// depot), lue par `App\Core\Project::version()` : plus de constante a tenir a
// jour en double. Elle reste egale a la plus recente entree de
// `$lang['changelog']` (language/fr/changelog.mo), et `ProjectTest` le verifie.

if (0 === filesize(ROOT_PATH . 'configs/config.php') /*&& !defined('IN_INSTALL')*/) {
    header('Location: install/');
    die();
}

$game_config   = array();
$user          = array();
$lang          = array();
$IsUserChecked = false;

define('DEFAULT_SKINPATH', '/public/xnova/');
define('TEMPLATE_DIR', realpath(ROOT_PATH . '/app/View/'));
define('TEMPLATE_NAME', 'OpenGame');
define('DEFAULT_LANG', 'fr');

include(ROOT_PATH . 'includes/debug.class.'.PHPEXT);
$debug = new Debug();

include(ROOT_PATH . 'includes/constants.' . PHPEXT);
include(ROOT_PATH . 'includes/functions.' . PHPEXT);
include(ROOT_PATH . 'includes/todofleetcontrol.' . PHPEXT);
include(ROOT_PATH . 'language/' . DEFAULT_LANG . '/lang_info.cfg');
include(ROOT_PATH . 'includes/db.' . PHPEXT);

// Données de jeu (resource/pricelist/reslist/CombatCaps/ProdGrid/messfields)
// et configuration : chargées via les classes Core puis exposées en globals
// pour compatibilité avec le code legacy non migré.
App\Core\GameData::load();
$game_config = App\Core\GameConfig::load();
$lang = App\Core\Language::all();

if (!defined('DISABLE_IDENTITY_CHECK')) {
    $Result        = CheckTheUser ( $IsUserChecked );
    $IsUserChecked = $Result['state'];
    $user          = $Result['record'];
} else if (!defined('DISABLE_IDENTITY_CHECK') && $game_config['game_disable'] && $user['authlevel'] < 1) {
    message(stripslashes($game_config['close_reason']), $game_config['game_name']);
}

includeLang('system');
includeLang('tech');

if (empty($user) && !defined('DISABLE_IDENTITY_CHECK')) {
    // Requête API sans session valide : réponse JSON plutôt que redirection HTML,
    // afin que le client puisse réagir (rechargement, message d'erreur).
    if (defined('API_REQUEST')) {
        App\Core\Api\ApiException::unauthenticated()->toResponse()->send();
        exit(0);
    }

    // Adresse **absolue** : une redirection relative (`login.php`) était résolue par
    // le navigateur contre le dossier courant, donc `/game/login.php` — une adresse
    // qui n'existe pas — dès qu'on arrivait d'une page profonde (vécu après une
    // déconnexion).
    header('Location: /front/login', true, 302);
    exit(0);
}

if (defined('DISABLE_IDENTITY_CHECK')) {
    $dpath = DEFAULT_SKINPATH;
    return;
}

// Déclenchement du traitement des flottes : deux passes comme le legacy
// (départs puis arrivées), requêtes préparées via Connection.
$fleetQueryStart = App\Database\Connection::preparedFetchAll(
    "SELECT * FROM {{table}} WHERE fleet_start_time <= UNIX_TIMESTAMP() AND (`flags` & ?) = 0",
    array((string) App\Core\Flags::DELETED),
    'fleets'
);
foreach ($fleetQueryStart as $row) {
    $array = array(
        'galaxy' => $row['fleet_start_galaxy'],
        'system' => $row['fleet_start_system'],
        'planet' => $row['fleet_start_planet'],
        'planet_type' => $row['fleet_start_type'],
    );
    $temp = FlyingFleetHandler($array);
}

$fleetQueryEnd = App\Database\Connection::preparedFetchAll(
    "SELECT * FROM {{table}} WHERE fleet_end_time <= UNIX_TIMESTAMP() AND (`flags` & ?) = 0",
    array((string) App\Core\Flags::DELETED),
    'fleets'
);
foreach ($fleetQueryEnd as $row) {
    $array = array(
        'galaxy' => $row['fleet_end_galaxy'],
        'system' => $row['fleet_end_system'],
        'planet' => $row['fleet_end_planet'],
        'planet_type' => $row['fleet_end_type'],
    );
    $temp = FlyingFleetHandler($array);
}
unset($fleetQueryStart, $fleetQueryEnd);

if (!defined('IN_ADMIN')) {
    $dpath = (isset($user['dpath']) && !empty($user["dpath"])) ? $user['dpath'] : DEFAULT_SKINPATH;
} else {
    // L'administration impose le skin par défaut, en chemin absolu : les pages
    // modernes vivent à une profondeur d'URL variable (`/back/userlist/delete`),
    // où un `../` calculé pour `/admin/xxx.php` ne résout plus au bon dossier.
    $dpath = DEFAULT_SKINPATH;
}


SetSelectedPlanet($user);

$planetrow = App\Database\Connection::preparedFetchOne(
    "SELECT * FROM {{table}} WHERE id = ?",
    array($user['current_planet']),
    'planets'
);
// La ligne galaxie se cherche par coordonnées : la planète et sa lune partagent
// la même position (le `id_luna` de la ligne porte la lune). Une recherche par
// `id_planet` ne trouvait rien pour une lune, et les pages typées `array`
// plantaient alors sur `false`.
$galaxyrow = App\Database\Connection::preparedFetchOne(
    "SELECT * FROM {{table}} WHERE galaxy = ? AND `system` = ? AND planet = ?",
    array($planetrow['galaxy'] ?? 0, $planetrow['system'] ?? 0, $planetrow['planet'] ?? 0),
    'galaxy'
);
$galaxyrow = is_array($galaxyrow) ? $galaxyrow : array();

// Normalisation : remplir les champs de bâtiments/technos/ressources absents
// avec 0 (PHP < 8 silenciait les clés absentes ; PHP 8 génère un warning).
$_gRes = App\Core\GameData::resource();
foreach ($_gRes as $_gid => $_gfield) {
    if (is_array($planetrow) && !isset($planetrow[$_gfield])) {
        $planetrow[$_gfield] = 0;
    }
    if (is_array($galaxyrow) && !isset($galaxyrow[$_gfield])) {
        $galaxyrow[$_gfield] = 0;
    }
    if (is_array($user) && !isset($user[$_gfield]) && $_gid >= 106) {
        $user[$_gfield] = 0;
    }
}
foreach (array('metal_perhour', 'crystal_perhour', 'deuterium_perhour', 'energy_used', 'energy_max', 'metal_max', 'crystal_max', 'deuterium_max', 'b_building', 'b_building_id', 'b_hangar', 'b_hangar_id', 'b_tech', 'b_tech_id', 'b_tech_planet', 'field_current', 'field_max', 'last_update', 'destruyed', 'interplanetary_misil', 'interceptor_misil', 'silo', 'jump_gate', 'phalanx') as $_gcalc) {
    if (is_array($planetrow) && !isset($planetrow[$_gcalc])) {
        $planetrow[$_gcalc] = 0;
    }
    if (is_array($galaxyrow) && !isset($galaxyrow[$_gcalc])) {
        $galaxyrow[$_gcalc] = 0;
    }
}
unset($_gRes, $_gid, $_gfield, $_gcalc);

CheckPlanetUsedFields($planetrow);

// Les robots autonomes jouent leur tour à l'affichage d'une page : le branchement
// est dans AbstractController::renderPage() (les routes API et l'installation sont
// laissées de côté).
