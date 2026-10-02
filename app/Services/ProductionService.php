<?php

namespace App\Services;

use App\Core\GameConfig;
use App\Core\GameConstants;
use App\Core\GameData;
use App\Repositories\BuildingQueueRepository;

/**
 * Mise à jour des ressources d'une planète (production horaire, stockage,
 * chantier en cours). Porte-métier de PlanetResourceUpdate.php et
 * HandleElementBuildingQueue.php.
 */
class ProductionService
{
    /**
     * Les files et le hangar viennent d'ici ; le service des durées est **résolu** (un
     * module peut le surcharger, donc il ne peut pas être construit par défaut dans la
     * signature : un défaut de paramètre n'appelle pas `ModuleService`).
     */
    private readonly BuildingService $buildings;

    public function __construct(
        private readonly BuildingQueueRepository $queues = new BuildingQueueRepository(),
        ?BuildingService $buildings = null,
        private readonly QueueService $hangar = new QueueService(),
    ) {
        $this->buildings = $buildings ?? \App\Services\ModuleService::instance(BuildingService::class);
    }

    /**
     * Débits horaires réellement appliqués par updatePlanetResources().
     *
     * Reprend les règles de la boucle de production ci-dessous (comportement
     * legacy conservé à l'identique) :
     *   - une lune ne produit rien via ses mines ;
     *   - sans énergie (energy_max == 0), la production des mines est remplacée
     *     par le revenu de base ;
     *   - le revenu de base est ensuite toujours ajouté ;
     *   - le multiplicateur de ressources s'applique à chaque terme.
     *
     * L'API temps réel s'en sert pour interpoler l'affichage entre deux synchros.
     * NB : cette règle est pour l'instant dupliquée dans updatePlanetResources()
     * (à unifier lors du chantier de dédoublonnage).
     *
     * @return array{metal: float, crystal: float, deuterium: float, production_level: int}
     */
    public static function effectiveRates(array $planet, array $gameConfig): array
    {
        $multiplier = (float) ($gameConfig['resource_multiplier'] ?? 1);
        $basicMetal = (float) ($gameConfig['metal_basic_income'] ?? 0) * $multiplier;
        $basicCrystal = (float) ($gameConfig['crystal_basic_income'] ?? 0) * $multiplier;
        $basicDeuterium = (float) ($gameConfig['deuterium_basic_income'] ?? 0) * $multiplier;

        $isMoon = ((int) ($planet['planet_type'] ?? 1)) === 3;
        $hasEnergy = (float) ($planet['energy_max'] ?? 0) != 0.0;

        if (!$hasEnergy) {
            // Sans énergie, la production des mines est remplacée par le revenu
            // de base (nul sur une lune).
            $metal = $isMoon ? 0.0 : $basicMetal;
            $crystal = $isMoon ? 0.0 : $basicCrystal;
            $deuterium = $isMoon ? 0.0 : $basicDeuterium;
        } else {
            // Sur une lune, les mines ne produisent rien (perhour forcé à 0).
            $metal = $isMoon ? 0.0 : (float) ($planet['metal_perhour'] ?? 0) * $multiplier;
            $crystal = $isMoon ? 0.0 : (float) ($planet['crystal_perhour'] ?? 0) * $multiplier;
            $deuterium = $isMoon ? 0.0 : (float) ($planet['deuterium_perhour'] ?? 0) * $multiplier;
        }

        // Le revenu de base est ajouté dans tous les cas par updatePlanetResources().
        return array(
            'metal' => $metal + $basicMetal,
            'crystal' => $crystal + $basicCrystal,
            'deuterium' => $deuterium + $basicDeuterium,
            'production_level' => 100,
        );
    }

    /**
     * Capacités de stockage d'une planète : silo de métal / cristal (22, 23) et
     * réservoir de deutérium (24), chacun majoré par l'officier Stockeur
     * (+50 % par niveau).
     *
     * Implémentation unique de cette règle : elle alimente la production, la page
     * /game/resources et l'état temps réel. Les colonnes `*_max` de la table des
     * planètes n'en sont que la copie persistée (voir BuildingQueueRepository).
     *
     * @return array{metal: float, crystal: float, deuterium: float}
     */
    public static function storageCapacities(array $planet, array $user): array
    {
        $resource = GameData::resource();
        $bonus = static::storageBonus($user);

        return array(
            'metal' => floor(GameConstants::baseStorageSize() * pow(1.5, (int) ($planet[$resource[22]] ?? 0))) * $bonus,
            'crystal' => floor(GameConstants::baseStorageSize() * pow(1.5, (int) ($planet[$resource[23]] ?? 0))) * $bonus,
            'deuterium' => floor(GameConstants::baseStorageSize() * pow(1.5, (int) ($planet[$resource[24]] ?? 0))) * $bonus,
        );
    }

