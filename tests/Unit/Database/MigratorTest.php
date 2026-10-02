<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Core\Flags;
use App\Database\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * Exécuteur de migrations : parties pures (plan de migration) et contenu du
 * schéma du Coeur d'application. Aucun accès à MySQL.
 *
 * Depuis la fusion des migrations 002 à 024, `db/migrations/` ne contient plus
 * qu'un fichier : ces tests décrivent donc le schéma du **Coeur d'application**, celui d'une
 * installation neuve — tables, colonnes, index et drapeaux, sans aucun
 * `ALTER TABLE`. Le schéma d'un module vit dans son module
 * (`modules/<nom>/db/migrations/`), et n'est donc pas décrit ici.
 */
final class MigratorTest extends TestCase
{
    /** Le fichier unique du Coeur d'application. */
    private const BASELINE = '/001_initial_schema.php';

    /**
     * Les instructions du fichier unique.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function baseline(): array
    {
        $migrator = new Migrator();

        return $migrator->statementsOf($migrator->directory() . self::BASELINE);
    }

    /** Le `CREATE TABLE` d'une table du schéma. */
    private function tableSql(string $table): string
    {
        foreach ($this->baseline() as $statement) {
            if ($statement[0] === $table && str_contains($statement[1], 'CREATE TABLE')) {
                return $statement[1];
            }
        }

        self::fail('La table ' . $table . ' n\'est pas dans le schéma.');
    }

    public function testPlanKeepsOnlyMigrationsThatAreNotAppliedYet(): void
    {
        $available = array(
            '001_initial_schema' => '/db/001.php',
            '002_exemple' => '/db/002.php',
        );

        self::assertSame($available, Migrator::plan($available, array()));
        self::assertSame(
            array('002_exemple' => '/db/002.php'),
            Migrator::plan($available, array('001_initial_schema'))
        );
        self::assertSame(
            array(),
            Migrator::plan($available, array('001_initial_schema', '002_exemple'))
        );
    }

    public function testTheAppliedListIgnoresUnknownNames(): void
    {
        $available = array('001_initial_schema' => '/db/001.php');

        // Une migration supprimée du dépôt ne doit pas perturber le plan.
        self::assertSame(array(), Migrator::plan($available, array('000_disparue', '001_initial_schema')));
    }

    /**
     * Le schéma du Core de l'application passe avant celui des modules : un module peut
     * donc compter sur les tables du jeu, et réclamer une colonne de `users`.
     *
     * Les migrations du Core de l'application passent **avant** celles des modules : un
     * module peut donc compter sur les tables du jeu. Les noms des migrations du Core n'ont
     * pas de dossier, ceux d'un module portent le nom du module (`marchand/001_market`) — le
     * Core vient en tête, et chaque famille reste triée par nom de fichier.
     *
     * Un dossier **imposé** ne prend jamais de migration de module : il décrit exactement ce
     * qu'on lui donne (jeu d'essai, test).
     */
    public function testTheCoreMigrationsComeBeforeTheModules(): void
    {
        $available = (new Migrator())->available();
        $names = array_keys($available);

        self::assertArrayHasKey('001_initial_schema', $available, 'Le schéma du Core est découvert.');
        self::assertSame('001_initial_schema', $names[0] ?? '', 'Le schéma du Core vient en tête.');

        $core = array_values(array_filter($names, static fn (string $name): bool => !str_contains($name, '/')));

        $sorted = $core;
        sort($sorted, SORT_STRING);

        self::assertSame($sorted, $core, 'Les migrations du Core sont triées par nom de fichier.');

        // Après la première migration de module, il n'y a plus une seule du Core.
        foreach (array_slice($names, count($core)) as $name) {
            self::assertStringContainsString('/', $name, 'Le Core passe avant les modules : ' . $name . ' est du Core.');
        }

        // Les noms sont comparés, pas les chemins (le séparateur du dossier imposé est celui
        // du système, comme `ROOT_PATH`).
        self::assertSame($core, array_keys((new Migrator(ROOT_PATH . 'db/migrations'))->available()));
    }

    /**
     * Le fichier unique crée toutes les tables du Core de l'application : une installation
     * neuve n'a besoin d'aucune mise à niveau. Les tables d'un module (alliance, annonce,
     * chat, notes, marché) naissent dans **son** module, joué juste après.
     */
    public function testTheBaselineCoversEveryGameTable(): void
    {
        $statements = $this->baseline();

        // 18 tables, plus l'insertion de la configuration qui suit la table config.
        self::assertCount(19, $statements);

        $tables = array_unique(array_map(static fn (array $statement): string => $statement[0], $statements));
        sort($tables);

        self::assertSame(array(
            'actions', 'aks', 'banned', 'buddy', 'config', 'declared', 'fleets', 'galaxy',
            'lunas', 'messages', 'modules', 'multi', 'planets', 'roles', 'rw', 'sessions',
            'statpoints', 'users',
        ), $tables);
    }

