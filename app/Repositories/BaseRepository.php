<?php

namespace App\Repositories;

use App\Core\Flags;
use App\Database\Connection;

abstract class BaseRepository
{
    /**
     * Condition d'état d'une table à drapeau : les lignes existantes (défaut), les
     * supprimées logiquement, ou les deux (`null`).
     *
     * Le drapeau passe **en paramètre** (`(flags & ?) = 0`), jamais en valeur
     * concaténée. `$prefix` qualifie la colonne quand la requête joint une autre
     * table à drapeau (`users`, `planets`) : sans lui, MySQL refuse la condition
     * (« Column 'flags' in where clause is ambiguous »). Le préfixe reste **hors**
     * des accents graves — `` f.`flags` `` est la colonne d'une table aliasée, alors
     * que `` `f.flags` `` est un identifiant nommé littéralement « f.flags ».
     *
     * Une seule implémentation pour les tables à drapeau lues par un dépôt
     * (messages, notes, vols, rôles) : elles divergeaient à la copie.
     *
     * @return array{0: string, 1: list<string>}
     */
    protected static function stateFilter(?bool $deleted, string $prefix = ''): array
    {
        if ($deleted === null) {
            return array('', array());
        }

        $column = $prefix . '`flags`';

        if ($deleted) {
            return array(' AND (' . $column . ' & ?) = ?', array((string) Flags::DELETED, (string) Flags::DELETED));
        }

        return array(' AND (' . $column . ' & ?) = 0', array((string) Flags::DELETED));
    }

    /**
     * Forme d'un nom de colonne acceptée dans une requête construite.
     *
     * Un nom de colonne **ne peut pas** être un paramètre lié : sa forme est donc vérifiée
     * avant d'entrer dans le SQL — jamais une saisie, jamais un échappement d'identifiant
     * (échapper protège une valeur, pas un identifiant). C'est la règle partagée des dépôts
     * qui écrivent une colonne dynamique (`setColumn`, `updateSettingsFull`,
     * `incrementUnread`, `setField`, `decrementField`…).
     */
    protected static function isColumnName(string $name): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $name) === 1;
    }

    protected function query(string $sql, string $table = '')
    {
        return Connection::query($sql, $table);
    }

    protected function fetchOne(string $sql, string $table = ''): array|false
    {
        return Connection::fetchOne($sql, $table);
    }

    protected function fetchAll(string $sql, string $table = ''): array
    {
        return Connection::fetchAll($sql, $table);
    }

    protected function escape(string $value): string
    {
        return Connection::escape($value);
    }

    /**
     * Efface **physiquement** les lignes d'une table qui portent une valeur donnée.
     *
     * **Réservé au nettoyage anti-triche** (`UserFunctions::DeleteSelectedUser()`) : le jeu
     * supprime **logiquement** (`Flags::DELETED`, `markDeletedById()`…), jamais pour de bon,
     * et une relecture ne doit pas prendre cette méthode pour la règle ordinaire. Le nom de
     * colonne est vérifié (`isColumnName`) et la valeur part en paramètre lié.
     */
    protected function purgeRows(string $table, string $column, int $value): void
    {
        if (!self::isColumnName($column)) {
            return;
        }

        $this->preparedExecute(
            'DELETE FROM {{table}} WHERE `' . $column . '` = ?',
            array($value),
            $table
        );
    }

    protected function preparedFetchAll(string $sql, array $params, string $table = ''): array
    {
        return Connection::preparedFetchAll($sql, $params, $table);
    }

    protected function preparedFetchOne(string $sql, array $params, string $table = '')
    {
        return Connection::preparedFetchOne($sql, $params, $table);
    }

    protected function preparedExecute(string $sql, array $params, string $table = ''): int
    {
        return Connection::preparedExecute($sql, $params, $table);
    }

    protected function preparedInsertId(string $sql, array $params, string $table = ''): int
    {
        return Connection::preparedInsertId($sql, $params, $table);
    }

    protected function bindTable(string $sql, string $table): string
    {
        return str_replace('{{table}}', Connection::table($table), $sql);
    }
}
