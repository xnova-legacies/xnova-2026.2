<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Groupe d'attaque combinée (table `game_aks`).
 *
 * Les colonnes `participants` (participants) et `fleets` (flottes attendues)
 * contiennent des listes sérialisées héritées : elles restent accessibles
 * via `raw()` et `toArray()`.
 */
final class Aks extends AbstractEntity
{
    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function id(): int
    {
        return (int) $this->raw('id');
    }

    public function name(): string
    {
        return (string) ($this->raw('name') ?? '');
    }

    /** Horodatage de l'arrivée prévue du groupe. */
    public function arrival(): int
    {
        return (int) $this->raw('arrival');
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

    /** Nombre d'invitations ouvertes. */
    public function invited(): int
    {
        return (int) $this->raw('invited');
    }

    public function coordinates(): string
    {
        return $this->galaxy() . ':' . $this->system() . ':' . $this->planet();
    }
}
