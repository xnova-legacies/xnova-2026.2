<?php

namespace App\Services;

use App\Core\Format;
use App\Core\GameConfig;
use App\Core\GameData;
use App\Core\ResearchMath;
use App\Repositories\BuildingQueueRepository;
use App\Repositories\PlanetRepository;

/**
 * Gestion des files de construction (bâtiments, recherche, hangar).
 * Porte-métier de AddBuildingToQueue, CancelBuildingFromQueue,
 * RemoveBuildingFromQueue, SetNextQueueElementOnTop, CheckPlanetBuildingQueue,
 * GetBuildingTime/Price/TimeLevel, IsElementBuyable, GetElementPrice,
 * GetMaxConstructibleElements, UpdatePlanetBatimentQueueList,
 * HandleTechnologieBuild, BuildingSavePlanetRecord/UserRecord.
 */
class BuildingService
{
    public function __construct(
        private readonly BuildingQueueRepository $queues = new BuildingQueueRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
    ) {
    }


    /** Remplit les champs de bâtiments/technos manquants avec 0 (ex legacy silencieux PHP < 8). */
    private function normalizeEntity(array $entity): array
    {
        $resource = GameData::resource();

        foreach ($resource as $id => $field) {
            if (!isset($entity[$field])) {
                $entity[$field] = 0;
            }
        }

        return $entity;
    }
    /** Coût d'un élément (ex GetBuildingPrice / GetRestPrice). */
    public function buildingPrice(array $user, array $planet, $element, bool $incremental = true, bool $forDestroy = false): array
    {
        $pricelist = GameData::priceList();
        $resource = GameData::resource();

        if ($incremental) {
            $level = ($planet[$resource[$element]]) ? $planet[$resource[$element]] : $user[$resource[$element]];
        }

        $array = array('metal', 'crystal', 'deuterium');
        $cost = array();

        foreach ($array as $resType) {
            if ($pricelist[$element][$resType] != 0) {
                if ($incremental) {
                    $cost[$resType] = floor($pricelist[$element][$resType] * pow($pricelist[$element]['factor'], $level));
                } else {
                    $cost[$resType] = floor($pricelist[$element][$resType]);
                }

                if ($forDestroy == true) {
                    $cost[$resType] = floor($cost[$resType] / 2);
                }
            } else {
                $cost[$resType] = 0;
            }
        }

        $cost['energy'] = ($pricelist[$element]['energy'] ?? 0) * ($incremental ? $level : 1);

        return $cost;
    }

