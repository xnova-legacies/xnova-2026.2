<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Modules;
use PHPUnit\Framework\TestCase;

/**
 * Registre des modules : découverte des manifestes et résolution du code d'un module.
 *
 * Tout est pur (aucune base, aucune écriture) : ce qui décide — quels modules
 * existent, quelles adresses ils gardent, où vit leur code — se lit sans MySQL.
 */
final class ModulesTest extends TestCase
{
    /**
     * Le Core de l'application est livré **sans** module : `modules/` ne contient que son
     * réponse (`README.md`), et le jeu tourne ainsi — c'est vérifié page par page.
     *
     * C'est une propriété de l'**état livré** (les modules sont archivés hors de l'historique),
     * pas de l'arbre de travail : le test s'écarte quand des modules y sont déposés, et les
     * règles de manifeste se vérifient alors dans le test suivant.
     */
    public function testTheGameIsShippedWithoutAnyModule(): void
    {
        if (Modules::names() !== array()) {
            self::markTestSkipped('Des modules sont déposés : l\'arbre de travail n\'est pas l\'état livré.');
        }

        self::assertSame(array(), Modules::names(), 'Aucun module n\'est livré avec le Core de l\'application.');
    }

    /** Ce que le registre attend de **tout** manifeste déposé, dans les deux états du dépôt. */
    public function testEveryDepositedModuleDeclaresAValidManifest(): void
    {
        if (Modules::names() === array()) {
            self::markTestSkipped('Aucun module déposé : rien à vérifier dans la version livrée.');
        }

        foreach (Modules::names() as $name) {
            self::assertMatchesRegularExpression('/^[a-z0-9][a-z0-9_-]*$/', $name, 'Le dossier du module est un nom technique.');
            self::assertNotSame('', Modules::label($name), $name . ' doit annoncer un libellé.');
            // Tous les modules n'ouvrent pas une page : celui qui n'apporte qu'un
            // vaisseau et une mission (Extracteurs) ne déclare aucune adresse. Quand il en
            // déclare une, elle est absolue.
            $page = Modules::page($name);
            self::assertTrue($page === '' || str_starts_with($page, '/'), $name . ' annonce une adresse absolue.');
            self::assertSame('module.' . $name, Modules::permission($name));
        }
    }

    public function testTheModuleGroupIsSortedByItsDeclaredOrder(): void
    {
        $orders = array();

        foreach (Modules::all() as $module) {
            $orders[] = (int) $module['order'];
        }

        $sorted = $orders;
        sort($sorted);

        self::assertSame($sorted, $orders, 'Le catalogue suit l\'ordre d\'affichage déclaré.');
    }

    /**
     * Un module apporte un Coeur d'application propre (`core/`) quand son besoin ne ressemble à
     * rien du jeu : le dossier est déclaré, et le namespace suit le module.
     */
    public function testThePackageDirectoriesAreTheOnesOfTheGame(): void
    {
        self::assertSame(
            array('core', 'controllers', 'entities', 'repositories', 'services', 'view', 'language'),
            Modules::DIRECTORIES,
            'Le module d\'un module suit le découpage de `app/`, Coeur d\'application et langue compris.'
        );
    }

    public function testAModuleClassIsResolvedInsideItsOwnPackage(): void
    {
        $path = Modules::classPath('Modules\\Chat\\Core\\Salon');

        self::assertNotNull($path, 'Une classe de module est résolue dans le module du module.');
        self::assertSame(logicalPaths(Modules::directory() . 'chat/core/Salon.php'), logicalPaths((string) $path));

        // Le deuxième segment est le dossier du module, insensible à la casse.
        self::assertSame(
            logicalPaths(Modules::directory() . 'marchand/services/Cote.php'),
            logicalPaths((string) Modules::classPath('Modules\\Marchand\\Services\\Cote'))
        );

        // Un sous-dossier profond suit le namespace.
        self::assertSame(
            logicalPaths(Modules::directory() . 'marchand/core/Combat/Impact.php'),
            logicalPaths((string) Modules::classPath('Modules\\Marchand\\Core\\Combat\\Impact'))
        );
    }

    /** Le registre ne sert pas de résolveur général : hors d'un module, il refuse. */
    public function testTheRegistrarRefusesEverythingThatIsNotModuleCode(): void
    {
        foreach (
            array(
                'App\\Core\\Acl',                          // le Coeur d'application du jeu
                'Modules\\Chat',                           // une classe à la racine du module
                'Modules\\Chat\\Core',                     // un dossier n'est pas une classe
                'Modules\\Chat\\Lib\\Salon',               // dossier non déclaré
                'Modules\\Chat\\core\\..\\..\\config',     // tentative de sortie du module
                'Modules\\Chat\\Core\\Salon\\..\\..\\x',   // segment de chemin
                'Modules\\\\Core\\Salon',                  // nom de module vide
            ) as $class
        ) {
            self::assertNull(Modules::classPath($class), $class . ' ne doit pas être résolu.');
        }
    }

    /** Un module ne contient que les dossiers déclarés : le reste serait du code que rien ne lit. */
    public function testAPackageOnlyCarriesDeclaredDirectories(): void
    {
        foreach (Modules::names() as $name) {
            foreach ((array) glob(Modules::directory() . $name . '/*', GLOB_ONLYDIR) as $path) {
                self::assertContains(
                    basename((string) $path),
                    array_merge(Modules::DIRECTORIES, Modules::DATA_DIRECTORIES),
                    $name . ' porte un dossier que le registre ne sait pas lire.'
                );
            }
        }

        // Une classe annoncée mais absente ne casse rien : le registre répond `null`
        // et l'autoloader laisse la main aux autres résolveurs.
        self::assertNull(Modules::classFile('Modules\\Chat\\Core\\Introuvable'));
    }

