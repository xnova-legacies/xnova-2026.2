<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Request;
use App\Core\Response;

/**
 * Page du panneau consacrée aux robots autonomes.
 *
 * Le Core de l'application ne porte que la **porte** : l'adresse (`/back/robots`), la
 * permission (`admin.robots`) et l'habillage. Le contenu appartient au module `bot`, qui
 * **dérive** cette page (`modules/bot/controllers/Back/RobotsController.php`) : c'est le
 * dossier `Back/` d'un module qui désigne le panneau, un fichier à plat désignant une page
 * du jeu.
 *
 * Sa permission est celle du **Core de l'application**, comme les autres pages du panneau
 * dont le contenu vient d'un module (`admin.chat`, `admin.notes`) : sans module, la porte
 * doit exister quand même pour dire l'absence — c'est par là qu'un administrateur
 * constate qu'il manque, et la permission `module.bot` du manifeste n'existerait pas.
 */
class RobotsController extends AdminController
{
    protected function requiredPermission(): string
    {
        return 'admin.robots';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        // Sans le module, il n'y a rien à administrer : le Coeur d'application le dit. La page reste
        // ouverte à qui porte la permission — c'est par là qu'un administrateur constate
        // l'absence.
        return $this->moduleMissing('bot');
    }
}