    /** Temps de construction (ex GetBuildingTime). */
    public function buildingTime(array $user, array $planet, $element): int
    {
        $pricelist = GameData::priceList();
        $resource = GameData::resource();
        $reslist = GameData::resList();
        $gameConfig = GameConfig::load();

        $level = ($planet[$resource[$element]]) ? $planet[$resource[$element]] : $user[$resource[$element]];

        if (in_array($element, $reslist['build'])) {
            $costMetal = floor($pricelist[$element]['metal'] * pow($pricelist[$element]['factor'], $level));
            $costCrystal = floor($pricelist[$element]['crystal'] * pow($pricelist[$element]['factor'], $level));
            $time = ((($costCrystal) + ($costMetal)) / $gameConfig['game_speed']) * (1 / ($planet[$resource['14']] + 1)) * pow(0.5, $planet[$resource['15']]);
            $time = floor(($time * 60 * 60) * (1 - static::durationBonus($user, 'build')));
        } elseif (in_array($element, $reslist['tech'])) {
            $costMetal = floor($pricelist[$element]['metal'] * pow($pricelist[$element]['factor'], $level));
            $costCrystal = floor($pricelist[$element]['crystal'] * pow($pricelist[$element]['factor'], $level));
            $intergalLab = $user[$resource[123]];

            if ($intergalLab < 1) {
                $lablevel = $planet[$resource['31']];
            } else {
                $rows = $this->planets->findAllByOwner((int) $user['id']);
                $techlevel = array();
                $nbLabs = 0;
                foreach ($rows as $colony) {
                    $techlevel[$nbLabs] = $colony[$resource['31']];
                    $nbLabs++;
                }
                $lablevel = 0;
                for ($lab = 1; $lab <= $intergalLab; $lab++) {
                    asort($techlevel);
                    $lablevel += $techlevel[$lab - 1] ?? 0;
                }
            }
            $time = (($costMetal + $costCrystal) / $gameConfig['game_speed']) / (($lablevel + 1) * 2);
            // Accelerateur de particules : une recherche est deux fois plus rapide par niveau.
            $time *= ResearchMath::accelerationFactor((int) ($planet[$resource[16]] ?? 0));
            $time = floor(($time * 60 * 60) * (1 - static::durationBonus($user, 'research')));
        } elseif (in_array($element, $reslist['defense'])) {
            $time = (($pricelist[$element]['metal'] + $pricelist[$element]['crystal']) / $gameConfig['game_speed']) * (1 / ($planet[$resource['21']] + 1)) * pow(1 / 2, $planet[$resource['15']]);
            $time = floor(($time * 60 * 60) * (1 - static::durationBonus($user, 'defense')));
        } elseif (in_array($element, $reslist['fleet'])) {
            $time = (($pricelist[$element]['metal'] + $pricelist[$element]['crystal']) / $gameConfig['game_speed']) * (1 / ($planet[$resource['21']] + 1)) * pow(1 / 2, $planet[$resource['15']]);
            $time = floor(($time * 60 * 60) * (1 - static::durationBonus($user, 'fleet')));
        } else {
            $time = 0;
        }

        // La duree du chantier ne depend que de game_speed (game_config).
        return (int) $time;
    }

    /**
     * Part d'un module dans la **durée** d'un élément (fraction retirée du temps).
     *
     * Le Coeur de l'application n'applique aucun bonus : cette méthode rend 0, et un module
     * la surcharge — le module `officier` retire 10 % par niveau de constructeur pour les
     * bâtiments, 10 % de scientifique pour la recherche, 37,5 % de défenseur pour la
     * défense et 5 % de technocrate pour les vaisseaux. Jumelle de
     * `protected static` et appelée en `static::` pour qu'un enfant puisse la remplacer.
     * **Publique** parce que les pages legacy (`GetBuildingTime()`, `GetBuildingTimeLevel()`)
     * l'appellent aussi : elles gardent leur formule, elles demandent seulement la part
     * des officiers au service résolu.
     *
     * @param array<string, mixed> $user
     * @param string               $kind `build`, `research`, `defense` ou `fleet`
     */
    public static function durationBonus(array $user, string $kind): float
    {
        return 0.0;
    }

    /**
     * Nombre d'unités réellement produites pour un élément du hangar.
     *
     * Le Coeur de l'application ne connaît aucun officier : la quantité demandée est rendue
     * telle quelle, et un module la surcharge — le module `officier` double les missiles
     * interplanétaires (élément 214) pour son « destructeur ». Ce n'est pas une durée : c'est
     * une quantité, d'où une seconde méthode plutôt qu'un cas de `durationBonus()`.
     * **Publique** parce que la page legacy (`FleetBuildingPage()`) l'appelle aussi : elle
     * garde sa boucle, elle demande seulement la quantité au service résolu.
     *
     * @param array<string, mixed> $user
     * @param int                  $element élément du hangar (vaisseau ou défense)
     * @param int                  $count   quantité demandée par le joueur
     */
    public static function shipCount(array $user, int $element, int $count): int
    {
        return $count;
    }

    /**
     * Colonnes du compte à écrire quand une construction se termine (vide = aucune).
     *
     * Le Coeur de l'application n'attribue **aucune** expérience : l'expérience des chantiers
     * appartient au module des officiers (niveaux mineur et raideur), qui surcharge cette méthode
     * pour la sienne. Le nom de la colonne est donc rendu par le module et écrit par le dépôt de la
     * file (`saveUserFields()`) : le Coeur de l'application ne le connaît pas.
     *
     * @param array<string, mixed>      $user       le compte, avant écriture
     * @param int                       $element    l'élément dont la construction se termine
     * @param bool                      $forDestroy true pour une démolition
     * @param array<string, int|float>  $price      le prix payé (metal, crystal, deuterium)
     * @return array<string, int|float> colonne => nouvelle valeur
     */
    protected static function constructionRewards(array $user, int $element, bool $forDestroy, array $price): array
    {
        return array();
    }

