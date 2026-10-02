<?php

declare(strict_types=1);

namespace App\Entities;

use App\Core\Acl;
use App\Core\Flags;

/**
 * Rôle du système d'ACL (table `game_roles`).
 *
 * Ses permissions sont stockées en **une seule colonne** (liste séparée par des
 * virgules) : un rôle se lit toujours en entier, la table de jointure n'apportait
 * rien. `permissions()` rend la liste rangée dans l'ordre du catalogue.
 */
final class Role extends AbstractEntity
{
    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function id(): int
    {
        return (int) $this->raw('id');
    }

    /** Nom technique (`operator`, `moderator`, `admin`, `super_admin`). */
    public function name(): string
    {
        return (string) ($this->raw('name') ?? '');
    }

    /** Libellé affiché (saisi par l'administrateur). */
    public function label(): string
    {
        return (string) ($this->raw('label') ?? '');
    }

    public function description(): string
    {
        return (string) ($this->raw('description') ?? '');
    }

    /** Liste brute des permissions, telle qu'elle est stockée. */
    public function permissionsRaw(): string
    {
        return (string) ($this->raw('permissions') ?? '');
    }

    /**
     * Permissions du rôle, rangées dans l'ordre du catalogue.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return Acl::parse($this->permissionsRaw());
    }

    public function flags(): int
    {
        return (int) $this->raw('flags');
    }

    public function isDeleted(): bool
    {
        return Flags::isDeleted($this->flags());
    }

    public function isEnabled(): bool
    {
        return Flags::isEnabled($this->flags());
    }

    /** Un rôle utilisable : actif et non supprimé logiquement. */
    public function isUsable(): bool
    {
        return Flags::usable($this->flags());
    }

    public function createdTime(): int
    {
        return (int) ($this->raw('created_time') ?? 0);
    }
}
