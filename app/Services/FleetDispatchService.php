<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Api\ApiException;
use App\Core\GameData;
use App\Core\Modules;
use App\Repositories\PlanetRepository;

/**
 * Envoi d'une flotte depuis la planète active.
 *
 * Reprend la séquence de FleetController::floten3Action() : contrôle des
 * vaisseaux et des coordonnées, calcul distance/durée/consommation par les
 * helpers du jeu, contrôle de soute et de stock, puis enregistrement de la
 * flotte et débit des ressources.
 *
 * Les règles propres à certaines missions (colonisation, recyclage, expédition,
 * attaque groupée) ne sont pas encore vérifiées ici : voir la documentation.
 */
final class FleetDispatchService
{
    /** Missions acceptées par l'API (identifiants du jeu). */
    public const MISSIONS = array(
        1 => 'attack',
        2 => 'acs',
        3 => 'transport',
        4 => 'deploy',
        5 => 'hold',
        6 => 'spy',
        7 => 'colonize',
        8 => 'recycle',
        9 => 'destroy',
        10 => 'orbit',
        15 => 'expedition',
    );

    /** Missions avec temps de maintien sur place. */
    private const STAY_MISSIONS = array(5, 15);

    /**
     * Missions qui visent une **position libre** : aucun planète n'y est requis.
     *
     * Colonisation (7) et expédition (15) partent vers du vide, et le recyclage (8)
     * vise un champ de débris, qui peut se trouver au-dessus d'une position vide.
     */
    private const FREE_TARGET_MISSIONS = array(7, 8, 15);

    /** Expédition : la technologie 124 borne le nombre de vols simultanés. */
    public const MISSION_EXPEDITION = 15;

    /** Mise en orbite : autour de la planète de départ, une seule flotte à la fois. */
    public const MISSION_ORBIT = 10;

    /** Colonisation : la mission que le raccourci d'une position libre ouvre. */
    public const MISSION_COLONIZE = 7;

    /**
     * Sonde de colonisation (vaisseau du Coeur d'application) : c'est elle que la colonisation
     * engage, et le raccourci de la vue galaxie s'en sert pour savoir s'il a le
     * droit de proposer le geste.
     */
    public const COLONY_SHIP = 208;

    /**
     * « Stationner chez un allié » (mission 5) : la cible doit être un ami accepté
     * ou un membre de la même alliance.
     *
     * Règle unique, partagée par l'API et par la page d'envoi classique : le
     * drapeau d'amitié est lu par l'appelant (les deux chemins n'ont pas les
     * mêmes dépôts sous la main).
     */
    public static function holdTargetAllowed(array $user, array $targetUser, bool $isFriend): bool
    {
        if ($isFriend) {
            return true;
        }

        $ally = (int) ($user['ally_id'] ?? 0);

        return $ally > 0 && $ally === (int) ($targetUser['ally_id'] ?? 0);
    }

    /** Champ technique portant la technologie d'expédition. */
    private const EXPEDITION_TECH = 124;

    /**
     * Vitesse maximale, en dixièmes : 10 = 100 %. Le jeu n'accepte que les
     * valeurs de 1 à 10 (voir $speed_possible dans floten3Action).
     */
    public const MAX_SPEED = 10;

