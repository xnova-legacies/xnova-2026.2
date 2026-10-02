<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\BuildingQueueService;
use App\Services\PlanetStateService;

/**
 * File de construction des bâtiments (/game/buildings).
 *
 *   POST /game/api/buildings/add      { element }
 *   POST /game/api/buildings/destroy  { element }
 *   POST /game/api/buildings/cancel
 *   POST /game/api/buildings/remove   { position }
 *
 * Les actions sont volontairement minces : les règles du jeu vivent dans
 * BuildingQueueService, qui réutilise les primitives legacy. La réponse
 * contient le nouvel état de la planète (`state`), ce qui évite au client
 * un second appel.
 */
final class BuildingsApiController extends ApiController
{
    public function __construct(
        private readonly BuildingQueueService $queue = new BuildingQueueService(),
        private readonly PlanetStateService $state = new PlanetStateService(),
    ) {
    }

    public function addAction(Request $request): Response
    {
        return $this->enqueue($request, false);
    }

    public function destroyAction(Request $request): Response
    {
        return $this->enqueue($request, true);
    }

    public function cancelAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $this->queue->cancel($user, $planet);

        return $this->success(
            array('cancelled' => true),
            $this->state->snapshot($user, $planet),
            array(array(
                'type' => 'success',
                'text' => 'Chantier interrompu, ressources remboursées.',
            ))
        );
    }

    public function removeAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $position = (int) ($this->payload($request)['position'] ?? 0);
        $this->queue->remove($user, $planet, $position);

        return $this->success(
            array('position' => $position),
            $this->state->snapshot($user, $planet),
            array(array(
                'type' => 'success',
                'text' => 'Élément retiré de la file de construction.',
            ))
        );
    }

    /** Traitement commun aux ajouts de construction et de destruction. */
    private function enqueue(Request $request, bool $destroy): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $element = $this->requireElement($request);
        $result = $this->queue->add($user, $planet, $element, $destroy);

        if ($result['dropped']) {
            $messages = array(array(
                'type' => 'warning',
                'text' => 'Ressources insuffisantes : le chantier n\'a pas pu démarrer.',
            ));
        } else {
            $messages = array(array(
                'type' => 'success',
                'text' => $destroy
                    ? 'Destruction ajoutée à la file.'
                    : 'Construction ajoutée à la file.',
            ));
        }

        return $this->success(
            array('element' => $element, 'destroy' => $destroy),
            $this->state->snapshot($user, $planet),
            $messages
        );
    }

    private function requireElement(Request $request): int
    {
        $payload = $this->payload($request);
        $raw = $payload['element'] ?? $payload['building'] ?? null;

        if (!is_numeric($raw)) {
            throw ApiException::validation(
                'invalid_element',
                'Bâtiment non précisé.',
                array('element' => 'Identifiant de bâtiment manquant.')
            );
        }

        return (int) $raw;
    }
}
