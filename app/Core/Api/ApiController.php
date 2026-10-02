<?php

namespace App\Core\Api;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;

/**
 * Base commune des contrôleurs JSON (/game/api/...).
 *
 * Elle centralise ce qui serait sinon répété dans chaque action :
 *   - l'accès à l'utilisateur / à la planète active (globals posés par common.php) ;
 *   - la lecture de l'entrée (JSON ou formulaire) ;
 *   - la vérification du jeton CSRF ;
 *   - le tableau de réponse { ok, data, state, messages } ;
 *   - la conversion des exceptions en réponse JSON.
 *
 * Réponse de succès : { "ok": true,  "data": {...}, "state": {...}, "messages": [...] }
 * Réponse d'erreur  : { "ok": false, "error": { "code", "message", "fields" } }
 */
abstract class ApiController extends AbstractController
{
    /**
     * Point d'entrée unique appelé par le front controller : garantit qu'aucune
     * exception ne casse le format JSON attendu par le client.
     */
    public function handle(string $action, Request $request): Response
    {
        try {
            if (!method_exists($this, $action)) {
                throw ApiException::notFound();
            }

            $result = $this->{$action}($request);

            if ($result instanceof Response) {
                return $result;
            }

            return $this->success(is_array($result) ? $result : []);
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (\Throwable $e) {
            $this->report($e);

            return ApiException::serverError()->toResponse();
        }
    }

    /** Utilisateur connecté (global posé par common.php). */
    protected function user(): array
    {
        $user = $GLOBALS['user'] ?? [];

        return is_array($user) ? $user : [];
    }

    /** Planète active (global posé par common.php). */
    protected function planet(): array
    {
        $planet = $GLOBALS['planetrow'] ?? [];

        return is_array($planet) ? $planet : [];
    }

    protected function requireUser(): array
    {
        $user = $this->user();

        if (empty($user['id'])) {
            throw ApiException::unauthenticated();
        }

        return $user;
    }

    protected function requirePlanet(): array
    {
        $planet = $this->planet();

        if (empty($planet['id'])) {
            throw ApiException::notFound('Aucune planète active.');
        }

        return $planet;
    }

    /** Corps brut de la requête (surchargeable en test). */
    protected function body(): string
    {
        $raw = file_get_contents('php://input');

        return is_string($raw) ? $raw : '';
    }

    /**
     * Entrée de l'action : JSON prioritaire, puis formulaire encodé, puis $_POST.
     * Permet au POST classique (sans JavaScript) de continuer à fonctionner.
     */
    protected function payload(Request $request): array
    {
        $raw = trim($this->body());

        if ($raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            parse_str($raw, $parsed);

            if ($parsed !== []) {
                return $parsed;
            }
        }

        return is_array($_POST) ? $_POST : [];
    }

    /** Jeton transmis par en-tête ou dans le corps de la requête. */
    protected function csrfTokenFrom(Request $request): string
    {
        $token = $request->server(CsrfToken::HEADER);

        if ($token === '') {
            $token = (string) ($this->payload($request)[CsrfToken::FIELD] ?? '');
        }

        return $token;
    }

    protected function requireCsrf(Request $request): void
    {
        if (!CsrfToken::validate($this->csrfTokenFrom($request))) {
            throw ApiException::forbidden('invalid_csrf', 'Jeton de sécurité invalide ou expiré.');
        }
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, mixed>  $state    État à appliquer côté client (évite un second appel).
     * @param list<array<string, string>> $messages
     */
    protected function success(array $data = [], array $state = [], array $messages = []): Response
    {
        return Response::json([
            'ok' => true,
            'data' => $data,
            'state' => $state,
            'messages' => $messages,
        ]);
    }

    /**
     * Journalisation applicative : le détail technique ne sort jamais dans la
     * réponse, mais il est utile dans le log (trace courte) pour diagnostiquer
     * une erreur 500 côté serveur.
     */
    protected function report(\Throwable $e): void
    {
        $trace = array();

        foreach (array_slice($e->getTrace(), 0, 6) as $frame) {
            $trace[] = ($frame['class'] ?? '') . ($frame['type'] ?? '')
                . ($frame['function'] ?? '') . '@' . ($frame['line'] ?? 0);
        }

        error_log(sprintf(
            '[xnova-api] %s: %s in %s:%d | %s',
            static::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            implode(' <- ', $trace)
        ));
    }
}
