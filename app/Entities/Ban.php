<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Bannissement (table `game_banned`).
 *
 * Colonnes héritées : `theme` porte le motif, `longer` la durée,
 * `who` / `who2` les identifiants concernés.
 */
final class Ban extends AbstractEntity
{
    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function id(): int
    {
        return (int) $this->raw('id');
    }

    public function who(): string
    {
        return (string) ($this->raw('who') ?? '');
    }

    public function secondWho(): string
    {
        return (string) ($this->raw('who2') ?? '');
    }

    /** Motif du bannissement (colonne `theme`). */
    public function reason(): string
    {
        return (string) ($this->raw('theme') ?? '');
    }

    /** Horodatage du bannissement. */
    public function bannedAt(): int
    {
        return (int) $this->raw('time');
    }

    /** Durée du bannissement (colonne `longer`). */
    public function duration(): int
    {
        return (int) $this->raw('longer');
    }

    public function author(): string
    {
        return (string) ($this->raw('author') ?? '');
    }

    public function email(): string
    {
        return (string) ($this->raw('email') ?? '');
    }
}
