<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Préparation des réglages du compte (/game/profil/options?mode=change).
 *
 * Reprend les règles du formulaire historique : les cases à cocher valent
 * « 1 » ou « 0 », un champ vide retombe sur la valeur du compte et les
 * compteurs numériques restent positifs. Aucune écriture ici : le contrôleur
 * applique le résultat avec UserRepository.
 *
 * Un réglage qu'un **module** possède dans `users` (sa colonne) s'ajoute par
 * surcharge de classe : `settings()` réunit les réglages du Coeur d'application et ceux que
 * `extraSettings()` renvoie.
 */
class UserSettingsService
{
    /** Cases à cocher du formulaire. */
    public const FLAGS = array(
        'design',
        'noipcheck',
        'settings_allylogo',
        'settings_esp',
        'settings_wri',
        'settings_bud',
        'settings_mis',
        'settings_rep',
        'vacation_mode',
        'no_javascript',
    );

    /** Réglages numériques (valeur par défaut : 1). */
    public const NUMBERS = array('spy_count', 'settings_tooltiptime', 'settings_fleetactions');

    /** Durée du mode vacances, en secondes (48 h comme le jeu). */
    public const VACATION_SECONDS = 172800;

    /** Valeur « 1 » ou « 0 » d'une case à cocher. Fonction pure. */
    public static function flag(array $payload, string $name): string
    {
        return in_array($payload[$name] ?? null, array('on', '1', 1, true), true) ? '1' : '0';
    }

    /** Compteur numérique (jamais négatif). Fonction pure. */
    public static function number(array $payload, string $name, string $default = '1'): string
    {
        $value = $payload[$name] ?? null;

        return is_numeric($value) && (int) $value >= 0 ? (string) (int) $value : $default;
    }

    /** Pseudo demandé, ou celui du compte si le champ est vide. Fonction pure. */
    public static function username(array $payload, array $user): string
    {
        $value = $payload['db_character'] ?? null;

        return is_string($value) && trim($value) !== ''
            ? CheckInputStrings($value)
            : (string) ($user['username'] ?? '');
    }

    /** Adresse e-mail demandée, ou celle du compte si le champ est vide. Fonction pure. */
    public static function email(array $payload, array $user): string
    {
        $value = $payload['db_email'] ?? null;

        return is_string($value) && trim($value) !== ''
            ? CheckInputStrings($value)
            : (string) ($user['email'] ?? '');
    }

    /** Un changement de mot de passe est-il demandé ? Fonction pure. */
    public static function wantsPasswordChange(array $payload): bool
    {
        return trim((string) ($payload['db_password'] ?? '')) !== '';
    }

    /**
     * Motif de refus du changement de mot de passe, ou null si la demande est
     * valide. Fonction pure : le hash courant est fourni par l'appelant.
     */
    public static function passwordProblem(array $payload, string $currentHash): ?string
    {
        if (md5((string) ($payload['db_password'] ?? '')) !== $currentHash) {
            return 'wrong_password';
        }

        $new1 = (string) ($payload['newpass1'] ?? '');
        $new2 = (string) ($payload['newpass2'] ?? '');

        if ($new1 === '' || $new2 === '') {
            return 'password_empty';
        }

        return $new1 === $new2 ? null : 'password_mismatch';
    }

    /** Hash du nouveau mot de passe. Fonction pure. */
    public static function newPasswordHash(array $payload): string
    {
        return md5((string) ($payload['newpass1'] ?? ''));
    }

    /**
     * Réglages prêts à enregistrer : mêmes clés que le formulaire historique.
     * `$overrides` sert au mode vacances, décidé par le contrôleur après
     * vérification des conditions d'accès.
     */
    public static function settings(array $payload, array $user, array $overrides = array()): array
    {
        $settings = array(
            'email' => self::email($payload, $user),
            'avatar' => isset($payload['avatar']) ? strip_tags((string) $payload['avatar']) : '',
            'dpath' => isset($payload['dpath']) && $payload['dpath'] !== ''
                ? strip_tags((string) $payload['dpath'])
                : (string) ($user['dpath'] ?? ''),
            'design' => self::flag($payload, 'design'),
            'noipcheck' => self::flag($payload, 'noipcheck'),
            'planet_sort' => is_numeric($payload['settings_sort'] ?? null)
                ? (int) $payload['settings_sort']
                : (int) ($user['planet_sort'] ?? 0),
            'planet_sort_order' => is_numeric($payload['settings_order'] ?? null)
                ? (int) $payload['settings_order']
                : (int) ($user['planet_sort_order'] ?? 0),
            'spy_count' => self::number($payload, 'spy_count'),
            'settings_tooltiptime' => self::number($payload, 'settings_tooltiptime'),
            'settings_fleetactions' => self::number($payload, 'settings_fleetactions'),
            'settings_allylogo' => self::flag($payload, 'settings_allylogo'),
            'settings_esp' => self::flag($payload, 'settings_esp'),
            'settings_wri' => self::flag($payload, 'settings_wri'),
            'settings_bud' => self::flag($payload, 'settings_bud'),
            'settings_mis' => self::flag($payload, 'settings_mis'),
            'settings_rep' => self::flag($payload, 'settings_rep'),
            'vacation_mode' => (string) ($overrides['vacation_mode'] ?? '0'),
            'no_javascript' => self::flag($payload, 'no_javascript'),
            'kolorminus' => (string) ($payload['kolorminus'] ?? ''),
            'kolorplus' => (string) ($payload['kolorplus'] ?? ''),
            'kolorpoziom' => (string) ($payload['kolorpoziom'] ?? ''),
        );

        // Les réglages qu'un module possède s'ajoutent aux nôtres : `+` garde la clé de
        // gauche, donc le Coeur d'application fait foi si une clé se retrouve des deux côtés.
        return $settings + static::extraSettings($payload, $user);
    }

    /**
     * Réglages qu'un **module** ajoute aux siens, colonne => valeur.
     *
     * Le Coeur d'application n'en connaît aucun : sans module, le tableau est vide et la page
     * enregistre exactement ce qu'elle enregistrait avant.
     *
     * @return array<string, string>
     */
    protected static function extraSettings(array $payload, array $user): array
    {
        return array();
    }
}
