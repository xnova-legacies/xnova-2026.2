<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Modules;
use PHPUnit\Framework\TestCase;

/**
 * Dépendances d'un module.
 *
 * Un module vit sous son propre namespace (`Modules\<Nom>\…`) : une classe du Coeur d'application
 * utilisée sans `use` est donc cherchée **dans le module**, où elle n'existe pas —
 * et PHP s'arrête sur une erreur fatale au premier appel (`Class
 * "Modules\Marchand\Services\BotService" not found`). Vécu en production sur la
 * page du marchand : rien ne le signalait, ni `php -l`, ni les tests.
 *
 * Ce test tient les deux bouts :
 *  - le **manifeste** déclare ce que le module exige (Coeur d'application, autres modules) ;
 *  - le **code** n'utilise aucune classe du Coeur d'application sans l'importer.
 */
final class ModuleDependenciesTest extends TestCase
{
    /** @return array<int, string> chemins des sources d'un module */
    private static function sources(): array
    {
        $files = array();

        foreach (Modules::names() as $name) {
            $files = array_merge($files, self::phpFiles(Modules::directory() . $name));
        }

        sort($files);

        return $files;
    }

    /**
     * Fichiers PHP d'un dossier, **à toute profondeur**.
     *
     * Un module range ses classes par couche (`services/X.php`) mais descend plus bas quand il
     * dérive une classe du Coeur de l'application (`core/Combat/DebrisDeuterium.php`), et le Coeur
     * de l'application fait de même (`Core/Combat/BattleEngine.php`, `Core/Ws/Protocol.php`). Un
     * `glob()` à deux niveaux les oubliait — et c'est exactement là qu'un `use` manquant a échappé
     * au contrôle (une classe du Coeur cherchée sous `Modules\…`, fatale au premier appel).
     *
     * @return array<int, string> chemins, triés
     */
    private static function phpFiles(string $folder): array
    {
        $folder = str_replace('\\', '/', $folder);

        if (!is_dir($folder)) {
            return array();
        }

        $files = array();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Couche d'un module → dossiers du Coeur d'application de la **même** couche.
     *
     * Un `controllers/` de module se compare aux contrôleurs du jeu, pas à ceux du
     * panneau : `Modules\Chat\Controllers\ChatController` (la page du tchat) et
     * `App\Controllers\Back\ChatController` (sa modération) sont deux pages
différentes, pas un remplacement.
     *
     * @var array<string, array<int, string>>
     */
    private const SAME_LAYER = array(
        'core' => array('app/Core'),
        'controllers' => array('app/Controllers/Game', 'app/Controllers/Api', 'app/Controllers/Front'),
        'entities' => array('app/Entities'),
        'repositories' => array('app/Repositories'),
        'services' => array('app/Services'),
    );

    /**
     * Classes du Coeur d'application, par nom court : c'est ce que le registre propose à un module.
     *
     * @param array<int, string> $folders dossiers du Coeur d'application à lire (tous par défaut)
     * @return array<string, string> nom court => chemin relatif
     */
    private static function coreClasses(array $folders = array('app')): array
    {
        $classes = array();

        foreach ($folders as $folder) {
            foreach (self::phpFiles(ROOT_PATH . $folder) as $file) {
                // Seuls les fichiers qui **déclarent une classe** comptent : `Core/Legacy/` porte des
                // fonctions globales, dont le nom de fichier n'est pas un nom de classe.
                $source = (string) file_get_contents($file);

                if (preg_match('/^\s*(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+/m', $source) !== 1) {
                    continue;
                }

                $classes[basename($file, '.php')] = str_replace(ROOT_PATH, '', $file);
            }
        }

        return $classes;
    }

    /** Retire commentaires et chaînes : on ne juge que le code. */
    private static function code(string $source): string
    {
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);
        $source = (string) preg_replace('#(^|\s)//.*$#m', '$1', $source);
        $source = (string) preg_replace("#(^|\s)\#.*$#m", '$1', $source);

        return (string) preg_replace(array('#\'[^\']*\'#s', '#"[^"]*"#s'), '', $source);
    }