    /**
     * Majoration des silos (1 = aucune) et part d'un officier dans la production (fraction).
     *
     * Le Coeur de l'application n'applique **aucun** bonus de module : les deux méthodes rendent
     * la valeur d'origine (1 et 0). Un module les surcharge — le module `officier` applique
     * +50 % par niveau de stockeur et 5 % par niveau de géologue (mines) ou d'ingénieur
     * (énergie) — et les appelants résolvent la classe par `ModuleService`
     * (`ProductionService::class`), sinon la surcharge ne serait jamais appelée.
     *
     * `protected static` **et** appelées en `static::` : PHP refuse qu'un enfant redéfinisse
     * une méthode privée, et un appel en `self::` viserait toujours celle du Coeur de l'application.
     *
     * @param array<string, mixed> $user
     */
    protected static function storageBonus(array $user): float
    {
        return 1.0;
    }

    /**
     * Part d'un module dans la production horaire d'une ressource.
     *
     * @param array<string, mixed> $user
     * @param string               $resource `metal`, `crystal`, `deuterium` ou `energy`
     */
    protected static function productionBonus(array $user, string $resource): float
    {
        return 0.0;
    }

    /**
     * Recalcule les ressources de la planète depuis last_update.
     * $currentPlanet est passé PAR RÉFÉRENCE (comme le legacy) : la planète
     * mise à jour est utilisée par l'appelant pour le rendu.
     */
    public function updatePlanetResources(array $currentUser, array &$currentPlanet, int $updateTime, bool $simul = false): void
    {
        $prodGrid = GameData::prodGrid();
        $resource = GameData::resource();
        $reslist = GameData::resList();
        $gameConfig = GameConfig::load();

        // Espace de stockage (source unique : storageCapacities())
        $storage = self::storageCapacities($currentPlanet, $currentUser);

        $currentPlanet['metal_max'] = $storage['metal'];
        $currentPlanet['crystal_max'] = $storage['crystal'];
        $currentPlanet['deuterium_max'] = $storage['deuterium'];

        $maxMetalStorage = $storage['metal'] * GameConstants::maxOverflow();
        $maxCristalStorage = $storage['crystal'] * GameConstants::maxOverflow();
        $maxDeuteriumStorage = $storage['deuterium'] * GameConstants::maxOverflow();

        // Production linéaire des divers types
        // Les formules eval() du ProdGrid utilisent $BuildLevel, $BuildLevelFactor, $BuildTemp (PascalCase)
        $caps = array();
        for ($prodId = 0; $prodId < 300; $prodId++) {
            if (in_array($prodId, $reslist['prod'])) {
                $BuildLevelFactor = $currentPlanet[$resource[$prodId] . "_porcent"];
                $BuildLevel = $currentPlanet[$resource[$prodId]];
                $BuildTemp = $currentPlanet['temp_max'];

                $caps['metal_perhour'] = ($caps['metal_perhour'] ?? 0) + floor(eval($prodGrid[$prodId]['formule']['metal']) * ($gameConfig['resource_multiplier']) * (1 + static::productionBonus($currentUser, 'metal')));
                $caps['crystal_perhour'] = ($caps['crystal_perhour'] ?? 0) + floor(eval($prodGrid[$prodId]['formule']['crystal']) * ($gameConfig['resource_multiplier']) * (1 + static::productionBonus($currentUser, 'crystal')));
                $caps['deuterium_perhour'] = ($caps['deuterium_perhour'] ?? 0) + floor(eval($prodGrid[$prodId]['formule']['deuterium']) * ($gameConfig['resource_multiplier']) * (1 + static::productionBonus($currentUser, 'deuterium')));
                if ($prodId < 4) {
                    $caps['energy_used'] = ($caps['energy_used'] ?? 0) + floor(eval($prodGrid[$prodId]['formule']['energy']) * ($gameConfig['resource_multiplier']) * (1 + static::productionBonus($currentUser, 'energy')));
                } elseif ($prodId >= 4) {
                    $caps['energy_max'] = ($caps['energy_max'] ?? 0) + floor(eval($prodGrid[$prodId]['formule']['energy']) * ($gameConfig['resource_multiplier']) * (1 + static::productionBonus($currentUser, 'energy')));
                }
            }
        }

        // Pas de production sur une lune
        if ($currentPlanet['planet_type'] == 3) {
            $currentPlanet['metal_perhour'] = 0;
            $currentPlanet['crystal_perhour'] = 0;
            $currentPlanet['deuterium_perhour'] = 0;
            $currentPlanet['energy_used'] = 0;
            $currentPlanet['energy_max'] = 0;
        } else {
            $currentPlanet['metal_perhour'] = $caps['metal_perhour'] ?? 0;
            $currentPlanet['crystal_perhour'] = $caps['crystal_perhour'] ?? 0;
            $currentPlanet['deuterium_perhour'] = $caps['deuterium_perhour'] ?? 0;
            $currentPlanet['energy_used'] = $caps['energy_used'] ?? 0;
            $currentPlanet['energy_max'] = $caps['energy_max'] ?? 0;
        }

        $productionTime = $updateTime - $currentPlanet['last_update'];
        $currentPlanet['last_update'] = $updateTime;

        if ($currentPlanet['energy_max'] == 0) {
            $metalIncome = $currentPlanet['planet_type'] == 3 ? 0 : $gameConfig['metal_basic_income'];
            $crystalIncome = $currentPlanet['planet_type'] == 3 ? 0 : $gameConfig['crystal_basic_income'];
            $deuteriumIncome = $currentPlanet['planet_type'] == 3 ? 0 : $gameConfig['deuterium_basic_income'];
            $currentPlanet['metal_perhour'] = $metalIncome;
            $currentPlanet['crystal_perhour'] = $crystalIncome;
            $currentPlanet['deuterium_perhour'] = $deuteriumIncome;
            $productionLevel = 100;
        } elseif ($currentPlanet["energy_max"] >= $currentPlanet["energy_used"]) {
            $productionLevel = 100;
        } else {
            $productionLevel = floor(($currentPlanet['energy_max'] / $currentPlanet['energy_used']) * 100);
        }

        if ($productionLevel > 100) {
            $productionLevel = 100;
        } elseif ($productionLevel < 0) {
            $productionLevel = 0;
        }

        if ($currentPlanet['metal'] <= $maxMetalStorage) {
            $metalProduction = (($productionTime * ($currentPlanet['metal_perhour'] / 3600)) * $gameConfig['resource_multiplier']) * (0.01 * $productionLevel);
            $metalBaseProduc = (($productionTime * ($gameConfig['metal_basic_income'] / 3600)) * $gameConfig['resource_multiplier']);
            $metalTheorical = $currentPlanet['metal'] + $metalProduction + $metalBaseProduc;
            $currentPlanet['metal'] = ($metalTheorical <= $maxMetalStorage) ? $metalTheorical : $maxMetalStorage;
        }

        if ($currentPlanet['crystal'] <= $maxCristalStorage) {
            $crystalProduction = (($productionTime * ($currentPlanet['crystal_perhour'] / 3600)) * $gameConfig['resource_multiplier']) * (0.01 * $productionLevel);
            $crystalBaseProduc = (($productionTime * ($gameConfig['crystal_basic_income'] / 3600)) * $gameConfig['resource_multiplier']);
            $crystalTheorical = $currentPlanet['crystal'] + $crystalProduction + $crystalBaseProduc;
            $currentPlanet['crystal'] = ($crystalTheorical <= $maxCristalStorage) ? $crystalTheorical : $maxCristalStorage;
        }

        if ($currentPlanet['deuterium'] <= $maxDeuteriumStorage) {
            $deuteriumProduction = (($productionTime * ($currentPlanet['deuterium_perhour'] / 3600)) * $gameConfig['resource_multiplier']) * (0.01 * $productionLevel);
            $deuteriumBaseProduc = (($productionTime * ($gameConfig['deuterium_basic_income'] / 3600)) * $gameConfig['resource_multiplier']);
            $deuteriumTheorical = $currentPlanet['deuterium'] + $deuteriumProduction + $deuteriumBaseProduc;
            $currentPlanet['deuterium'] = ($deuteriumTheorical <= $maxDeuteriumStorage) ? $deuteriumTheorical : $maxDeuteriumStorage;
        }

        if ($simul === false) {
            $builded = $this->handleElementBuildingQueue($currentUser, $currentPlanet, $productionTime);
            $this->queues->savePlanetProduction($currentPlanet, $builded);
        }
    }

