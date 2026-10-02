<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Flags;

/**
 * Accès à la table `game_modules` (état des modules du jeu).
 *
 * Une ligne par module **réglé** : le catalogue vit dans le code
 * (`App\Core\Modules`), et un module sans ligne est allumé. Les écritures sont donc
 * des `INSERT ... ON DUPLICATE KEY UPDATE` : poser un réglage crée la ligne au
 * besoin, sans que l'appelant ait à savoir si elle existe.
 */
final class ModuleRepository extends BaseRepository
{
    /**
     * Lignes existantes, indexées par nom technique.
     *
     * @return array<string, array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->preparedFetchAll(
            'SELECT * FROM {{table}} WHERE (`flags` & ?) = 0 ORDER BY `name` ASC',
            array((string) Flags::DELETED),
            'modules'
        );

        $modules = array();

        foreach ($rows as $row) {
            $modules[(string) $row['name']] = $row;
        }

        return $modules;
    }

    /** @return array<string, mixed>|false */
    public function findByName(string $name): array|false
    {
        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE `name` = ? AND (`flags` & ?) = 0',
            array($name, (string) Flags::DELETED),
            'modules'
        );
    }

    /** Allume ou éteint un module (la ligne est créée si elle manque). */
    public function saveActive(string $name, bool $active, int $time): void
    {
        $this->preparedExecute(
            'INSERT INTO {{table}} SET `name` = ?, `active` = ?, `flags` = ?, `created_time` = ?'
            . ' ON DUPLICATE KEY UPDATE `active` = VALUES(`active`)',
            array($name, $active ? 1 : 0, (string) Flags::DEFAULT, $time),
            'modules'
        );
    }

    /** Réécrit les réglages d'un module (la ligne est créée si elle manque). */
    public function saveSettings(string $name, string $settings, int $time): void
    {
        $this->preparedExecute(
            'INSERT INTO {{table}} SET `name` = ?, `settings` = ?, `flags` = ?, `created_time` = ?'
            . ' ON DUPLICATE KEY UPDATE `settings` = VALUES(`settings`)',
            array($name, $settings, (string) Flags::DEFAULT, $time),
            'modules'
        );
    }

    /**
     * Crée la ligne des modules qui n'en ont pas encore, avec l'état initial donné.
     *
     * @param array<string, bool> $states nom technique => allumé ?
     */
    public function insertMissing(array $states, int $time): int
    {
        $existing = $this->findAll();
        $created = 0;

        foreach ($states as $name => $active) {
            if (isset($existing[$name])) {
                continue;
            }

            $this->preparedExecute(
                'INSERT INTO {{table}} SET `name` = ?, `active` = ?, `settings` = \'\', `flags` = ?, `created_time` = ?',
                array($name, $active ? 1 : 0, (string) Flags::DEFAULT, $time),
                'modules'
            );

            $created++;
        }

        return $created;
    }
}
