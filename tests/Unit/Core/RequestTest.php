<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private array $serverBackup = [];
    private array $getBackup = [];
    private array $postBackup = [];
    private array $cookieBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->cookieBackup = $_COOKIE;

        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_COOKIE = $this->cookieBackup;
    }

    public function testUriKeepsQueryString(): void
    {
        $_SERVER['REQUEST_URI'] = '/game/fleet?mode=1';

        self::assertSame('/game/fleet?mode=1', (new Request())->uri());
    }

    public function testUriFallsBackToRoot(): void
    {
        self::assertSame('/', (new Request())->uri());
    }

    public function testPathStripsQueryString(): void
    {
        $_SERVER['REQUEST_URI'] = '/game/fleet?mode=1';

        self::assertSame('/game/fleet', (new Request())->path());
    }

    public function testMethodIsNormalisedToUpperCase(): void
    {
        self::assertSame('GET', (new Request())->method());

        $_SERVER['REQUEST_METHOD'] = 'post';

        self::assertSame('POST', (new Request())->method());
    }

    public function testGetReturnsScalarQueryParameters(): void
    {
        $_GET['galaxy'] = '4';

        $request = new Request();

        self::assertSame('4', $request->get('galaxy'));
        self::assertNull($request->get('system'));
        self::assertSame('9', $request->get('system', '9'));
    }

    public function testGetIgnoresNonScalarQueryParameters(): void
    {
        $_GET['mode'] = ['a'];

        self::assertSame('fallback', (new Request())->get('mode', 'fallback'));
    }

    public function testGetIntCastsOrFallsBack(): void
    {
        $_GET['planet'] = '12';
        $_GET['bad'] = 'abc';
        $_GET['empty'] = '';

        $request = new Request();

        self::assertSame(12, $request->getInt('planet'));
        self::assertSame(0, $request->getInt('bad'));
        self::assertSame(0, $request->getInt('empty'));
        self::assertSame(7, $request->getInt('missing', 7));
    }

    public function testPostAccessors(): void
    {
        $request = new Request();

        self::assertFalse($request->hasPost());
        self::assertNull($request->post('id'));

        $_POST['id'] = 42;

        self::assertTrue($request->hasPost());
        self::assertSame(42, $request->post('id'));
        self::assertSame('default', $request->post('missing', 'default'));
    }

    public function testCookieReturnsScalarValue(): void
    {
        $_COOKIE['nova'] = 'abc';

        self::assertSame('abc', (new Request())->cookie('nova'));
        self::assertNull((new Request())->cookie('unknown'));
    }

    public function testServerAccessorUsesDefaultWhenMissing(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';

        $request = new Request();

        self::assertSame('localhost', $request->server('SERVER_NAME'));
        self::assertSame('fallback', $request->server('HTTP_HOST', 'fallback'));
    }

    public function testRefererReadsHttpRefererHeader(): void
    {
        $_SERVER['HTTP_REFERER'] = '/game/overview';

        self::assertSame('/game/overview', (new Request())->referer());
    }
}
