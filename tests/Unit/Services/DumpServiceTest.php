<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DumpService;
use PHPUnit\Framework\TestCase;

/**
 * Le vidage lui-meme touche MySQL (non teste unitairement, voir la regle des
 * tests) : ces cas tiennent les **fonctions pures** qui composent le fichier.
 */
class DumpServiceTest extends TestCase
{
    /** L'echappement est injecte : on verifie le rendu sans base de donnees. */
    public static function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    public function testFileNameCarriesTheVersionAndTheTimestamp(): void
    {
        $this->assertSame(
            'xnova-2026.34-20261002123456.sql',
            DumpService::fileName('2026.34', '2026-10-02 12:34:56')
        );
    }

    public function testFileNameSurvivesAnEmptyOrHostileLabel(): void
    {
        $this->assertSame('xnova-20261002123456.sql', DumpService::fileName('', '2026-10-02 12:34:56'));
        $this->assertSame('xnova-..-..-etc-20261002123456.sql', DumpService::fileName('../../etc', '2026-10-02 12:34:56'));
    }

    public function testTupleRendersNullsNumbersAndQuotedText(): void
    {
        $columns = array('id', 'nom', 'metal', 'note', 'actif');

        $this->assertSame(
            "(7, 'Tour d''Or', 12.5, NULL, 1)",
            DumpService::tuple($columns, array(
                'id' => 7,
                'nom' => "Tour d'Or",
                'metal' => 12.5,
                'note' => null,
                'actif' => true,
            ), array(self::class, 'quote'))
        );
    }

    public function testTupleTreatsAMissingColumnAsNull(): void
    {
        $this->assertSame('(1, NULL)', DumpService::tuple(array('id', 'nom'), array('id' => 1), array(self::class, 'quote')));
    }

    public function testInsertGroupsSeveralTuplesOncePerTable(): void
    {
        $sql = DumpService::insert('game_users', array('id', 'nom'), array("(1, 'a')", "(2, 'b')"));

        $this->assertSame("INSERT INTO `game_users` (`id`, `nom`) VALUES (1, 'a'), (2, 'b');\n", $sql);
    }

    public function testInsertQuotesTheColumnNamesAndIgnoresAnEmptySet(): void
    {
        $this->assertSame("INSERT INTO `t` (`a``b`) VALUES (1);\n", DumpService::insert('t', array('a`b'), array('(1)')));
        $this->assertSame('', DumpService::insert('t', array(), array('(1)')));
        $this->assertSame('', DumpService::insert('t', array('id'), array()));
    }

    public function testHeaderAndFooterBracketTheFile(): void
    {
        $header = DumpService::header('2026.34', '2026-10-02 12:34:56', 'xnova');

        $this->assertStringContainsString('-- Version : 2026.34', $header);
        $this->assertStringContainsString('-- Date    : 2026-10-02 12:34:56', $header);
        $this->assertStringContainsString('-- Base    : xnova', $header);
        $this->assertStringContainsString('SET NAMES utf8;', $header);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=0;', $header);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=1;', DumpService::footer());
    }

    public function testTheChunkBoundsStaySane(): void
    {
        $this->assertGreaterThanOrEqual(50, DumpService::ROWS_PER_INSERT);
        $this->assertLessThanOrEqual(1048576, DumpService::CHUNK_BYTES);
    }
}
