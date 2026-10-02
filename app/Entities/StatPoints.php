<?php

declare(strict_types=1);

namespace App\Entities;

use InvalidArgumentException;

/**
 * Ligne de classement (table `game_statpoints`).
 *
 * La table stocke cinq familles de statistiques, chacune avec rang, ancien
 * rang, points et nombre d'éléments : `tech`, `build`, `defs`, `fleet`, `total`.
 * Clé primaire composite (`id_owner`, `id_ally`, `stat_type`, `stat_code`).
 */
final class StatPoints extends AbstractEntity
{
    public const FAMILIES = array('tech', 'build', 'defs', 'fleet', 'total');

    public static function primaryKey(): array
    {
        return array('id_owner', 'id_ally', 'stat_type', 'stat_code');
    }

    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    /**
     * Familles de statistiques disponibles.
     *
     * @return list<string>
     */
    public static function families(): array
    {
        return self::FAMILIES;
    }

    public function ownerId(): int
    {
        return (int) $this->raw('id_owner');
    }

    public function allyId(): int
    {
        return (int) $this->raw('id_ally');
    }

    public function statType(): int
    {
        return (int) $this->raw('stat_type');
    }

    public function statCode(): int
    {
        return (int) $this->raw('stat_code');
    }

    public function statDate(): int
    {
        return (int) $this->raw('stat_date');
    }

    public function pointsOf(string $family): int
    {
        return (int) $this->raw($this->column($family, 'points'));
    }

    public function rankOf(string $family): int
    {
        return (int) $this->raw($this->column($family, 'rank'));
    }

    public function oldRankOf(string $family): int
    {
        return (int) $this->raw($this->column($family, 'old_rank'));
    }

    public function countOf(string $family): int
    {
        return (int) $this->raw($this->column($family, 'count'));
    }

    public function totalPoints(): int
    {
        return $this->pointsOf('total');
    }

    public function totalRank(): int
    {
        return $this->rankOf('total');
    }

    /**
     * Construit le nom de colonne `<famille>_<suffixe>` en validant la famille.
     */
    private function column(string $family, string $suffix): string
    {
        if (!in_array($family, self::FAMILIES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Famille de statistiques inconnue : « %s ». Attendu : %s.',
                $family,
                implode(', ', self::FAMILIES)
            ));
        }

        return $family . '_' . $suffix;
    }
}
