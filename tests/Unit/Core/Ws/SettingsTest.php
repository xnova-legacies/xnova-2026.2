<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ws;

use App\Core\Ws\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv(Settings::ENABLED_VAR);
    }

    public function testFlagReadsBooleanValues(): void
    {
        foreach (array('1', 'true', 'TRUE', 'on', 'yes', 'oui', ' 1 ') as $value) {
            self::assertTrue(Settings::flag($value), $value . ' devrait activer le canal');
        }

        foreach (array('', '0', 'false', 'off', 'no', 'non', 'nope') as $value) {
            self::assertFalse(Settings::flag($value), $value . ' devrait laisser le repli HTTP');
        }
    }

    public function testEnabledFollowsTheEnvironment(): void
    {
        putenv(Settings::ENABLED_VAR . '=1');
        self::assertTrue(Settings::enabled());

        putenv(Settings::ENABLED_VAR . '=0');
        self::assertFalse(Settings::enabled());

        putenv(Settings::ENABLED_VAR);
        self::assertFalse(Settings::enabled());
    }

    public function testPublicUrlIsTrimmed(): void
    {
        putenv('WS_PUBLIC_URL= wss://exemple.org/ws ');
        self::assertSame('wss://exemple.org/ws', Settings::publicUrl());

        putenv('WS_PUBLIC_URL=');
        self::assertSame('', Settings::publicUrl());
    }
}
