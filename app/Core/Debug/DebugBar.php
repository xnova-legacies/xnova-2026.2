<?php

declare(strict_types=1);

namespace App\Core\Debug;

/**
 * Barre de debug (php-debugbar) branchée sur le jeu.
 *
 * Elle n'est active que si la variable d'environnement DEBUG_BAR=1 (fichier
 * .env.<env>) ET si la dépendance de développement php-debugbar/php-debugbar est
 * installée : en production, `enabled()` répond faux et n'a plus aucun coût
 * (résultat mis en cache, aucune méthode appelée dans la boucle de jeu).
 *
 * L'injection passe par un tampon de sortie : elle couvre aussi bien les
 * réponses des contrôleurs que les pages legacy (display()), et se retire
 * d'elle-même quand la sortie n'est pas du HTML (API JSON, fichiers, images).
 */
final class DebugBar
{
    /** Assets servis depuis vendor/ (le dépôt est la racine web). */
    private const ASSETS_URL = '/vendor/php-debugbar/php-debugbar/resources';

    private static ?\DebugBar\StandardDebugBar $bar = null;

    private static ?SqlCollector $sql = null;

    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        if (!class_exists(\DebugBar\StandardDebugBar::class)) {
            return self::$enabled = false;
        }

        if (!function_exists('xnova_env')) {
            $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3);
            include_once $root . '/includes/env.php';
        }

        return self::$enabled = xnova_env('DEBUG_BAR', '0') === '1';
    }

    /** Prépare la barre et branche l'injection en fin de page. */
    public static function boot(): void
    {
        if (!self::enabled() || self::$bar !== null) {
            return;
        }

        $bar = new \DebugBar\StandardDebugBar();

        if (isset($bar['time'])) {
            $bar['time']->startMeasure('xnova', 'Page');
        }

        self::$sql = new SqlCollector();
        $bar->addCollector(self::$sql);
        self::$bar = $bar;

        ob_start(array(self::class, 'flush'));

        register_shutdown_function(static function (): void {
            if (isset($bar['time'])) {
                $bar['time']->stopMeasure('xnova');
            }
        });
    }

    /**
     * Enregistre une requête SQL (appelé aussi par le code legacy, d'où le
     * garde-fou : sans boot(), la méthode ne fait rien).
     *
     * @param list<mixed> $params
     */
    public static function logQuery(string $sql, float $durationMs, string $table = '', array $params = array()): void
    {
        self::$sql?->addQuery($sql, $durationMs, $table, $params);
    }

    /** Callback de tampon de sortie : glisse la barre avant </body>. */
    public static function flush(string $buffer): string
    {
        if (self::$bar === null || $buffer === '' || self::looksLikeJson($buffer)) {
            return $buffer;
        }

        if (str_contains($buffer, 'phpdebugbar')) {
            return $buffer;
        }

        try {
            $renderer = self::$bar->getJavascriptRenderer(self::ASSETS_URL);
            // renderHead() apporte les assets (CSS/JS) et render() le code
            // d'initialisation : injectés ensemble avant </body>, l'ordre est
            // conservé (les <script src> sont exécutés avant l'initialisation).
            $assets = $renderer->renderHead() . $renderer->render();
        } catch (\Throwable) {
            return $buffer;
        }

        return self::injectHtml($buffer, $assets);
    }

    /** Insère le rendu de la barre avant </body> (fonction pure, testée). */
    public static function injectHtml(string $html, string $assets): string
    {
        $position = stripos($html, '</body>');

        if ($position === false || $assets === '') {
            return $html;
        }

        return substr($html, 0, $position) . $assets . substr($html, $position);
    }

    /** Détecte une réponse JSON (en-tête ou contenu) : rien à injecter dedans. */
    private static function looksLikeJson(string $buffer): bool
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') === 0 && stripos($header, 'json') !== false) {
                return true;
            }
        }

        $start = ltrim($buffer);

        return $start !== '' && ($start[0] === '{' || $start[0] === '[');
    }
}
