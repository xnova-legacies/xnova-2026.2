<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\GameConstants;
use App\Services\ResearchService;
use PHPUnit\Framework\TestCase;

/**
 * Limite de la file de recherche.
 *
 * La règle vient de la constante de jeu `MAX_TECHNOLOGY_QUEUE_SIZE` (variable
 * `MAX_TECHNOLOGIE_QUEUE`) et s'applique au lancement d'une recherche
 * (`ResearchService::start()`), pour que la page du laboratoire et l'API
 * refusent la même chose.
 */
final class ResearchServiceTest extends TestCase
{
    /** @var list<string> */
    private array $touched = array();

    protected function tearDown(): void
    {
        foreach ($this->touched as $key) {
            putenv($key);
        }

        $this->touched = array();
    }

    public function testTheQueueAcceptsUpToTheGameConstant(): void
    {
        $max = GameConstants::maxTechnologyQueueSize();

        self::assertSame(10, $max);
        self::assertTrue(ResearchService::queueHasRoom(0));
        self::assertTrue(ResearchService::queueHasRoom($max - 1));
        self::assertFalse(ResearchService::queueHasRoom($max));
        self::assertFalse(ResearchService::queueHasRoom($max + 1));
    }

    public function testTheLimitFollowsTheEnvironment(): void
    {
        putenv('MAX_TECHNOLOGIE_QUEUE=2');
        $this->touched[] = 'MAX_TECHNOLOGIE_QUEUE';

        self::assertSame(2, GameConstants::maxTechnologyQueueSize());
        self::assertTrue(ResearchService::queueHasRoom(1));
        self::assertFalse(ResearchService::queueHasRoom(2));
    }
}
