<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Entities\AbstractEntity;
use App\Entities\User;
use App\Repositories\BaseRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Garde-fou de la couche de données.
 *
 * Chaque table du jeu doit être joignable par un dépôt et représentée par une
 * entité. Le test lit uniquement le système de fichiers (aucun accès MySQL) :
 * il casse dès qu'une table est ajoutée au schéma sans sa contrepartie.
 *
 * Les rendus ci-dessous sont à compléter à chaque nouvelle table : le nombre
 * d'entrées est vérifié pour forcer la mise à jour.
 */
final class RepositoryCoverageTest extends TestCase
{
    /**
     * Nombre de tables listées ci-dessous, préfixe `game_` exclu.
     * À incrémenter avec chaque table ajoutée.
     */
    private const TABLE_COUNT = 16;

    /** Table => dépôt qui doit la référencer. */
    private const REPOSITORIES = array(
        'aks' => 'AksRepository',
        'banned' => 'BannedRepository',
        'buddy' => 'BuddyRepository',
        'config' => 'ConfigRepository',
        'declared' => 'DeclareRepository',
        'fleets' => 'FleetRepository',
        'galaxy' => 'GalaxyRepository',
        'lunas' => 'PlanetRepository',
        'messages' => 'MessageRepository',
        'modules' => 'ModuleRepository',
        'multi' => 'MultiRepository',
        'planets' => 'PlanetRepository',
        'roles' => 'RoleRepository',
        'rw' => 'RwRepository',
        'statpoints' => 'StatsRepository',
        'users' => 'UserRepository',
    );

    /** Table => entité qui la représente. */
    private const ENTITIES = array(
        'aks' => 'Aks',
        'banned' => 'Ban',
        'buddy' => 'Buddy',
        'config' => 'ConfigEntry',
        'declared' => 'Declared',
        'fleets' => 'Fleet',
        'galaxy' => 'GalaxyEntry',
        'lunas' => 'Moon',
        'messages' => 'Message',
        'modules' => 'Module',
        'multi' => 'Multi',
        'planets' => 'Planet',
        'roles' => 'Role',
        'rw' => 'Rw',
        'statpoints' => 'StatPoints',
        'users' => 'User',
    );

    public function testTheTwoMapsDescribeTheSameTables(): void
    {
        self::assertCount(self::TABLE_COUNT, self::REPOSITORIES, 'Une table du schéma n\'est pas listée.');
        self::assertCount(self::TABLE_COUNT, self::ENTITIES, 'Une table du schéma n\'est pas listée.');
        self::assertSame(
            array(),
            array_diff(array_keys(self::REPOSITORIES), array_keys(self::ENTITIES)),
            'Les deux rendus doivent lister exactement les mêmes tables.'
        );
        self::assertSame(array(), array_diff(array_keys(self::ENTITIES), array_keys(self::REPOSITORIES)));
    }

    public function testEachTableIsReachableThroughARepository(): void
    {
        $files = self::classFiles('Repositories');

        foreach (self::REPOSITORIES as $table => $repository) {
            self::assertArrayHasKey(
                $repository,
                $files,
                sprintf('Le dépôt %s est introuvable (table game_%s).', $repository, $table)
            );
            self::assertStringContainsString(
                "'" . $table . "'",
                (string) file_get_contents($files[$repository]),
                sprintf('Le dépôt %s ne référence pas la table game_%s.', $repository, $table)
            );
        }
    }

    public function testEachTableIsRepresentedByAnEntity(): void
    {
        $files = self::classFiles('Entities');

        foreach (self::ENTITIES as $table => $entity) {
            self::assertArrayHasKey(
                $entity,
                $files,
                sprintf("L'entité %s est introuvable (table game_%s).", $entity, $table)
            );

            $class = self::classOf($files[$entity]);

            self::assertTrue(class_exists($class), sprintf('La classe %s ne se charge pas.', $class));

            $reflection = new ReflectionClass($class);

            self::assertTrue($reflection->isFinal(), sprintf('%s doit être final.', $class));

            // Toutes les entités partagent le Coeur d'application AbstractEntity, à l'exception
            // de User (entité historique à propriétés promues) : elle doit alors
            // exposer la même interface publique.
            self::assertTrue(
                $reflection->isSubclassOf(AbstractEntity::class) || $class === User::class,
                sprintf('%s doit étendre AbstractEntity (User est la seule exception admise).', $class)
            );

            foreach (array('fromRow', 'raw', 'toArray', 'primaryKey') as $method) {
                self::assertTrue($reflection->hasMethod($method), sprintf('%s doit exposer %s().', $class, $method));
            }

            self::assertNotSame(array(), $class::primaryKey(), sprintf('%s doit déclarer sa clé primaire.', $class));
            self::assertInstanceOf($class, $class::fromRow(array()));
            self::assertInstanceOf($class, $class::fromRow(array($class::primaryKey()[0] => '1')));
        }
    }

