<?php

namespace App\Repositories;

final class BannedRepository extends BaseRepository
{
    /**
     * Clé d'un bannissement dans le pilori.
     *
     * Les colonnes `who` et `who2` sont des `varchar(11)` : un pseudo plus long
     * est tronqué (MySQL strict refuse la ligne sinon), et la levée du
     * bannissement doit chercher la même valeur. Une seule définition, donc.
     */
    public static function key(string $username): string
    {
        return mb_substr($username, 0, 11);
    }

    public function findAll(): array
    {
        return $this->fetchAll("SELECT * FROM {{table}} ORDER BY `id`;", 'banned');
    }

    /**
     * Ligne de pilori d'un bannissement : `who` sert à l'affichage, `who2` à la
     * levée (la page historique ne lisait que lui).
     */
    public function insert(
        string $username,
        string $reason,
        int $from,
        int $until,
        string $author,
        string $authorEmail,
    ): void {
        $key = self::key($username);

        $this->preparedExecute(
            'INSERT INTO {{table}} (`who`, `theme`, `who2`, `time`, `longer`, `author`, `email`)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?)',
            array(
                $key,
                $reason,
                $key,
                (string) $from,
                (string) $until,
                self::key($author),
                mb_substr($authorEmail, 0, 20),
            ),
            'banned'
        );
    }
}
