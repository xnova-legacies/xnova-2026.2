<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MessageComposer;
use PHPUnit\Framework\TestCase;

final class MessageComposerTest extends TestCase
{
    public function testSubjectRemovesTagsAndBounds(): void
    {
        self::assertSame('Bonjour', MessageComposer::subject('  <b>Bonjour</b> '));
        self::assertSame(40, MessageComposer::MAX_SUBJECT);
    }

    public function testEmptyMessageIsDetected(): void
    {
        self::assertTrue(MessageComposer::isEmptyMessage('   '));
        self::assertTrue(MessageComposer::isEmptyMessage('<b></b>'));
        self::assertFalse(MessageComposer::isEmptyMessage('salut'));
    }

    public function testBodyWithoutBbcodeKeepsLineBreaks(): void
    {
        self::assertSame("ligne1<br />\nligne2", MessageComposer::body("ligne1\nligne2", false));
    }

    public function testBodyWithBbcodeRendersTags(): void
    {
        self::assertSame('<b>gras</b>', MessageComposer::body('[b]gras[/b]', true));
    }

    public function testBodyRemovesHtmlTags(): void
    {
        self::assertSame('script', MessageComposer::body('<script>script</script>', false));
    }
}
