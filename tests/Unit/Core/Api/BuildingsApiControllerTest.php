<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Api;

use App\Controllers\Api\BuildingsApiController;
use App\Core\Api\CsrfToken;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\TestCase;

/**
 * Chemins de validation des actions de file (bâtiments).
 *
 * Les actions qui aboutissent écrivent en base via les primitives legacy :
 * elles ne sont donc pas couvertes ici (voir les tests de BuildingQueueService
 * pour les garde-fous purs).
 */
final class BuildingsApiControllerTest extends TestCase
{
    private const TOKEN = 'jeton-de-test';

    private array $globalsBackup = [];
    private array $sessionBackup = [];
    private array $serverBackup = [];
    private array $postBackup = [];

    protected function setUp(): void
    {
        $this->globalsBackup = $GLOBALS;
        $this->sessionBackup = $_SESSION ?? [];
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;

        $_SESSION = [CsrfToken::SESSION_KEY => self::TOKEN];
        $_SERVER = [];
        $_POST = [];
        $GLOBALS['user'] = ['id' => 1];
        $GLOBALS['planetrow'] = ['id' => 5, 'planet_type' => 1];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;

        foreach (['user', 'planetrow'] as $key) {
            if (array_key_exists($key, $this->globalsBackup)) {
                $GLOBALS[$key] = $this->globalsBackup[$key];
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function body(Response $response): array
    {
        $decoded = json_decode($response->content(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function action(string $action, array $post = []): Response
    {
        $_POST = $post;

        return (new BuildingsApiController())->handle($action, new Request());
    }

    public function testUnauthenticatedRequestIsRejected(): void
    {
        unset($GLOBALS['user']);

        $response = $this->action('addAction', ['element' => '1']);

        self::assertSame(401, $response->status());
        self::assertSame('unauthenticated', $this->body($response)['error']['code']);
    }

    public function testMissingCsrfTokenIsRejected(): void
    {
        $response = $this->action('addAction', ['element' => '1']);

        self::assertSame(403, $response->status());
        self::assertSame('invalid_csrf', $this->body($response)['error']['code']);
    }

    public function testMissingElementIsRejected(): void
    {
        $_SERVER[CsrfToken::HEADER] = self::TOKEN;

        $response = $this->action('addAction');

        self::assertSame(422, $response->status());
        self::assertSame('invalid_element', $this->body($response)['error']['code']);
    }

    public function testNonNumericElementIsRejected(): void
    {
        $_SERVER[CsrfToken::HEADER] = self::TOKEN;

        $response = $this->action('destroyAction', ['element' => 'mine']);

        self::assertSame(422, $response->status());
        self::assertSame('invalid_element', $this->body($response)['error']['code']);
    }

    public function testRemoveRequiresCsrfToo(): void
    {
        $response = $this->action('removeAction', ['position' => '2']);

        self::assertSame(403, $response->status());
    }

    public function testCancelRequiresCsrfToo(): void
    {
        $response = $this->action('cancelAction');

        self::assertSame(403, $response->status());
    }
}
