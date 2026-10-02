<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Flags;
use App\Database\Connection;

/**
 * Accès à la table `game_roles` (rôles du système d'ACL).
 *
 * Les permissions d'un rôle vivent dans une seule colonne (`permissions`, liste
 * séparée par des virgules) : un rôle se lit toujours en entier, une table de
 * jointure n'apporterait rien. Le rôle du compte est porté par `users.role_id`
 * (0 = aucun rôle).
 *
 * Les rôles sont **hiérarchisés** (`position`, plus grand = plus haut) et le tri
affiché suit cette place ; monter ou descendre un rôle échange sa position avec
celle de son voisin (`findNeighbour()`).
 *
 * Les rôles supprimés logiquement (`Flags::DELETED`) disparaissent des listes et
 * ne donnent plus aucun droit : `AclService` ne lit que les rôles utilisables.
 */
final class RoleRepository extends BaseRepository
{
    /**
     * Rôles, avec le nombre de comptes qui les portent.
     *
     * La jointure est faite ici (et non dans une seconde requête) : elle porte
     * aussi le filtre d'état des comptes, sinon un compte supprimé serait compté.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllWithCounts(bool $deleted = false): array
    {
        [$filter, $params] = self::stateFilter($deleted, 'r.');

        return $this->preparedFetchAll(
            'SELECT r.*, COUNT(u.`id`) AS `users`'
            . ' FROM {{table}} AS r'
            . ' LEFT JOIN ' . Connection::table('users') . ' AS u'
            . ' ON u.`role_id` = r.`id` AND (u.`flags` & ?) = 0'
            . ' WHERE 1' . $filter
            . ' GROUP BY r.`id`'
            . ' ORDER BY r.`position` DESC, r.`label` ASC',
            array_merge(array((string) Flags::DELETED), $params),
            'roles'
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function findAll(): array
    {
        [$filter, $params] = self::stateFilter(false);

        return $this->preparedFetchAll(
            'SELECT * FROM {{table}} WHERE 1' . $filter . ' ORDER BY `position` DESC, `label` ASC',
            $params,
            'roles'
        );
    }

    /** Rôle existant par identifiant. */
    public function findById(int $id): array|false
    {
        [$filter, $params] = self::stateFilter(false);

        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE `id` = ?' . $filter,
            array_merge(array($id), $params),
            'roles'
        );
    }

    /**
     * Rôle existant par nom technique. Sert au semis des rôles par défaut : un rôle
     * déjà présent n'est pas recréé, l'administrateur garde ses réglages.
     */
    public function findByName(string $name): array|false
    {
        [$filter, $params] = self::stateFilter(null);

        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE `name` = ?' . $filter,
            array_merge(array($name), $params),
            'roles'
        );
    }

    /**
     * Rôle par identifiant, **quel que soit son état** : la page des rôles doit
     * pouvoir rétablir un rôle supprimé logiquement.
     */
    public function findByIdAny(int $id): array|false
    {
        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE `id` = ?',
            array($id),
            'roles'
        );
    }

    public function insert(string $name, string $label, string $description, string $permissions, int $position, int $time): int
    {
        return $this->preparedInsertId(
            'INSERT INTO {{table}} SET `name` = ?, `label` = ?, `description` = ?, `permissions` = ?,'
            . ' `position` = ?, `flags` = ?, `created_time` = ?',
            array($name, $label, $description, $permissions, $position, (string) Flags::DEFAULT, $time),
            'roles'
        );
    }

    /** Déplace un rôle : sa place dans la hiérarchie (`position`). */
    public function setPosition(int $id, int $position): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `position` = ? WHERE `id` = ?',
            array($position, $id),
            'roles'
        );
    }

    /**
     * Voisin immédiat d'un rôle dans la hiérarchie : celui juste au-dessus
     * (`$up = true`, la plus petite place supérieure) ou juste en dessous. Renvoie
     * `false` quand le rôle est déjà en bout d'échelle.
     *
     * @return array<string, mixed>|false
     */
    public function findNeighbour(int $position, bool $up): array|false
    {
        [$filter, $params] = self::stateFilter(false);

        return $this->preparedFetchOne(
            'SELECT * FROM {{table}} WHERE `position` ' . ($up ? '>' : '<') . ' ?' . $filter
            . ' ORDER BY `position` ' . ($up ? 'ASC' : 'DESC') . ' LIMIT 1',
            array_merge(array($position), $params),
            'roles'
        );
    }

    /** Place la plus basse de la hiérarchie (0 quand aucun rôle n'existe). */
    public function lowestPosition(): int
    {
        [$filter, $params] = self::stateFilter(false);

        $row = $this->preparedFetchOne(
            'SELECT MIN(`position`) AS `lowest` FROM {{table}} WHERE 1' . $filter,
            $params,
            'roles'
        );

        return (int) ($row['lowest'] ?? 0);
    }

    public function update(int $id, string $label, string $description): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `label` = ?, `description` = ? WHERE `id` = ?',
            array($label, $description, $id),
            'roles'
        );
    }

    /** Réécrit la liste complète des permissions d'un rôle. */
    public function setPermissions(int $id, string $permissions): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `permissions` = ? WHERE `id` = ?',
            array($permissions, $id),
            'roles'
        );
    }

    /** Pose ou retire le drapeau de suppression logique. */
    public function setFlag(int $id, int $flag, bool $on = true): void
    {
        $this->preparedExecute(
            'UPDATE {{table}} SET `flags` = `flags` ' . ($on ? '|' : '& ~') . ' ? WHERE `id` = ?',
            array((string) $flag, $id),
            'roles'
        );
    }

    /** Nombre de comptes existants portant ce rôle (avant suppression). */
    public function countUsers(int $id): int
    {
        return (int) ($this->preparedFetchOne(
            'SELECT COUNT(*) AS `total` FROM ' . Connection::table('users')
            . ' WHERE `role_id` = ? AND (`flags` & ?) = 0',
            array($id, (string) Flags::DELETED),
            'users'
        )['total'] ?? 0);
    }
}
