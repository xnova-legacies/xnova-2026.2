<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ConfigDefaults;
use PHPUnit\Framework\TestCase;

/**
 * Réglages de la table `config` : l'environnement ne surcharge que ce qu'il
 * demande explicitement, le reste garde les valeurs par défaut du jeu.
 */
final class ConfigDefaultsTest extends TestCase
{
    /** Lecteur d'environnement factice : remplace les fichiers `.env` en test. */
    private function reader(array $values): callable
    {
        return static fn (string $key): string => $values[$key] ?? '';
    }

    /** Lecteur qui répond pour un seul réglage (les autres variables sont muettes). */
    private function only(string $envKey, string $value): callable
    {
        return $this->reader(array($envKey => $value));
    }

    public function testDefaultsCarryTheInitialFields(): void
    {
        self::assertSame('163', ConfigDefaults::DEFAULTS['initial_fields']);
        self::assertCount(48, ConfigDefaults::DEFAULTS);
    }

    public function testDefaultsAreTheOnesOfAFreshUniverse(): void
    {
        // Valeurs effectives d'un univers neuf : la mise à niveau 002 corrige
        // celles de la migration 001 (serveur ouvert, nom court, texte d'accueil).
        self::assertSame('0', ConfigDefaults::DEFAULTS['game_disable']);
        self::assertSame('', ConfigDefaults::DEFAULTS['close_reason']);
        self::assertSame('XNova', ConfigDefaults::DEFAULTS['COOKIE_NAME']);
        self::assertSame('XNova', ConfigDefaults::DEFAULTS['game_name']);
        self::assertSame('9', ConfigDefaults::DEFAULTS['LastSettedSystemPos']);
    }

    public function testEnvKeyIsDerivedFromTheSettingName(): void
    {
        self::assertSame('CONFIG_INITIAL_FIELDS', ConfigDefaults::envKey('initial_fields'));
        self::assertSame('CONFIG_FLEET_CDR', ConfigDefaults::envKey('Fleet_Cdr'));
        self::assertSame('CONFIG_GAME_NAME', ConfigDefaults::envKey('game_name'));
    }

    public function testOverridesOnlyCarryExplicitValues(): void
    {
        self::assertSame(array(), ConfigDefaults::overrides($this->reader(array())));

        self::assertSame(
            array('initial_fields' => '200'),
            ConfigDefaults::overrides($this->only('CONFIG_INITIAL_FIELDS', '200'))
        );

        // Une valeur vide (variable commentée puis vidée) ne compte pas.
        self::assertSame(array(), ConfigDefaults::overrides($this->only('CONFIG_INITIAL_FIELDS', '   ')));
    }

    public function testOverridesIgnoreUnknownSettings(): void
    {
        self::assertSame(array(), ConfigDefaults::overrides($this->reader(array('CONFIG_INCONNU' => 'valeur'))));
    }

    public function testValuesFallBackToDefaults(): void
    {
        $values = ConfigDefaults::values($this->only('CONFIG_INITIAL_FIELDS', '250'));

        self::assertSame('250', $values['initial_fields']);
        self::assertSame('2500', $values['game_speed']);
        self::assertSame('XNova', $values['game_name']);
        self::assertCount(48, $values);
    }

    public function testValuesKeepDefaultsWhenEnvironmentIsSilent(): void
    {
        self::assertSame(ConfigDefaults::DEFAULTS, ConfigDefaults::values($this->reader(array())));
    }

    public function testExampleDocumentsEverySetting(): void
    {
        $example = (string) file_get_contents(APP_ROOT . '/configs/.env.example');

        foreach (array_keys(ConfigDefaults::DEFAULTS) as $name) {
            self::assertStringContainsString(
                ConfigDefaults::envKey($name) . '=',
                $example,
                'Variable absente de .env.example : ' . ConfigDefaults::envKey($name)
            );
        }
    }
}