    /** Élément achetable ? (ex IsElementBuyable) */
    public function isElementBuyable(array $currentUser, array $currentPlanet, $element, bool $incremental = true, bool $forDestroy = false): bool
    {
        $pricelist = GameData::priceList();
        $resource = GameData::resource();

        if ($this->isVacationMode($currentUser)) {
            return false;
        }

        if ($incremental) {
            $level = ($currentPlanet[$resource[$element]]) ? $currentPlanet[$resource[$element]] : $currentUser[$resource[$element]];
        }

        $retValue = true;
        $array = array('metal', 'crystal', 'deuterium', 'energy_max');

        foreach ($array as $resType) {
            if ($pricelist[$element][$resType] != 0) {
                if ($incremental) {
                    $cost[$resType] = floor($pricelist[$element][$resType] * pow($pricelist[$element]['factor'], $level));
                } else {
                    $cost[$resType] = floor($pricelist[$element][$resType]);
                }

                if ($forDestroy == true) {
                    $cost[$resType] = floor($cost[$resType] / 2);
                }
            } else {
                $cost[$resType] = 0;
            }

            if (isset($cost[$resType])) {
                if ($cost[$resType] > $currentPlanet[$resType]) {
                    $retValue = false;
                }
            }
        }

        return $retValue;
    }

    public function isVacationMode(array $currentUser): bool
    {
        if (($currentUser['vacation_mode'] ?? 0) == 1) {
            return true;
        }

        $rows = $this->planets->findAllByOwner((int) $currentUser['id']);

        foreach ($rows as $planet) {
            if ($planet['b_building'] != 0) {
                continue;
            }
        }

        return false;
    }

    /** Prix en texte HTML (ex GetElementPrice). */
    public function elementPriceHtml(array $user, array $planet, $element, bool $userfactor = true): string
    {
        $pricelist = GameData::priceList();
        $resource = GameData::resource();
        $lang = \App\Core\Language::all();

        if ($userfactor) {
            $level = ($planet[$resource[$element]]) ? $planet[$resource[$element]] : $user[$resource[$element]];
        }

        $array = array(
            'metal' => $lang["Metal"],
            'crystal' => $lang["Crystal"],
            'deuterium' => $lang["Deuterium"],
            'energy_max' => $lang["Energy"]
        );

        $text = $lang['Requires'] . ": ";
        foreach ($array as $resType => $resTitle) {
            if ($pricelist[$element][$resType] != 0) {
                $text .= $resTitle . ": ";
                if ($userfactor) {
                    $cost = floor($pricelist[$element][$resType] * pow($pricelist[$element]['factor'], $level));
                } else {
                    $cost = floor($pricelist[$element][$resType]);
                }
                if ($cost > $planet[$resType]) {
                    $text .= "<b style=\"color:red;\">";
                } elseif ($pricelist[$element][$resType] != 0) {
                    $text .= "<b style=\"color:lime;\">";
                }
                $text .= Format::prettyNumber($cost) . " " . $lang['Energy'] . "</b> ";
            }
        }

        return $text;
    }

    /** Max constructible (ex GetMaxConstructibleElements). */
    public function maxConstructibleElements($element, array $planet): int
    {
        $pricelist = GameData::priceList();

        if ($pricelist[$element]['metal'] != 0) {
            $cost = $pricelist[$element]['metal'];
            $resourceType = 'metal';
        } elseif ($pricelist[$element]['crystal'] != 0) {
            $cost = $pricelist[$element]['crystal'];
            $resourceType = 'crystal';
        } elseif ($pricelist[$element]['deuterium'] != 0) {
            $cost = $pricelist[$element]['deuterium'];
            $resourceType = 'deuterium';
        }

        return (int) floor($planet[$resourceType] / $cost);
    }

