<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Api;

use App\Core\Api\CsrfToken;
use PHPUnit\Framework\TestCase;

final class CsrfTokenTest extends TestCase
{
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
    }

    public function testValidateAcceptsTheSessionToken(): void
    {
        $_SESSION[CsrfToken::SESSION_KEY] = 'jeton-de-test';

        self::assertTrue(CsrfToken::validate('jeton-de-test'));
    }

    public function testValidateRejectsInvalidOrMissingToken(): void
    {
        $_SESSION[CsrfToken::SESSION_KEY] = 'jeton-de-test';

        self::assertFalse(CsrfToken::validate('autre-valeur'));
        self::assertFalse(CsrfToken::validate(''));
        self::assertFalse(CsrfToken::validate(null));
    }

    public function testValidateRejectsEverythingWithoutSessionToken(): void
    {
        self::assertFalse(CsrfToken::validate('jeton-de-test'));
        self::assertFalse(CsrfToken::validate(''));
    }

    public function testTokenRequiresAnActiveSession(): void
    {
        // Aucun session_start() n'a été appelé dans le processus de test.
        self::assertSame('', CsrfToken::token());
    }

    public function testHeaderNameIsTheExpectedServerKey(): void
    {
        self::assertSame('HTTP_X_CSRF_TOKEN', CsrfToken::HEADER);
    }
}
