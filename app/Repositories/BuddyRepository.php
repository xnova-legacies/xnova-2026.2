<?php

namespace App\Repositories;

final class BuddyRepository extends BaseRepository
{
    public function findById(int $id): array|false
    {
        return $this->preparedFetchOne('SELECT * FROM {{table}} WHERE `id` = ?', array($id), 'buddy');
    }

    /**
     * Efface les demandes d'ami d'un compte (nettoyage anti-triche).
     *
     * Les deux côtés partent : celles qu'il a envoyées (`sender`) et celles qu'il a reçues
     * (`owner`).
     */
    public function purgeByOwner(int $ownerId): void
    {
        $this->purgeRows('buddy', 'sender', $ownerId);
        $this->purgeRows('buddy', 'owner', $ownerId);
    }

    public function deleteById(int $id): void
    {
        $this->preparedExecute('DELETE FROM {{table}} WHERE `id` = ?', array($id), 'buddy');
    }

    public function activate(int $id): void
    {
        $this->preparedExecute("UPDATE {{table}} SET `active` = '1' WHERE `id` = ?", array($id), 'buddy');
    }

    public function findBetweenUsers(int $senderId, int $ownerId): array|false
    {
        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE (sender = ? AND owner = ?) OR (sender = ? AND owner = ?)',
            array($senderId, $ownerId, $ownerId, $senderId),
            'buddy'
        );
    }

    public function insertRequest(int $senderId, int $ownerId, string $text): void
    {
        $this->preparedExecute(
            'INSERT INTO {{table}} SET sender = ?, owner = ?, active = 0, text = ?',
            [(string) $senderId, (string) $ownerId, $text],
            'buddy'
        );
    }

    /**
     * Demandes en attente d'un compte, dans un sens ou dans l'autre.
     *
     * La colonne est un **choix fermé** (le sens demandé), jamais une saisie ; le compte
     * passe en paramètre lié.
     */
    public function findByMode(bool $ownRequests, int $userId): array
    {
        $column = $ownRequests ? 'sender' : 'owner';

        return $this->preparedFetchAll(
            'SELECT * FROM {{table}} WHERE active = 0 AND `' . $column . '` = ?',
            array($userId),
            'buddy'
        );
    }

    /**
     * Demandes d'ami reçues et encore en attente de réponse.
     *
     * Sert de notification (badge de la barre de navigation) : `active = 0`
     * recouvre les demandes envoyées comme reçues, seul `owner` (le destinataire)
     * distingue celles qui attendent une réponse du joueur.
     */
    public function countPendingRequests(int $userId): int
    {
        $row = $this->preparedFetchOne(
            'SELECT COUNT(*) AS total FROM {{table}} WHERE active = 0 AND owner = ?',
            array((string) $userId),
            'buddy'
        );

        return (int) ($row['total'] ?? 0);
    }

    public function findAllActive(int $userId): array
    {
        return $this->preparedFetchAll(
            'SELECT * FROM {{table}} WHERE (active = 1 AND sender = ?) OR (active = 1 AND owner = ?)',
            array($userId, $userId),
            'buddy'
        );
    }

    public function findUserSummaryById(int $id): array|false
    {
        return $this->preparedFetchOne(
            'SELECT id, username, galaxy, `system`, planet, onlinetime, ally_id, ally_name'
            . ' FROM {{table}} WHERE id = ?',
            array($id),
            'users'
        );
    }
}
