<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\GameConstants;
use App\Services\PlanetService;
use PHPUnit\Framework\TestCase;

/** Le renommage écrit en base : seule la préparation du nom est testée ici. */
final class PlanetServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // CheckInputStrings() (legacy) lit $ListCensure, défini en production par
        // includes/constants.php.
        $GLOBALS['ListCensure'] = GameConstants::censoredWords();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['ListCensure']);
    }
    public function testCleanNameTrimsAndBoundsTheLength(): void
    {
        self::assertSame('Terre', PlanetService::cleanName('  Terre  '));
        self::assertSame(
            '12345678901234567890',
            PlanetService::cleanName('123456789012345678901234')
        );
        self::assertSame(20, PlanetService::MAX_NAME_LENGTH);
    }

    public function testCleanNameRemovesForbiddenCharacters(): void
    {
        // CheckInputStrings (legacy) censure < > ' http script...
        self::assertSame('a*b', PlanetService::cleanName('a<b'));
        self::assertStringNotContainsString("'", PlanetService::cleanName("L'Etoile"));
    }

    public function testEmptyNameStaysEmpty(): void
    {
        self::assertSame('', PlanetService::cleanName('   '));
        self::assertSame('', PlanetService::cleanName(null));
    }
}
