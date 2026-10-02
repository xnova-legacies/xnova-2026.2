<?php

require_once __DIR__ . '/app/bootstrap.php';

use App\Core\Api\ApiController;
use App\Core\Debug\DebugBar;
use App\Core\FleetBar;
use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\ActionService;
use App\Services\ModuleService;

// Barre de debug : active uniquement si DEBUG_BAR=1 et si la dépendance est
// installée. Démarrée avant le routage, elle couvre aussi les pages legacy.
DebugBar::boot();

// Bandeau « flottes en vol » : injecté en fin de page sur toutes les pages de
// jeu (même mécanisme de tampon que la barre de debug, sans dépendance).
FleetBar::boot();

$kernel = new Kernel(new Router());
$request = new Request();

$bootstrap = $kernel->resolve($request);

if (is_array($bootstrap)) {
    $isApi = !empty($bootstrap['api']);

    foreach ($bootstrap['constants'] as $name => $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    if ($bootstrap['constants'] !== []) {
        require_once __DIR__ . '/common.php';
    }

    // Journal des actions du joueur : une ligne par action demandée, avant
    // l'exécution du contrôleur (l'intention est gardée même si la page échoue).
    // Les robots et les sondages sont ignorés, et une erreur du journal ne doit
    // jamais priver le joueur de sa page (table absente avant migration).
    try {
        (new ActionService())->journal($request);
    } catch (\Throwable $journalError) {
        // Rien à faire : le jeu continue sans journal.
    }

    // Modules : une fonctionnalité éteinte ferme ses pages et ses appels JSON. Le
    // compte d'installation et les rôles qui administrent les modules gardent
    // l'accès (ce sont eux qui la rallument) ; le registre lit les manifestes, et
    // un manifeste cassé ne doit jamais priver le joueur de sa page.
    try {
        $moduleGuard = (new ModuleService())->guardRoute($request, $isApi);

        if ($moduleGuard !== null) {
            $moduleGuard->send();

            exit;
        }
    } catch (\Throwable $moduleError) {
        // Rien à faire : la page continue, comme si le module n'était pas gardé.
    }

    $controller = new $bootstrap['class']();

    // Les contrôleurs API gèrent eux-mêmes leurs erreurs pour garantir
    // une réponse JSON, même en cas d'exception.
    if ($controller instanceof ApiController) {
        $response = $controller->handle($bootstrap['action'], $request);
    } else {
        $response = $controller->{$bootstrap['action']}($request);
    }

    // common.php et les inclusions legacy peuvent écrire avant la réponse :
    // on purge les tampons pour ne jamais corrompre le JSON.
    if ($isApi) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    if ($response instanceof Response) {
        $response->send();
    }
} elseif (is_string($bootstrap)) {
    $legacyUri = '/' . $bootstrap;
    $_SERVER['SCRIPT_NAME'] = $legacyUri;
    $_SERVER['PHP_SELF'] = $legacyUri;

    include APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $bootstrap);
} else {
    // Adresse inconnue : le 404 est déjà parti (le routeur le pose lui-même),
    // mais la demande est journalisée — c'est ce qu'un administrateur cherche à
    // voir quand un compte sonde des adresses.
    try {
        (new ActionService())->journalUnknown($request);
    } catch (\Throwable $journalError) {
        // Sans journal, la page inconnue reste une page inconnue.
    }
}

exit;