    /** Vérifie/termine la file de construction (ex CheckPlanetBuildingQueue). */
    public function checkPlanetBuildingQueue(array &$currentPlanet, array &$currentUser): bool
    {
        $resource = GameData::resource();
        $lang = \App\Core\Language::all();

        $retValue = false;

        if ($currentPlanet['b_building_id'] != 0) {
            $currentQueue = $currentPlanet['b_building_id'];
            $queueArray = explode(";", $currentQueue);
            $actualCount = count($queueArray);

            $buildArray = explode(",", $queueArray[0]);
            $buildEndTime = floor($buildArray[3]);
            $buildMode = $buildArray[4];
            $element = $buildArray[0];
            array_shift($queueArray);

            $forDestroy = ($buildMode == 'destroy');

            if ($buildEndTime <= time()) {
                $needed = $this->buildingPrice($currentUser, $currentPlanet, $element, true, $forDestroy);

                // Récompense de la construction : le Coeur de l'application n'écrit aucune colonne
                // d'officier, il demande au module lesquelles et avec quelles valeurs. L'élément est
                // **casté** : la file legacy le porte en chaîne (`$buildArray[0]`), et vide sur une
                // file mal formée — le crochet, lui, reçoit un entier.
                $rewards = static::constructionRewards($currentUser, (int) $element, $forDestroy, $needed);

                foreach ($rewards as $rewardColumn => $rewardValue) {
                    $currentUser[$rewardColumn] = $rewardValue;
                }

                $current = intval($currentPlanet['field_current']);
                $max = intval($currentPlanet['field_max']);

                if ($currentPlanet['planet_type'] == 3) {
                    if ($element == 41) {
                        $current += 1;
                        $max += \App\Core\GameConstants::fieldsByMoonbasisLevel();
                        $currentPlanet[$resource[$element]]++;
                    } elseif ($element != 0) {
                        if ($forDestroy == false) {
                            $current += 1;
                            $currentPlanet[$resource[$element]]++;
                        } else {
                            $current -= 1;
                            $currentPlanet[$resource[$element]]--;
                        }
                    }
                } elseif ($currentPlanet['planet_type'] == 1) {
                    if ($forDestroy == false) {
                        $current += 1;
                        $currentPlanet[$resource[$element]]++;
                    } else {
                        $current -= 1;
                        $currentPlanet[$resource[$element]]--;
                    }
                }

                $newQueue = (count($queueArray) == 0) ? 0 : implode(";", $queueArray);
                $currentPlanet['b_building'] = 0;
                $currentPlanet['b_building_id'] = $newQueue;
                $currentPlanet['field_current'] = $current;
                $currentPlanet['field_max'] = $max;

                $elementField = $resource[$element] ?? '';
                if ($elementField !== '') {
                    $this->queues->saveBuildingLevel($currentPlanet, $elementField);
                } else {
                    $this->queues->saveBuildingQueueState($currentPlanet);
                }

                // Les colonnes rendues par le module sont écrites par le dépôt, qui les reçoit
                // de l'appelant : le Coeur de l'application n'en nomme aucune.
                if ($rewards !== array()) {
                    $this->queues->saveUserFields((int) $currentUser['id'], $rewards);
                }

                $retValue = true;
            } else {
                $retValue = false;
            }
        } else {
            $currentPlanet['b_building'] = 0;
            $currentPlanet['b_building_id'] = 0;
            $this->queues->saveBuildingQueueState($currentPlanet);
            $retValue = false;
        }

        return $retValue;
    }

    /** Liste la file de construction pour affichage (ex ShowBuildingQueue). */
    public function showBuildingQueue(array $user, array $planet): array
    {
        $resource = GameData::resource();
        $lang = \App\Core\Language::all();
        $script = array();
        $script['buildlist'] = '';
        $queue = $this->queueToString($planet);

        return array('queue' => $queue, 'script' => $script);
    }

    private function queueToString(array $planet): string
    {
        return (string) ($planet['b_building_id'] ?? '');
    }
}