    /** Chaque module déclare au moins le Core dont il dépend. */
    public function testEveryModuleDeclaresItsDependencies(): void
    {
        if (Modules::names() === array()) {
            self::markTestSkipped('Aucun module déposé : rien à déclarer dans la version livrée.');
        }

        foreach (Modules::names() as $name) {
            $dependencies = Modules::dependencies($name);

            self::assertArrayHasKey('core', $dependencies, $name . ' doit déclarer la version du Coeur d\'application qu\'il exige.');
            self::assertMatchesRegularExpression(
                '/^(>=|<=|>|<|=)?\s*\d+(\.\d+)*$/',
                (string) $dependencies['core'],
                $name . ' déclare une contrainte de version lisible (exemple : >=2026.6).'
            );
            self::assertTrue(
                Modules::coreSatisfies('2026.6', (string) $dependencies['core']),
                $name . ' exige un Coeur d\'application que cette version (' . '2026.6' . ') ne satisfait pas.'
            );

            foreach ((array) $dependencies['modules'] as $required) {
                self::assertTrue(Modules::exists($required), $name . ' exige le module ' . $required . ', qui doit être déposé.');
            }
        }
    }

    /** Une dépendance déclarée qu'on ne peut pas satisfaire doit se voir. */
    public function testAMissingDependencyIsReported(): void
    {
        // Le Coeur d'application : comparaison **numérique** des versions (2026.10 > 2026.6).
        self::assertTrue(Modules::coreSatisfies('2026.6', '>=2026.6'));
        self::assertTrue(Modules::coreSatisfies('2027.1', '>=2026.6'));
        self::assertTrue(Modules::coreSatisfies('2026.10', '>=2026.6'));
        self::assertFalse(Modules::coreSatisfies('2026.5', '>=2026.6'));
        self::assertTrue(Modules::coreSatisfies('2026.6', '=2026.6'));
        self::assertFalse(Modules::coreSatisfies('2026.7', '=2026.6'));
        self::assertFalse(Modules::coreSatisfies('2026.6', '>2026.6'));
        self::assertTrue(Modules::coreSatisfies('2026.6', '<2027.0'));
        // Rien de déclaré, ou rien pour juger : on ne refuse pas.
        self::assertTrue(Modules::coreSatisfies('2026.6', ''));
        self::assertTrue(Modules::coreSatisfies('', '>=2030.1'));
        self::assertTrue(Modules::coreSatisfies('2026.6', 'n\'importe quoi'));

        // Deux gammes se sont succédé : la millésimée est close, donc **toujours** plus
        // ancienne qu'un numéro. Sans cette règle, `2026` écraserait `1` et les neuf
        // modules, qui déclarent `>= 2026.6`, seraient jugés incompatibles d'un coup.
        self::assertTrue(Modules::coreSatisfies('1.0.0', '>=2026.6'));
        self::assertFalse(Modules::coreSatisfies('2026.37', '>=1.0.0'));
        self::assertTrue(Modules::coreSatisfies('1.1.0', '>1.0.0'));
        self::assertTrue(Modules::coreSatisfies('2.0.1', '>1.9.9'));
        self::assertFalse(Modules::coreSatisfies('1.0.9', '>1.1.0'));

        // Un module exigé et absent.
        self::assertSame(array('marchand'), Modules::missingModules(array('marchand'), array('chat')));
        self::assertSame(array(), Modules::missingModules(array('chat'), array('chat', 'notes')));

        // Le verdict complet. `dependencyProblems()` lit le **manifeste** du module : un nom
        // sans manifeste déposé ne déclare rien, et ne peut donc rien exiger. Les exigences
        // **déclarées** se vérifient sur le manifeste du module, jamais sur une constante du
        // test ; la règle elle-même — une déclaration qu'on ne peut pas satisfaire se voit —
        // est vérifiée juste au-dessus, sur `problems()` (pure).
        self::assertSame(array(), Modules::dependencyProblems('inconnu', '2026.5', Modules::names()));

        $declared = (string) (Modules::all()['chat']['dependencies']['core'] ?? '');

        if ($declared === '') {
            self::assertSame(array(), Modules::dependencyProblems('chat', '2026.5', Modules::names()));

            return;
        }

        if (!Modules::coreSatisfies('2026.5', $declared)) {
            self::assertSame(array('core' => $declared), Modules::dependencyProblems('chat', '2026.5', Modules::names()));
        }

        // À la version minimale qu'il déclare, l'exigence est tenue.
        self::assertSame(
            array(),
            Modules::dependencyProblems('chat', (string) preg_replace('/[^0-9.]/', '', $declared), Modules::names())
        );
    }

