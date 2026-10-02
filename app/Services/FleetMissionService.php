<?php

namespace App\Services;

use App\Core\GameData;
use App\Core\Language;
use App\Core\SpyReport;
use App\Entities\Fleet;
use App\Entities\Message;
use App\Entities\Planet;
use App\Repositories\MissionRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;

/**
 * Traitement des missions de flotte (ex FlyingFleetHandler + MissionCase*).
 * Toutes les requêtes passent par MissionRepository (préparées).
 * Attack/Destruction restent en fonctions legacy (moteur de combat + rapports
 * complexes) mais leurs appels traversent ce service.
 */
final class FleetMissionService
{
    public function __construct(
        private readonly MissionRepository $missions = new MissionRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /** Traite toutes les flottes arrivant/partant d'une position (ex FlyingFleetHandler). */
    public function handle(array $position): void
    {
        foreach ($this->missions->findFleetsToProcess($position) as $currentFleet) {
            // Les fonctions legacy (MissionCaseAttack & co) attendent toujours la
            // ligne brute : l'entité ne sert qu'à la logique de ce service.
            $fleet = Fleet::fromRow($currentFleet);

            switch ($fleet->mission()) {
                case 1:
                    MissionCaseAttack($currentFleet);
                    break;
                case 2:
                    $this->acs($currentFleet);
                    break;
                case 3:
                    $this->transport($currentFleet);
                    break;
                case 4:
                    $this->stay($currentFleet);
                    break;
                case 5:
                    $this->stayAlly($currentFleet);
                    break;
                case 6:
                    $this->spy($currentFleet);
                    break;
                case 7:
                    $this->colonise($currentFleet);
                    break;
                case 8:
                    $this->recycle($currentFleet);
                    break;
                case 9:
                    MissionCaseDestruction($currentFleet);
                    break;
                case 10:
                    $this->orbit($currentFleet);
                    break;
                case 11:
                    $this->missileStrike($currentFleet);
                    break;
                case 15:
                    $this->expedition($currentFleet);
                    break;
                default:
                    $this->handleModuleMission($currentFleet);
            }
        }
    }

    /**
     * Mission que le Coeur d'application ne connaît pas : elle appartient peut-être à un module
     * (clé `missions` de son manifeste), à qui la ligne de flotte brute est passée.
     *
     * Sans gestionnaire — mission inconnue, ou module éteint —, le vol est libéré
     * comme avant l'arrivée des modules.
     */
    public function handleModuleMission(array $fleetRow): void
    {
        $fleet = Fleet::fromRow($fleetRow);
        $handler = (new ModuleService())->missionHandler($fleet->mission());

        if ($handler !== null && method_exists($handler['class'], $handler['method'])) {
            (new $handler['class']())->{$handler['method']}($fleetRow);

            return;
        }

        $this->missions->deleteFleet($fleet->id());
    }

    /**
     * Attaque groupée (mission 2).
     *
     * Le moteur ne connaît qu'une flotte attaquante : le groupe est donc résolu
     * en **une seule bataille**, à l'arrivée de tous les participants. Les
     * flottes du groupe (même `fleet_group`) sont fusionnées en une ligne de
     * combat, puis supprimées avec le groupe : les survivants rentrent avec la
     * flotte du meneur, et le rapport part aux mêmes destinataires qu'une
     * attaque simple (meneur et défenseur).
     */
    public function acs(array $fleetRow): void
    {
        $fleet = Fleet::fromRow($fleetRow);
        $group = $fleet->groupId();

        // « Attaque groupée » lancée sans groupe : c'est une attaque ordinaire.
        if ($group <= 0) {
            MissionCaseAttack($fleetRow);
            return;
        }

        $members = $this->missions->findGroupFleets($group);

        if ($members === array()) {
            $this->missions->deleteFleet($fleet->id());
            return;
        }

        $now = time();

        // Tant qu'un participant est en vol, la flotte attend : le handler
        // repasse à chaque affichage de page.
        foreach ($members as $member) {
            if ((int) ($member['fleet_start_time'] ?? 0) > $now) {
                return;
            }
        }

        $merged = self::mergeGroup($members);

        // Chaque participant est nommé dans le rapport, avec la flotte qu'il engage :
        // le moteur, lui, ne voit que la force réunie (voir MissionCaseAttack).
        $merged['acs_attackers'] = array();

        foreach ($members as $member) {
            $owner = (int) ($member['fleet_owner'] ?? 0);
            $ownerRow = $this->users->findFullById($owner);

            $merged['acs_attackers'][] = array(
                'id' => $owner,
                'name' => (string) ($ownerRow['username'] ?? ('#' . $owner)),
                'galaxy' => (int) ($member['fleet_start_galaxy'] ?? 0),
                'system' => (int) ($member['fleet_start_system'] ?? 0),
                'planet' => (int) ($member['fleet_start_planet'] ?? 0),
                'units' => FlyingFleetService::parseUnits(isset($member['fleet_array']) ? (string) $member['fleet_array'] : null),
            );
        }

        MissionCaseAttack($merged);
        foreach ($members as $member) {
            $this->missions->deleteFleet((int) $member['fleet_id']);
        }

        $this->missions->deleteGroup($group);
    }

    /**
     * Fusionne les flottes d'un groupe en une seule ligne de combat.
     *
     * Seuls les vaisseaux et les soutes s'additionnent : les coordonnées, les
     * dates et le propriétaire du meneur (première flotte du groupe) sont
     * conservés, puisque le moteur et le rapport ne lisent qu'une flotte.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function mergeGroup(array $rows): array
    {
        $merged = $rows[0];
        $ships = array();
        $amount = 0;

        // Les soutes s'additionnent comme les vaisseaux : on repart de zéro pour
        // ne pas compter deux fois celle du meneur (déjà dans `$merged`).
        foreach (array('fleet_resource_metal', 'fleet_resource_crystal', 'fleet_resource_deuterium') as $field) {
            $merged[$field] = 0;
        }

        foreach ($rows as $row) {
            foreach (explode(';', (string) ($row['fleet_array'] ?? '')) as $chunk) {
                if ($chunk === '') {
                    continue;
                }

                $parts = explode(',', $chunk);
                $shipId = (int) ($parts[0] ?? 0);
                $count = (int) ($parts[1] ?? 0);

                if ($shipId <= 0 || $count <= 0) {
                    continue;
                }

                $ships[$shipId] = ($ships[$shipId] ?? 0) + $count;
                $amount += $count;
            }

            foreach (array('fleet_resource_metal', 'fleet_resource_crystal', 'fleet_resource_deuterium') as $field) {
                $merged[$field] = (int) ($merged[$field] ?? 0) + (int) ($row[$field] ?? 0);
            }
        }

        $fleetArray = '';

        foreach ($ships as $shipId => $count) {
            $fleetArray .= $shipId . ',' . $count . ';';
        }

        $merged['fleet_array'] = $fleetArray;
        $merged['fleet_amount'] = $amount;
        $merged['fleet_mess'] = 0;

        return $merged;
    }

    /**
     * Compteurs de raids d'un attaquant après un combat.
     *
     * Un raid perdu (match nul ou défaite) incrémente `raidsloose`, un raid gagné
     * `raidswin` ; `raids` compte les deux. La page Vue générale lit ces colonnes.
     *
     * @param array<string, mixed> $user
     * @return array{raids: int, raidswin: int, raidsloose: int}
     */
    public static function raidCounters(array $user, bool $won): array
    {
        return array(
            'raids' => (int) ($user['raids'] ?? 0) + 1,
            'raidswin' => (int) ($user['raidswin'] ?? 0) + ($won ? 1 : 0),
            'raidsloose' => (int) ($user['raidsloose'] ?? 0) + ($won ? 0 : 1),
        );
    }

    public function transport(array $fleetRow): void
    {
        global $lang;

        $fleet = Fleet::fromRow($fleetRow);

        $startPlanet = $this->planets->findByCoords($fleet->startGalaxy(), $fleet->startSystem(), $fleet->startPlanet(), $fleet->startType());
        $startName = $startPlanet['name'];
        $startOwner = $startPlanet['id_owner'];

        $targetPlanet = $this->planets->findByCoords($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet(), $fleet->endType());
        $targetName = $targetPlanet['name'];
        $targetOwner = $targetPlanet['id_owner'];

        if (!$fleet->isReturning()) {
            if ($fleet->departureTime() < time()) {
                StoreGoodsToPlanet($fleetRow, false);

                $message = sprintf(
                    $lang['sys_tran_mess_owner'],
                    $targetName,
                    GetTargetAdressLink($fleetRow, ''),
                    $fleet->cargoMetal(),
                    $lang['Metal'],
                    $fleet->cargoCrystal(),
                    $lang['Crystal'],
                    $fleet->cargoDeuterium(),
                    $lang['Deuterium']
                );

                SendSimpleMessage($startOwner, '', $fleet->departureTime(), 5, $lang['sys_mess_tower'], $lang['sys_mess_transport'], $message);

                if ($targetOwner <> $startOwner) {
                    $message = sprintf(
                        $lang['sys_tran_mess_user'],
                        $startName,
                        GetStartAdressLink($fleetRow, ''),
                        $targetName,
                        GetTargetAdressLink($fleetRow, ''),
                        $fleet->cargoMetal(),
                        $lang['Metal'],
                        $fleet->cargoCrystal(),
                        $lang['Crystal'],
                        $fleet->cargoDeuterium(),
                        $lang['Deuterium']
                    );
                    SendSimpleMessage($targetOwner, '', $fleet->departureTime(), 5, $lang['sys_mess_tower'], $lang['sys_mess_transport'], $message);
                }

                $this->missions->clearFleetCargo($fleet->id());
            }
        } else {
            if ($fleet->arrivalTime() < time()) {
                $message = sprintf($lang['sys_tran_mess_back'], $startName, GetStartAdressLink($fleetRow, ''));
                SendSimpleMessage($startOwner, '', $fleet->arrivalTime(), 5, $lang['sys_mess_tower'], $lang['sys_mess_fleetback'], $message);
                $this->missions->restoreFleet($fleetRow, true);
                $this->missions->deleteFleet($fleet->id());
            }
        }
    }

    public function stay(array $fleetRow): void
    {
        global $lang;

        $fleet = Fleet::fromRow($fleetRow);

        if (!$fleet->isReturning()) {
            if ($fleet->departureTime() <= time()) {
                $targetPlanet = $this->planets->findByCoords($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet(), $fleet->endType());
                $targetUserId = $targetPlanet['id_owner'];

                $targetAdress = sprintf($lang['sys_adress_planet'], $fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet());
                $targetAddedGoods = sprintf(
                    $lang['sys_stay_mess_goods'],
                    $lang['Metal'],
                    pretty_number($fleet->cargoMetal()),
                    $lang['Crystal'],
                    pretty_number($fleet->cargoCrystal()),
                    $lang['Deuterium'],
                    pretty_number($fleet->cargoDeuterium())
                );

                $targetMessage = $lang['sys_stay_mess_start'] . "<a href=\"/game/galaxy?mode=3&galaxy=" . $fleet->endGalaxy() . "&system=" . $fleet->endSystem() . "\">";
                $targetMessage .= $targetAdress . "</a>" . $lang['sys_stay_mess_end'] . "<br />" . $targetAddedGoods;

                SendSimpleMessage($targetUserId, '', $fleet->departureTime(), 5, $lang['sys_mess_qg'], $lang['sys_stay_mess_stay'], $targetMessage);
                $this->missions->restoreFleet($fleetRow, false);
                $this->missions->deleteFleet($fleet->id());
            }
        } else {
            if ($fleet->arrivalTime() <= time()) {
                $targetAdress = sprintf($lang['sys_adress_planet'], $fleet->startGalaxy(), $fleet->startSystem(), $fleet->startPlanet());
                $targetAddedGoods = sprintf(
                    $lang['sys_stay_mess_goods'],
                    $lang['Metal'],
                    pretty_number($fleet->cargoMetal()),
                    $lang['Crystal'],
                    pretty_number($fleet->cargoCrystal()),
                    $lang['Deuterium'],
                    pretty_number($fleet->cargoDeuterium())
                );

                $targetMessage = $lang['sys_stay_mess_back'] . "<a href=\"/game/galaxy?mode=3&galaxy=" . $fleet->startGalaxy() . "&system=" . $fleet->startSystem() . "\">";
                $targetMessage .= $targetAdress . "</a>" . $lang['sys_stay_mess_bend'] . "<br />" . $targetAddedGoods;

                SendSimpleMessage($fleet->ownerId(), '', $fleet->arrivalTime(), 5, $lang['sys_mess_qg'], $lang['sys_mess_fleetback'], $targetMessage);
                $this->missions->restoreFleet($fleetRow, true);
                $this->missions->deleteFleet($fleet->id());
            }
        }
    }

    public function stayAlly(array $fleetRow): void
    {
        global $lang;

        $fleet = Fleet::fromRow($fleetRow);

        $startPlanet = $this->planets->findByCoords($fleet->startGalaxy(), $fleet->startSystem(), $fleet->startPlanet(), 1);
        $startName = $startPlanet['name'];
        $startOwner = $startPlanet['id_owner'];

        $targetPlanet = $this->planets->findByCoords($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet(), 1);
        $targetName = $targetPlanet['name'];
        $targetOwner = $targetPlanet['id_owner'];

        switch (self::holdStep($fleetRow, time())) {
            case 'notify':
                $message = sprintf(
                    $lang['sys_tran_mess_owner'],
                    $targetName,
                    GetTargetAdressLink($fleetRow, ''),
                    $fleet->cargoMetal(),
                    $lang['Metal'],
                    $fleet->cargoCrystal(),
                    $lang['Crystal'],
                    $fleet->cargoDeuterium(),
                    $lang['Deuterium']
                );
                SendSimpleMessage($startOwner, '', $fleet->departureTime(), 5, $lang['sys_mess_tower'], $lang['sys_mess_transport'], $message);

                $message = sprintf(
                    $lang['sys_tran_mess_user'],
                    $startName,
                    GetStartAdressLink($fleetRow, ''),
                    $targetName,
                    GetTargetAdressLink($fleetRow, ''),
                    $fleet->cargoMetal(),
                    $lang['Metal'],
                    $fleet->cargoCrystal(),
                    $lang['Crystal'],
                    $fleet->cargoDeuterium(),
                    $lang['Deuterium']
                );
                SendSimpleMessage($targetOwner, '', $fleet->departureTime(), 5, $lang['sys_mess_tower'], $lang['sys_mess_transport'], $message);

                $this->missions->markFleetOnStation($fleet->id());
                return;

            case 'return-home':
                // Fin du maintien : la flotte rentre. Le vol retour est déjà daté
                // à l'envoi (`fleet_end_time`), il ne reste qu'à la marquer.
                $this->missions->markFleetReturning($fleet->id());
                return;

            case 'arrived-home':
                $message = sprintf($lang['sys_tran_mess_back'], $startName, GetStartAdressLink($fleetRow, ''));
                SendSimpleMessage($startOwner, '', $fleet->arrivalTime(), 5, $lang['sys_mess_tower'], $lang['sys_mess_fleetback'], $message);
                $this->missions->restoreFleet($fleetRow, true);
                $this->missions->deleteFleet($fleet->id());
                return;

            default:
                // Pas encore arrivée, en stationnement, ou sur le chemin du retour.
                return;
        }
    }

    /**
     * Étape d'une flotte de garde (mission 5), calculée à partir de ses dates.
     *
     * `fleet_mess` rythme la vie de la flotte : 0 = en vol, 2 = en stationnement
     * (arrivée déjà annoncée), 1 = retour. Sans la marque « en stationnement », le
     * handler — qui repasse à chaque affichage de page — annoncerait l'arrivée
     * en boucle et n'atteindrait jamais la fin du maintien.
     *
     * @param array<string, mixed> $row
     * @return string waiting|notify|on-station|return-home|returning|arrived-home
     */
    public static function holdStep(array $row, int $now): string
    {
        $mess  = (int) ($row['fleet_mess'] ?? 0);
        $start = (int) ($row['fleet_start_time'] ?? 0);
        $stay  = (int) ($row['fleet_end_stay'] ?? 0);
        $end   = (int) ($row['fleet_end_time'] ?? 0);

        if ($mess === 1) {
            return $end <= $now ? 'arrived-home' : 'returning';
        }

        if ($mess === 2) {
            return $stay <= $now ? 'return-home' : 'on-station';
        }

        return $start <= $now ? 'notify' : 'waiting';
    }

    /**
     * Mise en orbite (mission 10) : la flotte se place autour de sa planète de
     * départ et y reste jusqu'à son rappel.
     *
     * Les vaisseaux ne reviennent donc PAS sur la planète à l'arrivée : ils
     * restent dans la table des flottes, hors de la planète — donc invisibles à
     * l'espionnage (le rapport ne liste que les vaisseaux de la planète), hors de
     * portée des attaques, et indisponibles pour le joueur tant que la flotte
     * n'est pas rappelée. Le rappel (bouton « Retour » de la page des flottes)
     * passe la flotte en retour : c'est la branche ci-dessous qui rend alors les
     * vaisseaux à la planète.
     *
     * La méthode est appelée à chaque affichage de page tant que la flotte
     * existe : elle ne fait donc quelque chose qu'une seule fois par étape.
     */
    public function orbit(array $fleetRow): void
    {
        global $lang;

        $fleet = Fleet::fromRow($fleetRow);

        if ($fleet->isReturning()) {
            if ($fleet->arrivalTime() <= time()) {
                $message = $lang['sys_orbit_mess_back']
                    . self::planetLink($fleet->startGalaxy(), $fleet->startSystem(), $fleet->startPlanet())
                    . $lang['sys_orbit_mess_bend'];

                SendSimpleMessage($fleet->ownerId(), '', $fleet->arrivalTime(), 5, $lang['sys_mess_tower'], $lang['sys_mess_fleetback'], $message);
                $this->missions->restoreFleet($fleetRow, true);
                $this->missions->deleteFleet($fleet->id());
            }

            return;
        }

        // Pas encore arrivée, ou déjà en orbite (`fleet_end_stay` négatif) : rien
        // à faire — la flotte repasse ici à chaque affichage de page.
        if ($fleet->departureTime() > time() || $fleet->isOrbiting()) {
            return;
        }

        $this->missions->markFleetInOrbit($fleet->id());

        $message = $lang['sys_orbit_mess_start']
            . self::planetLink($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet())
            . $lang['sys_orbit_mess_end'];
        SendSimpleMessage($fleet->ownerId(), '', $fleet->departureTime(), 5, $lang['sys_mess_tower'], $lang['sys_orbit_mess_subject'], $message);
    }

    /** Lien vers une position de la vue galaxie, coordonnées affichées comprises. */
    private static function planetLink(int $galaxy, int $system, int $planet): string
    {
        global $lang;

        return '<a href="/game/galaxy?mode=3&galaxy=' . $galaxy . '&system=' . $system . '">'
            . sprintf($lang['sys_adress_planet'], $galaxy, $system, $planet)
            . '</a>';
    }

    public function spy(array $fleetRow): void
    {
        global $lang;

        $fleet = Fleet::fromRow($fleetRow);

        if ($fleet->departureTime() > time()) {
            return;
        }

        $currentUser = $this->users->findFullById($fleet->ownerId());
        $currentUserId = $fleet->ownerId();

        $targetPlanet = $this->planets->findByCoords($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet(), $fleet->endType());
        $targetUserId = $targetPlanet['id_owner'];

        $currentPlanet = $this->planets->findByCoords($fleet->startGalaxy(), $fleet->startSystem(), $fleet->startPlanet(), 1);
        $currentSpyLvl = $currentUser['spy_tech'];

        $targetUser = $this->users->findFullById((int) $targetUserId);
        $targetSpyLvl = $targetUser['spy_tech'];

        PlanetResourceUpdate($targetUser, $targetPlanet, time());

        foreach ($fleet->array() as $shipId => $shipCount) {
            if (!$fleet->isReturning() && $shipId === 210) {
                $LS = $shipCount;

                $targetGalaxy = $this->missions->findGalaxyRow($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet());
                $cristalDebris = $targetGalaxy['crystal'];
                $spyToolDebris = $LS * 300;

                $materialsInfo = SpyTarget($targetPlanet, 0, $lang['sys_spy_maretials']);
                $materials = $materialsInfo['String'];

                $planetFleetInfo = SpyTarget($targetPlanet, 1, $lang['sys_spy_fleet']);
                $planetFleet = $materials . $planetFleetInfo['String'];

                $planetDefenInfo = SpyTarget($targetPlanet, 2, $lang['sys_spy_defenses']);
                $planetDefense = $planetFleet . $planetDefenInfo['String'];

                $planetBuildInfo = SpyTarget($targetPlanet, 3, $lang['tech'][0]);
                $planetBuildings = $planetDefense . $planetBuildInfo['String'];

                $targetTechnInfo = SpyTarget($targetUser, 4, $lang['tech'][100]);
                $targetTechnos = $planetBuildings . $targetTechnInfo['String'];

                $targetForce = ($planetFleetInfo['Count'] * $LS) / 4;
                if ($targetForce > 100) {
                    $targetForce = 100;
                }
                $targetChances = rand(0, $targetForce);
                $spyerChances = rand(0, 100);
                $destroyed = $targetChances < $spyerChances;

                $pT = ($targetSpyLvl - $currentSpyLvl);
                $pW = ($currentSpyLvl - $targetSpyLvl);
                if ($targetSpyLvl > $currentSpyLvl) {
                    $ST = ($LS - pow($pT, 2));
                }
                if ($currentSpyLvl > $targetSpyLvl) {
                    $ST = ($LS + pow($pW, 2));
                }
                if ($targetSpyLvl == $currentSpyLvl) {
                    $ST = $currentSpyLvl;
                }
                if ($ST <= "1") {
                    $revealed = $materials;
                }
                if ($ST == "2") {
                    $revealed = $planetFleet;
                }
                if ($ST == "4" or $ST == "3") {
                    $revealed = $planetDefense;
                }
                if ($ST == "5" or $ST == "6") {
                    $revealed = $planetBuildings;
                }
                if ($ST >= "7") {
                    $revealed = $targetTechnos;
                }

                // Mise en forme du rapport dans un gabarit (App\Core\SpyReport) :
                // seule la règle du niveau d'information reste ici.
                $spyMessage = SpyReport::assemble(array(
                    'title' => $lang['sys_mess_spy_report'],
                    'target' => (string) $targetPlanet['name'],
                    'coordinates' => '[' . $fleet->endGalaxy() . ':' . $fleet->endSystem() . ':' . $fleet->endPlanet() . ']',
                    'date' => gmdate('d-m-Y H:i:s', $fleet->departureTime() + 2 * 60 * 60),
                    'sections' => $revealed,
                    'hint' => $lang['sys_mess_spy_control'],
                    'fate' => $destroyed
                        ? $lang['sys_mess_spy_destroyed']
                        : sprintf($lang['sys_mess_spy_lostproba'], $targetChances),
                    'fate_variant' => $destroyed ? 'danger' : 'warning',
                    'attack_url' => '/game/fleet?galaxy=' . $fleet->endGalaxy() . '&system=' . $fleet->endSystem()
                        . '&planet=' . $fleet->endPlanet() . '&target_mission=1',
                    'attack_label' => $lang['type_mission'][1],
                ));

                SendSimpleMessage($currentUserId, '', $fleet->departureTime(), 0, $lang['sys_mess_qg'], $lang['sys_mess_spy_report'], $spyMessage);

                $currentPlanetRow = Planet::fromRow($currentPlanet);
                $targetPlanetRow = Planet::fromRow($targetPlanet);

                $targetMessage = $lang['sys_mess_spy_ennemyfleet'] . " " . $currentPlanetRow->name();
                $targetMessage .= "<a href=\"/game/galaxy?mode=3&galaxy=" . $currentPlanetRow->galaxy() . "&system=" . $currentPlanetRow->system() . "\">";
                $targetMessage .= "[" . $currentPlanetRow->coordinates() . "]</a> ";
                $targetMessage .= $lang['sys_mess_spy_seen_at'] . " " . $targetPlanetRow->name();
                $targetMessage .= " [" . $targetPlanetRow->coordinates() . "].";

                SendSimpleMessage($targetUserId, '', $fleet->departureTime(), 0, $lang['sys_mess_spy_control'], $lang['sys_mess_spy_activity'], $targetMessage);

                if ($targetChances >= $spyerChances) {
                    $this->missions->incrementGalaxyDebris($targetPlanetRow->id(), 0 + $spyToolDebris, 0);
                    $this->missions->deleteFleet($fleet->id());
                } else {
                    $this->missions->markFleetReturning($fleet->id());
                }
            }
        }

        // Retour de sondes
        if ($fleet->isReturning() && $fleet->arrivalTime() <= time()) {
            $this->missions->restoreFleet($fleetRow, true);
            $this->missions->deleteFleet($fleet->id());
        }
    }

    /**
     * Attaque de missiles interplanétaires (mission 11).
     *
     * La salve est une ligne `fleets` dont `fleet_array` porte le missile lui-même
     * (`503,n;`) : le traitement appartient à `MissileService` (dégâts, rapport,
     * fin de mission — un tir ne rentre pas).
     *
     * @param array<string, mixed> $fleetRow
     */
    public function missileStrike(array $fleetRow): void
    {
        (new MissileService())->strike($fleetRow);
    }

    public function colonise(array $fleetRow): void
    {
        global $lang;

        $fleet = Fleet::fromRow($fleetRow);

        $planetCount = $this->missions->countPlayerPlanets($fleet->ownerId());

        if ($fleet->isReturning()) {
            if ($fleet->arrivalTime() <= time()) {
                $this->missions->restoreFleet($fleetRow, true);
                $this->missions->deleteFleet($fleet->id());
            }

            return;
        }

        $galaxyPlace = $this->missions->countGalaxyPlace($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet());
        $targetAdress = sprintf($lang['sys_adress_planet'], $fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet());

        if ($galaxyPlace != 0) {
            $theMessage = $lang['sys_colo_arrival'] . $targetAdress . $lang['sys_colo_notfree'];
            SendSimpleMessage($fleet->ownerId(), '', $fleet->arrivalTime(), 0, $lang['sys_colo_mess_from'], $lang['sys_colo_mess_report'], $theMessage);
            $this->missions->markFleetReturning($fleet->id());

            return;
        }

        // Une colonie abandonnée réserve ses coordonnées un moment : la flotte
        // revient, comme devant une position occupée, mais le message dit pourquoi.
        $reservedDelay = \App\Core\GameConstants::abandonedPositionDelay();

        if ($this->planets->isPositionReserved($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet(), $reservedDelay)) {
            $theMessage = $lang['sys_colo_arrival'] . $targetAdress
                . sprintf((string) ($lang['sys_colo_reserved'] ?? $lang['sys_colo_notfree']), (int) round($reservedDelay / 3600));
            SendSimpleMessage($fleet->ownerId(), '', $fleet->arrivalTime(), 0, $lang['sys_colo_mess_from'], $lang['sys_colo_mess_report'], $theMessage);
            $this->missions->markFleetReturning($fleet->id());

            return;
        }

        if ($planetCount >= \App\Core\GameConstants::maxPlayerPlanets()) {
            $theMessage = $lang['sys_colo_arrival'] . $targetAdress . $lang['sys_colo_maxcolo'] . \App\Core\GameConstants::maxPlayerPlanets() . $lang['sys_colo_planet'];
            SendSimpleMessage($fleet->ownerId(), '', $fleet->departureTime(), 0, $lang['sys_colo_mess_from'], $lang['sys_colo_mess_report'], $theMessage);
            $this->missions->markFleetReturning($fleet->id());

            return;
        }

        $newOwnerPlanet = CreateOnePlanetRecord($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet(), $fleet->ownerId(), $lang['sys_colo_defaultname'], false);

        if ($newOwnerPlanet == true) {
            $theMessage = $lang['sys_colo_arrival'] . $targetAdress . $lang['sys_colo_allisok'];
            SendSimpleMessage($fleet->ownerId(), '', $fleet->departureTime(), 0, $lang['sys_colo_mess_from'], $lang['sys_colo_mess_report'], $theMessage);

            if ($fleet->amount() === 1) {
                $this->missions->deleteFleet($fleet->id());
            } else {
                $this->missions->removeColonizerFromFleet($fleetRow);
            }
        } else {
            $theMessage = $lang['sys_colo_arrival'] . $targetAdress . $lang['sys_colo_badpos'];
            SendSimpleMessage($fleet->ownerId(), '', $fleet->departureTime(), 0, $lang['sys_colo_mess_from'], $lang['sys_colo_mess_report'], $theMessage);
            $this->missions->markFleetReturning($fleet->id());
        }
    }

    public function recycle(array $fleetRow): void
    {
        global $lang;

        $pricelist = GameData::priceList();
        $fleet = Fleet::fromRow($fleetRow);

        if ($fleet->isReturning() || $fleet->departureTime() > time()) {
            if ($fleet->isReturning() && $fleet->arrivalTime() <= time()) {
                $this->missions->restoreFleet($fleetRow, true);
                $this->missions->deleteFleet($fleet->id());
            }

            return;
        }

        $targetGalaxy = $this->missions->findGalaxyRow($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet());

        $recyclerCapacity = 0;
        $otherFleetCapacity = 0;
        foreach ($fleet->array() as $shipId => $shipCount) {
            if ($shipId === 209) {
                $recyclerCapacity += $pricelist[$shipId]["capacity"] * $shipCount;
            } else {
                $otherFleetCapacity += $pricelist[$shipId]["capacity"] * $shipCount;
            }
        }

        $incomingFleetGoods = $fleet->cargoMetal() + $fleet->cargoCrystal() + $fleet->cargoDeuterium();
        if ($incomingFleetGoods > $otherFleetCapacity) {
            $recyclerCapacity -= ($incomingFleetGoods - $otherFleetCapacity);
        }

        if (($targetGalaxy["metal"] + $targetGalaxy["crystal"]) <= $recyclerCapacity) {
            $recycledGoods["metal"] = $targetGalaxy["metal"];
            $recycledGoods["crystal"] = $targetGalaxy["crystal"];
        } else {
            if (
                ($targetGalaxy["metal"] > $recyclerCapacity / 2) and
                ($targetGalaxy["crystal"] > $recyclerCapacity / 2)
            ) {
                $recycledGoods["metal"] = $recyclerCapacity / 2;
                $recycledGoods["crystal"] = $recyclerCapacity / 2;
            } else {
                if ($targetGalaxy["metal"] > $targetGalaxy["crystal"]) {
                    $recycledGoods["crystal"] = $targetGalaxy["crystal"];
                    if ($targetGalaxy["metal"] > ($recyclerCapacity - $recycledGoods["crystal"])) {
                        $recycledGoods["metal"] = $recyclerCapacity - $recycledGoods["crystal"];
                    } else {
                        $recycledGoods["metal"] = $targetGalaxy["metal"];
                    }
                } else {
                    $recycledGoods["metal"] = $targetGalaxy["metal"];
                    if ($targetGalaxy["crystal"] > ($recyclerCapacity - $recycledGoods["metal"])) {
                        $recycledGoods["crystal"] = $recyclerCapacity - $recycledGoods["metal"];
                    } else {
                        $recycledGoods["crystal"] = $targetGalaxy["crystal"];
                    }
                }
            }
        }

        $newCargo['Metal'] = $fleet->cargoMetal() + $recycledGoods["metal"];
        $newCargo['Crystal'] = $fleet->cargoCrystal() + $recycledGoods["crystal"];
        $newCargo['Deuterium'] = $fleet->cargoDeuterium();

        $this->missions->decrementGalaxyDebris($fleet->endGalaxy(), $fleet->endSystem(), $fleet->endPlanet(), $recycledGoods["metal"], $recycledGoods["crystal"]);

        $message = sprintf($lang['sys_recy_gotten'], pretty_number($recycledGoods["metal"]), $lang['Metal'], pretty_number($recycledGoods["crystal"]), $lang['Crystal']);
        SendSimpleMessage($fleet->ownerId(), '', $fleet->departureTime(), 4, $lang['sys_mess_spy_control'], $lang['sys_recy_report'], $message);
        $this->missions->incrementExplorerMessages($fleet->ownerId());

        $this->missions->updateFleetCargo($fleet->id(), $newCargo['Metal'], $newCargo['Crystal'], $newCargo['Deuterium']);
        $this->missions->markFleetReturning($fleet->id());
    }

    public function expedition(array $fleetRow): void
    {
        global $lang;

        $pricelist = GameData::priceList();

        $fleet = Fleet::fromRow($fleetRow);
        $fleetOwner = $fleet->ownerId();
        $messSender = $lang['sys_mess_qg'];
        $messTitle = $lang['sys_expe_report'];

        if ($fleet->isReturning()) {
            if ($fleet->arrivalTime() < time()) {
                // Le reliquat legacy qui construisait ici une requête de
                // restauration a été retiré : son résultat n'était jamais utilisé
                // et il appelait BaseRepository::escape(), protégée depuis un
                // service (erreur fatale), ce qui laissait la flotte en vol pour
                // toujours et faisait échouer chaque affichage de page.
                $this->missions->restoreFleet($fleetRow, true);
                SendSimpleMessage($fleetOwner, '', $fleet->arrivalTime(), 15, $messSender, $messTitle, $lang['sys_expe_back_home']);
                $this->missions->deleteFleet($fleet->id());
            }

            return;
        }

        if ($fleet->stayTime() >= time()) {
            return;
        }

        $pointsFlotte = array(
            202 => 1.0,
            203 => 1.5,
            204 => 0.5,
            205 => 1.5,
            206 => 2.0,
            207 => 2.5,
            208 => 0.5,
            209 => 1.0,
            210 => 0.01,
            211 => 3.0,
            212 => 0.0,
            213 => 3.5,
            214 => 5.0,
            215 => 3.2,
        );

        $ratioGain = array(
            202 => 0.1,
            203 => 0.1,
            204 => 0.1,
            205 => 0.5,
            206 => 0.25,
            207 => 0.125,
            208 => 0.5,
            209 => 0.1,
            210 => 0.1,
            211 => 0.0625,
            212 => 0.0,
            213 => 0.0625,
            214 => 0.03125,
            215 => 0.0625,
        );

        $fleetCapacity = 0;
        $fleetPoints = 0;
        $laFlotte = array();

        foreach ($fleet->array() as $typeVaisseau => $nbreVaisseau) {
            $laFlotte[$typeVaisseau] = $nbreVaisseau;
            $fleetCapacity += $pricelist[$typeVaisseau]['capacity'];
            $fleetPoints += ($nbreVaisseau * $pointsFlotte[$typeVaisseau]);
        }

        $fleetUsedCapacity = $fleet->cargoMetal() + $fleet->cargoCrystal() + $fleet->cargoDeuterium();
        $fleetCapacity -= $fleetUsedCapacity;
        $fleetCount = $fleet->amount();

        $hasard = rand(0, 10);
        $messSender = $lang['sys_mess_qg'] . "(" . $hasard . ")";

        if ($hasard < 3) {
            $hasard += 1;
            $lostAmount = (($hasard * 33) + 1) / 100;

            if ($lostAmount == 100) {
                SendSimpleMessage($fleetOwner, '', $fleet->stayTime(), 15, $messSender, $messTitle, $lang['sys_expe_blackholl_2']);
                $this->missions->deleteFleet($fleet->id());
            } else {
                $newFleetArray = '';
                foreach ($laFlotte as $ship => $count) {
                    $lostShips[$ship] = intval($count * $lostAmount);
                    $newFleetArray .= $ship . "," . ($count - $lostShips[$ship]) . ";";
                }
                $this->missions->updateFleetArray($fleet->id(), $newFleetArray);
                SendSimpleMessage($fleetOwner, '', $fleet->stayTime(), 15, $messSender, $messTitle, $lang['sys_expe_blackholl_1']);
            }
        } elseif ($hasard == 3) {
            $this->missions->markFleetReturning($fleet->id());
            SendSimpleMessage($fleetOwner, '', $fleet->stayTime(), 15, $messSender, $messTitle, $lang['sys_expe_nothing_1']);
        } elseif ($hasard >= 4 && $hasard < 7) {
            if ($fleetCapacity > 5000) {
                $foundGoods = rand($fleetCapacity - 5000, $fleetCapacity);
                $foundMetal = intval($foundGoods / 2);
                $foundCrist = intval($foundGoods / 4);
                $foundDeute = intval($foundGoods / 6);

                $this->missions->addFleetCargo($fleet->id(), $foundMetal, $foundCrist, $foundDeute);
                $message = sprintf(
                    $lang['sys_expe_found_goods'],
                    pretty_number($foundMetal),
                    $lang['Metal'],
                    pretty_number($foundCrist),
                    $lang['Crystal'],
                    pretty_number($foundDeute),
                    $lang['Deuterium']
                );
                SendSimpleMessage($fleetOwner, '', $fleet->stayTime(), 15, $messSender, $messTitle, $message);
            }
        } elseif ($hasard == 7) {
            $this->missions->markFleetReturning($fleet->id());
            SendSimpleMessage($fleetOwner, '', $fleet->stayTime(), 15, $messSender, $messTitle, $lang['sys_expe_nothing_2']);
        } elseif ($hasard >= 8 && $hasard < 11) {
            $foundChance = $fleetPoints / $fleetCount;
            $foundShip = array();
            for ($ship = 202; $ship < 216; $ship++) {
                if (isset($laFlotte[$ship]) && $laFlotte[$ship] != 0) {
                    $foundShip[$ship] = round($laFlotte[$ship] * $ratioGain[$ship]);
                    if ($foundShip[$ship] > 0) {
                        $laFlotte[$ship] += $foundShip[$ship];
                    }
                }
            }
            $newFleetArray = "";
            $foundShipMess = "";
            foreach ($laFlotte as $ship => $count) {
                if ($count > 0) {
                    $newFleetArray .= $ship . "," . $count . ";";
                }
            }
            foreach ($foundShip as $ship => $count) {
                if ($count != 0) {
                    $foundShipMess .= $count . " " . $lang['tech'][$ship] . ",";
                }
            }

            $this->missions->updateFleetArray($fleet->id(), $newFleetArray);
            $message = $lang['sys_expe_found_ships'] . $foundShipMess . "";
            SendSimpleMessage($fleetOwner, '', $fleet->stayTime(), 15, $messSender, $messTitle, $message);
        }
    }
}
