<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\Back\AdminController;
use App\Core\Acl;
use PHPUnit\Framework\TestCase;

/**
 * Une page du panneau = une permission du catalogue.
 *
 * Le Coeur d'application (`AdminController`) ne propose **aucune** permission par défaut : une
 * page qui n'en déclare pas est fermée à tout le monde sauf au compte
 * d'installation. Ce test lit les contrôleurs (aucune instanciation, aucun accès
 * base) et vérifie les deux sens de la correspondance : chaque page déclare une
 * permission qui existe, et chaque permission du catalogue a sa page.
 */
final class AdminPermissionsTest extends TestCase
{
    /** Permissions déclarées par les pages du panneau : contrôleur => permission. */
    private function declared(): array
    {
        $declared = array();

        foreach (glob(ROOT_PATH . 'app/Controllers/Back/*.php') ?: array() as $file) {
            $name = basename($file, '.php');

            // Le Coeur d'application est abstrait : c'est lui qui laisse la permission vide.
            if ($name === 'AdminController') {
                continue;
            }

            $source = (string) file_get_contents($file);
            $body = $this->methodSource($source, 'requiredPermission');

            self::assertNotSame('', $body, $name . ' doit déclarer requiredPermission().');
            self::assertSame(
                1,
                preg_match("/return '([^']+)';/", $body, $matches),
                $name . ' doit renvoyer une permission littérale.'
            );

            $declared[$name] = $matches[1];
        }

        return $declared;
    }

    public function testEveryPanelPageDeclaresAKnownPermission(): void
    {
        $declared = $this->declared();

        self::assertNotSame(array(), $declared);

        foreach ($declared as $controller => $permission) {
            self::assertTrue(
                Acl::exists($permission),
                $controller . ' déclare « ' . $permission . ' », absente du catalogue (App\\Core\\Acl).'
            );
            self::assertNotSame(Acl::ALL, $permission, $controller . ' ne peut pas exiger la permission « tout ».');
        }
    }

    public function testEveryCatalogPermissionHasItsPage(): void
    {
        $declared = array_flip($this->declared());

        foreach (array_keys(Acl::all()) as $permission) {
            // Les permissions des modules n'ouvrent pas une page du panneau mais une
            // fonctionnalité du jeu : elles viennent des manifestes, pas de
            // `requiredPermission()`.
            if (Acl::groupOf($permission) === 'module') {
                continue;
            }

            self::assertArrayHasKey(
                $permission,
                $declared,
                $permission . ' est au catalogue mais aucune page du panneau ne l\'exige.'
            );
        }
    }

    public function testEveryPermissionLabelExistsInAFrenchFile(): void
    {
        $sources = '';

        // `language/fr/*.mo` et son sous-dossier `admin/` : les libellés du panneau
        // vivent dans les deux (le menu dans `leftmenu.mo`, la page dans `admin.mo`).
        // Les libellés des modules appartiennent à leur module (`modules/<nom>/language/`).
        $files = array_merge(
            glob(ROOT_PATH . 'language/fr/*.mo') ?: array(),
            glob(ROOT_PATH . 'language/fr/*/*.mo') ?: array(),
            glob(ROOT_PATH . 'modules/*/language/fr/*.mo') ?: array(),
            // Un module **archivé** garde ses libellés avec lui
            // (`modules/.archive/<nom>/language/`) : la permission existe toujours.
            glob(ROOT_PATH . 'modules/.archive/*/language/fr/*.mo') ?: array()
        );

        foreach ($files as $file) {
            $sources .= (string) file_get_contents($file);
        }

        foreach (Acl::all() as $permission => $entry) {
            self::assertStringContainsString(
                "\$lang['" . $entry['label'] . "']",
                $sources,
                $permission . ' : le libellé « ' . $entry['label'] . ' » n\'existe dans aucun fichier de langue.'
            );
        }
    }

    /** Le corps d'une méthode, lu dans la source du contrôleur. */
    private function methodSource(string $source, string $method): string
    {
        $pattern = '/function\s+' . preg_quote($method, '/') . '\s*\([^)]*\)\s*(?::\s*[^\{]+)?\{(.*?)\n    \}/s';

        return preg_match($pattern, $source, $matches) === 1 ? $matches[1] : '';
    }

    public function testTheBaseControllerDeclaresNoPermission(): void
    {
        // Sans permission par défaut, une page qui oublie la sienne est fermée et
        // non ouverte : c'est le sens de la garde.
        $source = (string) file_get_contents(ROOT_PATH . 'app/Controllers/Back/AdminController.php');

        self::assertStringContainsString("return '';", $this->methodSource($source, 'requiredPermission'));
        self::assertTrue((new \ReflectionClass(AdminController::class))->isAbstract());
    }
}