    /**
     * La dépendance à un **autre module** se juge à partir de sa déclaration : un
     * module absent est à déposer, un module éteint est à rallumer.
     */
    public function testAModuleCanDependOnAnotherModule(): void
    {
        $declared = array('core' => '>=2026.6', 'modules' => array('marchand', 'chat'));

        // Le Coeur d'application et les deux modules sont là, tout va bien.
        self::assertSame(
            array(),
            Modules::problems($declared, '2026.6', array('marchand', 'chat', 'notes'))
        );

        // Un module absent : introuvable, donc à déposer.
        self::assertSame(
            array('module:chat' => 'chat'),
            Modules::problems($declared, '2026.6', array('marchand'))
        );

        // Un module déposé mais éteint : il suffit de le rallumer.
        self::assertSame(
            array('off:marchand' => 'marchand'),
            Modules::problems($declared, '2026.6', array('marchand', 'chat'), array('marchand'))
        );

        // Les deux à la fois, et le Coeur d'application en prime.
        self::assertSame(
            array('core' => '>=2027.1', 'module:chat' => 'chat', 'off:marchand' => 'marchand'),
            Modules::problems(
                array('core' => '>=2027.1', 'modules' => array('marchand', 'chat')),
                '2026.6',
                array('marchand'),
                array('marchand')
            )
        );

        // Un module qui ne déclare rien ne se plaint de rien.
        self::assertSame(array(), Modules::problems(array(), '2026.6', array()));
    }

    /** Une classe du Core doit être importée, sinon elle est cherchée dans le module. */
    public function testModuleCodeImportsEveryCoreClassItUses(): void
    {
        $sources = self::sources();

        // Aucun module n'est livré : la vérification porte sur du vide, et se réarme dès
        // qu'un module est déposé (le chemin du dossier est tenu par `ModulesTest`).
        $problems = array();

        foreach ($sources as $file) {
            $code = self::code((string) file_get_contents($file));
            $own = basename($file, '.php');
            $imports = array();

            if (preg_match_all('/^use\s+([^;]+);/m', $code, $matches) > 0) {
                foreach ($matches[1] as $import) {
                    $parts = explode('\\', trim($import));
                    $imports[end($parts)] = trim($import);
                }
            }

            foreach (self::coreClasses() as $class => $path) {
                if ($class === $own || isset($imports[$class])) {
                    continue;
                }

                // Un nom **pleinement qualifié** (`\App\Repositories\X::class`) n'a pas besoin
                // d'import : seul l'usage du **nom court** en dépend (sans `use`, PHP le cherche
                // sous `Modules\<Nom>\…`, où il n'existe pas).
                if (preg_match('/(?<!\\\\)\b(new\s+' . $class . '\s*\(|' . $class . '::)/', $code) === 1) {
                    $problems[] = str_replace(ROOT_PATH, '', $file) . ' utilise ' . $class
                        . ' sans l\'importer (' . $path . ').';
                }
            }
        }

        self::assertSame(
            array(),
            $problems,
            "Une classe du Coeur d'application s'écrit avec son `use` : sans lui, elle est cherchée dans le module (erreur fatale).\n"
                . implode("\n", $problems)
        );
    }

