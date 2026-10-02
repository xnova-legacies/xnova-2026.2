<?php

declare(strict_types=1);

namespace App\Core\Ws;

/**
 * Réglages du canal temps réel, lus dans l'environnement (.env du compose).
 *
 * `WS_ENABLED=0` suffit à désactiver le canal : le script client n'est plus
 * envoyé, le navigateur retombe sur l'API JSON (fetch) et son polling, et le
 * service `ws` n'est plus démarré par le compose (profil « websocket »).
 */
final class Settings
{
    /** Variable d'environnement qui active le canal. */
    public const ENABLED_VAR = 'WS_ENABLED';

    /** Variables reconnues comme vraies (insensibles à la casse). */
    public const TRUE_VALUES = array('1', 'true', 'on', 'yes', 'oui');

    /** Le canal temps réel est-il activé ? */
    public static function enabled(): bool
    {
        return self::flag((string) getenv(self::ENABLED_VAR));
    }

    /** Interprétation d'une valeur d'environnement booléenne. Fonction pure. */
    public static function flag(string $value): bool
    {
        return in_array(strtolower(trim($value)), self::TRUE_VALUES, true);
    }

    /**
     * URL publique du canal, telle que vue par le navigateur. Vide = déduite
     * côté client (même hôte, port 8081).
     */
    public static function publicUrl(): string
    {
        return trim((string) getenv('WS_PUBLIC_URL'));
    }
}
