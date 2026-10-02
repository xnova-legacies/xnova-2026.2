<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PlanetService;
use App\Services\PlanetStateService;

/**
 * POST /game/api/profil/rename  { newname }
 *
 * Renomme la planète (ou la lune) active, comme le formulaire de
 * /game/overview?mode=renameplanet.
 */
final class ProfilApiController extends ApiController
{
    public function __construct(
        private readonly PlanetService $planets = new PlanetService(),
        private readonly PlanetStateService $state = new PlanetStateService(),
    ) {
    }

    public function renameAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $name = PlanetService::cleanName($this->payload($request)['newname'] ?? '');

        if ($name === '') {
            throw ApiException::validation(
                'invalid_name',
                'Nom de planète invalide.',
                array('newname' => 'Merci de saisir un nom.')
            );
        }

        $this->planets->rename($planet, $name);

        return $this->success(
            array('name' => $name),
            $this->state->snapshot($user, $planet),
            array(array('type' => 'success', 'text' => 'Planète renommée.'))
        );
    }
}