    /**
     * Traite la file du hangar : les éléments dont le temps de construction
     * est écoulé sont produits (ex HandleElementBuildingQueue).
     *
     * Le format de la file (`element,count;`) est lu et réécrit par QueueService,
     * comme pour le réordonnancement : une seule implémentation.
     */
    public function handleElementBuildingQueue(array $currentUser, array &$currentPlanet, int $productionTime): array
    {
        $resource = GameData::resource();

        if (!$currentPlanet['b_hangar_id']) {
            $currentPlanet['b_hangar'] = 0;

            return array();
        }

        $entries = $this->hangar->hangarEntries($currentPlanet);
        $buildTimes = array();

        foreach ($entries as $entry) {
            $buildTimes[$entry['element']] = $this->buildings->buildingTime(
                $currentUser,
                $currentPlanet,
                $entry['element']
            );
        }

        $result = self::consumeHangarWork($entries, (int) $currentPlanet['b_hangar'] + $productionTime, $buildTimes);
        $result = self::capUniqueProduction($result, $currentPlanet);

        foreach ($result['built'] as $element => $count) {
            $currentPlanet[$resource[$element]] += $count;
        }

        $currentPlanet['b_hangar'] = $result['hangar'];
        $currentPlanet['b_hangar_id'] = QueueService::serializeHangarEntries($result['remaining']);

        return $result['built'];
    }

