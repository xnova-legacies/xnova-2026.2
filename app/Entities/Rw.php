<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Rapport de combat (table `game_rw`).
 *
 * La table n'a pas d'`id` : le rapport est identifié par `rid`.
 */
final class Rw extends AbstractEntity
{
    public static function primaryKey(): array
    {
        return array('rid');
    }

    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    /** Premier propriétaire du rapport (attaquant). */
    public function owner1Id(): int
    {
        return (int) $this->raw('id_owner1');
    }

    /** Second propriétaire du rapport (défenseur). */
    public function owner2Id(): int
    {
        return (int) $this->raw('id_owner2');
    }

    public function rid(): string
    {
        return (string) ($this->raw('rid') ?? '');
    }

    /** Contenu du rapport (colonne `raport`). */
    public function report(): string
    {
        return (string) ($this->raw('raport') ?? '');
    }

    /** Un des deux camps a été anéanti au deuxième tour (colonne `struck`). */
    public function struck(): int
    {
        return (int) $this->raw('struck');
    }

    public function time(): int
    {
        return (int) $this->raw('time');
    }
}
