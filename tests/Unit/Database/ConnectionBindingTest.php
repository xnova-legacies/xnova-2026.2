<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;

/**
 * Le type d'un paramètre lié décide de la liaison.
 *
 * La couche base est passée de mysqli à PDO : `buildTypes()` rendait `'i'` pour
 * un entier, `bind_param()` l'envoyait donc **non cité**. PDO, en émulation,
 * citerait une chaîne — et `LIMIT '2'` est refusé par MySQL (« You have an error
 * in your SQL syntax near ''2'' »). La liaison doit donc suivre le type PHP.
 *
 * Le test lit la source : les tests unitaires ne touchent jamais MySQL (règle du
 * projet), et c'est le test de fumée qui joue la requête pour de vrai.
 */
final class ConnectionBindingTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(ROOT_PATH . 'app/Database/Connection.php');
    }

    public function testIntegersAreBoundAsIntegers(): void
    {
        $source = $this->source();

        self::assertStringContainsString(
            'is_int($value)',
            $source,
            'Un entier doit être lié comme entier : un `LIMIT ?` cité est refusé par MySQL.'
        );

        self::assertStringContainsString(
            '$statement->bindValue($index + 1, $value, PDO::PARAM_INT);',
            $source,
            'PDO::PARAM_INT est ce qui empêche PDO de citer la valeur.'
        );
    }

    public function testNullIsBoundAsNull(): void
    {
        self::assertStringContainsString(
            '$statement->bindValue($index + 1, null, PDO::PARAM_NULL);',
            $this->source(),
            'Un paramètre nul reste nul : une chaîne vide ne dirait pas la même chose.'
        );
    }

    public function testThePlaceholdersAreNumberedInOrder(): void
    {
        self::assertStringContainsString(
            'foreach (array_values($params) as $index => $value) {',
            $this->source(),
            'Les paramètres sont numérotés dans l\'ordre du tableau : `array_values()` '
            . 'ignore les clés, comme le faisait `bind_param()` avec le déroulé de `...$params`.'
        );
    }
}
