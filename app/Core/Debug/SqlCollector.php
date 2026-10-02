<?php

declare(strict_types=1);

namespace App\Core\Debug;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * Collecteur de requêtes SQL pour php-debugbar.
 *
 * La couche base est passée à PDO, mais les requêtes du jeu ne passent pas
 * toutes par lui : le collecteur maison enregistre donc les requêtes là où elles
 * passent réellement (Connection::executePrepared() et doquery()), avec leur durée.
 *
 * Le format de collect() est celui attendu par le widget
 * PhpDebugBar.Widgets.SQLQueriesWidget (onglet « Queries »).
 */
final class SqlCollector extends DataCollector implements Renderable
{
    /** @var list<array<string, mixed>> */
    private array $statements = [];

    /**
     * @param list<mixed> $params
     */
    public function addQuery(string $sql, float $durationMs, string $table = '', array $params = array()): void
    {
        $this->statements[] = array(
            'sql' => $sql,
            'params' => $params,
            'type' => $table !== '' ? $table : null,
            'duration' => $durationMs,
            'duration_str' => self::formatDuration($durationMs),
            'stmt_id' => count($this->statements),
            'is_success' => true,
            'connection' => 'xnova',
        );
    }

    public function collect(): array
    {
        $duration = 0.0;

        foreach ($this->statements as $statement) {
            $duration += (float) $statement['duration'];
        }

        return array(
            'nb_statements' => count($this->statements),
            'nb_failed_statements' => 0,
            'accumulated_duration' => $duration,
            'accumulated_duration_str' => self::formatDuration($duration),
            'statements' => $this->statements,
        );
    }

    public function getName(): string
    {
        return 'queries';
    }

    public function getWidgets(): array
    {
        return array(
            'queries' => array(
                'icon' => 'database',
                'widget' => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map' => 'queries',
                'default' => '[]',
            ),
            'queries:badge' => array(
                'map' => 'queries.nb_statements',
                'default' => 0,
            ),
        );
    }

    private static function formatDuration(float $ms): string
    {
        return $ms >= 1000.0
            ? sprintf('%.2f s', $ms / 1000)
            : sprintf('%.2f ms', $ms);
    }
}
