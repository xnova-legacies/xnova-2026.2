<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;

final class BuildingsController extends AbstractController
{
    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('buildings');

        $user = $this->user();
        $planetrow = $this->planetRow();

        UpdatePlanetBatimentQueueList($planetrow, $user);
        $IsWorking = HandleTechnologieBuild($planetrow, $user);

        switch (($_GET['mode'] ?? null)) {
            case 'fleet':
                FleetBuildingPage($planetrow, $user);
                break;

            case 'research':
                ResearchBuildingPage($planetrow, $user, $IsWorking['OnWork'], $IsWorking['WorkOn']);
                break;

            case 'defense':
                DefensesBuildingPage($planetrow, $user);
                break;

            default:
                BatimentBuildingPage($planetrow, $user);
                break;
        }

        return Response::html('');
    }
}
