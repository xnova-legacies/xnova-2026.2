<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Réponse : ce qu'un module détient sur un compte, qu'il efface lui-même.
 *
 * Le Coeur d'application nomme le **geste**, jamais la table d'un module : le nettoyage anti-triche
 * (`UserFunctions::DeleteSelectedUser()`) efface physiquement les données d'un compte, puis
 * demande à chaque module déposé et allumé d'effacer **ses** tables
 * (`ModuleService::purgeAccountData()`).
 *
 * Un module qui possède de telles tables dépose `services/AccountPurgeService.php` (même nom
 * court, même couche que cette classe : c'est son point de surcharge) et en dérive celle-ci.
 * Sans module — ou module éteint — rien n'est effacé : la table n'existe pas.
 */
class AccountPurgeService
{
    /**
     * Efface ce que le module détient sur un compte effacé.
     *
     * La classe du Coeur d'application ne fait rien : elle n'existe que pour être dérivée.
     */
    public function purge(int $userId): void
    {
    }
}
