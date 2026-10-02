<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;
use App\Services\MissileService;

/**
 * Tir de missiles interplanétaires (adresse historique /game/mipattack).
 *
 * Même règle que la page de la galaxie : la salve devient une **mission de
 * flotte** (mission 11) confiée à `MissileService`, la validation se fait avant
 * tout débit, et l'impact est résolu par le moteur de missions — plus rien n'est
 * appliqué immédiatement ici.
 */
final class MipAttackController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly MissileService $missiles = new MissileService(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $user = $this->user();
        $gameConfig = $this->gameConfig();

        $currentplanet = $this->planets->findCurrentById((int) $user['current_planet']);
        $galaxy = (int) ($_GET['galaxy'] ?? 0);
        $system = (int) ($_GET['system'] ?? 0);
        $planet = (int) ($_GET['planet'] ?? 0);
        $count = (int) ($_POST['SendMI'] ?? 0);

        $targetPlanet = $this->planets->findByCoords($galaxy, $system, $planet, 1);
        $refusal = MissileService::refusal($user, $currentplanet, $targetPlanet, $galaxy, $system, $count);

        if ($refusal !== null) {
            // Silo insuffisant, coordonnées hors de portée, colonie abandonnée
            // (elle n'est plus une planète) ou stock épuisé : un seul texte de refus.
            return $this->renderMessage($refusal, 'Erreur');
        }

        $this->missiles->launch($user, $currentplanet, $targetPlanet, $count, 0, (int) $gameConfig['game_speed']);

        return $this->renderMessage(
            $count . ' missile(s) en route vers [' . $galaxy . ':' . $system . ':' . $planet . '].',
            'Attaque de missiles'
        );
    }
}
