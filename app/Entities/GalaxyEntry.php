<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Case de le rendu galactique (table `game_galaxy`).
 *
 * Table de position : clé primaire composite (`galaxy`, `system`, `planet`).
 */
final class GalaxyEntry extends AbstractEntity
{
    public static function primaryKey(): array
    {
        return array('galaxy', 'system', 'planet');
    }

    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function galaxy(): int
    {
        return (int) $this->raw('galaxy');
    }

    public function system(): int
    {
        return (int) $this->raw('system');
    }

    public function planet(): int
    {
        return (int) $this->raw('planet');
    }

    /** Identifiant de la planète occupant la case (0 = case vide). */
    public function planetId(): int
    {
        return (int) $this->raw('id_planet');
    }

    public function isOccupied(): bool
    {
        return $this->planetId() > 0;
    }

    /** Métal laissé en débris dans la case. */
    public function metal(): int
    {
        return (int) $this->raw('metal');
    }

    /** Cristal laissé en débris dans la case. */
    public function crystal(): int
    {
        return (int) $this->raw('crystal');
    }

    /** Identifiant de la lune présente dans la case (0 = aucune). */
    public function moonId(): int
    {
        return (int) $this->raw('id_luna');
    }

    public function moonPosition(): int
    {
        return (int) $this->raw('luna');
    }

    public function hasMoon(): bool
    {
        return $this->moonId() > 0;
    }

    public function coordinates(): string
    {
        return $this->galaxy() . ':' . $this->system() . ':' . $this->planet();
    }
}