    /**
     * Consomme le temps accumulé dans le hangar. Fonction pure.
     *
     * Les entrées sont traitées dans l'ordre : la première épuise le temps
     * accumulé avant que la suivante ne commence, et ce qui n'a pas pu être
     * produit reste en file.
     *
     * Un temps de construction **nul** (partie accélérée, nanites) produit le lot
     * immédiatement : c'est ce que faisait le code d'origine, où la condition
     * `b_hangar >= buildTime` était toujours vraie avec 0. Le garde-fou `> 0`
     * ajouté à la migration bloquait au contraire la file pour toujours
     * (b_hangar grossissait sans que rien ne sorte).
     *
     * @param list<array{element: int, count: int}> $entries
     * @param array<int, int> $buildTimes temps de construction, par élément
     * @return array{built: array<int, int>, hangar: int, remaining: list<array{element: int, count: int}>}
     */
    public static function consumeHangarWork(array $entries, int $hangar, array $buildTimes): array
    {
        $built = array();
        $remaining = array();

        foreach ($entries as $entry) {
            $element = (int) $entry['element'];
            $count = (int) $entry['count'];

            if ($count <= 0) {
                continue;
            }

            $buildTime = (int) ($buildTimes[$element] ?? 0);

            if ($buildTime <= 0) {
                $produced = $count;
            } else {
                $produced = min($count, intdiv($hangar, $buildTime));
                $hangar -= $buildTime * $produced;
            }

            if ($produced > 0) {
                $built[$element] = ($built[$element] ?? 0) + $produced;
                $count -= $produced;
            }

            if ($count > 0) {
                $remaining[] = array('element' => $element, 'count' => $count);
            }
        }

        return array('built' => $built, 'hangar' => $hangar, 'remaining' => $remaining);
    }

    /**
     * Les éléments uniques (boucliers) ne peuvent dépasser un exemplaire : le
     * surplus est retiré de la file au lieu d'être produit, ce qui répare au
     * passage une file ouverte avant la règle. Fonction pure.
     *
     * @param array{built: array<int, int>, hangar: int, remaining: list<array{element: int, count: int}>} $result
     * @return array{built: array<int, int>, hangar: int, remaining: list<array{element: int, count: int}>}
     */
    public static function capUniqueProduction(array $result, array $planet): array
    {
        $resource = GameData::resource();

        foreach (GameData::uniqueUnits() as $element) {
            if (!isset($result['built'][$element])) {
                continue;
            }

            $column = $resource[$element] ?? null;
            $already = $column !== null ? (int) ($planet[$column] ?? 0) : 0;
            $produced = min((int) $result['built'][$element], max(0, 1 - $already));

            if ($produced > 0) {
                $result['built'][$element] = $produced;
            } else {
                unset($result['built'][$element]);
            }

            // Le reste de la commande ne peut pas être livré : il quitte la file.
            $result['remaining'] = array_values(array_filter(
                $result['remaining'],
                static fn (array $entry): bool => (int) ($entry['element'] ?? 0) !== $element
            ));
        }

        return $result;
    }
}
