<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\GameConstants;
use PHPUnit\Framework\TestCase;

/**
 * Source unique des constantes de jeu : valeurs par défaut historiques,
 * surcharge par l'environnement, et noms legacy exposés à includes/constants.php.
 */
final class GameConstantsTest extends TestCase
{
    /** @var list<string> */
    private array $touched = array();

    /**
     * Les tests portent sur les **valeurs par défaut** : l'environnement de la
     * machine (variables du conteneur, `configs/.env.<env>`) ne doit pas décider
     * du résultat, sinon la suite passe ici et échoue là.
     */
    protected function setUp(): void
    {
        foreach (GameConstants::ENV_KEYS as $key) {
            $this->setEnv($key, '');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->touched as $key) {
            putenv($key);
        }

        $this->touched = array();
    }

    private function setEnv(string $key, string $value): void
    {
        $this->touched[] = $key;
        putenv($key . '=' . $value);
    }

    public function testDefaultsMatchTheHistoricalValues(): void
    {
        self::assertSame(9, GameConstants::maxGalaxyInWorld());
        self::assertSame(499, GameConstants::maxSystemInGalaxy());
        self::assertSame(15, GameConstants::maxPlanetInSystem());
        self::assertSame(2, GameConstants::spyReportRows());
        self::assertSame(4, GameConstants::fieldsByMoonbasisLevel());
        self::assertSame(21, GameConstants::maxPlayerPlanets());
        self::assertSame(86400, GameConstants::abandonedPositionDelay());
        self::assertSame(5, GameConstants::maxBuildingQueueSize());
        self::assertSame(10, GameConstants::maxTechnologyQueueSize());
        self::assertSame(1000, GameConstants::maxUnitsPerRow());
        self::assertSame(1.1, GameConstants::maxOverflow());
        self::assertSame(1000000, GameConstants::baseStorageSize());
        self::assertSame('admin@xnova.fr', GameConstants::adminEmail());
        self::assertFalse(GameConstants::showAdminInRecords());
        self::assertSame(
            array('metal' => 500, 'crystal' => 500, 'deuterium' => 500),
            GameConstants::colonyResources()
        );
    }

    public function testEnvironmentOverridesDefaults(): void
    {
        $this->setEnv('UNIVERSE_GALAXIES', '5');
        $this->setEnv('UNIVERSE_SYSTEMS', '99');
        $this->setEnv('STORAGE_OVERFLOW', '1,5');
        $this->setEnv('ADMIN_EMAIL', 'ops@exemple.org');
        $this->setEnv('MAX_BUILDING_QUEUE', '3');
        $this->setEnv('MAX_TECHNOLOGIE_QUEUE', '2');
        $this->setEnv('RECORDS_SHOW_ADMINS', '1');
        $this->setEnv('MAX_PLAYER_PLANETS', '7');
        $this->setEnv('ABANDONED_POSITION_DELAY', '3600');

        self::assertSame(5, GameConstants::maxGalaxyInWorld());
        self::assertSame(99, GameConstants::maxSystemInGalaxy());
        self::assertSame(1.5, GameConstants::maxOverflow());
        self::assertSame('ops@exemple.org', GameConstants::adminEmail());
        self::assertSame(3, GameConstants::maxBuildingQueueSize());
        self::assertSame(2, GameConstants::maxTechnologyQueueSize());
        self::assertSame(7, GameConstants::maxPlayerPlanets());
        self::assertTrue(GameConstants::showAdminInRecords());
        self::assertSame(3600, GameConstants::abandonedPositionDelay());
    }

    public function testEmptyOrMissingEnvironmentFallsBackToDefaults(): void
    {
        $this->setEnv('MAX_UNITS_PER_ROW', '');
        $this->setEnv('COLONY_METAL', '   ');

        self::assertSame(1000, GameConstants::maxUnitsPerRow());
        self::assertSame(500, GameConstants::colonyResources()['metal']);
    }

    public function testAllExposesTheLegacyNames(): void
    {
        self::assertSame(array_keys(GameConstants::DEFAULTS), array_keys(GameConstants::ENV_KEYS));
        self::assertSame(array_keys(GameConstants::DEFAULTS), array_keys(GameConstants::all()));

        $all = GameConstants::all();

        self::assertSame('admin@xnova.fr', $all['ADMINEMAIL']);
        self::assertSame(9, $all['MAX_GALAXY_IN_WORLD']);
        self::assertSame(1.1, $all['MAX_OVERFLOW']);
    }

    public function testAllFollowsTheEnvironment(): void
    {
        $this->setEnv('UNIVERSE_PLANETS', '12');

        self::assertSame(12, GameConstants::all()['MAX_PLANET_IN_SYSTEM']);
        self::assertSame(12, GameConstants::maxPlanetInSystem());
    }
}
