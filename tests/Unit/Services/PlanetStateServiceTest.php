<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PlanetStateService;
use PHPUnit\Framework\TestCase;

final class PlanetStateServiceTest extends TestCase
{
    /**
     * Le client interroge /state chaque seconde : la production ne doit être
     * enregistrée que toutes les PERSIST_INTERVAL secondes (calcul en mémoire
     * entre-temps), sinon chaque joueur écrirait une fois par seconde.
     */
    public function testProductionIsPersistedEveryFiveSeconds(): void
    {
        self::assertSame(5, PlanetStateService::PERSIST_INTERVAL);

        self::assertFalse(PlanetStateService::shouldPersist(1000, 1000));
        self::assertFalse(PlanetStateService::shouldPersist(1004, 1000));
        self::assertTrue(PlanetStateService::shouldPersist(1005, 1000));
        self::assertTrue(PlanetStateService::shouldPersist(1100, 1000));
    }

    public function testProductionIsPersistedWhenThePlanetWasNeverUpdated(): void
    {
        self::assertTrue(PlanetStateService::shouldPersist(1000, 0));
    }
}
