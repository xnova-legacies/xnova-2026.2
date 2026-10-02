<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Request;
use App\Core\Response;

/**
 * Adresse historique de la page « Connexions », fusionnée dans la vue générale.
 *
 * L'historique des connexions **est** la vue générale de l'administration
 * (`OverviewController`, `/back/overview`) : cette adresse ne sert plus qu'à
 * conduire vers la nouvelle les liens déjà en circulation, filtres compris.
 */
final class SessionsController extends AdminController
{
    protected function requiredPermission(): string
    {
        return 'admin.overview';
    }

    public function indexAction(Request $request): Response
    {
        $query = http_build_query($_GET);

        return Response::redirect(OverviewController::PATH . ($query === '' ? '' : '?' . $query));
    }
}
