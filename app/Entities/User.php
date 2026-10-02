<?php

namespace App\Entities;

final class User
{
    private function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly int $authlevel,
        public readonly ?string $dpath,
        public readonly ?string $avatar,
        public readonly ?string $allyName,
        public readonly int $allyId,
        public readonly int $galaxy,
        public readonly int $system,
        public readonly int $planet,
        public readonly int $currentPlanet,
        public readonly int $banaday,
        public readonly string $bana,
        public readonly string $vacationMode,
        public readonly int $registerTime,
        private readonly array $row,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            username: (string) ($row['username'] ?? ''),
            email: (string) ($row['email'] ?? ''),
            authlevel: (int) ($row['authlevel'] ?? 0),
            dpath: isset($row['dpath']) ? (string) $row['dpath'] : null,
            avatar: isset($row['avatar']) ? (string) $row['avatar'] : null,
            allyName: isset($row['ally_name']) ? (string) $row['ally_name'] : null,
            allyId: (int) ($row['ally_id'] ?? 0),
            galaxy: (int) ($row['galaxy'] ?? 0),
            system: (int) ($row['system'] ?? 0),
            planet: (int) ($row['planet'] ?? 0),
            currentPlanet: (int) ($row['current_planet'] ?? 0),
            banaday: (int) ($row['banaday'] ?? 0),
            bana: (string) ($row['bana'] ?? ''),
            vacationMode: (string) ($row['vacation_mode'] ?? ''),
            registerTime: (int) ($row['register_time'] ?? 0),
            row: $row,
        );
    }

    /**
     * Entité historique : elle n'étend pas `AbstractEntity` (propriétés promues)
     * mais expose la même interface publique, clé primaire comprise.
     */
    public static function primaryKey(): array
    {
        return array('id');
    }

    public function raw(string $key): mixed
    {
        return $this->row[$key] ?? null;
    }

    public function toArray(): array
    {
        return $this->row;
    }
}
