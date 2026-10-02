<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;
use App\Services\MissileService;

/**
 * Tir de missiles interplanétaires (formulaire de la galaxie).
 *
 * Le tir est une **mission de flotte** (mission 11, table `fleets`) : ce
 * contrôleur ne fait que valider la demande — silo, portée, cible existante,
 * stock — puis confie la salve à `MissileService`. C'est la même règle que la
 * page /game/mipattack : une seule implémentation du tir.
 *
 * Adresse publique `/game/missile-attack` (l'ancienne adresse allemande redirige, le formulaire
 * de la galaxie la poste) ; le fichier, lui, suit la règle de nommage moderne.
 */
final class MissileLaunchController extends AbstractController
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
        $target = $_POST['Target'] ?? 'all';

        $targetPlanet = $this->planets->findByCoords($galaxy, $system, $planet, 1);
        $refusal = MissileService::refusal($user, $currentplanet, $targetPlanet, $galaxy, $system, $count);

        if ($refusal !== null) {
            return $this->renderMessage($refusal, 'Erreur');
        }

        $primary = ($target === 'all') ? 0 : (int) $target;

        $this->missiles->launch($user, $currentplanet, $targetPlanet, $count, $primary, (int) $gameConfig['game_speed']);

        $parse = array(
            'dpath' => (!$user['dpath']) ? DEFAULT_SKINPATH : $user['dpath'],
            'g' => $galaxy,
            's' => $system,
            'i' => $planet,
            'anz' => $count,
            'n' => ($count === 1) ? '' : 'n',
        );

        return Response::raw($this->parse($this->template('missile_launch_result'), $parse));
    }
}
