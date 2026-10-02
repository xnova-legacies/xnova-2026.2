<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Robots autonomes : **réponse** de l'extension.
 *
 * Tout le comportement vit dans le module `bot` (`Modules\Bot\Services\BotService`),
 * qui **dérive** cette classe ; le Coeur d'application n'a donc aucune règle à lui. Il garde la
 * classe — c'est elle qu'il appelle partout — et chaque réponse **délègue** à
 * l'implémentation effective quand le module est utilisable :
 *
 *     ModuleService::instance(BotService::class)->tick();   // un tour
 *     BotService::present();                                // « y a-t-il des robots ? »
 *     BotService::interactionAllowed($user);                // ce compte les voit-il ?
 *
 * Sans le module — non déposé, éteint, ou privé d'une de ses dépendances — c'est cette
 * version-ci qui répond : elle ne fait **rien**. Aucun tour n'est joué, aucun compte
 * n'est créé, et `present()` dit `false` à tout le jeu (galaxie, historique des
 * connexions, statistiques du panneau, journal des actions, marché) : les robots
 * disparaissent comme s'ils n'avaient jamais existé.
 *
 * `present()` est la seule question que le Coeur d'application pose aux robots, et la seule règle de
 * leur présence : jamais un `if` sur un nom de module ailleurs.
 */
class BotService
{
    /**
     * Classe effective du service : celle du module quand il est utilisable, celle du
     * Coeur d'application sinon. Résolue une fois par requête (`ModuleService::resolve()`).
     *
     * @return class-string
     */
    private static function effective(): string
    {
        return ModuleService::resolve(self::class);
    }

    /** Le module des robots répond-il ? (le Coeur d'application n'a pas d'autre question à poser) */
    public static function present(): bool
    {
        return self::effective() !== self::class;
    }

    /** Interrupteur du jeu : le réglage `enable_bot` de la table `config`. */
    public static function enabled(): bool
    {
        $service = self::effective();

        return $service !== self::class && $service::enabled();
    }

    /** Nombre de comptes robots souhaité par l'univers. */
    public static function wanted(): int
    {
        $service = self::effective();

        return $service === self::class ? 0 : $service::wanted();
    }

    /** Délai minimal entre deux tours d'un même robot (secondes). */
    public static function tickSeconds(): int
    {
        $service = self::effective();

        return $service === self::class ? 0 : $service::tickSeconds();
    }

    /** L'univers autorise-t-il l'interaction des robots avec les joueurs ? */
    public static function interactionEnabled(): bool
    {
        $service = self::effective();

        return $service !== self::class && $service::interactionEnabled();
    }

    /** Ce compte voit-il l'activité des robots ? (univers **et** option du compte) */
    public static function interactionAllowed(array $user): bool
    {
        $service = self::effective();

        return $service !== self::class && $service::interactionAllowed($user);
    }

    /** Réglage d'environnement des robots (`BOTS_MARKET`, …). */
    public static function env(string $key, string $default = '1'): string
    {
        $service = self::effective();

        return $service === self::class ? $default : $service::env($key, $default);
    }

    /** Nombre de comptes robots existants. */
    public function population(): int
    {
        return 0;
    }

    /**
     * Crée au plus un compte robot manquant.
     *
     * @return int identifiant du compte créé, 0 si rien à faire
     */
    public function ensure(): int
    {
        return 0;
    }

    /** Joue un tour pour les robots dont le délai est écoulé. Nombre de tours joués. */
    public function tick(?int $now = null): int
    {
        return 0;
    }
}
