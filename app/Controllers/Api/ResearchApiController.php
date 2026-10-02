<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PlanetStateService;
use App\Services\ResearchService;

/**
 * Recherche du laboratoire.
 *
 *   POST /game/api/research/start   { element }
 *   POST /game/api/research/cancel  { element }
 *
 * Comme la page /game/buildings?mode=research (cmd=search / cmd=cancel) : `start`
 * ajoute à la file du laboratoire, `cancel` interrompt la recherche en cours.
 */
final class ResearchApiController extends ApiController
{
    public function __construct(
        private readonly ResearchService $research = new ResearchService(),
        private readonly PlanetStateService $state = new PlanetStateService(),
    ) {
    }

    public function startAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $element = $this->requireElement($request);
        $this->research->start($user, $planet, $element);

        return $this->success(
            array('element' => $element),
            $this->state->snapshot($user, $planet),
            array(array('type' => 'success', 'text' => 'Recherche ajoutée à la file.'))
        );
    }

    public function cancelAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $element = $this->requireElement($request);
        $this->research->cancel($user, $planet, $element);

        return $this->success(
            array('element' => $element),
            $this->state->snapshot($user, $planet),
            array(array('type' => 'success', 'text' => 'Recherche interrompue (ressources remboursées), la suivante démarre.'))
        );
    }

    private function requireElement(Request $request): int
    {
        $payload = $this->payload($request);
        $raw = $payload['element'] ?? $payload['tech'] ?? null;

        if (!is_numeric($raw)) {
            throw ApiException::validation(
                'invalid_element',
                'Technologie non précisée.',
                array('element' => 'Identifiant de technologie manquant.')
            );
        }

        return (int) $raw;
    }
}
