<?php

namespace App\Services;

use App\Core\Format;
use App\Core\GameConfig;
use App\Core\GameConstants;
use App\Entities\Planet;
use App\Repositories\BuddyRepository;

/**
 * Construit l'état d'une planète tel qu'exposé par l'API temps réel.
 *
 * Le client reçoit les valeurs affichables (display) ET les débits horaires
 * effectifs (rates) : il peut ainsi interpoler l'affichage entre deux
 * synchronisations sans recalculer la production côté navigateur.
 */
final class PlanetStateService
{
    /**
     * Intervalle minimal entre deux écritures de production, en secondes.
     *
     * Le client interroge /state chaque seconde : enregistrer la production à
     * chaque appel écrirait inutilement en base. Entre deux écritures, elle est
     * calculée en mémoire pour l'affichage (rien n'est perdu, le client interpole
     * à partir des débits renvoyés).
     */
    public const PERSIST_INTERVAL = 5;

    public function __construct(
        private readonly QueueService $queues = new QueueService(),
        private readonly BuildingQueueService $buildingQueue = new BuildingQueueService(),
        private readonly FlyingFleetService $flyingFleets = new FlyingFleetService(),
        private readonly BuddyRepository $buddies = new BuddyRepository(),
    ) {
    }

    /**
     * Fait avancer la planète (production, chantiers, recherche) exactement
     * comme le ferait un rendu de page, puis renvoie son état.
     *
     * $planet est passé par référence, comme dans le code legacy : l'appelant
     * dispose de la version mise à jour.
     */
    public function sync(array $user, array &$planet, ?int $updateTime = null): array
    {
        $now = $updateTime ?? time();
        // Production calculée en mémoire entre deux écritures (voir PERSIST_INTERVAL).
        PlanetResourceUpdate($user, $planet, $now, !self::shouldPersist($now, (int) ($planet['last_update'] ?? 0)));

        // Termine les chantiers dont l'heure est atteinte et lance le suivant.
        // UpdatePlanetBatimentQueueList() boucle tant que la file n'est pas vide :
        // elle suppose qu'un chantier est en cours. Si ce n'est pas le cas (état
        // incohérent), on relance d'abord le premier élément, comme le fait la
        // page /game/buildings en fin de traitement.
        if ((int) ($planet['b_building_id'] ?? 0) !== 0) {
            if ((int) ($planet['b_building'] ?? 0) === 0) {
                SetNextQueueElementOnTop($planet, $user);
            } else {
                UpdatePlanetBatimentQueueList($planet, $user);
            }
        }

        // Termine la recherche en cours le cas échéant.
        HandleTechnologieBuild($planet, $user);

        return $this->snapshot($user, $planet);
    }

    /** État courant, sans écriture en base. */
    public function snapshot(array $user, array $planet): array
    {
        $gameConfig = GameConfig::load();
        $rates = ProductionService::effectiveRates($planet, $gameConfig);

        $planetRow = Planet::fromRow($planet);

        $metal = $planetRow->metal();
        $crystal = $planetRow->crystal();
        $deuterium = $planetRow->deuterium();
        // Capacités calculées depuis les niveaux des silos, jamais lues telles
        // quelles : la colonne persistée peut être en retard d'une mise à jour.
        $storageService = \App\Services\ModuleService::resolve(ProductionService::class);
        $storage = $storageService::storageCapacities($planet, $user);
        $metalMax = $storage['metal'];
        $crystalMax = $storage['crystal'];
        $deuteriumMax = $storage['deuterium'];
        $energyMax = $planetRow->energyMax();
        $energyUsed = $planetRow->energyUsed();
        $newMessage = (int) ($user['new_message'] ?? 0);
        // Demandes d'ami en attente : notifiées comme les messages non lus (badge
        // de la barre de navigation), y compris entre deux chargements de page.
        $buddyRequests = $this->buddies->countPendingRequests((int) ($user['id'] ?? 0));

        // Bandeau « flottes en vol » : le client ne redemande le fragment que
        // lorsque l'empreinte change (un vol apparaît, disparaît ou change
        // d'échéance), jamais à chaque décompte.
        $flyingFleets = $this->flyingFleets->entries((int) ($user['id'] ?? 0));

        // Files d'attente, dans l'ordre d'affichage (position 1 = en cours).
        $queues = array(
            QueueService::DOMAIN_BUILDINGS => $this->queues->buildingItems($planet),
            QueueService::DOMAIN_HANGAR => $this->queues->hangarItems($planet, $user),
            QueueService::DOMAIN_RESEARCH => $this->researchItems($planet, $user),
        );

        return array(
            'server_time' => time(),
            'revision' => $this->contentRevision($user, $planet, $queues),
            'planet' => array(
                'id' => $planetRow->id(),
                'name' => $planetRow->name(),
                // Repli à 1 (et non à 0) quand la colonne manque : conservé tel quel.
                'type' => (int) ($planet['planet_type'] ?? 1),
                'resources' => array(
                    'metal' => $metal,
                    'crystal' => $crystal,
                    'deuterium' => $deuterium,
                    'metal_max' => $metalMax,
                    'crystal_max' => $crystalMax,
                    'deuterium_max' => $deuteriumMax,
                    // Plafond de production (stockage x MAX_OVERFLOW) : le client
                    // s'en sert pour ne pas depasser le stockage en interpolant.
                    'metal_cap' => $metalMax * GameConstants::maxOverflow(),
                    'crystal_cap' => $crystalMax * GameConstants::maxOverflow(),
                    'deuterium_cap' => $deuteriumMax * GameConstants::maxOverflow(),
                ),
                'energy' => array('max' => $energyMax, 'used' => $energyUsed),
                // Débit horaire réellement appliqué, facteur d'accélération inclus :
                // le client calcule value + rate * secondes_écoulées / 3600.
                'rates' => array(
                    'metal' => $rates['metal'],
                    'crystal' => $rates['crystal'],
                    'deuterium' => $rates['deuterium'],
                ),
                'production_level' => $rates['production_level'],
                // Files d'attente, dans l'ordre d'affichage (position 1 = en cours).
                'queues' => $queues,
            ),
            'user' => array('new_message' => $newMessage, 'buddy_requests' => $buddyRequests),
            'fleets' => array(
                'count' => count($flyingFleets),
                'revision' => FlyingFleetService::fingerprint($flyingFleets),
            ),
            'display' => array(
                'metal' => $this->resourceLabel($metal, $metalMax),
                'crystal' => $this->resourceLabel($crystal, $crystalMax),
                'deuterium' => $this->resourceLabel($deuterium, $deuteriumMax),
                'energy' => $this->energyLabel($energyMax, $energyUsed),
                'message' => $newMessage,
            ),
        );
    }

