<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\FleetBar;
use App\Core\Request;
use App\Core\Response;
use App\Services\FlyingFleetService;

/**
 * Bandeau « flottes en vol ».
 *
 *   GET /game/api/fleets
 *
 * Renvoie le fragment HTML du panneau tel que le serveur le rend, avec le
 * nombre de vols et l'empreinte correspondante. Le client remplace le contenu du
 * bandeau par ce fragment dès que l'état temps réel annonce une empreinte
 * différente : une seule mise en forme, côté serveur (App\Core\FleetBar).
 */
final class FleetsApiController extends ApiController
{
    public function __construct(
        private readonly FlyingFleetService $fleets = new FlyingFleetService(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $user = $this->requireUser();
        $entries = $this->fleets->entries((int) $user['id']);
        $lang = $GLOBALS['lang'] ?? array();

        return $this->success(array(
            'html' => FleetBar::renderList($entries, FleetBar::labels(is_array($lang) ? $lang : array())),
            'count' => count($entries),
            'revision' => FlyingFleetService::fingerprint($entries),
        ));
    }
}
