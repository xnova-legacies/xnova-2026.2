<?php

declare(strict_types=1);

namespace Tests\Unit\Entities;

use App\Entities\ConfigEntry;
use App\Entities\Declared;
use App\Entities\Rw;
use App\Entities\StatPoints;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Entités de journalisation et de classement : rapports, points, erreurs,
 * configuration, déclarations de guerre.
 */
final class RecordsEntitiesTest extends TestCase
{
    public function testRwIsIdentifiedByItsRid(): void
    {
        $rw = Rw::fromRow(array(
            'id_owner1' => '1',
            'id_owner2' => '2',
            'rid' => 'a1b2c3',
            'raport' => 'Rapport de combat',
            'struck' => '0',
            'time' => '1700000000',
        ));

        self::assertSame(array('rid'), Rw::primaryKey());
        self::assertSame('a1b2c3', $rw->rid());
        self::assertSame(1, $rw->owner1Id());
        self::assertSame(2, $rw->owner2Id());
        self::assertSame('Rapport de combat', $rw->report());
        self::assertSame(0, $rw->struck());
        self::assertSame(1700000000, $rw->time());
    }

    public function testStatPointsReadsEachFamily(): void
    {
        $stats = StatPoints::fromRow(array(
            'id_owner' => '1',
            'id_ally' => '0',
            'stat_type' => '1',
            'stat_code' => '2',
            'stat_date' => '1700000000',
            'tech_rank' => '12',
            'tech_old_rank' => '15',
            'tech_points' => '2048',
            'tech_count' => '7',
            'build_rank' => '3',
            'fleet_rank' => '9',
            'defs_count' => '42',
            'total_rank' => '5',
            'total_points' => '98765',
        ));

        self::assertSame(1, $stats->ownerId());
        self::assertSame(2, $stats->statCode());
        self::assertSame(1700000000, $stats->statDate());
        self::assertSame(2048, $stats->pointsOf('tech'));
        self::assertSame(12, $stats->rankOf('tech'));
        self::assertSame(15, $stats->oldRankOf('tech'));
        self::assertSame(7, $stats->countOf('tech'));
        self::assertSame(3, $stats->rankOf('build'));
        self::assertSame(9, $stats->rankOf('fleet'));
        self::assertSame(42, $stats->countOf('defs'));
        self::assertSame(98765, $stats->totalPoints());
        self::assertSame(5, $stats->totalRank());
        self::assertSame(array('tech', 'build', 'defs', 'fleet', 'total'), StatPoints::families());
    }

    public function testStatPointsRejectsAnUnknownFamily(): void
    {
        $stats = StatPoints::fromRow(array('total_points' => '10'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inconnue');

        $stats->pointsOf('inconnue');
    }

    public function testConfigEntryConvertsItsValue(): void
    {
        $enabled = ConfigEntry::fromRow(array('config_name' => 'chat_enabled', 'config_value' => '1'));
        $disabled = ConfigEntry::fromRow(array('config_name' => 'chat_enabled', 'config_value' => '0'));
        $textual = ConfigEntry::fromRow(array('config_name' => 'nom', 'config_value' => 'YES'));
        $empty = ConfigEntry::fromRow(array());

        self::assertSame(array('config_name'), ConfigEntry::primaryKey());
        self::assertSame('chat_enabled', $enabled->name());
        self::assertSame(1, $enabled->valueAsInt());
        self::assertTrue($enabled->valueAsBool());
        self::assertFalse($disabled->valueAsBool());
        self::assertTrue($textual->valueAsBool());
        self::assertFalse($empty->valueAsBool());
        self::assertSame('', $empty->value());
    }

    /**
     * `ConfigRepository::findValue()` ne sélectionne que `config_value` :
     * l'entité doit accepter une ligne partielle.
     */
    public function testConfigEntryAcceptsAPartialRow(): void
    {
        $entry = ConfigEntry::fromRow(array('config_value' => '7'));

        self::assertSame('', $entry->name());
        self::assertSame(7, $entry->valueAsInt());
        self::assertFalse($entry->valueAsBool());
    }

    public function testDeclaredOnlyKeepsFilledTargets(): void
    {
        $war = Declared::fromRow(array(
            'declarator' => '10',
            'declared_1' => '20',
            'declared_2' => '',
            'declared_3' => '30',
            'reason' => 'Espionnage',
            'declarator_name' => 'Ariane',
        ));

        self::assertSame(array('declarator', 'declared_1'), Declared::primaryKey());
        self::assertSame('10', $war->declarator());
        self::assertSame('Ariane', $war->declaratorName());
        self::assertSame('Espionnage', $war->reason());
        self::assertSame(array('20', '30'), $war->targets());

        self::assertSame(array(), Declared::fromRow(array())->targets());
    }
}