    /**
     * File de recherche, dans l'ordre d'affichage (position 1 = en cours), pour
     * que le client traite les trois files de la même manière.
     *
     * @return list<array<string, mixed>>
     */
    private function researchItems(array $planet, array $user): array
    {
        return $this->queues->researchItems($user, $this->researchPlanet($planet, $user));
    }

    /**
     * Planète sur laquelle tourne la recherche : la planète active, ou une
     * colonie si la recherche y a été lancée.
     */
    private function researchPlanet(array $planet, array $user): ?array
    {
        $planetId = $this->queues->researchPlanetId($user);

        if ($planetId === 0) {
            return null;
        }

        if ($planetId === Planet::fromRow($planet)->id()) {
            return $planet;
        }

        return $this->queues->findPlanet($planetId);
    }

    /** Faut-il enregistrer la production à cet instant ? (fonction pure) */
    public static function shouldPersist(int $now, int $lastUpdate): bool
    {
        return ($now - $lastUpdate) >= self::PERSIST_INTERVAL;
    }

    /**
     * Empreinte des capacités affichées par les rendus de construction
     * (prérequis, ressources, place disponible, état de la file).
     *
     * Le client la compare à chaque synchronisation : si elle change, il
     * recharge le contenu de la page pour que les boutons s'activent sans que
     * le joueur ait à rafraîchir lui-même.
     */
    private function contentRevision(array $user, array $planet, array $queues = array()): string
    {
        $queueLength = $this->buildingQueue->queueLength($planet);
        $flags = '';

        foreach ($this->buildingQueue->allowedElements((int) ($planet['planet_type'] ?? 1)) as $element) {
            $flags .= (IsTechnologieAccessible($user, $planet, $element)
                && $this->buildingQueue->isAffordable($user, $planet, $element)) ? '1' : '0';
        }

        // Composition des files (élément et quantité, jamais les horodatages qui
        // avancent chaque seconde) : le hangar et la recherche n'ont pas d'autre
        // signal, et leurs lignes sont désormais rendues par le serveur.
        $queuesSignature = '';

        foreach ($queues as $domain => $items) {
            foreach ($items as $item) {
                $queuesSignature .= $domain . ':' . ($item['element'] ?? '') . ':'
                    . ($item['count'] ?? $item['level'] ?? '') . ';';
            }
        }

        return md5(implode('', array(
            $flags,
            $this->buildingQueue->hasRoom($planet, $queueLength) ? '1' : '0',
                $queueLength < GameConstants::maxBuildingQueueSize() ? '1' : '0',
            $queueLength === 0 ? 'empty' : 'filling',
            (int) ($planet['field_current'] ?? 0),
            $queuesSignature,
        )));
    }

    /** Reprend l'affichage de ShowTopNavigationBar() : rouge au-delà du stockage. */
    private function resourceLabel(float $value, float $max): string
    {
        $text = Format::prettyNumber($value);

        return $value > $max ? Format::colorRed($text) : $text;
    }

    /** Reprend l'affichage de ShowTopNavigationBar() : "total/max", rouge si négatif. */
    private function energyLabel(float $max, float $used): string
    {
        $total = $max + $used;
        $text = Format::prettyNumber($total) . '/' . Format::prettyNumber($max);

        return $total < 0 ? Format::colorRed($text) : $text;
    }
}
