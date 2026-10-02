<?php

namespace App\Controllers\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\Request;
use App\Core\Response;

/**
 * Toute URL /game/api/... inconnue répond en JSON (jamais du HTML),
 * afin que le client puisse afficher une erreur exploitable.
 */
final class ApiNotFoundController extends ApiController
{
    public function notFoundAction(Request $request): Response
    {
        return ApiException::notFound('Point d\'entrée API inconnu.')->toResponse();
    }
}
