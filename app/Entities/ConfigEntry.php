<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Ligne de configuration du jeu (table `game_config`).
 *
 * Table clé/valeur : la clé primaire est `config_name`.
 */
final class ConfigEntry extends AbstractEntity
{
    public static function primaryKey(): array
    {
        return array('config_name');
    }

    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function name(): string
    {
        return (string) ($this->raw('config_name') ?? '');
    }

    public function value(): string
    {
        return (string) ($this->raw('config_value') ?? '');
    }

    public function valueAsInt(): int
    {
        return (int) $this->raw('config_value');
    }

    /**
     * Valeur interprétée comme un booléen : `1`, `true`, `yes` ou `on`.
     */
    public function valueAsBool(): bool
    {
        return in_array(strtolower(trim($this->value())), array('1', 'true', 'yes', 'on'), true);
    }
}