    public function testTheBaselineKeepsTablePlaceholdersUnresolved(): void
    {
        $statements = $this->baseline();

        // Le nom de table est substitué à l'exécution : la migration reste
        // indépendante du préfixe.
        self::assertStringContainsString('CREATE TABLE `{{table}}`', $statements[0][1]);
    }

    /**
     * Une colonne nouvelle s'écrit dans sa table, jamais dans un `ALTER TABLE` :
     * c'est ce qui rend l'installation complète en une seule fois.
     */
    public function testTheSchemaIsCreatedWithoutAlterTable(): void
    {
        foreach ($this->baseline() as $statement) {
            self::assertStringNotContainsString(
                'ALTER TABLE',
                strtoupper($statement[1]),
                'Le schéma se crée d\'un coup : aucune modification de table.'
            );
        }
    }

    /**
     * Tout le schéma naît en InnoDB/utf8mb3, et aucune colonne ne porte son
     * propre jeu de caractères : celui de la table suffit.
     */
    public function testTheSchemaIsCreatedInInnoDbAndUtf8(): void
    {
        foreach ($this->baseline() as $statement) {
            self::assertStringNotContainsString('MyISAM', $statement[1], 'Les tables se créent en InnoDB.');
            self::assertStringNotContainsString('latin1', $statement[1], 'Le schéma est en utf8mb3.');
            self::assertStringNotContainsString(
                'character set',
                strtolower($statement[1]),
                'Aucune colonne ne porte son jeu de caractères.'
            );

            if (str_starts_with($statement[1], 'CREATE TABLE')) {
                self::assertStringContainsString('ENGINE=InnoDB', $statement[1]);
                self::assertStringContainsString(
                    'DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci',
                    $statement[1]
                );
            }
        }
    }

    /**
     * L'annulation retire exactement les tables créées, dans l'ordre inverse.
     */
    public function testTheSchemaIsReversible(): void
    {
        $migrator = new Migrator();
        $migration = $migrator->migrationOf($migrator->directory() . self::BASELINE);

        self::assertCount(19, $migration['up']);
        self::assertNotNull($migration['down']);
        self::assertCount(18, $migration['down']);
        self::assertSame('users', $migration['down'][0][0], 'L\'annulation suit l\'ordre inverse de la création.');
        self::assertSame('actions', $migration['down'][17][0]);

        $created = array();
        $dropped = array();

        foreach ($migration['up'] as $statement) {
            $created[] = $statement[0];
        }

        foreach ($migration['down'] as $statement) {
            $dropped[] = $statement[0];

            self::assertStringContainsString('DROP TABLE IF EXISTS', $statement[1]);
        }

        sort($created);
        sort($dropped);

        self::assertSame(
            array_values(array_unique($created)),
            $dropped,
            'Chaque table créée est retirée par l\'annulation.'
        );
    }

    public function testTheTrackingTableIsCreatedLikeTheSchema(): void
    {
        // La table de suivi n'est pas dans le fichier du schéma : le migrateur la
        // crée lui-même. Elle a longtemps été créée en MyISAM, du temps où tout le
        // schéma l'était, et le test des migrations ne la voyait pas (il ne lit que
        // les fichiers de `db/migrations/`).
        $source = (string) file_get_contents(ROOT_PATH . 'app/Database/Migrator.php');

        self::assertStringContainsString('ENGINE=InnoDB', $source, 'La table de suivi se crée en InnoDB.');
        self::assertStringNotContainsString('ENGINE=MyISAM', $source, 'Plus aucune table ne se crée en MyISAM.');
    }

    public function testAMigrationWithoutDownIsNotReversible(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'migr');
        file_put_contents($file, '<?php return array("up" => array("CREATE TABLE x (id int)"), "down" => null);');

