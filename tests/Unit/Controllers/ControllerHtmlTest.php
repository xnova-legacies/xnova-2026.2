<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Un contrôleur prépare des données, il ne fabrique pas de balisage : le balisage
 * vit dans `app/View/OpenGame/*.tpl`, chargé puis rempli par
 * `AbstractController::partial()` (ou `TemplateEngine::render()` depuis un
 * service). Une ligne répétée est le même gabarit rempli en boucle ; un bouton
 * réutilisé est un gabarit unique appelé plusieurs fois.
 *
 * Les fichiers encore listés ci-dessous concatènent du HTML : la liste ne doit que
 * rétrécir. Migrer un fichier = retirer son nom d'ici ; en ajouter un serait un
 * retour en arrière. Un fichier déplacé dans son module change de chemin (même
 * travail restant) : c'est la seule raison de réécrire une entrée.
 */
final class ControllerHtmlTest extends TestCase
{
    /** Contrôleurs à migrer (balisage encore écrit dans le PHP), par chemin relatif. */
    private const HTML_IN_CONTROLLERS = array(
        // Reste, dans le module des alliances : le mini-BBCode des textes (`bbcodeToHtml()`,
        // un **formateur** — ses motifs produisent du balisage) et la redirection en
        // JavaScript de `indexAction`. Le reste de la page est passé en gabarits
        // (`alliance_info_*`, `alliance_member_online`, `alliance_admin_laws_empty`,
        // `alliance_admin_give*`, `alliance_admin_request_empty`).
        'modules/alliance/controllers/AllianceController.php',
        'app/Controllers/Game/FleetController.php',
        'app/Controllers/Game/InfosController.php',
        'app/Controllers/Game/OverviewController.php',
        'app/Controllers/Game/ProfilController.php',
        'app/Controllers/Game/StatsController.php',
        'app/Controllers/Game/AcsController.php',
    );

    /** Gabarits historiquement identiques (en-tête jeu / en-tête admin). */
    private const DUPLICATE_TEMPLATES = array(
        'app/View/OpenGame/admin/simple_header.tpl',
        'app/View/OpenGame/simple_header.tpl',
    );

    public function testControllersDoNotBuildHtml(): void
    {
        $found = array();

        foreach ($this->controllerFiles() as $file) {
            if (preg_match('/[\'"]<[a-zA-Z\/!]/', (string) file_get_contents($file)) === 1) {
                $found[] = $file;
            }
        }

        sort($found);

        // Un module peut ne pas être déposé : le Core tourne avec `modules/` vide, et c'est
        // ainsi que l'intégration le vérifie. Une entrée de module ne compte donc que s'il est
        // là ; les entrées du Core, elles, restent exigées (la liste ne doit que rétrécir).
        $expected = array_values(array_filter(
            self::HTML_IN_CONTROLLERS,
            static fn (string $file): bool => !str_starts_with($file, 'modules/') || is_file(ROOT_PATH . $file)
        ));

        sort($expected);

        self::assertSame(
            $expected,
            $found,
            'Le balisage doit vivre dans un gabarit (AbstractController::partial()).'
        );
    }

    public function testTemplatesAreNotDuplicated(): void
    {
        $groups = array();

        foreach ($this->templateFiles() as $template => $file) {
            // Deux fichiers au contenu identique à la mise en forme près : c'est
            // une copie de gabarit, à fusionner en un seul fichier réutilisé.
            $key = sha1((string) preg_replace('/\s+/', ' ', trim((string) file_get_contents($file))));
            $groups[$key][] = $template;
        }

        foreach ($groups as $same) {
            if (count($same) < 2) {
                continue;
            }

            sort($same);

            self::assertSame(
                self::DUPLICATE_TEMPLATES,
                $same,
                'Deux gabarits identiques : n\'en garder qu\'un, appelé par les deux pages.'
            );
        }
    }

    /**
     * Contrôleurs du jeu et des modules, par chemin relatif à la racine.
     *
     * Un module suit le même réponse que le Coeur d'application : son balisage vit dans ses
     * gabarits (`modules/<nom>/view/`).
     *
     * @return list<string>
     */
    private function controllerFiles(): array
    {
        $found = array();

        foreach (array_merge(array(ROOT_PATH . 'app/Controllers'), (array) glob(ROOT_PATH . 'modules/*/controllers', GLOB_ONLYDIR)) as $directory) {
            foreach ($this->files($directory, 'php') as $file) {
                $found[] = $this->relative($file);
            }
        }

        sort($found);

        return $found;
    }

    /** @return array<string, string> chemin du gabarit => fichier */
    private function templateFiles(): array
    {
        $found = array();
        $roots = array_merge(array(ROOT_PATH . 'app/View'), (array) glob(ROOT_PATH . 'modules/*/view', GLOB_ONLYDIR));

        foreach ($roots as $root) {
            foreach ($this->files($root, 'tpl') as $file) {
                $found[$this->relative($file)] = $file;
            }
        }

        return $found;
    }

    /**
     * Chemin d'un fichier relatif à la racine du projet, séparateurs normalisés.
     *
     * `ROOT_PATH` porte le séparateur du système (`\` sous Windows) alors que `files()`
     * normalise déjà en `/` : la comparaison directe ne retirait rien sous Windows, et le
     * test ne passait que sur un système de fichiers à `/`.
     */
    private function relative(string $file): string
    {
        $root = str_replace('\\', '/', rtrim(ROOT_PATH, '/\\')) . '/';

        return str_replace($root, '', str_replace('\\', '/', $file));
    }

    /** @return list<string> */
    private function files(string $directory, string $extension): array
    {
        $found = array();

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->isFile() && $file->getExtension() === $extension) {
                $found[] = str_replace('\\', '/', (string) $file->getPathname());
            }
        }

        sort($found);

        return $found;
    }
}
