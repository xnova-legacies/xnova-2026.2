<?php

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Request;
use App\Core\Response;
use App\Services\PlanetStateService;

/**
 * GET /game/api/state
 *
 * État de la planète active, utilisé par l'affichage temps réel.
 * L'appel fait avancer la production et les files exactement comme un rendu de
 * page classique (même appel que la barre de navigation), afin que le client
 * reste synchronisé avec la base.
 */
final class StateApiController extends ApiController
{
    public function __construct(
        private readonly PlanetStateService $state = new PlanetStateService(),
    ) {
    }

    public function showAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();

        return $this->success($this->state->sync($user, $planet));
    }
}
