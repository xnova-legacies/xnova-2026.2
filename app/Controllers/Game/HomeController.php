<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;

final class HomeController extends AbstractController
{
    public static function legacyConstants(): array
    {
        return [];
    }

    public function indexAction(Request $request): Response
    {
        $configFile = APP_ROOT . '/configs/config.php';

        if (!is_file($configFile) || filesize($configFile) === 0) {
            return $this->redirect('install/');
        }

        return $this->redirect('/front/login');
    }
}
