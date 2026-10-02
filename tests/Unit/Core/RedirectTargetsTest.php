<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Controllers\Front\LoginController;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Où mènent les redirections vers la connexion.
 *
 * Une redirection **relative** (`Location: login.php`) est résolue par le navigateur
 * contre le dossier courant : depuis `/game/overview`, elle désignait
 * `/game/login.php`, une adresse qui n'existe pas — le joueur déconnecté tombait sur
 * un 404 au lieu de la page de connexion (vécu après une déconnexion). Le panneau a
 * eu le même défaut avec `/game/login`.
 *
 * La cible unique est `/front/login` : ce test vérifie qu'elle est bien servie et
 * qu'aucune source ne redirige ailleurs.
 */
final class RedirectTargetsTest extends TestCase
{
    /** Adresse de la page de connexion, seule cible admise. */
    private const LOGIN = '/front/login';

    public function testTheLoginPageIsServed(): void
    {
        $route = (new Router())->match('front/login');

        self::assertNotNull($route, 'La cible des redirections doit répondre.');
        self::assertSame(LoginController::class, $route['class']);
        self::assertSame('indexAction', $route['action']);

        // L'adresse historique mène aussi à la page moderne.
        self::assertSame(self::LOGIN, (new Router())->redirect301('login.php'));
    }

    public function testNoSourceRedirectsToARelativeOrWrongLoginAddress(): void
    {
        $problems = array();

        foreach ($this->sources() as $file) {
            $source = (string) file_get_contents($file);

            foreach (
                array(
                '/Location:\s*[\'"]?login\.php/',   // relative : dépend du dossier courant
                '/Location:\s*[\'"]?\/game\/login/', // adresse inexistante
                ) as $pattern
            ) {
                if (preg_match($pattern, $source) === 1) {
                    $problems[] = str_replace(ROOT_PATH, '', $file) . ' redirige vers ' . $pattern;
                }
            }
        }

        self::assertSame(
            array(),
            $problems,
            "La page de connexion s'adresse en absolu (" . self::LOGIN . ") :\n" . implode("\n", $problems)
        );
    }

    /** @return array<int, string> */
    private function sources(): array
    {
        $files = array(
            ROOT_PATH . 'common.php',
            ROOT_PATH . 'index.php',
        );

        foreach (array('app/*/*.php', 'app/*/*/*.php', 'includes/*.php', 'includes/*/*.php') as $pattern) {
            foreach ((array) glob(ROOT_PATH . $pattern) as $file) {
                $files[] = (string) $file;
            }
        }

        return $files;
    }
}
