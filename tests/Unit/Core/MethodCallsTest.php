<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Garde-fou : un appel `$this->propriété->méthode()` doit viser une méthode qui **existe**.
 *
 * PHP ne le dit qu'à l'exécution (`Call to undefined method`, page blanche) et `php -l`
 * ne voit rien : c'est ainsi qu'une méthode peut disparaître d'un dépôt sans que l'appelant
 * suive. Vécu : `GalaxyRepository::findPosition()` a été retirée au passage aux lectures
 * groupées de la galaxie, alors que l'inscription (`RegistrationService`) et le déplacement
 * d'une planète (`PlayerAdminService`) l'appelaient encore — aucune inscription n'était plus
 * possible.
 *
 * Le contrôle porte sur les propriétés **typées par une classe du jeu** (dépôts, services) :
 * le nom de la classe y est certain, donc `method_exists()` fait foi — hiérarchie comprise.
 * Les fichiers sans espace de noms (les tableaux de `app/Core/Legacy/`, faites de fonctions
 * globales) s'écartent d'eux-mêmes, puisqu'aucune classe n'en sort. Rien n'est deviné : pas
 * de `__call` dans le jeu (vérifié), donc une méthode absente est bien une erreur.
 */
final class MethodCallsTest extends TestCase
{
    public function testEveryCallOnATypedPropertyTargetsAnExistingMethod(): void
    {
        $problems = array();

        foreach ($this->sources() as $source) {
            $class = $this->classNameOf($source);

            if ($class === null || !class_exists($class)) {
                continue;
            }

            $types = $this->propertyTypes(new ReflectionClass($class));

            if ($types === array()) {
                continue;
            }

            foreach ($this->callsIn($source) as $property => $methods) {
                foreach ($methods as $method) {
                    if (isset($types[$property]) && !method_exists($types[$property], $method)) {
                        $problems[] = $class . ' : $this->' . $property . '->' . $method
                            . '() sur ' . $types[$property];
                    }
                }
            }
        }

        $problems = array_values(array_unique($problems));
        sort($problems);

        self::assertSame(
            array(),
            $problems,
            "Appel d'une méthode qui n'existe pas :\n - " . implode("\n - ", $problems)
        );
    }

    /**
     * Les sources à analyser : le jeu, puis les modules quand il y en a de déposés.
     *
     * `modules/.archive/` est un état d'installation (un module désinstallé), pas du
     * code en service : il est écarté.
     *
     * @return list<string>
     */
    private function sources(): array
    {
        $root = str_replace('\\', '/', ROOT_PATH);
        $roots = array($root . 'app');

        foreach (glob($root . 'modules/*', GLOB_ONLYDIR) ?: array() as $module) {
            if (basename($module) !== '.archive') {
                $roots[] = $module;
            }
        }

        $sources = array();

        foreach ($roots as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $sources[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }

        sort($sources);

        return $sources;
    }

    /**
     * Le nom complet de la classe d'une source, lu dans le fichier (pas déduit du chemin :
     * les modules ont leurs propres conventions de dossiers).
     */
    private function classNameOf(string $path): ?string
    {
        $code = $this->withoutComments($path);

        if (preg_match('/^[ \t]*namespace[ \t]+([A-Za-z0-9_\\\\]+)[ \t]*;/m', $code, $namespace) !== 1) {
            return null;
        }

        $declaration = '/^[ \t]*(?:(?:final|abstract|readonly)[ \t]+)*'
            . '(?:class|interface|trait|enum)[ \t]+([A-Za-z0-9_]+)/m';

        if (preg_match($declaration, $code, $name) !== 1) {
            return null;
        }

        return $namespace[1] . '\\' . $name[1];
    }

    /**
     * Propriétés typées par une classe : nom de la propriété => nom de la classe.
     *
     * @param ReflectionClass<object> $class
     * @return array<string, string>
     */
    private function propertyTypes(ReflectionClass $class): array
    {
        $types = array();

        foreach ($class->getProperties() as $property) {
            $type = $property->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $name = $type->getName();

            if (class_exists($name) || interface_exists($name)) {
                $types[$property->getName()] = $name;
            }
        }

        return $types;
    }

    /**
     * Les appels `$this->propriété->méthode()` d'un fichier, groupés par propriété.
     *
     * @return array<string, list<string>>
     */
    private function callsIn(string $path): array
    {
        $code = $this->withoutComments($path);
        $calls = array();

        preg_match_all('/\$this->([A-Za-z0-9_]+)->([A-Za-z0-9_]+)[ \t]*\(/', $code, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $calls[$match[1]][] = $match[2];
        }

        return $calls;
    }

    /**
     * Le source d'un fichier, commentaires retirés : un appel cité dans un commentaire
     * n'est pas un appel (les retours à la ligne sont conservés, les expressions
     * régulières qui ancrent en début de ligne continuent de fonctionner).
     */
    private function withoutComments(string $path): string
    {
        $code = '';

        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $code .= str_repeat("\n", substr_count($token[1], "\n"));
                    continue;
                }

                $code .= $token[1];
                continue;
            }

            $code .= $token;
        }

        return $code;
    }
}