    /**
     * Surcharge d'une classe du Core de l'application : la **convention** suffit (même nom
     * court, même couche), rien à déclarer au manifeste — et une classe que personne ne
     * dérive reste celle du Core.
     *
     * Les modules qui dérivaient ici (`extracteurs` pour le moteur de combat et le
     * contrôleur des flottes, `officier` pour le service des ressources) sont archivés : la
     * convention se vérifie donc par ses **refus**, et elle se réarme dès qu'un module est
     * reposé. `tests/Unit/Repositories/RepositoryCoverageTest.php` tient alors la règle
     * « une classe que le registre résout ne peut pas être `final` ».
     */
    public function testAnOverrideIsDetectedByConvention(): void
    {
        // La même règle dans les deux sens : sans module, la classe du Core est seule ; avec
        // lui, la surcharge **se déduit du dossier** (même nom court, même couche) et la
        // classe trouvée doit **hériter** de celle du Core.
        $derived = array(
            'App\\Core\\Combat\\BattleEngine' => 'extracteurs',
            'App\\Controllers\\Game\\FleetController' => 'extracteurs',
            'App\\Services\\ResourceService' => 'officier',
            'App\\Services\\ProductionService' => 'officier',
            'App\\Services\\BuildingService' => 'officier',
            'App\\Controllers\\Game\\OverviewController' => 'officier',
            'App\\Controllers\\Game\\InfosController' => 'officier',
            'App\\Services\\TechTreeService' => 'officier',
        );

        foreach ($derived as $class => $module) {
            $override = Modules::overrideFor($class);

            if ($override === null) {
                self::assertFalse(Modules::exists($module), $module . ' déposé doit dériver ' . $class . '.');

                continue;
            }

            self::assertSame($module, $override['module'], $class . ' est dérivée par ' . $module . '.');
            self::assertTrue(
                is_subclass_of((string) $override['class'], $class),
                $override['class'] . ' hérite de ' . $class . ' : on étend, on ne remplace pas.'
            );
        }
    }

    /**
     * Un module **archivé** (désinstallé depuis le panneau) n'est plus découvert : c'est
     * exactement l'effet de son absence — ses pages, ses routes et ses surcharges
     * disparaissent. Ses fichiers restent dans `modules/.archive/`, donc il reste
     * réinstallable.
     */
    public function testAnArchivedPackageLeavesTheCatalogue(): void
    {
        self::assertSame(
            logicalPaths(Modules::directory() . '.archive/'),
            logicalPaths(Modules::archiveDirectory())
        );
        self::assertSame(
            logicalPaths(Modules::directory() . '.archive/bot/'),
            logicalPaths(Modules::archiveDirectory('bot'))
        );

        // Rien d'archivé n'est dans le catalogue, quels que soient les modules rangés
        // dans le dépôt.
        self::assertSame(array(), array_intersect(array_keys(Modules::archived()), Modules::names()));
    }

    /**
     * Le panneau d'administration n'est dérivé que depuis le dossier `Back/` d'un module :
     * un fichier à plat (`controllers/X.php`) désigne une page du **jeu**, jamais une page
     * du panneau — et une page du panneau que le module ne reprend pas reste celle du Core.
     *
     * C'est le cas de la porte `/back/robots` : sans le module `bot`, elle reste au Core et
     * annonce l'absence (`AdminController::moduleMissing()`), au lieu de tomber sur une
     * classe introuvable.
     */
    public function testAnAdminPageIsOnlyOverriddenFromTheBackFolder(): void
    {
        // Une page du panneau qu'un module reprend est dérivée depuis son dossier `Back/`,
        // et **par héritage** : la porte du Core garde l'adresse, la permission, l'habillage.
        $robots = Modules::overrideFor('App\\Controllers\\Back\\RobotsController');

        if (Modules::exists('bot')) {
            self::assertSame('Modules\\Bot\\Controllers\\Back\\RobotsController', $robots['class'] ?? null);
            self::assertTrue(
                is_subclass_of((string) ($robots['class'] ?? ''), 'App\\Controllers\\Back\\RobotsController'),
                'La page du panneau est dérivée, jamais remplacée.'
            );
        } else {
            self::assertNull($robots, 'Sans le module, la page du panneau reste celle du Core.');
        }

        // Une page du panneau que personne ne reprend reste celle du Core — le tchat en est
        // une, côté panneau : c'est la page du **jeu** que le module apporte.
        self::assertNull(Modules::overrideFor('App\\Controllers\\Back\\ChatController'));
        self::assertNull(Modules::overrideFor('App\\Controllers\\Back\\UserlistController'));
    }

    /** Une classe du Core de l'application que personne ne dérive reste celle du Core. */
    public function testAnUndeclaredCoreClassStaysTheCoreOne(): void
    {
        // `ProductionService` a longtemps servi d'exemple ici : l'officier le dérive
        // désormais (`modules/officier/services/ProductionService.php`).
        self::assertNull(Modules::overrideFor('App\\Core\\Acl'));
    }

    /**
     * Aucun module n'a besoin de déclarer ses surcharges : la convention les couvre. La clé
     * `overrides` du manifeste reste pour les cas particuliers.
     */
    public function testShippedModulesDoNotNeedToDeclareTheirOverrides(): void
    {
        if (Modules::names() === array()) {
            self::markTestSkipped('Aucun module déposé : rien à déclarer dans la version livrée.');
        }

        foreach (Modules::names() as $name) {
            self::assertSame(array(), Modules::overrides($name), $name . ' n\'a pas à déclarer ses surcharges.');
        }
    }
}

/**
 * Comparaison de chemins, quel que soit le séparateur du système.
 */
function logicalPaths(string $path): string
{
    return str_replace('\\', '/', $path);
}
