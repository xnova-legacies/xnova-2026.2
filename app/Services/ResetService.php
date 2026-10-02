<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;

/**
 * Remise à zéro d'un univers (ex `admin/XNovaResetUnivers.php`).
 *
 * La page historique renommait la table des planètes et celle des comptes en
 * tables de repli, recréait les originaux, vidait les tables de jeu, puis
 * réinscrivait les joueurs actifs (connectés dans les quinze derniers jours) avec
 * leur planète mère. La règle est ici, une seule fois, et l'appelant l'annonce
 * clairement — c'est l'action la plus destructrice du panneau.
 */
final class ResetService
{
    /** Tables vidées par la remise à zéro (tables de jeu, hors comptes/planètes). */
    private const PURGED = array(
        'aks', 'alliance', 'annonce', 'banned', 'buddy', 'chat', 'galaxy',
        'fleets', 'lunas', 'messages', 'notes', 'rw', 'statpoints',
    );

    /** Un joueur est réinscrit s'il s'est connecté dans les quinze derniers jours. */
    private const KEEP_ONLINE_SECONDS = 15 * 24 * 3600;

    /**
     * Remet l'univers à zéro.
     *
     * @return int nombre de comptes réinscrits
     */
    public function reset(): int
    {
        $this->backupTables();
        $this->purgeGameTables();

        $kept = $this->reimportActiveUsers();

        Connection::query('DROP TABLE {{table}}', 'planets_s');
        Connection::query('DROP TABLE {{table}}', 'users_s');
        Connection::preparedExecute(
            "UPDATE {{table}} SET `config_value` = ? WHERE `config_name` = 'users_amount' LIMIT 1",
            array((string) $kept),
            'config'
        );

        return $kept;
    }

    /** Copie les comptes et les planètes dans des tables de repli, puis recrée les originales. */
    private function backupTables(): void
    {
        foreach (array('planets', 'users') as $table) {
            Connection::query('RENAME TABLE {{table}} TO {{table}}_s', $table);
            Connection::query('CREATE TABLE IF NOT EXISTS {{table}} ( LIKE {{table}}_s )', $table);
        }
    }

    private function purgeGameTables(): void
    {
        foreach (self::PURGED as $table) {
            Connection::query('TRUNCATE TABLE {{table}}', $table);
        }
    }

    /** Réinscrit les joueurs actifs, chacun avec sa planète mère. */
    private function reimportActiveUsers(): int
    {
        $limit = time() - self::KEEP_ONLINE_SECONDS;
        $kept = 0;

        $users = Connection::fetchAll('SELECT * FROM {{table}}', 'users_s');

        foreach ($users as $user) {
            if ((int) $user['onlinetime'] <= $limit) {
                continue;
            }

            $home = Connection::preparedFetchOne(
                'SELECT `name` FROM {{table}} WHERE `id` = ?',
                array((int) $user['id_planet']),
                'planets_s'
            );

            if (!is_array($home) || (string) $home['name'] === '') {
                continue;
            }

            Connection::preparedExecute(
                'INSERT INTO {{table}} SET `username` = ?, `email` = ?, `email_2` = ?, `sex` = ?,'
                . " `id_planet` = '0', `authlevel` = ?, `dpath` = ?, `galaxy` = ?, `system` = ?,"
                . ' `planet` = ?, `register_time` = ?, `password` = ?',
                array(
                    (string) $user['username'],
                    (string) $user['email'],
                    (string) $user['email_2'],
                    (string) $user['sex'],
                    (int) $user['authlevel'],
                    (string) $user['dpath'],
                    (int) $user['galaxy'],
                    (int) $user['system'],
                    (int) $user['planet'],
                    (int) $user['register_time'],
                    (string) $user['password'],
                ),
                'users'
            );

            $new = Connection::preparedFetchOne(
                'SELECT `id` FROM {{table}} WHERE `username` = ? LIMIT 1',
                array((string) $user['username']),
                'users'
            );

            if (!is_array($new)) {
                continue;
            }

            $ownerId = (int) $new['id'];

            CreateOnePlanetRecord(
                (int) $user['galaxy'],
                (int) $user['system'],
                (int) $user['planet'],
                $ownerId,
                (string) $home['name'],
                true
            );

            $planet = Connection::preparedFetchOne(
                'SELECT `id` FROM {{table}} WHERE `id_owner` = ? LIMIT 1',
                array($ownerId),
                'planets'
            );

            if (!is_array($planet)) {
                continue;
            }

            Connection::preparedExecute(
                'UPDATE {{table}} SET `id_planet` = ?, `current_planet` = ? WHERE `id` = ?',
                array((int) $planet['id'], (int) $planet['id'], $ownerId),
                'users'
            );

            $kept++;
        }

        return $kept;
    }
}
