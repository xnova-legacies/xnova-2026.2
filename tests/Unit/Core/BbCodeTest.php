<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\BbCode;
use PHPUnit\Framework\TestCase;

final class BbCodeTest extends TestCase
{
    public function testRenderHandlesBasicFormattingTags(): void
    {
        self::assertSame('<b>gras</b>', BbCode::render('[b]gras[/b]'));
        self::assertSame('<strong>fort</strong>', BbCode::render('[strong]fort[/strong]'));
        self::assertSame('<i>italique</i>', BbCode::render('[i]italique[/i]'));
    }

    public function testRenderHandlesUnderlineAndStrikeThrough(): void
    {
        self::assertSame(
            '<span style="text-decoration: underline;">souligné</span>',
            BbCode::render('[u]souligné[/u]')
        );
        self::assertSame(
            '<span style="text-decoration: line-through;">barré</span>',
            BbCode::render('[s]barré[/s]')
        );
        self::assertSame(
            '<span style="text-decoration: line-through;">barré</span>',
            BbCode::render('[del]barré[/del]')
        );
    }

    public function testRenderEscapesHtmlBeforeApplyingTags(): void
    {
        self::assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            BbCode::render('<script>alert(1)</script>')
        );
    }

    public function testRenderHandlesQuoteCodeAndList(): void
    {
        self::assertSame('<blockquote>citation</blockquote>', BbCode::render('[quote]citation[/quote]'));
        self::assertSame('<pre>code</pre>', BbCode::render('[code]code[/code]'));
        self::assertSame(
            '<ul><li>un</li><li>deux</li></ul>',
            BbCode::render('[list][*]un[*]deux[/list]')
        );
    }

    public function testRenderBuildsLinks(): void
    {
        self::assertSame(
            '<a href="http://example.com" title="Exemple">Exemple</a>',
            BbCode::render('[url=http://example.com]Exemple[/url]')
        );
        self::assertSame(
            '<a href="mailto:a@b.fr" title="a@b.fr">contact</a>',
            BbCode::render('[email=a@b.fr]contact[/email]')
        );
    }

    public function testRenderColorsText(): void
    {
        self::assertSame('<span style="color: red;">rouge</span>', BbCode::render('[color=red]rouge[/color]'));
    }

    public function testRenderPrefixesRelativeImagePaths(): void
    {
        self::assertSame(
            '<img src="./images/photo.png" alt="./images/photo.png" title="./images/photo.png" />',
            BbCode::render('[img]photo.png[/img]')
        );
    }

    public function testRenderKeepsAbsoluteImagePaths(): void
    {
        // Les émoticônes utilisent un chemin absolu qui doit rester intact sous le routeur MVC.
        self::assertSame(
            '<img src="/emoticones/Smile.png" alt="/emoticones/Smile.png" title="/emoticones/Smile.png" />',
            BbCode::render('[img]/emoticones/Smile.png[/img]')
        );
    }

    public function testRenderConvertsNewLinesToHtmlBreaks(): void
    {
        self::assertSame('ligne1<br />ligne2', BbCode::render("ligne1\nligne2"));
    }

    public function testSmileysReplacesShortcutsWithImageTags(): void
    {
        self::assertSame('[img]/emoticones/Smile.png[/img]', BbCode::smileys('Smile'));
        self::assertSame('[img]/emoticones/wow.png[/img]', BbCode::smileys('wow'));
    }

    public function testUrlfixBuildsAnAnchor(): void
    {
        self::assertSame('<a href="/game/overview" title="Vue">Vue</a>', BbCode::urlfix('/game/overview', 'Vue'));
    }
}
