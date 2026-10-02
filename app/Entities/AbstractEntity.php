<?php

namespace App\Entities;

abstract class AbstractEntity
{
    protected readonly array $row;

    protected function __construct(array $row)
    {
        $this->row = $row;
    }

    public function raw(string $key): mixed
    {
        return $this->row[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->row);
    }

    public function toArray(): array
    {
        return $this->row;
    }

    public function &toLegacyArray(): array
    {
        return $this->row;
    }

    /**
     * Colonnes composant la clé primaire de la table.
     *
     * À redéfinir dans l'entité lorsque la table n'utilise pas `id`
     * (clé nommée autrement, ou clé composite).
     *
     * @return list<string>
     */
    public static function primaryKey(): array
    {
        return array('id');
    }
}
