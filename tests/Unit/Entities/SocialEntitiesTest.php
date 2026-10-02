<?php

declare(strict_types=1);

namespace Tests\Unit\Entities;

use App\Entities\Ban;
use App\Entities\Buddy;
use App\Entities\Multi;
use PHPUnit\Framework\TestCase;

/**
 * Entités sociales du Core de l'application : contacts, multi-comptes, bannissements.
 *
 * Les entités d'un module — la note, le message de tchat, l'annonce — sont testées dans le
 * module qui les porte (`modules/<nom>/tests/`) : elles voyagent avec lui.
 */
final class SocialEntitiesTest extends TestCase
{
    public function testBuddyTellsPendingFromAcceptedRequest(): void
    {
        $pending = Buddy::fromRow(array('id' => '3', 'sender' => '10', 'owner' => '20', 'active' => '0', 'text' => 'salut'));
        $accepted = Buddy::fromRow(array('id' => '4', 'sender' => '10', 'owner' => '20', 'active' => '1'));

        self::assertSame(10, $pending->senderId());
        self::assertSame(20, $pending->ownerId());
        self::assertSame('salut', $pending->text());
        self::assertFalse($pending->isActive());
        self::assertTrue($accepted->isActive());
    }

    public function testMultiExposesBothSuspectsAndTheReason(): void
    {
        $multi = Multi::fromRow(array('id' => '2', 'player' => '5', 'sharer' => '6', 'reason' => 'IP identique'));

        self::assertSame(2, $multi->id());
        self::assertSame(5, $multi->playerId());
        self::assertSame(6, $multi->sharerId());
        self::assertSame('IP identique', $multi->reason());
    }

    public function testBanExposesItsLegacyColumnNames(): void
    {
        $ban = Ban::fromRow(array(
            'id' => '1',
            'who' => 'Fritz',
            'theme' => 'Insultes',
            'who2' => 'Fritz2',
            'time' => '1699999999',
            'longer' => '604800',
            'author' => 'Yann',
            'email' => 'a@b.c',
        ));

        self::assertSame(1, $ban->id());
        self::assertSame('Fritz', $ban->who());
        self::assertSame('Fritz2', $ban->secondWho());
        self::assertSame('Insultes', $ban->reason());
        self::assertSame(1699999999, $ban->bannedAt());
        self::assertSame(604800, $ban->duration());
        self::assertSame('Yann', $ban->author());
        self::assertSame('a@b.c', $ban->email());
    }
}
