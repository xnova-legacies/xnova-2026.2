<?php

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\GameData;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlanetRepository;
use App\Services\PlanetStateService;

/**
 * POST /game/api/resources/percent
 *
 * Répartition de la production (0 à 100 %, par pas de 10) comme le formulaire de
 * /game/resources. Cette action est la version JSON ; le POST classique reste
 * géré par ResourceService pour la dégradation sans JavaScript.
 *
 * Entrée acceptée :
 *   { "percent": { "metal_mine": 60, "crystal_mine": 40 } }
 * ou directement les champs du formulaire :
 *   { "metal_mine": 60, "crystal_mine": 40 }
 * avec les alias courts metal / crystal / deuterium / solar / fusion / satellite.
 */
final class ResourcesApiController extends ApiController
{
    /** Valeurs acceptées par le formulaire legacy. */
    private const ALLOWED_PERCENTS = array(0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100);

    /** Alias courts -> bâtiment producteur. */
    private const ALIASES = array(
        'metal' => 'metal_mine',
        'crystal' => 'crystal_mine',
        'deuterium' => 'deuterium_sintetizer',
        'solar' => 'solar_plant',
        'fusion' => 'fusion_plant',
        'satellite' => 'solar_satelit',
    );

    public function __construct(
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly PlanetStateService $state = new PlanetStateService(),
    ) {
    }

    public function percentAction(Request $request): Response
    {
        $user = $this->requireUser();
        $planet = $this->requirePlanet();
        $this->requireCsrf($request);

        $input = $this->payload($request);
        $percent = $input['percent'] ?? $input;

        if (!is_array($percent) || $percent === array()) {
            throw ApiException::validation('invalid_payload', 'Aucun pourcentage reçu.');
        }

        $known = $this->knownProductionFields();
        $invalid = array();
        $applied = array();

        foreach ($percent as $name => $value) {
            $name = (string) $name;

            if ($name === '' || $name === '_token' || $name === 'action') {
                continue;
            }

            $field = self::ALIASES[$name] ?? $name;

            if (!isset($known[$field])) {
                $invalid[$name] = 'Champ de production inconnu.';
                continue;
            }

            if (!is_numeric($value) || !in_array((int) $value, self::ALLOWED_PERCENTS, true)) {
                $invalid[$name] = 'Pourcentage invalide (0 à 100, par pas de 10).';
                continue;
            }

            $stored = intdiv((int) $value, 10); // le legacy stocke 0..10 en base
            $applied[$field] = $stored;
        }

        if ($invalid !== array()) {
            throw ApiException::validation('invalid_percent', 'Pourcentages invalides.', $invalid);
        }

        if ($applied === array()) {
            throw ApiException::validation('invalid_payload', 'Aucun pourcentage exploitable reçu.');
        }

        // Le dépôt compose le `SET` : les colonnes arrivent nommées, les valeurs liées.
        $columns = array();

        foreach ($applied as $field => $stored) {
            $columns[$field . '_porcent'] = $stored;
        }

        $this->planets->updatePorcents((int) $planet['id'], $columns);

        // Le calcul de production lit les pourcentages depuis la planète :
        // on applique les mêmes valeurs en mémoire avant de recalculer.
        foreach ($applied as $field => $stored) {
            $planet[$field . '_porcent'] = $stored;
        }

        return $this->success(
            array('updated' => $applied),
            $this->state->sync($user, $planet),
            array(array('type' => 'success', 'text' => 'Répartition de la production enregistrée.'))
        );
    }

    /**
     * Colonnes de pourcentage autorisées, déduites des bâtiments producteurs
     * (resource[1] => 'metal_mine', 212 => 'solar_satelit', ...).
     *
     * @return array<string, true>
     */
    private function knownProductionFields(): array
    {
        $resource = GameData::resource();
        $known = array();

        foreach (GameData::resList()['prod'] as $element) {
            $field = $resource[$element] ?? null;

            if (is_string($field) && $field !== '') {
                $known[$field] = true;
            }
        }

        return $known;
    }
}
