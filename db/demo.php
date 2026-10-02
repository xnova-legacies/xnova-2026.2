<?php

declare(strict_types=1);

/**
 * Univers de démonstration : une base neuve, un compte garni.
 *
 * C'est ce que jouent les environnements de review et d'intégration : la base est
 * remise à neuf (toutes les tables tombent, les migrations les recréent), puis
 * l'univers est garni d'un compte d'administration et de trois voisins, dont un
 * compte qui a tout (ressources, bâtiments, vaisseaux, défenses, recherches,
 * officiers, colonie).
 *
 *   php db/demo.php             simulation : dit ce qui serait fait
 *   php db/demo.php --force     remet la base à neuf et garnit
 *
 * Depuis l'hôte :
 *   docker compose exec -T app php /var/www/html/db/demo.php --force
 *
 * Le script détruit toutes les tables : il n'accepte donc que les environnements
 * d'un poste de travail, de revue ou d'intégration (liste **blanche**), et il écrit
 * la base visée avant de toucher à quoi que ce soit. Un déploiement de production
 * ne peut pas l'exécuter, même par erreur.
 *
 * Les réglages `CONFIG_*` de `configs/.env.<env>` s'appliquent ici, comme à
 * l'installation : `CONFIG_GAME_SPEED=250000` donne une partie cent fois plus
 * rapide, ce qui est pratique pour une review.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande.\n");
}

define('INSIDE', true);
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('PHPEXT', 'php');

require ROOT_PATH . 'app/bootstrap.php';

use App\Core\ConfigDefaults;
use App\Core\GameConfig;
use App\Core\GameData;
use App\Database\Connection;
use App\Database\Migrator;
use App\Repositories\GalaxyRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;
use App\Services\AclService;
use App\Services\ModuleService;

// Coeur d'application legacy, comme `db/stats.php` : les tables de données du jeu
// (prix, listes d'éléments) doivent être recopiées dans les globales.
require_once ROOT_PATH . 'includes/constants.php';
require_once ROOT_PATH . 'includes/functions.php';
require_once ROOT_PATH . 'includes/db.php';

GameData::load();

const DEMO_PASSWORD = 'demo';

$force = in_array('--force', $argv, true);
$environment = (string) xnova_env('APP_ENV', 'local');

// La base visée est écrite **avant** tout geste : un déploiement qui lancerait ce
// script par erreur doit le voir à l'écran, pas dans le journal d'un conteneur.
echo 'Base « ', Connection::prefix(), ' » — hôte ', xnova_env('DB_HOST', '(config.php)'),
    ', base ', xnova_env('DB_NAME', '(config.php)'), ' — environnement ', $environment, ' — ',
    $force ? "écriture\n\n" : "simulation\n\n";

// Liste **blanche** des environnements : tout ce qui n'est pas un poste de travail,
// une revue ou l'intégration est refusé. La production n'est pas le seul danger :
// un déploiement qui n'annonce pas son environnement ne doit pas non plus tout effacer.
$allowedEnvironments = array('local', 'dev', 'development', 'test', 'ci', 'review', 'integration');

if (!in_array($environment, $allowedEnvironments, true)) {
    exit('Refus : environnement « ' . $environment . " ». L'univers de démonstration détruit"
        . " et recrée toutes les tables ; il ne s'installe que sur un poste de travail,\n"
        . "une revue ou l'intégration (APP_ENV parmi : " . implode(', ', $allowedEnvironments) . ").\n");
}

if (!$force) {
    echo "Rien n'a été modifié. Ajouter --force pour remettre la base à neuf et garnir.\n";
    return;
}

// 1. La base repart de zéro : toutes les tables tombent, les migrations les recréent.
//    Un rejeu partiel laisserait des comptes et des positions d'un run précédent.
$dropped = dropAllTables();
echo '1. Tables supprimées   : ', $dropped, "\n";

$migrations = (new Migrator())->run(false);
echo '2. Migrations jouées   : ', count($migrations), "\n";

echo '3. Réglages complétés : ', GameConfig::seed(ConfigDefaults::DEFAULTS, ConfigDefaults::overrides()), "\n";
echo '4. Rôles semés         : ', count(AclService::seedDefaults()), "\n";
echo '5. Modules enregistrés : ', ModuleService::seedDefaults(), "\n";

$world = seedDemoUniverse();
echo '6. Comptes créés       : ', $world['accounts'], "\n";
echo '7. Planètes créées     : ', $world['planets'], "\n";
echo '8. Éléments garnis     : ', $world['elements'], "\n\n";

echo "Le jeu est prêt :\n";
foreach ($world['logins'] as $login) {
    echo '  · ', $login, "\n";
}
echo "\nMot de passe : ", DEMO_PASSWORD, " (mot de passe d'administration inclus).\n";

/**
 * Toutes les tables du préfixe courant tombent.
 *
 * Le nom vient de `SHOW TABLES` et sa **forme** est vérifiée avant l'interpolation :
 * un nom de table ne peut pas être un paramètre lié (même règle que les colonnes,
 * `BaseRepository::isColumnName()`).
 */
