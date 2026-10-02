<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * État d'un module (table `game_modules`).
 *
 * Le catalogue (libellé, description, page, routes, permission) vit dans le code
 * (`App\Core\Modules`) ; cette entité ne représente que ce qui est réglé : allumé
 * ou éteint, et les réglages propres au module, stockés en JSON.
 *
 * Un module sans ligne en base est allumé : `isActive()` n'est donc appelé que sur
 * une ligne existante, mais `settings()` tolère un JSON illisible (retourne un
 * tableau vide) — un module ne doit jamais casser la page qui le lit.
 */
final class Module extends AbstractEntity
{
    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function id(): int
    {
        return (int) $this->raw('id');
    }

    /** Nom technique (`chat`, `marchand`…), celui du catalogue. */
    public function name(): string
    {
        return (string) ($this->raw('name') ?? '');
    }

    /** Le module est-il allumé pour le jeu ? */
    public function isActive(): bool
    {
        return (int) $this->raw('active') === 1;
    }

    /** Réglages bruts, tels qu'ils sont stockés. */
    public function settingsRaw(): string
    {
        return (string) ($this->raw('settings') ?? '');
    }

    /**
     * Réglages du module, décodés.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $raw = $this->settingsRaw();

        if (trim($raw) === '') {
            return array();
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : array();
    }

    public function createdTime(): int
    {
        return (int) $this->raw('created_time');
    }
}
