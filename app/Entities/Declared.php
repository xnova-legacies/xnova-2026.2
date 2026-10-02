<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Déclaration de guerre (table `game_declared`).
 *
 * Clé primaire composite (`declarator`, `declared_1`) : la table n'a pas d'`id`.
 */
final class Declared extends AbstractEntity
{
    public static function primaryKey(): array
    {
        return array('declarator', 'declared_1');
    }

    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    /** Identifiant du joueur qui déclare la guerre. */
    public function declarator(): string
    {
        return (string) ($this->raw('declarator') ?? '');
    }

    public function declaratorName(): string
    {
        return (string) ($this->raw('declarator_name') ?? '');
    }

    public function reason(): string
    {
        return (string) ($this->raw('reason') ?? '');
    }

    /**
     * Joueurs visés : les colonnes `declared_1` à `declared_3` non vides.
     *
     * @return list<string>
     */
    public function targets(): array
    {
        $targets = array();

        foreach (array('declared_1', 'declared_2', 'declared_3') as $column) {
            $value = (string) ($this->raw($column) ?? '');

            if ($value !== '') {
                $targets[] = $value;
            }
        }

        return $targets;
    }
}
