<?php

namespace App\Core\Api;

use App\Core\Response;

/**
 * Erreur métier renvoyée par l'API sous forme de réponse JSON normalisée.
 *
 * Réponse : { "ok": false, "error": { "code": ..., "message": ..., "fields": {...} } }
 */
final class ApiException extends \RuntimeException
{
    /**
     * @param array<string, string> $fields Erreurs par champ (formulaires).
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $fields = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function unauthenticated(): self
    {
        return new self('unauthenticated', 'Session expirée, merci de vous reconnecter.', 401);
    }

    public static function forbidden(string $code = 'forbidden', string $message = 'Action non autorisée.'): self
    {
        return new self($code, $message, 403);
    }

    public static function notFound(string $message = 'Ressource introuvable.'): self
    {
        return new self('not_found', $message, 404);
    }

    public static function methodNotAllowed(string $message = 'Méthode non autorisée.'): self
    {
        return new self('method_not_allowed', $message, 405);
    }

    /**
     * @param array<string, string> $fields
     */
    public static function validation(string $code, string $message, array $fields = []): self
    {
        return new self($code, $message, 422, $fields);
    }

    /** Erreur inattendue : aucun détail technique n'est exposé au client. */
    public static function serverError(): self
    {
        return new self('server_error', 'Une erreur interne est survenue.', 500);
    }

    public function toResponse(): Response
    {
        return Response::json([
            'ok' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'fields' => $this->fields === [] ? new \stdClass() : $this->fields,
            ],
        ], $this->status);
    }
}
