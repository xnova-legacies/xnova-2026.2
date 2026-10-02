<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use App\Core\GameConstants;
use PHPUnit\Framework\TestCase;

final class UserFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['ListCensure'] = GameConstants::censoredWords();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['ListCensure']);
    }

    public function testCheckInputStringsReplacesForbiddenCharacters(): void
    {
        self::assertSame('a*b', CheckInputStrings('a<b'));
        self::assertSame('a*b', CheckInputStrings('a>b'));
    }

    public function testCheckInputStringsReplacesForbiddenWords(): void
    {
        self::assertSame('*://example.com', CheckInputStrings('http://example.com'));
        // '<' et '>' deviennent '*' puis 'script' devient '*' : '***'.
        self::assertSame('***', CheckInputStrings('<script>'));
    }

    public function testCheckInputStringsIsCaseInsensitive(): void
    {
        self::assertSame('x*y', CheckInputStrings('xSCRIPTy'));
        // 'script' est censuré avant 'javascript' dans la liste.
        self::assertSame('xJava*y', CheckInputStrings('xJavaScripty'));
    }

    public function testCheckInputStringsReplacesQuotes(): void
    {
        self::assertSame('a*b*c', CheckInputStrings("a'b'c"));
    }

    public function testCheckInputStringsLeavesValidTextUntouched(): void
    {
        self::assertSame('Colonie Alpha', CheckInputStrings('Colonie Alpha'));
    }

    /**
     * Les deux routines qui effacent un compte (le nettoyage anti-triche) assemblaient leurs
     * requetes par concatenation. Elles passent par la connexion preparee : la regle vaut pour
     * tout le fichier, une valeur concatenee y serait une injection SQL en puissance.
     */
    public function testTheAccountWipeNoLongerBuildsItsQueries(): void
    {
        $source = (string) file_get_contents(ROOT_PATH . 'app/Core/Legacy/UserFunctions.php');

        self::assertStringNotContainsString('doquery(', $source);
        self::assertStringNotContainsString('mysql_query(', $source);
        self::assertStringNotContainsString('Connection::escape(', $source);
        // La fonction morte de reinitialisation par courriel n'est plus appelee par personne.
        self::assertStringNotContainsString('function sendnewpassword', $source);
    }
}
