<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Demande de contact « ami » (table `game_buddy`).
 */
final class Buddy extends AbstractEntity
{
    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function id(): int
    {
        return (int) $this->raw('id');
    }

    /** Joueur à l'origine de la demande. */
    public function senderId(): int
    {
        return (int) $this->raw('sender');
    }

    /** Joueur destinataire de la demande. */
    public function ownerId(): int
    {
        return (int) $this->raw('owner');
    }

    /** La demande est acceptée (valeur 1) ou en attente. */
    public function isActive(): bool
    {
        return (int) $this->raw('active') === 1;
    }

    public function text(): string
    {
        return (string) ($this->raw('text') ?? '');
    }
}
