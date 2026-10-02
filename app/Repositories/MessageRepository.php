<?php

namespace App\Repositories;

use App\Core\Flags;
use App\Entities\Message;

final class MessageRepository extends BaseRepository
{
    public function insertMessage(Message $message): int
    {
        return $this->preparedInsertId(
            "INSERT INTO {{table}}
                (message_owner, message_sender, message_time, message_type, message_from, message_subject, message_text)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            array(
                $message->ownerId(),
                $message->senderId(),
                $message->time(),
                $message->type(),
                $message->from(),
                $message->subject(),
                $message->text(),
            ),
            'messages'
        );
    }

    /**
     * Messages d'un compte, les plus récents d'abord.
     *
     * @param bool|null $deleted false = existants (défaut), true = supprimés, null = les deux
     */
    public function findAllByOwner(int $ownerId, ?bool $deleted = false): array
    {
        [$filter, $params] = self::stateFilter($deleted);

        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE message_owner = ?" . $filter . " ORDER BY message_time DESC",
            array_merge(array($ownerId), $params),
            'messages'
        );
    }

    public function findByOwnerAndType(int $ownerId, int $type, ?bool $deleted = false): array
    {
        [$filter, $params] = self::stateFilter($deleted);

        return $this->preparedFetchAll(
            "SELECT * FROM {{table}} WHERE message_owner = ? AND message_type = ?" . $filter . " ORDER BY message_time DESC",
            array_merge(array($ownerId, $type), $params),
            'messages'
        );
    }

    /** Supprime **logiquement** tous les messages d'un compte (drapeau `DELETED`). */
    public function markDeletedByOwner(int $ownerId): int
    {
        return $this->preparedExecute(
            'UPDATE {{table}} SET `flags` = `flags` | ? WHERE message_owner = ?',
            array((string) Flags::DELETED, (string) $ownerId),
            'messages'
        );
    }

    /**
     * Supprime logiquement tous les messages antérieurs à une date.
     */
    public function markDeletedBefore(int $timestamp): int
    {
        return $this->preparedExecute(
            'UPDATE {{table}} SET `flags` = `flags` | ? WHERE message_time <= ?',
            array((string) Flags::DELETED, (string) $timestamp),
            'messages'
        );
    }

    /**
     * Un message existe-t-il, existant, pour ce compte ? Un message supprimé
     * logiquement n'est plus lisible : c'est ce que teste cette garde.
     */
    public function existsByIdAndOwner(int $messageId, int $ownerId): bool
    {
        [$filter, $params] = self::stateFilter(false);

        return $this->preparedFetchOne(
            "SELECT message_id FROM {{table}} WHERE message_id = ? AND message_owner = ?" . $filter,
            array_merge(array($messageId, $ownerId), $params),
            'messages'
        ) !== false;
    }

    /** Supprime logiquement un message (la ligne reste en base). */
    public function markDeletedById(int $messageId): int
    {
        return $this->setFlag(array($messageId), true);
    }

    /** Nombre de messages d'un type donné (modération). */
    public function countByType(int $type, ?bool $deleted = false): int
    {
        [$filter, $params] = self::stateFilter($deleted);

        $row = $this->preparedFetchOne(
            'SELECT COUNT(*) AS total FROM {{table}} WHERE message_type = ?' . $filter,
            array_merge(array((string) $type), $params),
            'messages'
        );

        return (int) ($row['total'] ?? 0);
    }

    /** Colonnes de tri de la liste des messages (liste blanche). */
    public const SORTS = array(
        'time' => 'message_time',
        'from' => 'message_from',
        'owner' => 'message_owner',
    );

    /**
     * Une page de messages d'un type, triée par colonne.
     *
     * La page historique calculait `LIMIT 1 + (page - 1) * 25` : le premier
     * message de la première page était donc sauté. Le décalage part de zéro.
     *
     * @return list<array<string, mixed>>
     */
    public function findByTypePage(
        int $type,
        int $offset,
        int $limit = 25,
        string $sort = 'time',
        string $order = 'desc',
        ?bool $deleted = false
    ): array {
        $column = self::SORTS[$sort] ?? self::SORTS['time'];
        $direction = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
        [$filter, $params] = self::stateFilter($deleted);

        return $this->preparedFetchAll(
            'SELECT * FROM {{table}} WHERE message_type = ?' . $filter . ' ORDER BY ' . $column . ' ' . $direction
            . ', message_id ' . $direction
            . ' LIMIT ' . max(0, $offset) . ',' . max(1, $limit),
            array_merge(array((string) $type), $params),
            'messages'
        );
    }

    /**
     * Suppression **logique** d'une sélection de messages.
     *
     * @param list<int> $ids
     *
     * @return int nombre de messages supprimés
     */
    public function markDeletedMany(array $ids): int
    {
        return $this->setFlag($ids, true);
    }

    /**
     * Rétablissement d'une sélection de messages (drapeau retiré).
     *
     * @param list<int> $ids
     *
     * @return int nombre de messages rétablis
     */
    /**
     * Efface tous les messages d'un compte (nettoyage anti-triche).
     *
     * Le jeu, lui, **marque** (`markDeletedById()` / `restoreMany()`) : cette suppression
     * physique n'existe que pour `DeleteSelectedUser()`.
     */
    public function purgeByOwner(int $ownerId): void
    {
        $this->purgeRows('messages', 'message_owner', $ownerId);
        $this->purgeRows('messages', 'message_sender', $ownerId);
    }

    public function restoreMany(array $ids): int
    {
        return $this->setFlag($ids, false);
    }

    /**
     * Pose ou retire le drapeau `DELETED` sur une sélection de messages.
     *
     * @param list<int|string> $ids
     *
     * @return int nombre de lignes touchées
     */
    private function setFlag(array $ids, bool $on): int
    {
        if ($ids === array()) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $expression = $on ? '`flags` = `flags` | ?' : '`flags` = `flags` & ~?';

        return $this->preparedExecute(
            'UPDATE {{table}} SET ' . $expression . ' WHERE message_id IN (' . $placeholders . ')',
            array_merge(array((string) Flags::DELETED), array_map('strval', array_values($ids))),
            'messages'
        );
    }
    public function markAllRead(int $ownerId, array $messfields, array $types): void
    {
        $sets = [];
        $params = array();
        foreach ($types as $type) {
            if (!isset($messfields[$type])) {
                continue;
            }
            $sets[] = "`" . $this->escape($messfields[$type]) . "` = '0'";
        }

        if ($sets === []) {
            return;
        }

        $this->preparedExecute(
            "UPDATE {{table}} SET " . implode(', ', $sets) . " WHERE id = ?",
            array($ownerId),
            'users'
        );
    }

    public function markTypeRead(int $ownerId, array $messfields, int $type, int $waiting): void
    {
        if (!isset($messfields[$type], $messfields[100])) {
            return;
        }

        $this->preparedExecute(
            "UPDATE {{table}} SET `" . $this->escape($messfields[$type]) . "` = '0',
             `" . $this->escape($messfields[100]) . "` = `" . $this->escape($messfields[100]) . "` - ?
             WHERE id = ?",
            array($waiting, $ownerId),
            'users'
        );
    }

    public function findAllyRecipients(int $allyId, ?int $rankFilter): array
    {
        if ($rankFilter === null || $rankFilter === 0) {
            return $this->preparedFetchAll(
                "SELECT id, username FROM {{table}} WHERE ally_id = ?",
                array($allyId),
                'users'
            );
        }

        return $this->preparedFetchAll(
            "SELECT id, username FROM {{table}} WHERE ally_id = ? AND ally_rank_id = ?",
            array($allyId, $rankFilter),
            'users'
        );
    }

    public function incrementUnreadColumn(int $ownerId, string $column, int $amount = 1): void
    {
        $this->preparedExecute(
            "UPDATE {{table}} SET `" . $this->escape($column) . "` = `" . $this->escape($column) . "` + ? WHERE id = ?",
            array($amount, $ownerId),
            'users'
        );
    }
}
