<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\GameConstants;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\BuddyRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;
use App\Services\FleetDispatchService;
use App\Services\PlanetStateService;

/**
 * Envoi de flotte.
 *
 *   POST /game/api/fleet/estimate  { ships, galaxy, system, planet, planet_type }
 *   POST /game/api/fleet/send      { ships, mission, galaxy, system, planet,
 *                                    planet_type, metal?, crystal?, deuterium?, stay? }
 *
 * `ships` est un objet { "203": 5, "204": 3 }. L'origine est toujours la
 * planète active (elle ne peut donc pas être falsifiée, contrairement au
 * formulaire historique qui la reposte).
 */
final class FleetApiController extends ApiController
{
    public function __construct(
        private readonly FleetDispatchService $fleet = new FleetDispatchService(),
        private readonly PlanetStateService $state = new PlanetStateService(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly BuddyRepository $buddies = new BuddyRepository(),
    ) {
    }

    public function estimateAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $payload = $this->payload($request);
        $target = $this->target($payload, $user, 0);
        $estimated = $this->fleet->estimate(
            $this->ships($payload),
            $user,
            $planet,
            $target,
            $payload['speed'] ?? FleetDispatchService::MAX_SPEED
        );

        return $this->success($estimated);
    }

    public function sendAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $payload = $this->payload($request);
        $mission = (int) ($payload['mission'] ?? 0);
        $target = $this->target($payload, $user, $mission);

        // « Stationner chez un allié » : la cible doit être un ami accepté ou un
        // membre de la même alliance (règle partagée avec la page classique).
        if ($mission === 5 && (string) ($target['owner'] ?? '') !== '') {
            $TargetUser = $this->users->findFullById((int) $target['owner']);
            $BuddyRow = $this->buddies->findBetweenUsers((int) $user['id'], (int) $target['owner']);
            $IsFriend = $BuddyRow !== false && (int) ($BuddyRow['active'] ?? 0) === 1;

            if (
                is_array($TargetUser)
                && !FleetDispatchService::holdTargetAllowed($user, $TargetUser, $IsFriend)
            ) {
                throw ApiException::forbidden('not_friendly_target', 'Vous ne pouvez stationner que chez un ami ou un membre de votre alliance.');
            }
        }

        $result = $this->fleet->send(
            $user,
            $planet,
            $this->ships($payload),
            $target,
            $mission,
            $payload['speed'] ?? FleetDispatchService::MAX_SPEED,
            array(
                'metal' => $payload['metal'] ?? 0,
                'crystal' => $payload['crystal'] ?? 0,
                'deuterium' => $payload['deuterium'] ?? 0,
            ),
            $this->stayHours($payload)
        );

        return $this->success(
            array(
                'mission' => $mission,
                'distance' => $result['estimate']['distance'],
                'duration' => $result['estimate']['duration'],
                'consumption' => $result['estimate']['consumption'],
            ),
            $this->state->snapshot($user, $planet),
            array(array('type' => 'success', 'text' => 'Flotte envoyée.'))
        );
    }

    /** @return array<int, int> */
    private function ships(array $payload): array
    {
        $ships = $payload['ships'] ?? array();

        if (!is_array($ships)) {
            throw ApiException::validation('invalid_ships', 'Liste de vaisseaux invalide.');
        }

        return $ships;
    }

    /**
     * Durée de maintien sur place, en heures. Le formulaire historique utilise
     * deux champs distincts selon la mission (stationnement / expédition).
     */
    private function stayHours(array $payload): int
    {
        foreach (array('stay', 'holdingtime', 'expeditiontime') as $field) {
            if (isset($payload[$field]) && is_numeric($payload[$field])) {
                return max(0, (int) $payload[$field]);
            }
        }

        return 0;
    }

    /**
     * Valide les coordonnées de la cible et retrouve son propriétaire.
     *
     * @return array{galaxy: int, system: int, planet: int, planet_type: int, owner: string}
     */
    private function target(array $payload, array $user, int $mission): array
    {
        $galaxy = (int) ($payload['galaxy'] ?? 0);
        $system = (int) ($payload['system'] ?? 0);
        $planet = (int) ($payload['planet'] ?? 0);
        $type = (int) ($payload['planet_type'] ?? 1);

        if ($galaxy < 1 || $galaxy > GameConstants::maxGalaxyInWorld()) {
            throw ApiException::validation('invalid_galaxy', 'Galaxie hors limites.', array('galaxy' => 'Valeur hors limites.'));
        }

        if ($system < 1 || $system > GameConstants::maxSystemInGalaxy()) {
            throw ApiException::validation('invalid_system', 'Système hors limites.', array('system' => 'Valeur hors limites.'));
        }

        if ($planet < 1 || $planet > GameConstants::maxPlanetInSystem() + 1) {
            throw ApiException::validation('invalid_planet', 'Position hors limites.', array('planet' => 'Valeur hors limites.'));
        }

        if (!in_array($type, array(1, 2, 3), true)) {
            throw ApiException::validation('invalid_planet_type', 'Type de cible inconnu.', array('planet_type' => 'Valeur hors limites.'));
        }

        $row = $this->planets->findByCoords($galaxy, $system, $planet, $type);

        // Cible protégée : les missions offensives y sont interdites (règle legacy).
        if (
            is_array($row)
            && (int) ($row['id_level'] ?? 0) > (int) $user['authlevel']
            && in_array($mission, array(1, 2, 6, 9), true)
        ) {
            throw ApiException::forbidden('protected_target', 'Cette cible est protégée.');
        }

        return array(
            'galaxy' => $galaxy,
            'system' => $system,
            'planet' => $planet,
            'planet_type' => $type,
            // Même convention que le moteur : chaîne vide quand la cible est déserte.
            'owner' => is_array($row) ? (string) ($row['id_owner'] ?? '') : '',
        );
    }
}
