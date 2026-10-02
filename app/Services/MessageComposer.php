<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BbCode;

/**
 * Préparation du corps et du sujet d'un message privé.
 *
 * Reprend la chaîne legacy de ProfilController::messagesAction() :
 * trim(nl2br(BbCode::render(BbCode::smileys(strip_tags(texte, '<br>')))))
 * lorsque le BBCode est activé, sinon un simple nl2br.
 */
final class MessageComposer
{
    public const MAX_SUBJECT = 40;
    public const MAX_TEXT = 5000;

    /** Sujet nettoyé (balises retirées, longueur bornée). Fonction pure. */
    public static function subject(mixed $value): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, self::MAX_SUBJECT);
    }

    /** Message prêt à être stocké. Fonction pure (aucun helper legacy). */
    public static function body(mixed $value, bool $bbcodeEnabled): string
    {
        $text = strip_tags((string) $value, '<br>');

        if ($bbcodeEnabled) {
            $text = BbCode::render(BbCode::smileys($text));
        }

        return trim(nl2br($text));
    }

    /** Le message contient-il quelque chose ? Fonction pure. */
    public static function isEmptyMessage(mixed $value): bool
    {
        return trim(strip_tags((string) $value, '<br>')) === '';
    }
}
