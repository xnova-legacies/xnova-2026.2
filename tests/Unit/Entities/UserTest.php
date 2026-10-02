<?php

declare(strict_types=1);

namespace Tests\Unit\Entities;

use App\Entities\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testMapsUserColumns(): void
    {
        $user = User::fromRow([
            'id' => '5',
            'username' => 'Neo',
            'email' => 'neo@example.com',
            'authlevel' => '1',
            'galaxy' => 2,
            'system' => 15,
            'planet' => 7,
            'current_planet' => '99',
            'register_time' => '1600000000',
        ]);

        self::assertSame(5, $user->id);
        self::assertSame('Neo', $user->username);
        self::assertSame('neo@example.com', $user->email);
        self::assertSame(1, $user->authlevel);
        self::assertSame(2, $user->galaxy);
        self::assertSame(15, $user->system);
        self::assertSame(7, $user->planet);
        self::assertSame(99, $user->currentPlanet);
        self::assertSame(1600000000, $user->registerTime);
    }

    public function testAppliesDefaultsForMissingColumns(): void
    {
        $user = User::fromRow([]);

        self::assertSame(0, $user->id);
        self::assertSame('', $user->username);
        self::assertSame('', $user->email);
        self::assertSame(0, $user->authlevel);
        self::assertNull($user->dpath);
        self::assertNull($user->avatar);
        self::assertNull($user->allyName);
        self::assertSame(0, $user->allyId);
        self::assertSame('', $user->bana);
        self::assertSame('', $user->vacationMode);
        self::assertSame([], $user->toArray());
    }

    public function testKeepsOptionalColumnsWhenPresent(): void
    {
        $user = User::fromRow([
            'dpath' => 'public/xnova/',
            'avatar' => 'avatar.png',
            'ally_name' => 'Les Anciens',
            'ally_id' => '3',
        ]);

        self::assertSame('public/xnova/', $user->dpath);
        self::assertSame('avatar.png', $user->avatar);
        self::assertSame('Les Anciens', $user->allyName);
        self::assertSame(3, $user->allyId);
    }

    public function testExposesLegacyRowValues(): void
    {
        $user = User::fromRow(['id' => 1, 'besonderheiten' => 'x']);

        self::assertSame('x', $user->raw('besonderheiten'));
        self::assertNull($user->raw('unknown'));
    }
}
