<?php

namespace App\Core\Api;

/**
 * Jeton CSRF des appels AJAX.
 *
 * Le jeton est stocké en session (démarrée par common.php) et exposé au
 * navigateur via <meta name="csrf-token">. Le JavaScript le renvoie dans
 * l'en-tête X-CSRF-Token pour chaque écriture.
 */
final class CsrfToken
{
    public const SESSION_KEY = 'xnova_api_token';
    public const HEADER = 'HTTP_X_CSRF_TOKEN';
    public const FIELD = '_token';
    private const BYTES = 32;

    /** Jeton courant, créé à la demande. Chaîne vide si aucune session active. */
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY]) || $_SESSION[self::SESSION_KEY] === '') {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(self::BYTES));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }
}
