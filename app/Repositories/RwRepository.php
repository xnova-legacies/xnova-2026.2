<?php

namespace App\Repositories;

final class RwRepository extends BaseRepository
{
    public function findByRid(string $rid): array|false
    {
        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE `rid` = ?',
            array($rid),
            'rw'
        );
    }

    /**
     * Range un rapport de combat (le rapport long, en HTML).
     *
     * `rid` est l'empreinte du rapport : elle sert d'adresse (`/game/rw?raport=…`) et arrive
     * donc de l'URL — elle se lie, jamais ne se concatène. `struck` dit qu'un des deux
     * camps a été anéanti au deuxième tour ; il commande l'indicateur du rapport.
     */
    public function insertReport(int $ownerId1, int $ownerId2, string $rid, int $struck, string $report): void
    {
        $this->preparedExecute(
            'INSERT INTO {{table}} SET `time` = UNIX_TIMESTAMP(), `id_owner1` = ?, `id_owner2` = ?,'
                . ' `rid` = ?, `struck` = ?, `raport` = ?',
            array($ownerId1, $ownerId2, $rid, $struck, $report),
            'rw'
        );
    }

    /**
     * Efface les rapports de combat d'un compte (nettoyage anti-triche).
     *
     * Un rapport appartient aux deux camps (`id_owner1`, `id_owner2`) : le compte effacé
     * laisse donc au second son rapport, qui reste lisible.
     */
    public function purgeByOwner(int $ownerId): void
    {
        $this->purgeRows('rw', 'id_owner1', $ownerId);
        $this->purgeRows('rw', 'id_owner2', $ownerId);
    }

    /**
     * Supprime les rapports antérieurs à une date (purge du panneau).
     *
     * La liste des messages supprimait ses messages et les rapports du même
     * âge : la règle de purge des rapports vit ici, une seule fois.
     */
    public function deleteBefore(int $timestamp): int
    {
        return $this->preparedExecute(
            'DELETE FROM {{table}} WHERE time <= ?',
            array((string) $timestamp),
            'rw'
        );
    }
}
