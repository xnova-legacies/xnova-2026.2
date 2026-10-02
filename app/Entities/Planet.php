<?php

namespace App\Entities;

final class Planet extends AbstractEntity
{
    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function id(): int
    {
        return (int) $this->raw('id');
    }

    public function ownerId(): int
    {
        return (int) $this->raw('id_owner');
    }

    public function name(): string
    {
        return (string) ($this->raw('name') ?? '');
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

    public function type(): int
    {
        return (int) $this->raw('planet_type');
    }

    public function isMoon(): bool
    {
        return $this->type() === 3;
    }

    public function metal(): float
    {
        return (float) $this->raw('metal');
    }

    public function crystal(): float
    {
        return (float) $this->raw('crystal');
    }

    public function deuterium(): float
    {
        return (float) $this->raw('deuterium');
    }

    public function metalMax(): float
    {
        return (float) $this->raw('metal_max');
    }

    public function crystalMax(): float
    {
        return (float) $this->raw('crystal_max');
    }

    public function deuteriumMax(): float
    {
        return (float) $this->raw('deuterium_max');
    }

    public function energyMax(): float
    {
        return (float) $this->raw('energy_max');
    }

    public function energyUsed(): float
    {
        return (float) $this->raw('energy_used');
    }

    public function shipCount(int $shipId): int
    {
        return (int) ($this->raw($this->shipField($shipId)) ?? 0);
    }

    public function shipField(int $shipId): string
    {
        // correspondance via la ressource globale legacy (resource[210] => 'spy_sonde', etc.)
        $resource = $GLOBALS['resource'] ?? [];

        return (string) ($resource[$shipId] ?? ('ship_' . $shipId));
    }

    public function fieldCurrent(): int
    {
        return (int) $this->raw('field_current');
    }

    public function fieldMax(): int
    {
        return (int) $this->raw('field_max');
    }

    public function coordinates(): string
    {
        return $this->galaxy() . ':' . $this->system() . ':' . $this->planet();
    }
}
