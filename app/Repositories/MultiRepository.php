<?php

namespace App\Repositories;

use App\Database\Connection;

final class MultiRepository extends BaseRepository
{
    /** Colonnes triables de la liste (clé d'URL → expression SQL). */
    public const SORTS = array(
        'id' => 'm.id',
        'player' => 'declarant.username',
        'sharer' => 'sharer.username',
        'reason' => 'm.reason',
    );

    public function insertDeclaration(int $playerId, string $reason): void
    {
        $this->preparedExecute(
            'INSERT INTO {{table}}(player, sharer, reason) VALUES (?, ?, ?)',
            [(string) $playerId, '0', $reason],
            'multi'
        );
    }

    /**
     * Déclarations de multi-comptes, pseudos résolus (page admin).
     *
     * La page historique lisait une colonne `text` qui n'existe pas : elle
     * affichait des lignes vides. Les colonnes réelles sont `player`, `sharer`
     * et `reason`.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllForAdmin(string $sort = 'id', string $order = 'desc'): array
    {
        return $this->preparedFetchAll(
            'SELECT m.id, m.player, m.sharer, m.reason,'
            . ' declarant.username AS declarant_name,'
            . ' sharer.username AS sharer_name'
            . ' FROM {{table}} AS m'
            . ' LEFT JOIN ' . Connection::table('users') . ' AS declarant ON declarant.id = m.player'
            . ' LEFT JOIN ' . Connection::table('users') . ' AS sharer ON sharer.id = m.sharer'
            . $this->orderBy($sort, $order),
            array(),
            'multi'
        );
    }

    /** Clause `ORDER BY`, bornée à la liste blanche (identifiant en départage). */
    private function orderBy(string $sort, string $order): string
    {
        $column = self::SORTS[$sort] ?? self::SORTS['id'];
        $direction = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        return ' ORDER BY ' . $column . ' ' . $direction . ', m.id ' . $direction;
    }
}