        try {
            $migration = (new Migrator())->migrationOf($file);

            self::assertCount(1, $migration['up']);
            self::assertNull($migration['down']);
        } finally {
            unlink($file);
        }
    }

    public function testAMigrationCanSkipTheUpDownKeys(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'migr');
        file_put_contents($file, '<?php return array("ALTER TABLE x ADD y int");');

        try {
            $migration = (new Migrator())->migrationOf($file);

            // Forme historique : la liste est le `up`, et il n'y a pas de `down`.
            self::assertCount(1, $migration['up']);
            self::assertSame('ALTER TABLE x ADD y int', $migration['up'][0][1]);
            self::assertNull($migration['down']);
        } finally {
            unlink($file);
        }
    }

    /**
     * Les tables à drapeaux du Coeur d'application (`App\Core\Flags`) : un bit par état, et un
     * défaut qui doit rester celui de la classe — sinon les lignes neuves
     * naîtraient dans un état que `Flags::usable()` ne reconnaît pas. Les tables à
     * drapeaux d'un module (`notes`, par exemple) sont créées par son module.
     */
    public function testEveryFlagsColumnUsesTheClassDefault(): void
    {
        $covered = array();

        foreach ($this->baseline() as $statement) {
            if (str_contains($statement[1], '`flags` int(11)')) {
                $covered[$statement[0]] = $statement[1];
            }
        }

        $tables = array_keys($covered);
        sort($tables);

        self::assertSame(
            array('fleets', 'lunas', 'messages', 'modules', 'planets', 'roles', 'users'),
            $tables,
            'Les tables à drapeaux du Coeur d\'application : messages, planètes, lunes, comptes, vols, rôles et modules.'
        );

        foreach ($covered as $table => $sql) {
            self::assertStringContainsString(
                '`flags` int(11) NOT NULL default \'' . Flags::DEFAULT . '\'',
                $sql,
                'La table ' . $table . ' doit recevoir le défaut de Flags::DEFAULT.'
            );
        }
    }

    /**
     * La suppression d'un compte est **logique** : son état est le drapeau, il
     * n'existe donc plus de colonne `deleted` — `deleted_time` ne garde que la
     * date. Une colonne d'état réintroduite ferait deux vérités pour un état.
     */
    public function testTheAccountFlagReplacesTheDeletedColumn(): void
    {
        $users = $this->tableSql('users');

        self::assertStringNotContainsString('`deleted`', $users);
        self::assertStringContainsString('`deleted_time` int(11) NOT NULL default \'0\'', $users);
        self::assertStringContainsString('`flags` int(11) NOT NULL default \'' . Flags::DEFAULT . '\'', $users);
        self::assertStringContainsString('`role_id` int(11) NOT NULL default \'0\'', $users);
    }

    /**
     * Toutes les tables du jeu sont en InnoDB, et deux d'entre elles portent un
     * index particulier que le code attend : la table de la galaxie n'a **pas** de
     * clé primaire (elle n'a jamais eu d'identifiant unique), et ses coordonnées
     * sont indexées séparément.
     */
    public function testTheGalaxyTableKeepsItsShape(): void
    {
        $galaxy = $this->tableSql('galaxy');

        self::assertStringNotContainsString('PRIMARY KEY', $galaxy);
        self::assertStringContainsString('KEY `galaxy` (`galaxy`)', $galaxy);
        self::assertStringContainsString('KEY `system` (`system`)', $galaxy);
        self::assertStringContainsString('KEY `planet` (`planet`)', $galaxy);
    }

    public function testToleratedErrorsCoverReplayingAMigration(): void
    {
        // 1050 table présente, 1060 colonne présente, 1062 entrée en doublon,
        // 1091 objet absent : les quatre cas d'un rejeu.
        self::assertSame(array(1050, 1060, 1062, 1091), Migrator::TOLERATED_ERRNOS);
    }

    public function testTheDriverErrorCodeIsReadInsteadOfTheSqlState(): void
    {
        // Une colonne déjà présente : PDO range le code du pilote (1060) dans
        // `errorInfo`, et ne met que le SQLSTATE dans `getCode()` (« 42S21 », dont
        // la conversion en entier vaut 42). La tolérance ne lisait donc jamais le
        // bon nombre, et la migration d'un module échouait au lieu d'être sautée.
        $exception = new \PDOException('SQLSTATE[42S21]: Column already exists');
        $exception->errorInfo = array('42S21', 1060, "Duplicate column name 'deuterium'");

        self::assertSame(1060, Migrator::errno($exception));
        self::assertContains(Migrator::errno($exception), Migrator::TOLERATED_ERRNOS);
    }

    public function testAnExceptionWithoutDriverCodeIsNeverTolerated(): void
    {
        // Sans code de pilote, la migration doit échouer : 0 n'est pas toléré.
        self::assertSame(0, Migrator::errno(new \PDOException('connexion perdue')));
        self::assertNotContains(0, Migrator::TOLERATED_ERRNOS);
    }

    public function testOnlyDmlMigrationsCanBeWrappedInATransaction(): void
    {
        self::assertTrue(Migrator::isDmlOnly(array()));
        self::assertTrue(Migrator::isDmlOnly(array(
            array('', 'INSERT INTO `game_config` SET a = 1'),
            array('config', 'UPDATE `{{table}}` SET a = 2'),
        )));

        self::assertFalse(Migrator::isDmlOnly(array(array('aks', 'CREATE TABLE `{{table}}` (id int)'))));
        self::assertFalse(Migrator::isDmlOnly(array(array('', 'ALTER TABLE x ADD y int'))));
        self::assertFalse(Migrator::isDmlOnly(array(array('', 'drop table x'))));
        self::assertFalse(Migrator::isDmlOnly(array(
            array('', 'INSERT INTO x SET a = 1'),
            array('', 'TRUNCATE TABLE x'),
        )));
    }
}
