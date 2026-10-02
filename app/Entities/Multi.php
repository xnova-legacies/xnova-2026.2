<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Soupçon de multi-compte (table `game_multi`).
 */
final class Multi extends AbstractEntity
{
    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function id(): int
    {
        return (int) $this->raw('id');
    }

    /** Joueur signalé. */
    public function playerId(): int
    {
        return (int) $this->raw('player');
    }

    /** Joueur avec qui le partage est suspecté. */
    public function sharerId(): int
    {
        return (int) $this->raw('sharer');
    }

    public function reason(): string
    {
        return (string) ($this->raw('reason') ?? '');
    }
}
