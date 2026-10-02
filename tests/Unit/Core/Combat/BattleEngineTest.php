<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Combat;

use App\Core\Combat\BattleEngine;
use App\Core\GameData;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * Moteur de combat : forme du résultat, déterminisme (le moteur utilise rand())
 * et effet des technologies. Aucun accès à MySQL : les tables de jeu viennent de
 * includes/vars.php via GameData, la configuration est posée à la main.
 *
 * `#[WithoutErrorHandler]` : le moteur est un portage fidèle du legacy et garde son
 * corps d'origine. Il ne lit plus rien d'indéfini (voir `BattleEngine` : les compteurs
 * et le bonus amiral sont déclarés à zéro en tête de méthode), mais l'attribut reste :
 * un avertissement du moteur ne doit pas devenir un avertissement de test, c'est
 * `testTheEngineRaisesNoWarning` qui le constate.
 */
final class BattleEngineTest extends TestCase
{
    private array $configBackup = [];

    protected function setUp(): void
    {
        GameData::load();

        $this->configBackup = $GLOBALS['game_config'] ?? [];
        $GLOBALS['game_config'] = array('Fleet_Cdr' => 30, 'Defs_Cdr' => 30);
    }

    protected function tearDown(): void
    {
        $GLOBALS['game_config'] = $this->configBackup;
    }

    private function attacker(): array
    {
        // 204 = croiseur, 206 = bombardier.
        return array(204 => array('count' => 10), 206 => array('count' => 5));
    }

    private function defender(): array
    {
        // 401 = lanceur de missiles, 203 = chasseur léger.
        return array(401 => array('count' => 20), 203 => array('count' => 4));
    }

    private function techs(int $level): array
    {
        return array(
            'military_tech' => $level,
            'defence_tech' => $level,
            'shield_tech' => $level,
        );
    }

    #[WithoutErrorHandler]
    public function testResolvesABattleAndReturnsTheExpectedShape(): void
    {
        srand(1234);

        $result = BattleEngine::resolve($this->attacker(), $this->defender(), $this->techs(0), $this->techs(0));

        self::assertSame(array('attacker', 'defender', 'victory', 'rounds', 'debris'), array_keys($result));
        self::assertContains($result['victory'], array('a', 'w', 'r'));
        self::assertNotSame(array(), $result['rounds']);
        self::assertArrayHasKey('metal', $result['debris']);
        self::assertArrayHasKey('crystal', $result['debris']);
        self::assertArrayHasKey('attacker', $result['debris']);
        self::assertArrayHasKey('defender', $result['debris']);
    }

    #[WithoutErrorHandler]
    public function testTheEngineIsDeterministicForAGivenSeed(): void
    {
        srand(99);
        $first = BattleEngine::resolve($this->attacker(), $this->defender(), $this->techs(2), $this->techs(1));

        srand(99);
        $second = BattleEngine::resolve($this->attacker(), $this->defender(), $this->techs(2), $this->techs(1));

        self::assertSame($first, $second);
    }

    /**
     * Le moteur ne lit **rien** d'indéfini.
     *
     * Ses compteurs d'origine ne sont alimentés que dans une branche (unités ≥ 300) et le bonus
     * de l'officier amiral n'est jamais appliqué : les deux sont désormais déclarés à zéro en
     * tête de méthode, à la valeur que PHP lisait déjà dans une variable non déclarée. Avant ce
     * correctif, chaque combat imprimait une trentaine d'avertissements — de quoi noyer les
     * journaux du jeu comme ceux du contrôle d'intégration.
     */
    public function testTheEngineRaisesNoWarning(): void
    {
        srand(1234);

        $warnings = array();

        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            if (($level & (E_WARNING | E_NOTICE)) !== 0) {
                $warnings[] = $message;
            }

            return true;
        });

        try {
            BattleEngine::resolve($this->attacker(), $this->defender(), $this->techs(2), $this->techs(1));
        } finally {
            restore_error_handler();
        }

        self::assertSame(array(), $warnings, 'Le moteur ne doit plus rien lire d indéfini.');
    }

    #[WithoutErrorHandler]
    public function testAnEmptyAttackerLoses(): void
    {
        srand(5);

        $result = BattleEngine::resolve(null, $this->defender(), $this->techs(0), $this->techs(0));

        self::assertSame('w', $result['victory']);
    }

    #[WithoutErrorHandler]
    public function testOverwhelmingForceWins(): void
    {
        srand(4242);

        $result = BattleEngine::resolve(
            array(204 => array('count' => 2000)),
            $this->defender(),
            $this->techs(10),
            $this->techs(0)
        );

        self::assertSame('a', $result['victory']);
        self::assertGreaterThan(0, $result['debris']['defender']);
    }

    #[WithoutErrorHandler]
    public function testOverwhelmingDefenceWins(): void
    {
        srand(4242);

        $result = BattleEngine::resolve(
            $this->attacker(),
            array(401 => array('count' => 5000)),
            $this->techs(0),
            $this->techs(10)
        );

        self::assertSame('w', $result['victory']);
    }
}