function dropAllTables(): int
{
    Connection::query('SET FOREIGN_KEY_CHECKS = 0');

    $dropped = 0;

    foreach (Connection::preparedFetchAll('SHOW TABLES', array()) as $row) {
        $name = (string) reset($row);

        if ($name === '' || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            continue;
        }

        Connection::query('DROP TABLE IF EXISTS `' . $name . '`');
        $dropped++;
    }

    Connection::query('SET FOREIGN_KEY_CHECKS = 1');

    return $dropped;
}

/**
 * Crée les comptes, leurs planètes et la garniture du compte de démonstration.
 *
 * Tout passe par les dépôts du jeu : aucune règle n'est réécrite ici, et une
 * colonne nouvelle n'a pas besoin d'être connue du script (les éléments sont
 * nommés par `GameData::resource()`, les catégories par `$reslist`).
 *
 * @return array{accounts: int, planets: int, elements: int, logins: list<string>}
 */
function seedDemoUniverse(): array
{
    $users = new UserRepository();
    $planets = new PlanetRepository();
    $galaxies = new GalaxyRepository();

    $resource = GameData::resource();
    $lists = $GLOBALS['reslist'] ?? array();

    if ($resource === array() || !isset($lists['build'], $lists['fleet'], $lists['defense'])) {
        exit("Refus : les tables de données du jeu ne sont pas chargées.\n");
    }

    // L'administrateur (id 1 : super administrateur par définition), le compte
    // garni, et deux voisins à portée de flotte pour espionner et attaquer.
    $accounts = array(
        array('id' => 1, 'name' => 'Admin', 'authlevel' => 3, 'furnished' => false, 'colony' => false),
        array('id' => 2, 'name' => 'Demo', 'authlevel' => 3, 'furnished' => true, 'colony' => true),
        array('id' => 3, 'name' => 'Voisin', 'authlevel' => 0, 'furnished' => false, 'colony' => false),
        array('id' => 4, 'name' => 'Railleur', 'authlevel' => 0, 'furnished' => false, 'colony' => false),
    );

    $createdAccounts = 0;
    $createdPlanets = 0;
    $elements = 0;
    $logins = array();

    foreach ($accounts as $index => $account) {
        $userId = (int) $account['id'];
        $galaxy = 1;
        $system = 1;
        $position = $index + 1;

        // `insertResetAccount()` force l'identifiant : le compte d'installation reste
        // le numéro 1, comme après une vraie installation.
        $users->insertResetAccount(array(
            'id' => $userId,
            'username' => (string) $account['name'],
            'email' => strtolower((string) $account['name']) . '@xnova.local',
            'email_2' => strtolower((string) $account['name']) . '@xnova.local',
            'sex' => '',
            'authlevel' => (int) $account['authlevel'],
            'dpath' => '',
            'galaxy' => $galaxy,
            'system' => $system,
            'planet' => $position,
            'register_time' => time(),
            'password' => md5(DEMO_PASSWORD),
        ));
        $createdAccounts++;

        $planetId = $planets->insertPlanetRecord(array(
            'name' => (string) $account['name'] . ' — planète mère',
            'id_owner' => $userId,
            'galaxy' => $galaxy,
            'system' => $system,
            'planet' => $position,
            'planet_type' => 1,
            'image' => 'normaltempplanet01',
            'diameter' => 12800,
            'field_max' => 163,
            'temp_min' => 20,
            'temp_max' => 60,
            'last_update' => time(),
            'metal' => 1000000000,
            'crystal' => 1000000000,
            'deuterium' => 1000000000,
            'metal_perhour' => 0,
            'crystal_perhour' => 0,
            'deuterium_perhour' => 0,
            'metal_max' => 10000000000,
            'crystal_max' => 10000000000,
            'deuterium_max' => 10000000000,
            'energy_used' => 0,
            'energy_max' => 0,
            'b_building' => 0,
            'b_building_id' => '',
            'b_hangar' => 0,
            'b_hangar_id' => '',
            'destruyed' => 0,
        ));
        $createdPlanets++;

        // La position existe pour la galaxie (ligne `galaxy`), et le compte pointe
        // sur sa planète mère.
        $galaxies->linkPlanet($galaxy, $system, $position, $planetId);
        $users->updateHomePlanet($userId, $planetId, $galaxy, $system, $position);
        $users->setCurrentPlanet($planetId, $userId);
        $users->setColumn($userId, 'id_planet', $planetId);

        if ($account['furnished'] === true) {
            $elements += furnishPlanet($planets, $planetId, $resource, $lists);
            $elements += furnishAccount($users, $userId, $resource, $lists);

            if ($account['colony'] === true) {
                $colonyId = $planets->insertPlanetRecord(array(
                    'name' => $account['name'] . ' — colonie',
                    'id_owner' => $userId,
                    'galaxy' => $galaxy,
                    'system' => $system,
                    'planet' => 9,
                    'planet_type' => 1,
                    'image' => 'normaltempplanet02',
                    'diameter' => 10240,
                    'field_max' => 120,
                    'temp_min' => 10,
                    'temp_max' => 45,
                    'last_update' => time(),
                    'metal' => 500000000,
                    'crystal' => 500000000,
                    'deuterium' => 500000000,
                    'metal_max' => 10000000000,
                    'crystal_max' => 10000000000,
                    'deuterium_max' => 10000000000,
                    'destruyed' => 0,
                ));
                $createdPlanets++;
                $galaxies->linkPlanet($galaxy, $system, 9, $colonyId);
                $elements += furnishPlanet($planets, $colonyId, $resource, $lists);
            }
        }

        $logins[] = (string) $account['name']
            . ($account['furnished'] === true ? ' (compte garni)' : '')
            . ' — ' . $galaxy . ':' . $system . ':' . $position;
    }

    return array(
        'accounts' => $createdAccounts,
        'planets' => $createdPlanets,
        'elements' => $elements,
        'logins' => $logins,
    );
}

