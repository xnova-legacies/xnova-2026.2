<?php

/**
 * Tis file is part of XNova:Legacies
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

define('INSIDE', true);
define('INSTALL', false);
define('IN_INSTALL', true);

define('ROOT_PATH', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
define('PHPEXT', include ROOT_PATH . 'extension.inc');
// index.php peut deja avoir charge le fichier (barre de debug) : include_once
// evite une seconde execution des declarations.
include_once(ROOT_PATH . 'includes/env.' . PHPEXT);

// Rejouer l'installateur écrase l'univers (schéma, comptes, réglages) : en
// production, sur une instance déjà installée, il reste fermé. En local ou en
// développement, la procédure de réinstallation documentée continue de marcher.
if (
    strtolower((string) xnova_env('APP_ENV', 'local')) === 'prod'
    && @filesize(ROOT_PATH . 'configs/config.php') > 0
) {
    http_response_code(403);
    exit("Installation désactivée : le jeu est déjà installé (APP_ENV=prod).\n");
}

define('DEFAULT_SKINPATH', '../public/xnova/');
define('TEMPLATE_DIR', realpath(ROOT_PATH . '/app/View/'));
define('TEMPLATE_NAME', 'OpenGame');
define('DEFAULT_LANG', 'fr');
$dpath = DEFAULT_SKINPATH;

include(ROOT_PATH . 'includes/debug.class.' . PHPEXT);
$debug = new debug();

include(ROOT_PATH . 'includes/constants.' . PHPEXT);
include(ROOT_PATH . 'includes/functions.' . PHPEXT);
include(ROOT_PATH . 'includes/todofleetcontrol.' . PHPEXT);
include(ROOT_PATH . 'language/' . DEFAULT_LANG . '/lang_info.cfg');
include(ROOT_PATH . 'includes/db.' . PHPEXT);

// Migrations : le schéma n'est plus inclus en globals, il est joué par l'exécuteur.
require_once(ROOT_PATH . 'app/bootstrap.php');

// Tables de jeu (ex includes/vars.php) : désormais portées par App\Core\GameTables.
\App\Core\GameData::load();

$mode     = isset($_GET['mode']) ? strval($_GET['mode']) : 'intro';
$page     = isset($_GET['page']) ? intval($_GET['page']) : 1;
$nextPage = $page + 1;

$mainTpl = gettemplate('install/ins_body');
includeLang('install/install');

switch ($mode) {
    case 'intro':
        $subTpl = gettemplate('install/ins_intro');
        $bloc = $lang;
        $bloc['dpath'] = $dpath;
        $frame  = parsetemplate($subTpl, $bloc);
        break;

    case 'ins':
        if ($page == 1) {
            // Ce que le serveur doit avoir **avant** qu'on parle de base de donnees :
            // une installation hors Docker n'a rien de tout cela par defaut.
            $requirements = (new \App\Core\Requirements())->all(dirname(__DIR__));
            $rowTemplate  = gettemplate('install/ins_check_row');
            $rows         = '';
            $missing      = array();

            foreach ($requirements as $requirement) {
                if (!$requirement['ok']) {
                    $missing[] = $requirement['detail'];
                }

                $rows .= parsetemplate($rowTemplate, array(
                    'ins_check_label' => (string) ($lang[$requirement['key']] ?? $requirement['key']),
                    'ins_check_state' => (string) ($lang[$requirement['ok'] ? 'ins_chk_ok' : 'ins_chk_ko'] ?? ''),
                    'ins_check_detail' => htmlspecialchars($requirement['detail'], ENT_QUOTES, 'UTF-8'),
                ));
            }

            $subTpl = gettemplate('install/ins_check');
            $bloc   = $lang;
            $bloc['dpath'] = $dpath;
            $bloc['ins_check_rows'] = $rows;
            $bloc['ins_check_ko_class'] = $missing === array() ? ' d-none' : '';
            $bloc['ins_check_next_class'] = $missing === array() ? '' : ' d-none';
            $frame  = parsetemplate($subTpl, $bloc);
        } elseif ($page == 2) {
            if (isset($_GET['error']) && intval($_GET['error']) == 1) {
                adminMessage($lang['ins_error1'], $lang['ins_error']);
            } elseif (isset($_GET['error']) && intval($_GET['error']) == 2) {
                adminMessage($lang['ins_error2'], $lang['ins_error']);
            }

            $subTpl = gettemplate('install/ins_form');
            $bloc   = $lang;
            $bloc['dpath'] = $dpath;
            $bloc['ins_db_host'] = htmlspecialchars(xnova_env('DB_HOST', 'localhost'), ENT_QUOTES, 'UTF-8');
            $bloc['ins_db_name'] = htmlspecialchars(xnova_env('DB_NAME', ''), ENT_QUOTES, 'UTF-8');
            $bloc['ins_db_prefix'] = htmlspecialchars(xnova_env('DB_PREFIX', 'game_'), ENT_QUOTES, 'UTF-8');
            $bloc['ins_db_user'] = htmlspecialchars(xnova_env('DB_USER', ''), ENT_QUOTES, 'UTF-8');
            $bloc['ins_db_password'] = htmlspecialchars(xnova_env('DB_PASSWORD', ''), ENT_QUOTES, 'UTF-8');
            $frame  = parsetemplate($subTpl, $bloc);
        } elseif ($page == 3) {
            $host   = $_POST['host'];
            $user   = $_POST['user'];
            $pass   = $_POST['password'];
            $prefix = $_POST['prefix'];
            $db     = $_POST['db'];

            try {
                // La connexion passe par le Coeur d'application : un seul créateur de
                // connexion, donc un seul jeu de caractères annoncé à MySQL.
                $connection = \App\Database\Connection::open($host, $user, $pass, $db);
            } catch (\PDOException $exception) {
                $connection = null;
            }

            if ($connection === null) {
                header("Location: ?mode=ins&page=2&error=1");
                exit();
            }

            $dz = fopen(dirname(__DIR__) . "/configs/config.php", "w");
            if (!$dz) {
                header("Location: ?mode=ins&page=2&error=2");
                exit();
            }
            $fileData = <<<EOF
<?php return array(
    'global' => array(
        'database' => array(
            'engine' => 'mysql',
            'options' => array(
                'hostname' => '{$host}',
                'username' => '{$user}',
                'password' => '{$pass}',
                'database' => '{$db}'
                ),
            'table_prefix' => '{$prefix}',
            )
        )
    );
EOF;
            fwrite($dz, $fileData);
            fclose($dz);

            // Schéma initial puis mises à niveau, dans l'ordre des fichiers.
            (new \App\Database\Migrator())->run();

            // Réglages : l'environnement n'a la main qu'ici (les variables
            // `CONFIG_<NOM>` écrasent alors les valeurs par défaut) ; une fois le
            // jeu démarré, c'est la table `config` qui fait foi.
            \App\Core\GameConfig::seed(
                \App\Core\ConfigDefaults::values(),
                \App\Core\ConfigDefaults::overrides()
            );

            // Rôles du panneau : les quatre rôles par défaut, leurs permissions (le
            // catalogue d'`App\Core\Acl`) et le rattachement des comptes créés par
            // l'installateur à celui de leur niveau. Idempotent : relancer
            // `php db/acl.php` sur une base en service ne réécrit rien.
            \App\Services\AclService::seedDefaults();
            \App\Services\ModuleService::seedDefaults();
            $subTpl = gettemplate('install/ins_form_done');
            $bloc   = $lang;
            $bloc['dpath']        = $dpath;
            $frame  = parsetemplate($subTpl, $bloc);
        } elseif ($page == 4) {
            if (isset($_GET['error']) && intval($_GET['error']) == 3) {
                adminMessage($lang['ins_error3'], $lang['ins_error']);
            }

            $subTpl = gettemplate('install/ins_acc');
            $bloc   = $lang;
            $bloc['dpath']        = $dpath;
            $bloc['ins_admin_user'] = htmlspecialchars(xnova_env('INSTALL_ADMIN_USER', ''), ENT_QUOTES, 'UTF-8');
            $bloc['ins_admin_password'] = htmlspecialchars(xnova_env('INSTALL_ADMIN_PASSWORD', ''), ENT_QUOTES, 'UTF-8');
            $bloc['ins_admin_email'] = htmlspecialchars(xnova_env('INSTALL_ADMIN_EMAIL', ''), ENT_QUOTES, 'UTF-8');
            $bloc['ins_admin_planet'] = htmlspecialchars(xnova_env('INSTALL_ADMIN_PLANET', ''), ENT_QUOTES, 'UTF-8');
            $frame  = parsetemplate($subTpl, $bloc);
        } elseif ($page == 5) {
            $adm_user   = $_POST['adm_user'];
            $adm_pass   = $_POST['adm_pass'];
            $adm_email  = $_POST['adm_email'];
            $adm_planet = $_POST['adm_planet'];
            $adm_sex    = $_POST['adm_sex'];
            $md5pass    = md5($adm_pass);

            if (!isset($_POST['adm_user'])) {
                header("Location: ?mode=ins&page=4&error=3");
                exit();
            }
            if (!isset($_POST['adm_pass'])) {
                header("Location: ?mode=ins&page=4&error=3");
                exit();
            }
            if (!isset($_POST['adm_email'])) {
                header("Location: ?mode=ins&page=4&error=3");
                exit();
            }
            if (!isset($_POST['adm_planet'])) {
                header("Location: ?mode=ins&page=4&error=3");
                exit();
            }

            $config = include(ROOT_PATH . 'configs/config.php');
            $db_host   = $config['global']['database']['options']['hostname'];
            $db_user   = $config['global']['database']['options']['username'];
            $db_pass   = $config['global']['database']['options']['password'];
            $db_db     = $config['global']['database']['options']['database'];
            $db_prefix = $config['global']['database']['table_prefix'];

            $connection = @mysql_connect($db_host, $db_user, $db_pass);
            if (!$connection) {
                header("Location: ?mode=ins&page=2&error=1");
                exit();
            }

            $dbselect = @mysql_select_db($db_db);
            if (!$dbselect) {
                header("Location: ?mode=ins&page=2&error=1");
                exit();
            }

            // Taille de la planète mère : la même règle que les colonies
            // (PlanetSizeRandomiser), alimentée par `initial_fields` de la table
            // `config` — donc par la variable d'environnement à l'installation.
            $GLOBALS['game_config'] = \App\Core\GameConfig::load();
            $homeworld = PlanetSizeRandomiser(1, true);

            // Un tableau colonne => valeur plutôt qu'une concaténation : les
            // trois champs du formulaire (`$adm_user`, `$adm_email`, `$adm_sex`)
            // partent en paramètres, jamais dans le texte de la requête.
            $admValues = array(
                'id'             => 1,
                'username'       => $adm_user,
                'email'          => $adm_email,
                'email_2'        => $adm_email,
                'authlevel'      => 3,
                'sex'            => $adm_sex,
                'id_planet'      => 1,
                'galaxy'         => 1,
                'system'         => 1,
                'planet'         => 1,
                'current_planet' => 1,
                'register_time'  => time(),
                'user_agent'     => '',
                'current_page'   => '',
                'menu_alliance'   => 0,
                'menu_player'     => 0,
                'menu_attaque'    => 0,
                'menu_spy'        => 0,
                'menu_exploit'    => 0,
                'menu_transport'  => 0,
                'menu_expedition' => 0,
                'menu_general'    => 0,
                'menu_buildlist'  => 0,
                'password'       => $md5pass,
            );

            \App\Database\Connection::preparedExecute(
                // Colonnes en clair, dans l'ordre du tableau ci-dessus.
                'INSERT INTO {{table}} (id, username, email, email_2, authlevel, sex, id_planet, galaxy, system, planet, current_planet, register_time, user_agent, current_page, menu_alliance, menu_player, menu_attaque, menu_spy, menu_exploit, menu_transport, menu_expedition, menu_general, menu_buildlist, password)'
                    . ' VALUES (' . implode(', ', array_fill(0, count($admValues), '?')) . ')',
                array_values($admValues),
                'users'
            );

            $admPlanetValues = array(
                'name'              => $adm_planet,
                'id_owner'          => 1,
                'galaxy'            => 1,
                'system'            => 1,
                'planet'            => 1,
                'last_update'       => time(),
                'planet_type'       => 1,
                'b_building_id'     => '',
                'b_hangar_id'       => '',
                'image'             => 'normaltempplanet02',
                'diameter'          => (int) $homeworld['diameter'],
                'field_max'         => (int) $homeworld['field_max'],
                'temp_min'          => 47,
                'temp_max'          => 87,
                'metal'             => 500,
                'metal_perhour'     => 0,
                'metal_max'         => 1000000,
                'crystal'           => 500,
                'crystal_perhour'   => 0,
                'crystal_max'       => 1000000,
                'deuterium'         => 500,
                'deuterium_perhour' => 0,
                'deuterium_max'     => 1000000,
            );

            \App\Database\Connection::preparedExecute(
                // Colonnes en clair, dans l'ordre du tableau ci-dessus.
                'INSERT INTO {{table}} (name, id_owner, galaxy, system, planet, last_update, planet_type, b_building_id, b_hangar_id, image, diameter, field_max, temp_min, temp_max, metal, metal_perhour, metal_max, crystal, crystal_perhour, crystal_max, deuterium, deuterium_perhour, deuterium_max)'
                    . ' VALUES (' . implode(', ', array_fill(0, count($admPlanetValues), '?')) . ')',
                array_values($admPlanetValues),
                'planets'
            );

            $QryAddAdmGlx  = "INSERT INTO {{table}} SET ";
            $QryAddAdmGlx .= "`galaxy`            = '1', ";
            $QryAddAdmGlx .= "`system`            = '1', ";
            $QryAddAdmGlx .= "`planet`            = '1', ";
            $QryAddAdmGlx .= "`id_planet`         = '1'; ";
            doquery($QryAddAdmGlx, 'galaxy');

            doquery("UPDATE {{table}} SET `config_value` = '1' WHERE `config_name` = 'LastSettedGalaxyPos';", 'config');
            doquery("UPDATE {{table}} SET `config_value` = '1' WHERE `config_name` = 'LastSettedSystemPos';", 'config');
            doquery("UPDATE {{table}} SET `config_value` = '1' WHERE `config_name` = 'LastSettedPlanetPos';", 'config');
            doquery("UPDATE {{table}} SET `config_value` = `config_value` + '1' WHERE `config_name` = 'users_amount' LIMIT 1;", 'config');

            $subTpl = gettemplate('install/ins_acc_done');
            $bloc   = $lang;
            $bloc['dpath']        = $dpath;
            $frame  = parsetemplate($subTpl, $bloc);
        }
        break;

    case 'bye':
        header("Location: ../");
        break;

    default:
        header('Location: ?mode=intro');
        die();
}


$parse                 = $lang;
$parse['ins_state']    = $page;
$parse['ins_page']     = $frame;
$parse['dis_ins_btn']  = "?mode=$mode&page=$nextPage";
$parse['dpath']        = $dpath;
$parse['ins_nav_intro'] = ($mode === 'intro') ? 'active' : '';
$parse['ins_nav_ins']   = ($mode === 'ins') ? 'active' : '';
$parse['ins_nav_bye']   = ($mode === 'bye') ? 'active' : '';
$data                 = parsetemplate($mainTpl, $parse);

display($data, "Installeur", false, '', true);
