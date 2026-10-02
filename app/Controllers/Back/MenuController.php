<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Request;
use App\Core\Response;

/**
 * Menu du panneau d'administration (le « hub » ouvert depuis la barre du jeu).
 *
 * Reprend `admin/leftmenu.php`. Le menu lui-même est rendu par
 * `AdminController::adminMenu()` : cette page ne fait que l'afficher seul, sans
 * le doubler dans la colonne latérale.
 */
final class MenuController extends AdminController
{
    protected function requiredPermission(): string
    {
        return 'admin.menu';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        return $this->adminPage($this->adminMenu(), '', false);
    }
}