    /**
     * Une classe de **module** ne se charge jamais dans un constructeur du Coeur d'application.
     *
     * Un module absent n'a pas de classe du tout : `new NotesRepository()` en valeur par
     * défaut d'une propriété promue fait tomber la page **à l'instanciation**, donc avant
     * toute garde (`Class Modules\Notes\Repositories\NotesRepository not found`, vécu sur
     * `/back/notes` sans le module). Ces dépôts se chargent **paresseusement**, après avoir
     * vérifié que le module est déposé (`Modules::exists()`), et la page annonce l'absence
     * (`AdminController::moduleMissing()`) au lieu de tomber.
     */
    public function testTheCoreNeverLoadsAModuleClassInAConstructor(): void
    {
        $problems = array();

        foreach (self::coreSources() as $file) {
            $code = self::code((string) file_get_contents($file));

            if (preg_match_all('/^use\s+(Modules\\\\[^;]+);/m', $code, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $import) {
                $short = basename(str_replace('\\', '/', trim($import)));

                if (preg_match('/function\s+__construct\s*\((.*?)\)\s*\{/s', $code, $constructor) !== 1) {
                    continue;
                }

                if (preg_match('/\b' . preg_quote($short, '/') . '\b/', $constructor[1]) === 1) {
                    $problems[] = str_replace(array(ROOT_PATH, '\\'), array('', '/'), $file)
                        . ' reçoit ' . $import . ' dans son constructeur.';
                }
            }
        }

        self::assertSame(
            array(),
            $problems,
            "Un dépôt d'un module se charge paresseusement : la classe n'existe pas sans le module.\n"
                . implode("\n", $problems)
        );
    }

    /** @return array<int, string> sources du Coeur d'application (contrôleurs, services, dépôts, Coeur d'application) */
    private static function coreSources(): array
    {
        $files = array();

        foreach (array('app/*/*.php', 'app/*/*/*.php', 'app/*/*/*/*.php') as $pattern) {
            foreach ((array) glob(ROOT_PATH . $pattern) as $file) {
                $files[] = (string) $file;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Le menu du jeu ne nomme aucune page d'un module : il la prend au manifeste.
     *
     * Les entrées de module du menu latéral (tchat, alliances, records, officiers,
     * annonces, marchand) ne portent ni adresse ni libellé : `LeftMenu` lit `page` et
     * `label` dans le manifeste et **n'écrit** l'entrée que si le module est ouvert.
     * Un Coeur d'application qui garderait l'adresse en dur produirait un lien vers une page
     * absente sur un jeu sans ce module — ou caché par une classe au lieu de ne pas
     * être écrit.
     */
    public function testTheGameMenuTakesItsModulePagesFromTheManifests(): void
    {
        $menu = (string) file_get_contents(ROOT_PATH . 'app/View/OpenGame/left_menu.tpl');
        $problems = array();

        foreach (Modules::all() as $name => $module) {
            $page = (string) ($module['page'] ?? '');

            // Seules les pages **du jeu** nous occupent : celle d'un module du panneau
            // est ajoutée par le panneau lui-même (`AdminController::moduleMenuLinks()`).
            if (!str_starts_with($page, '/game/')) {
                continue;
            }

            if (str_contains($menu, $page)) {
                $problems[] = $name . ' : ' . $page . ' ecrit dans left_menu.tpl';
            }
        }

        self::assertSame(
            array(),
            $problems,
            "Une page de module s'appelle par son manifeste, jamais en dur dans le menu du jeu.\n"
                . implode("\n", $problems)
        );
    }

    /**
     * Un module qui reprend le nom d'une classe du Coeur d'application **de la même couche** doit en
     * **dériver** : c'est la seule façon admise d'ajuster une page du jeu (jamais une
     * copie).
     */
    public function testAPackageClassWithACoreNameExtendsTheCoreOne(): void
    {
        $sources = self::sources();

        if ($sources === array()) {
            self::markTestSkipped('Aucun module déposé : rien à dériver dans la version livrée.');
        }

        foreach ($sources as $file) {
            $short = basename($file, '.php');
            $layer = strtolower((string) explode('/', str_replace('\\', '/', str_replace(Modules::directory(), '', $file)))[1]);

            // `db/` porte les migrations du module : du schéma, pas des classes.
            if (in_array($layer, Modules::DATA_DIRECTORIES, true)) {
                continue;
            }

            $folders = self::SAME_LAYER[$layer] ?? array();

            self::assertNotSame(array(), $folders, $layer . ' est une couche connue du registre.');

            $core = self::coreClasses($folders);

            if (!isset($core[$short])) {
                continue;
            }

            $class = self::packageClass($file);
            $coreClass = self::coreClass($core[$short]);

            self::assertTrue(class_exists($class), $class . ' ne se charge pas.');
            self::assertTrue(class_exists($coreClass), $coreClass . ' ne se charge pas.');
            self::assertTrue(
                is_subclass_of($class, $coreClass),
                $class . ' reprend le nom de ' . $coreClass . ' : il doit en dériver (`extends`), jamais le recopier.'
            );
        }
    }

    /**
     * Nom de classe d'un fichier d'un module (`modules/chat/core/Salon.php`,
     * `modules/extracteurs/core/Combat/BattleEngine.php`).
     */
    private static function packageClass(string $file): string
    {
        $parts = explode('/', trim(str_replace('\\', '/', str_replace(Modules::directory(), '', $file)), '/'));
        $parts[count($parts) - 1] = basename($parts[count($parts) - 1], '.php');

        return 'Modules\\' . implode('\\', array_map('ucfirst', $parts));
    }

    /** Nom de classe d'un fichier du Coeur d'application (`app/Core/Flags.php`). */
    private static function coreClass(string $path): string
    {
        $parts = array_slice(explode('/', str_replace('\\', '/', $path)), 1, -1);

        return 'App\\' . implode('\\', $parts) . '\\' . basename($path, '.php');
    }
}
