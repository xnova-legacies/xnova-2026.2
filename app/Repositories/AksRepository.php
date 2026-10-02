<?php

namespace App\Repositories;

final class AksRepository extends BaseRepository
{
    public function insert(string $name, int $userId, int $fleetId, int $arrival, int $galaxy, int $system, int $planet): void
    {
        // Le préfixe « KV » fait partie de la valeur : il voyage donc avec elle, en paramètre lié.
        $this->preparedExecute(
            'INSERT INTO {{table}} SET
            `name` = ?,
            `participants` = ?,
            `fleets` = ?,
            `arrival` = ?,
            `galaxy` = ?,
            `system` = ?,
            `planet` = ?,
            `invited` = ?',
            array('KV' . $name, (string) $userId, (string) $fleetId, (string) $arrival, (string) $galaxy, (string) $system, (string) $planet, (string) $userId),
            'aks'
        );
    }

    /** Le groupe d'attaque que la file vient de créer (`false` s'il n'y est pas). */
    public function findCreated(string $name, int $userId, int $fleetId, int $arrival, int $galaxy, int $system, int $planet): array|false
    {
        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE
            `name` = ? AND
            `participants` = ? AND
            `flotten` = ? AND
            `arrival` = ? AND
            `galaxy` = ? AND
            `system` = ? AND
            `planet` = ? AND
            `invited` = ?',
            array('KV' . $name, (string) $userId, (string) $fleetId, (string) $arrival, (string) $galaxy, (string) $system, (string) $planet, (string) $userId),
            'aks'
        );
    }

    /** Groupe d'attaque par identifiant (`false` s'il n'existe plus). */
    public function findById(int $id): array|false
    {
        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE `id` = ?',
            array((string) $id),
            'aks'
        );
    }
}