    public function __construct(
        private readonly FleetService $fleets = new FleetService(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
    ) {
    }

    public static function missions(): array
    {
        $missions = array_keys(self::MISSIONS);

        foreach (Modules::all() as $name => $module) {
            $missions = array_merge($missions, array_keys(Modules::missions($name)));
        }

        $missions = array_values(array_unique(array_map('intval', $missions)));
        sort($missions);

        return $missions;
    }

    /**
     * Missions de **champ de débris** déclarées par un module utilisable :
     * identifiant de mission => vaisseaux que le module a déclarés.
     *
     * Le Coeur d'application n'en connaît aucune (l'extraction appartient au module
     * `extracteurs`) : c'est le manifeste qui le dit (`debris`), et la vue galaxie
     * propose le raccourci à partir de cette déclaration, jamais d'un identifiant
     * écrit ici.
     *
     * @return array<int, array<int, int>>
     */
    public static function galaxyDebrisMissions(): array
    {
        $missions = array();

        foreach (Modules::all() as $name => $module) {
            foreach (Modules::missions($name) as $mission => $declared) {
                if (empty($declared['debris']) || empty($declared['ships'])) {
                    continue;
                }

                $ships = array();

                foreach ((array) $declared['ships'] as $ship) {
                    $ships[] = (int) $ship;
                }

                if ($ships !== array()) {
                    $missions[(int) $mission] = array_values(array_unique($ships));
                }
            }
        }

        return $missions;
    }

    /**
     * Missions que le raccourci de la vue galaxie a le droit de lancer d'un clic :
     * les trois gestes du Coeur d'application (espionnage 6, colonisation 7, recyclage 8) et les
     * missions de champ de débris d'un module. Règle unique, partagée par la page
     * et par l'action JSON qui l'exécute.
     *
     * @return array<int, int>
     */
    public static function galaxyMissionIds(): array
    {
        $missions = array_merge(array(6, 7, 8), array_keys(self::galaxyDebrisMissions()));

        return array_values(array_unique(array_map('intval', $missions)));
    }

    /**
     * Ramène la vitesse choisie dans les valeurs acceptées par le jeu.
     * Fonction pure.
     */
    public static function normalizeSpeed(mixed $speed): int
    {
        return max(1, min(self::MAX_SPEED, (int) $speed));
    }

    /**
     * Ne garde que les vaisseaux réellement demandés, en entiers positifs.
     * Fonction pure.
     *
     * @param array<string|int, mixed> $ships
     * @return array<int, int>
     */
    public static function normalizeShips(array $ships): array
    {
        $clean = array();

        foreach ($ships as $shipId => $count) {
            $shipId = (int) $shipId;
            $count = (int) $count;

            if ($shipId > 0 && $count > 0) {
                $clean[$shipId] = $count;
            }
        }

        return $clean;
    }

    /** Volume total transporté (métal + cristal + deutérium). Fonction pure. */
    public static function storageNeeded(float $metal, float $crystal, float $deuterium): float
    {
        return $metal + $crystal + $deuterium;
    }

    /**
     * Cette mission exige-t-elle une planète **existant** à l'arrivée ? Fonction pure.
     *
     * Une colonie abandonnée ou une lune détruite n'existe plus pour le jeu : ni
     * attaque, ni espionnage, ni transport, ni stationnement ne peuvent la viser.
     * Règle unique, partagée par l'API et par la page d'envoi classique.
     */
    public static function missionNeedsTarget(int $mission): bool
    {
        // Une mission déclarée par un module dit elle-même si elle vise une position
        // libre (l'extraction vise un champ de débris, qui peut être dans le vide).
        $declared = Modules::missionFor($mission);

        if ($declared !== null) {
            return !$declared['free_target'];
        }

        return !in_array($mission, self::FREE_TARGET_MISSIONS, true);
    }

    /**
     * Nombre d'expéditions simultanées autorisées par la technologie 124.
     * Reprend la règle de la page /game/fleet : 1 + tech / 3, et zéro sans la
     * technologie (donc expédition interdite). Fonction pure.
     */
    public static function maxExpeditions(int $expeditionTech): int
    {
        return $expeditionTech >= 1 ? 1 + (int) floor($expeditionTech / 3) : 0;
    }

    /** La soute peut-elle contenir la cargaison ? Fonction pure. */
    public static function capacityOk(float $capacity, float $needed): bool
    {
        return $needed <= $capacity;
    }

    /**
     * Les ressources restantes suffisent-elles ? Fonction pure. Le deutérium
     * doit en plus couvrir la consommation du trajet.
     */
    public static function stockOk(array $planet, float $metal, float $crystal, float $deuterium, int $consumption): bool
    {
        if ((float) ($planet['metal'] ?? 0) < $metal) {
            return false;
        }

        if ((float) ($planet['crystal'] ?? 0) < $crystal) {
            return false;
        }

        return (float) ($planet['deuterium'] ?? 0) - $consumption >= $deuterium;
    }

    /**
     * Distances, durée et consommation pour un envoi donné.
     *
     * `$speed` est la vitesse choisie en dixièmes (1 = 10 % ... 10 = 100 %) :
     * le jeu s'en sert comme premier paramètre de GetMissionDuration().
     *
     * @param array<int, int> $ships
     * @return array{distance: int, duration: int, consumption: int, max_speed: float, speed: int}
     */
    public function estimate(array $ships, array $user, array $origin, array $target, mixed $speed = self::MAX_SPEED): array
    {
        $fleetArray = self::normalizeShips($ships);
        $speedTenths = self::normalizeSpeed($speed);

        if ($fleetArray === array()) {
            throw ApiException::validation('no_ship', 'Aucun vaisseau sélectionné.');
        }

        $speeds = GetFleetMaxSpeed($fleetArray, 0, $user);
        $maxSpeed = (float) (is_array($speeds) && $speeds !== array() ? min($speeds) : 0);

        if ($maxSpeed <= 0) {
            throw ApiException::validation('no_speed', 'Vitesse de flotte invalide.');
        }

        $speedFactor = GetGameSpeedFactor();
        $distance = (int) GetTargetDistance(
            (int) $origin['galaxy'],
            (int) $target['galaxy'],
            (int) $origin['system'],
            (int) $target['system'],
            (int) $origin['planet'],
            (int) $target['planet']
        );
        $duration = (int) GetMissionDuration($speedTenths, $maxSpeed, $distance, $speedFactor);
        $consumption = (int) GetFleetConsumption($fleetArray, $speedFactor, $duration, $distance, $maxSpeed, $user);

        return array(
            'distance' => $distance,
            'duration' => $duration,
            'consumption' => $consumption,
            'max_speed' => $maxSpeed,
            'speed' => $speedTenths,
        );
    }

    /**
     * Envoie la flotte : contrôles puis écriture.
     *
     * @param array<int, int> $ships
     * @param array{metal?: float, crystal?: float, deuterium?: float} $resources
     * @return array{fleet: \App\Entities\Fleet, estimate: array<string, mixed>}
     */
    public function send(
        array $user,
        array &$planet,
        array $ships,
        array $target,
        int $mission,
        mixed $speed = self::MAX_SPEED,
        array $resources = array(),
        int $stayHours = 0
    ): array {
        if (!isset(self::MISSIONS[$mission]) && (new ModuleService())->missionHandler($mission) === null) {
            throw ApiException::validation('invalid_mission', 'Mission inconnue.');
        }

        $resource = GameData::resource();
        $pricelist = GameData::priceList();
        $fleetArray = self::normalizeShips($ships);

        if ($fleetArray === array()) {
            throw ApiException::validation('no_ship', 'Aucun vaisseau sélectionné.');
        }

        if ($mission === self::MISSION_EXPEDITION) {
            $this->assertExpeditionAllowed($user);
        }

        $this->assertTargetExists($target, $mission);

        // Une mission déclarée par un module peut refuser sa cible (l'extraction
        // n'a de sens que sur un champ de débris) : c'est le module qui le dit.
        $this->assertModuleMissionAllowed($user, $target, $mission);

        if ($mission === self::MISSION_ORBIT) {
            $this->assertOrbitAllowed($user, $planet, $target);
        }

        foreach ($fleetArray as $shipId => $count) {
            $field = $resource[$shipId] ?? null;

            if ($field === null) {
                throw ApiException::validation('invalid_ship', 'Vaisseau inconnu : ' . $shipId . '.');
            }

            if ((int) ($planet[$field] ?? 0) < $count) {
                throw ApiException::validation(
                    'not_enough_ships',
                    'Vaisseaux insuffisants sur la planète.'
                );
            }
        }

        $estimate = $this->estimate($fleetArray, $user, $planet, $target, $speed);
        $metal = max(0.0, (float) ($resources['metal'] ?? 0));
        $crystal = max(0.0, (float) ($resources['crystal'] ?? 0));
        $deuterium = max(0.0, (float) ($resources['deuterium'] ?? 0));

        if (!self::stockOk($planet, $metal, $crystal, $deuterium, $estimate['consumption'])) {
            throw ApiException::validation(
                'not_enough_resources',
                'Ressources insuffisantes (carburant ou cargaison).'
            );
        }

        $capacity = 0.0;
        foreach ($fleetArray as $shipId => $count) {
            $capacity += (float) ($pricelist[$shipId]['capacity'] ?? 0) * $count;
        }

        $capacity -= $estimate['consumption'];
        $needed = self::storageNeeded($metal, $crystal, $deuterium);

        if (!self::capacityOk($capacity, $needed)) {
            throw ApiException::validation(
                'not_enough_capacity',
                'Espace de soute insuffisant pour cette cargaison.'
            );
        }

        $startTime = time() + $estimate['duration'];
        $stayTime = in_array($mission, self::STAY_MISSIONS, true)
            ? $startTime + ($stayHours * 3600)
            : 0;
        // Mise en orbite : aucun retour n'est programmé (le rappel fixe l'heure),
        // l'échéance de la flotte est donc son arrivée en orbite.
        $endTime = $mission === self::MISSION_ORBIT
            ? $startTime
            : ($stayTime > 0
                ? $stayTime + $estimate['duration']
                : $startTime + $estimate['duration']);

        // Débit des vaisseaux en mémoire (FleetService les retire en base).
        foreach ($fleetArray as $shipId => $count) {
            $field = $resource[$shipId];
            $planet[$field] = (int) ($planet[$field] ?? 0) - $count;
        }

        $planet['metal'] = (float) ($planet['metal'] ?? 0) - $metal;
        $planet['crystal'] = (float) ($planet['crystal'] ?? 0) - $crystal;
        $planet['deuterium'] = (float) ($planet['deuterium'] ?? 0) - $deuterium - $estimate['consumption'];

        $fleet = $this->fleets->dispatch(
            (int) $user['id'],
            $mission,
            $fleetArray,
            array(
                'id' => (int) $planet['id'],
                'galaxy' => (int) $planet['galaxy'],
                'system' => (int) $planet['system'],
                'planet' => (int) $planet['planet'],
                'planet_type' => (int) $planet['planet_type'],
            ),
            array(
                'galaxy' => (int) $target['galaxy'],
                'system' => (int) $target['system'],
                'planet' => (int) $target['planet'],
                'planet_type' => (int) $target['planet_type'],
                'owner' => (string) ($target['owner'] ?? ''),
            ),
            $startTime,
            $endTime,
            $stayTime,
            array('metal' => $metal, 'crystal' => $crystal, 'deuterium' => $deuterium)
        );

        $this->planets->updateResources(
            (int) $planet['id'],
            $planet['metal'],
            $planet['crystal'],
            $planet['deuterium']
        );

        return array('fleet' => $fleet, 'estimate' => $estimate);
    }

    /**
     * Une mission déclarée par un module peut refuser sa cible.
     *
     * Le Coeur d'application ne connaît pas la règle : le module déclare une méthode statique qui
     * rend le message de refus (chaîne vide = la cible convient). Générique — toute
     * mission déclarée peut s'en servir, le module reste seul maître de sa règle.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $target
     */
    private function assertModuleMissionAllowed(array $user, array $target, int $mission): void
    {
        $handler = (new ModuleService())->missionHandler($mission);

        if ($handler === null) {
            return;
        }

        $method = (string) ($handler['refusal_method'] ?? '');

        if ($method === '' || !method_exists($handler['class'], $method)) {
            return;
        }

        $reason = (string) $handler['class']::{$method}($user, $target);

        if ($reason !== '') {
            throw ApiException::validation('mission_refused', $reason);
        }
    }

    /**
     * La cible doit exister : une colonie abandonnée ou une lune détruite n'est
     * plus une planète, la flotte n'a rien à y faire.
     *
     * @param array<string, mixed> $target position visée
     */
    private function assertTargetExists(array $target, int $mission): void
    {
        if (!self::missionNeedsTarget($mission)) {
            return;
        }

        $type = (int) ($target['planet_type'] ?? 1);

        if (
            $this->planets->findByCoords(
                (int) ($target['galaxy'] ?? 0),
                (int) ($target['system'] ?? 0),
                (int) ($target['planet'] ?? 0),
                $type
            ) !== false
        ) {
            return;
        }

        throw ApiException::validation(
            'no_target',
            $type === 3
                ? 'Aucune lune à ces coordonnées.'
                : 'Aucune planète à ces coordonnées.'
        );
    }

    /**
     * Mise en orbite : la flotte tourne autour de sa planète de départ (elle ne
     * peut pas viser une autre coordonnée), et une seule flotte à la fois par
     * planète — elle reste sur place jusqu'à son rappel.
     *
     * @param array<string, mixed> $origin planète de départ
     * @param array<string, mixed> $target position visée
     */
    private function assertOrbitAllowed(array $user, array $origin, array $target): void
    {
        if (!self::orbitTargetOk($origin, $target)) {
            throw ApiException::validation(
                'orbit_target',
                'La mise en orbite se fait uniquement autour de la planète de départ.'
            );
        }

        if ($this->fleets->countByMissionAt((int) $user['id'], self::MISSION_ORBIT, $target) > 0) {
            throw ApiException::validation(
                'orbit_busy',
                'Une flotte est déjà en orbite autour de cette planète.'
            );
        }
    }

    /**
     * La cible de la mise en orbite est-elle la planète de départ ?
     * Fonction pure.
     *
     * @param array<string, mixed> $origin
     * @param array<string, mixed> $target
     */
    public static function orbitTargetOk(array $origin, array $target): bool
    {
        foreach (array('galaxy', 'system', 'planet') as $axis) {
            if ((int) ($origin[$axis] ?? 0) !== (int) ($target[$axis] ?? 0)) {
                return false;
            }
        }

        return (int) ($origin['planet_type'] ?? 1) === (int) ($target['planet_type'] ?? 1);
    }

    /**
     * Règles de l'expédition, reprises de la page /game/fleet :
     *   - la technologie d'expédition est obligatoire ;
     *   - le nombre de vols simultanés est borné par cette technologie.
     *
     * L'API étant la seule source de vérité, elle doit les vérifier : le
     * formulaire legacy ne les contrôle que côté page.
     */
    private function assertExpeditionAllowed(array $user): void
    {
        $resource = GameData::resource();
        $field = $resource[self::EXPEDITION_TECH] ?? 'expedition_tech';
        $max = self::maxExpeditions((int) ($user[$field] ?? 0));

        if ($max < 1) {
            throw ApiException::validation(
                'expedition_tech_missing',
                'La technologie d\'expédition est nécessaire pour lancer une expédition.'
            );
        }

        if ($this->fleets->countByMission((int) $user['id'], self::MISSION_EXPEDITION) >= $max) {
            throw ApiException::validation(
                'expedition_limit',
                'Nombre maximum d\'expéditions simultanées atteint.'
            );
        }
    }
}
