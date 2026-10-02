<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Api;

use App\Core\Api\ApiController;
use App\Core\Api\ApiException;
use App\Core\Api\CsrfToken;
use App\Core\Request;
use App\Core\Response;
use PHPUnit\Framework\TestCase;

final class ApiControllerTest extends TestCase
{
    private array $postBackup = [];
    private array $serverBackup = [];
    private array $sessionBackup = [];
    private array $globalsBackup = [];

    protected function setUp(): void
    {
        $this->postBackup = $_POST;
        $this->serverBackup = $_SERVER;
        $this->sessionBackup = $_SESSION ?? [];
        $this->globalsBackup = $GLOBALS;

        $_POST = [];
        $_SERVER = [];
        $_SESSION = [];
        unset($GLOBALS['user'], $GLOBALS['planetrow']);
    }

    protected function tearDown(): void
    {
        $_POST = $this->postBackup;
        $_SERVER = $this->serverBackup;
        $_SESSION = $this->sessionBackup;

        foreach (['user', 'planetrow'] as $key) {
            if (array_key_exists($key, $this->globalsBackup)) {
                $GLOBALS[$key] = $this->globalsBackup[$key];
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    /** Contrôleur de test : expose les helpers protégés sans base de données. */
    private function controller(): ApiController
    {
        return new class extends ApiController {
            public function okAction(Request $request): Response
            {
                return $this->success(['valeur' => 1], ['planet' => ['id' => 7]]);
            }

            public function payloadAction(Request $request): array
            {
                return $this->payload($request);
            }

            public function userAction(Request $request): Response
            {
                $user = $this->requireUser();

                return $this->success(['id' => (int) $user['id']]);
            }

            public function planetAction(Request $request): Response
            {
                $planet = $this->requirePlanet();

                return $this->success(['id' => (int) $planet['id']]);
            }

            public function validationAction(Request $request): Response
            {
                throw ApiException::validation('bad_value', 'Valeur invalide.', ['metal' => 'Trop élevé.']);
            }

            public function crashAction(Request $request): Response
            {
                throw new \RuntimeException('panne simulée');
            }

            public function csrfAction(Request $request): Response
            {
                $this->requireCsrf($request);

                return $this->success(['csrf' => true]);
            }

            protected function report(\Throwable $e): void
            {
                // Rien : on ne veut pas de bruit dans la sortie de test.
            }
        };
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->content(), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function testSuccessEnvelopeContainsOkDataStateAndMessages(): void
    {
        $response = $this->controller()->handle('okAction', new Request());
        $payload = $this->decode($response);

        self::assertSame(200, $response->status());
        self::assertTrue($payload['ok']);
        self::assertSame(['valeur' => 1], $payload['data']);
        self::assertSame(['planet' => ['id' => 7]], $payload['state']);
        self::assertSame([], $payload['messages']);
    }

    public function testUnknownActionReturnsJson404(): void
    {
        $response = $this->controller()->handle('missingAction', new Request());
        $payload = $this->decode($response);

        self::assertSame(404, $response->status());
        self::assertFalse($payload['ok']);
        self::assertSame('not_found', $payload['error']['code']);
    }

    public function testApiExceptionBecomesValidationErrorWithFields(): void
    {
        $response = $this->controller()->handle('validationAction', new Request());
        $payload = $this->decode($response);

        self::assertSame(422, $response->status());
        self::assertFalse($payload['ok']);
        self::assertSame('bad_value', $payload['error']['code']);
        self::assertSame('Trop élevé.', $payload['error']['fields']['metal']);
    }

    public function testUnexpectedErrorIsReportedAsServerErrorWithoutLeakingDetails(): void
    {
        $response = $this->controller()->handle('crashAction', new Request());
        $payload = $this->decode($response);

        self::assertSame(500, $response->status());
        self::assertSame('server_error', $payload['error']['code']);
        self::assertStringNotContainsString('panne simulée', $response->content());
    }

    public function testRequireUserFailsWithoutSession(): void
    {
        $response = $this->controller()->handle('userAction', new Request());

        self::assertSame(401, $response->status());
        self::assertSame('unauthenticated', $this->decode($response)['error']['code']);
    }

    public function testRequireUserReturnsTheGlobalUser(): void
    {
        $GLOBALS['user'] = ['id' => 42];

        $payload = $this->decode($this->controller()->handle('userAction', new Request()));

        self::assertTrue($payload['ok']);
        self::assertSame(42, $payload['data']['id']);
    }

    public function testRequirePlanetFailsWithoutPlanet(): void
    {
        $response = $this->controller()->handle('planetAction', new Request());

        self::assertSame(404, $response->status());
    }

    public function testPayloadFallsBackToPostForClassicFormSubmission(): void
    {
        $_POST = ['metal_mine' => '60'];

        $payload = $this->decode($this->controller()->handle('payloadAction', new Request()));

        self::assertSame(['metal_mine' => '60'], $payload['data']);
    }

    public function testCsrfIsRequiredForWrites(): void
    {
        $response = $this->controller()->handle('csrfAction', new Request());

        self::assertSame(403, $response->status());
        self::assertSame('invalid_csrf', $this->decode($response)['error']['code']);
    }

    public function testCsrfAcceptsTheHeaderToken(): void
    {
        $_SESSION[CsrfToken::SESSION_KEY] = 'jeton';
        $_SERVER[CsrfToken::HEADER] = 'jeton';

        $payload = $this->decode($this->controller()->handle('csrfAction', new Request()));

        self::assertTrue($payload['ok']);
    }

    public function testCsrfAcceptsTheTokenFromThePayload(): void
    {
        $_SESSION[CsrfToken::SESSION_KEY] = 'jeton';
        $_POST = ['_token' => 'jeton'];

        $payload = $this->decode($this->controller()->handle('csrfAction', new Request()));

        self::assertTrue($payload['ok']);
    }
}
