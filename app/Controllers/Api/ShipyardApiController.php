<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PlanetStateService;
use App\Services\ShipyardService;

/**
 * Hangar : construction de vaisseaux et de défenses.
 *
 *   POST /game/api/shipyard/add  { kind: "fleet"|"defense", units: { 203: 5 } }
 *
 * L'action ne réimplémente aucune règle : elle présente le tableau `amounts`
 * attendu par FleetBuildingPage() / DefensesBuildingPage() (technologies,
 * ressources, plafond par ligne, file commune du hangar) puis renvoie l'état.
 */
final class ShipyardApiController extends ApiController
{
    public function __construct(
        private readonly PlanetStateService $state = new PlanetStateService(),
    ) {
    }

    public function addAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $payload = $this->payload($request);
        $kind = ShipyardService::kind($payload['kind'] ?? null);
        $units = is_array($payload['units'] ?? null) ? $payload['units'] : array();
        $units = ShipyardService::normalizeUnits($units);

        if ($kind === null) {
            throw ApiException::validation('invalid_kind', 'Type de hangar inconnu.');
        }

        if ($units === array()) {
            throw ApiException::validation('no_unit', 'Aucun élément sélectionné.');
        }

        // La file commune du hangar et les ressources servent de temoin : si rien
        // ne bouge, c'est que les technologies ou les ressources manquaient (le
        // legacy plafonne silencieusement a zero). Les ressources comptent aussi :
        // un temps de construction nul livre les unites immediatement.
        $before = $this->signature($planet);

        $_POST['amounts'] = ShipyardService::amounts($units);
        $GLOBALS['xnova_shipyard_apply'] = true;

        try {
            if ($kind === 'fleet') {
                $this->captureLegacy(static function () use (&$planet, $user): void {
                    FleetBuildingPage($planet, $user);
                });
            } else {
                $this->captureLegacy(static function () use (&$planet, $user): void {
                    DefensesBuildingPage($planet, $user);
                });
            }
        } finally {
            unset($GLOBALS['xnova_shipyard_apply'], $_POST['amounts']);
        }

        if ($this->signature($planet) === $before) {
            throw ApiException::validation(
                'nothing_queued',
                'Aucun élément ajouté : technologies ou ressources insuffisantes.',
                array('units' => 'Demande hors de portée.')
            );
        }

        return $this->success(
            array('kind' => $kind, 'units' => $units),
            $this->state->snapshot($user, $planet),
            array(array('type' => 'success', 'text' => 'Éléments ajoutés à la file du hangar.'))
        );
    }

    /** Empreinte du planete sur ce qui bouge lors d'une commande de hangar. */
    private function signature(array $planet): string
    {
        return implode('|', array(
            (string) ($planet['b_hangar_id'] ?? ''),
            (string) (float) ($planet['metal'] ?? 0),
            (string) (float) ($planet['crystal'] ?? 0),
            (string) (float) ($planet['deuterium'] ?? 0),
        ));
    }
}
