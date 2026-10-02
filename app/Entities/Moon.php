<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Lune (table `game_lunas`).
 *
 * `destruyed` est un indicateur hérité : une valeur non nulle marque une lune
 * en cours de destruction (tentative d'étoile de la mort).
 */
final class Moon extends AbstractEntity
{
    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function id(): int
    {
        return (int) $this->raw('id');
    }

    /** Identifiant de la lune utilisé par le reste du jeu (colonne `id_luna`). */
    public function moonId(): int
    {
        return (int) $this->raw('id_luna');
    }

    public function name(): string
    {
        return (string) ($this->raw('name') ?? '');
    }

    public function image(): string
    {
        return (string) ($this->raw('image') ?? '');
    }

    public function isDestroyed(): bool
    {
        return (int) $this->raw('destruyed') !== 0;
    }

    public function ownerId(): int
    {
        return (int) $this->raw('id_owner');
    }

    public function galaxy(): int
    {
        return (int) $this->raw('galaxy');
    }

    public function system(): int
    {
        return (int) $this->raw('system');
    }

    /** Position de la lune dans le système (colonne `lunapos`). */
    public function position(): int
    {
        return (int) $this->raw('lunapos');
    }

    public function temperatureMin(): int
    {
        return (int) $this->raw('temp_min');
    }

    public function temperatureMax(): int
    {
        return (int) $this->raw('temp_max');
    }

    public function diameter(): int
    {
        return (int) $this->raw('diameter');
    }

    public function coordinates(): string
    {
        return $this->galaxy() . ':' . $this->system() . ':' . $this->position();
    }
}
