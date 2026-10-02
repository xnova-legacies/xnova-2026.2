<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testHtmlResponseDefaultsToOkStatus(): void
    {
        $response = Response::html('<p>ok</p>');

        self::assertSame('<p>ok</p>', $response->content());
        self::assertSame(200, $response->status());
    }

    public function testRawResponseKeepsContentAndStatus(): void
    {
        $response = Response::raw("texte\n", 'text/plain; charset=UTF-8', 201);

        self::assertSame("texte\n", $response->content());
        self::assertSame(201, $response->status());
    }

    public function testJsonResponseEncodesPayload(): void
    {
        $response = Response::json(['success' => true, 'count' => 3]);

        self::assertSame('{"success":true,"count":3}', $response->content());
        self::assertSame(200, $response->status());
    }

    public function testJsonResponseKeepsUnicodeReadable(): void
    {
        self::assertSame('{"nom":"Vaisseau"}', Response::json(['nom' => 'Vaisseau'])->content());
    }

    public function testJsonResponseAcceptsAnExplicitStatus(): void
    {
        self::assertSame(422, Response::json(['error' => 'invalid'], 422)->status());
    }

    public function testRedirectHasEmptyBodyAndFoundStatus(): void
    {
        $response = Response::redirect('/game/overview');

        self::assertSame('', $response->content());
        self::assertSame(302, $response->status());
    }

    public function testRedirectCanUsePermanentStatus(): void
    {
        self::assertSame(301, Response::redirect('/game/overview', 301)->status());
    }

    public function testNotFoundResponse(): void
    {
        $response = Response::notFound();

        self::assertSame('404 Not Found' . "\n", $response->content());
        self::assertSame(404, $response->status());
    }

    public function testWithHeaderIsChainable(): void
    {
        $response = Response::html('x');

        self::assertSame($response, $response->withHeader('X-Test', '1'));
    }
}
