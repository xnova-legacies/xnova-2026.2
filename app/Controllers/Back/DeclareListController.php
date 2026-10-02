<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Request;
use App\Core\Response;

/**
 * Adresse historique de la liste des IP collectives, fusionnée dans la page des
 * multi-comptes (`MultiController`, `/back/multi?tab=declared`).
 */
final class DeclareListController extends AdminController
{
    protected function requiredPermission(): string
    {
        return 'admin.multi';
    }

    public function indexAction(Request $request): Response
    {
        return Response::redirect(MultiController::PATH . '?tab=declared');
    }
}
