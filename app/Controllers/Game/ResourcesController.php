<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Services\ResourceService;

/**
 * La page des ressources a été déportée dans un onglet de la vue générale
 * (`/game/overview?tab=resources`) : les liens et favoris existants sont donc
 * redirigés. L'écriture des pourcentages reste traitée ici pour les formulaires
 * qui postent encore sur cette adresse (amélioration progressive).
 */
final class ResourcesController extends AbstractController
{
    private readonly ResourceService $resources;

    public function __construct()
    {
        $this->resources = \App\Services\ModuleService::instance(ResourceService::class);
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        if ($request->method() === 'POST') {
            $this->resources->buildPage($this->user(), $this->planetRow());
        }

        return Response::redirect('/game/overview?tab=resources');
    }
}