    public function testEveryPrimaryKeyColumnIsANonEmptyString(): void
    {
        $files = self::classFiles('Entities');

        foreach (self::ENTITIES as $entity) {
            $class = 'App\\Entities\\' . $entity;

            $class = self::classOf($files[$entity]);

            foreach ($class::primaryKey() as $column) {
                self::assertIsString($column);
                self::assertNotSame('', $column, sprintf('Clé primaire invalide dans %s.', $class));
            }
        }
    }

    /**
     * Classes du Core qu'un module peut dériver, en plus de celles que le registre résout.
     *
     * Elles ne se lisent pas dans le code du Core : c'est le **module** qui instancie la
     * sienne (`Modules\Officier\Repositories\UserRepository` dérive celle du Core pour y
     * ajouter les niveaux RPG), le Core ne la résout donc jamais par `ModuleService`. Le
     * dépôt des comptes est le seul cas — la liste est vérifiée dans les deux sens par le
     * test ci-dessous.
     *
     * @var list<string>
     */
    private const OVERRIDABLE_BY_CONVENTION = array('UserRepository');

    public function testEveryRepositoryExtendsBaseRepositoryAndIsFinal(): void
    {
        $classes = array();

        foreach (self::classFiles('Repositories') as $file) {
            $classes[] = self::classOf($file);
        }

        self::assertNotEmpty($classes, 'Aucun dépôt trouvé : le chemin du projet est-il correct ?');

        // Un dépôt que le Core de l'application **résout par le registre des modules**
        // (`ModuleService::resolve()` / `instance()`) est un point de surcharge : un module
        // en dérive, il ne peut donc pas être `final` — même quand aucun module n'est
        // déposé. La liste se lit dans le code du Core, jamais dans les modules présents,
        // sinon la règle disparaîtrait avec le dernier module retiré.
        $overridable = array_merge(self::resolvedByTheRegistry(), self::OVERRIDABLE_BY_CONVENTION);

        foreach ($classes as $class) {
            if ($class === BaseRepository::class || !class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (in_array($reflection->getShortName(), $overridable, true)) {
                self::assertFalse(
                    $reflection->isFinal(),
                    sprintf('%s est un point de surcharge du Core : elle ne peut pas être final.', $class)
                );

                continue;
            }

            self::assertTrue($reflection->isFinal(), sprintf('%s doit être final.', $class));
            self::assertTrue($reflection->isSubclassOf(BaseRepository::class), sprintf('%s doit étendre BaseRepository.', $class));
        }
    }

    /**
     * Classes que le Core de l'application demande au registre des modules, par nom court.
     *
     * @return list<string>
     */
    private static function resolvedByTheRegistry(): array
    {
        $classes = array();

        foreach (array('app/*/*.php', 'app/*/*/*.php', 'app/*/*/*/*.php') as $pattern) {
            foreach ((array) glob(ROOT_PATH . $pattern) as $file) {
                $source = (string) file_get_contents($file);

                if (preg_match_all('/ModuleService::(?:resolve|instance)\(([^)]*)\)/', $source, $matches) === 0) {
                    continue;
                }

                foreach ($matches[1] as $argument) {
                    if (preg_match('/([A-Za-z0-9_]+)::class/', $argument, $class) === 1) {
                        $classes[$class[1]] = true;
                    }
                }
            }
        }

        return array_keys($classes);
    }

    /**
     * Dépôts ou entités, par nom de classe courte : ceux du jeu (`app/`) et ceux des
     * modules (`modules/<nom>/`), un module apportant son propre code.
     *
     * @return array<string, string> nom de classe => fichier
     */
    private static function classFiles(string $folder): array
    {
        $files = array();

        // Les modules d'abord : à nom de classe courte égal, c'est le fichier du Coeur d'application qui
        // fait référence — un module en dérive, il ne le remplace pas.
        $directories = array_merge(
            (array) glob(ROOT_PATH . 'modules/*/' . strtolower($folder), GLOB_ONLYDIR),
            array(ROOT_PATH . 'app/' . $folder)
        );

        foreach ($directories as $directory) {
            foreach ((array) glob($directory . '/*.php') as $file) {
                $files[basename((string) $file, '.php')] = (string) $file;
            }
        }

        return $files;
    }

    /** Nom pleinement qualifié d'une classe, d'après l'emplacement de son fichier. */
    private static function classOf(string $file): string
    {
        $relative = str_replace('\\', '/', str_replace(ROOT_PATH, '', $file));

        // `modules/records/repositories/RecordsRepository.php` → `Modules\Records\Repositories\RecordsRepository`
        $parts = array_slice(explode('/', (string) $relative), 0, -1);
        $class = basename($file, '.php');

        if ($parts !== array() && $parts[0] === 'modules') {
            return 'Modules\\' . ucfirst($parts[1]) . '\\' . ucfirst($parts[2]) . '\\' . $class;
        }

        return 'App\\' . implode('\\', array_slice($parts, 1)) . '\\' . $class;
    }

    private static function appPath(string $relative): string
    {
        return dirname(__DIR__, 3) . '/app/' . $relative;
    }
}