/**
 * Garnit une planète : bâtiments, vaisseaux et défenses.
 *
 * Les boucliers sont des colonnes `enum('0','1')` : un seul exemplaire, comme la
 * file de construction l'écrit (`BuildingQueueRepository::savePlanetProduction`).
 *
 * @param array<int, string>      $resource
 * @param array<string, int[]>    $lists
 */
function furnishPlanet(PlanetRepository $planets, int $planetId, array $resource, array $lists): int
{
    $unique = GameData::uniqueUnits();
    $columns = array();
    $count = 0;

    foreach (array('build' => 30, 'fleet' => 1000, 'defense' => 1000) as $category => $level) {
        foreach (($lists[$category] ?? array()) as $unitId) {
            if (!isset($resource[$unitId])) {
                continue;
            }

            $columns[$resource[$unitId]] = in_array((int) $unitId, $unique, true) ? 1 : $level;
            $count++;
        }
    }

    if ($columns !== array()) {
        $planets->updateColumns($planetId, $columns);
    }

    return $count;
}

/**
 * Garnit un compte : recherches, officiers, expérience et points.
 *
 * Les recherches vivent sur le compte (`users.spy_tech`…), pas sur la planète :
 * les colonnes sont celles de `GameData::resource()`.
 *
 * @param array<int, string>   $resource
 * @param array<string, int[]> $lists
 */
function furnishAccount(UserRepository $users, int $userId, array $resource, array $lists): int
{
    $count = 0;

    foreach (($lists['tech'] ?? array()) as $technoId) {
        if (!isset($resource[$technoId])) {
            continue;
        }

        $users->setColumn($userId, (string) $resource[$technoId], 20);
        $count++;
    }

    return $count;
}
