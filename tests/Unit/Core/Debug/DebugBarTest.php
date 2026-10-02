<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Debug;

use App\Core\Debug\DebugBar;
use App\Core\Debug\SqlCollector;
use PHPUnit\Framework\TestCase;

/**
 * Barre de debug : seule la partie pure est testée (l'activation dépend des
 * fichiers .env et la collecte d'un vrai tampon de sortie).
 */
final class DebugBarTest extends TestCase
{
    public function testInjectHtmlPlacesTheBarBeforeTheClosingBody(): void
    {
        $html = "<html><body>jeu</body></html>\n";

        self::assertSame(
            "<html><body>jeu<div id=\"phpdebugbar\"></div></body></html>\n",
            DebugBar::injectHtml($html, '<div id="phpdebugbar"></div>')
        );
    }

    public function testInjectHtmlLeavesPagesWithoutBodyUntouched(): void
    {
        // Une réponse JSON (ou un fragment de page) ne doit jamais être modifiée.
        self::assertSame('{"ok":true}', DebugBar::injectHtml('{"ok":true}', '<div></div>'));
        self::assertSame('<p>x</p>', DebugBar::injectHtml('<p>x</p>', ''));
    }

    public function testSqlCollectorSummarisesStatements(): void
    {
        $collector = new SqlCollector();
        $collector->addQuery('SELECT * FROM {{table}}', 2.5, 'users', array(1));
        $collector->addQuery('UPDATE {{table}} SET a = ?', 1.5, 'planets');

        $data = $collector->collect();

        self::assertSame('queries', $collector->getName());
        self::assertSame(2, $data['nb_statements']);
        self::assertSame(4.0, $data['accumulated_duration']);
        self::assertSame('4.00 ms', $data['accumulated_duration_str']);
        self::assertSame('users', $data['statements'][0]['type']);
        self::assertSame(array(1), $data['statements'][0]['params']);
        self::assertTrue($data['statements'][1]['is_success']);
        self::assertArrayHasKey('queries', $collector->getWidgets());
    }
}
