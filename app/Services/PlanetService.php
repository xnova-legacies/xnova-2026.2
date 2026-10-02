<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlanetRepository;

/**
 * Renommage de la planète (ou de la lune) active.
 *
 * Reprend le traitement de OverviewController (mode=renameplanet) : le nom passe
 * par CheckInputStrings() comme dans le legacy, ce qui supprime au passage les
 * caractères interdits (dont l'apostrophe) — la requête préparée évite toute
 * injection résiduelle.
 */
final class PlanetService
{
    /** Longueur maximale du champ de saisie du formulaire (maxlength). */
    public const MAX_NAME_LENGTH = 20;

    public function __construct(
        private readonly PlanetRepository $planets = new PlanetRepository(),
    ) {
    }

    /** Nom nettoyé, tel que le rendrait le formulaire legacy. Fonction pure. */
    public static function cleanName(mixed $value): string
    {
        return mb_substr(trim(CheckInputStrings((string) $value)), 0, self::MAX_NAME_LENGTH);
    }

    /**
     * Renomme la planète active. Une lune partage le nom de sa planète : le
     * doublon est mis à jour par position, comme dans le legacy.
     */
    public function rename(array &$planet, string $name): void
    {
        // Le dépôt pose une requête préparée : le nom peut contenir n'importe
        // quel caractère autorisé par CheckInputStrings().
        $this->planets->renamePlanet((int) $planet['id'], $name);

        if ((int) ($planet['planet_type'] ?? 1) === 3) {
            $this->planets->renameMoonByPosition($planet, $name);
        }

        $planet['name'] = $name;
    }
}
