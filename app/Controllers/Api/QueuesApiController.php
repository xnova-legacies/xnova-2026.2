<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PlanetStateService;
use App\Services\QueueService;

/**
 * POST /game/api/queues/reorder  { domain, from, to }
 *
 * Déplace un élément dans une file d'attente. `domain` vaut `buildings`,
 * `hangar` ou `research` ; `from`/`to` sont les positions affichées (1 = en
 * cours). L'élément en cours n'est jamais déplaçable : il faut l'interrompre
 * d'abord (les ressources engagées ne sont donc jamais recalculées ici).
 */
final class QueuesApiController extends ApiController
{
    public function __construct(
        private readonly QueueService $queues = new QueueService(),
        private readonly PlanetStateService $state = new PlanetStateService(),
    ) {
    }

    public function reorderAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $payload = $this->payload($request);
        $domain = (string) ($payload['domain'] ?? '');
        $from = (int) ($payload['from'] ?? 0);
        $to = (int) ($payload['to'] ?? 0);

        if (!in_array($domain, QueueService::domains(), true)) {
            throw ApiException::validation(
                'invalid_domain',
                'File inconnue.',
                array('domain' => 'Valeurs acceptées : ' . implode(', ', QueueService::domains()) . '.')
            );
        }

        switch ($domain) {
            case QueueService::DOMAIN_BUILDINGS:
                $this->queues->reorderBuildings($user, $planet, $from, $to);
                break;

            case QueueService::DOMAIN_HANGAR:
                $this->queues->reorderHangar($planet, $from, $to);
                break;

            case QueueService::DOMAIN_RESEARCH:
                $this->queues->reorderResearch($user, $planet, $from, $to);
                break;

            default:
                throw ApiException::validation('invalid_domain', 'File inconnue.');
        }

        return $this->success(
            array('domain' => $domain, 'from' => $from, 'to' => $to),
            $this->state->snapshot($user, $planet),
            array(array('type' => 'success', 'text' => 'Ordre de la file mis à jour.'))
        );
    }
}
