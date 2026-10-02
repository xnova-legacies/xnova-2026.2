<?php

namespace App\Services;

/**
 * Page des ressources (onglet de la vue générale).
 *
 * La classe n'est **pas** `final` : un module peut en dériver (même nom, même
 * couche) pour compléter l'affichage — le module officier y explique sa part de
 * production. Sans module allumé, c'est cette classe qui sert.
 */
class ResourceService
{
    /**
     * Compléments affichés sous chaque production (`{metal_extra}`, `{energy_extra}`…).
     *
     * Le Coeur d'application n'en remplit aucun. Un module **dérive ce service** (même nom court,
     * même couche) pour expliquer une partie de la production — la part des
     * officiers, par exemple. Éteint, le module disparaît et les lignes restent
     * exactement comme s'il n'avait jamais été déposé.
     *
     * @param array<string, mixed> $user
     * @param array<string, array{base: float, value: float}> $production
     *        production des **bâtiments seuls** (`base`) et production affichée (`value`)
     * @return array<string, string>
     */
    protected function rowExtras(array $user, array $production): array
    {
        return array(
            'metal_extra' => '',
            'crystal_extra' => '',
            'deuterium_extra' => '',
            'energy_extra' => '',
        );
    }

    public function buildPage(array $currentUser, array $currentPlanet): string
    {
        global $lang, $ProdGrid, $resource, $reslist, $game_config;

        $planetRepository = new \App\Repositories\PlanetRepository();

        includeLang('resources');

        $RessBodyTPL = gettemplate('resources');
        $RessRowTPL = gettemplate('resources_row');

        if ($currentPlanet['planet_type'] == 3) {
            $game_config['metal_basic_income'] = 0;
            $game_config['crystal_basic_income'] = 0;
            $game_config['deuterium_basic_income'] = 0;
        }

        $ValidList['percent'] = array(0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100);
        $pourcentages = array();
        if ($_POST) {
            foreach ($_POST as $Field => $Value) {
                $FieldName = $Field . "_porcent";
                if (isset($currentPlanet[$FieldName])) {
                    if (!in_array($Value, $ValidList['percent'])) {
                        header("Location: /game/overview?tab=resources");
                        exit;
                    }

                    $Value = $Value / 10;
                    $currentPlanet[$FieldName] = $Value;
                    $pourcentages[$FieldName] = $Value;
                }
            }
        }

        $parse = $lang;

        $parse['production_level'] = 100;
        if (
            $currentPlanet['energy_max'] == 0 &&
            $currentPlanet['energy_used'] > 0
        ) {
            $post_porcent = 0;
        } elseif (
            $currentPlanet['energy_max'] > 0 &&
            ($currentPlanet['energy_used'] + $currentPlanet['energy_max']) < 0
        ) {
            $post_porcent = floor(($currentPlanet['energy_max']) / $currentPlanet['energy_used'] * 100);
        } else {
            $post_porcent = 100;
        }
        if ($post_porcent > 100) {
            $post_porcent = 100;
        }

        $storageService = \App\Services\ModuleService::resolve(ProductionService::class);
        $storage = $storageService::storageCapacities($currentPlanet, $currentUser);

        $currentPlanet['metal_max'] = $storage['metal'];
        $currentPlanet['crystal_max'] = $storage['crystal'];
        $currentPlanet['deuterium_max'] = $storage['deuterium'];

        $parse['resource_row'] = "";
        $currentPlanet['metal_perhour'] = 0;
        $currentPlanet['crystal_perhour'] = 0;
        $currentPlanet['deuterium_perhour'] = 0;
        $currentPlanet['energy_max'] = 0;
        $currentPlanet['energy_used'] = 0;
        $BuildTemp = $currentPlanet['temp_max'];
        foreach ($reslist['prod'] as $ProdID) {
            if ($currentPlanet[$resource[$ProdID]] > 0 && isset($ProdGrid[$ProdID])) {
                $BuildLevelFactor = $currentPlanet[$resource[$ProdID] . "_porcent"];
                $BuildLevel = $currentPlanet[$resource[$ProdID]];
                // Valeur **brute** de la formule (bâtiments seuls, sans bonus) puis valeur
                // affichée : l'écart est passé à `rowExtras()`, qu'un module peut remplir.
                $metalRaw = eval($ProdGrid[$ProdID]['formule']['metal']) * ($game_config['resource_multiplier']);
                $crystalRaw = eval($ProdGrid[$ProdID]['formule']['crystal']) * ($game_config['resource_multiplier']);
                $deuteriumRaw = eval($ProdGrid[$ProdID]['formule']['deuterium']) * ($game_config['resource_multiplier']);
                $energyRaw = eval($ProdGrid[$ProdID]['formule']['energy']) * ($game_config['resource_multiplier']);

                // La valeur affichée vaut la valeur brute : un module apporte sa part en
                // surchargeant `productionFactor()`, et l'explique dans `rowExtras()`.
                $metal = floor($metalRaw * $this->productionFactor($currentUser, 'metal'));
                $crystal = floor($crystalRaw * $this->productionFactor($currentUser, 'crystal'));
                $deuterium = floor($deuteriumRaw * $this->productionFactor($currentUser, 'deuterium'));
                $energy = floor($energyRaw * $this->productionFactor($currentUser, 'energy'));
                if ($energy > 0) {
                    $currentPlanet['energy_max'] += $energy;
                } else {
                    $currentPlanet['energy_used'] += $energy;
                }
                $currentPlanet['metal_perhour'] += $metal;
                $currentPlanet['crystal_perhour'] += $crystal;
                $currentPlanet['deuterium_perhour'] += $deuterium;

                $metal = $metal * 0.01 * $post_porcent;
                $crystal = $crystal * 0.01 * $post_porcent;
                $deuterium = $deuterium * 0.01 * $post_porcent;
                $energy = $energy * 0.01 * $post_porcent;
                $Field = $resource[$ProdID] . "_porcent";
                $CurrRow = array();
                $CurrRow['name'] = $resource[$ProdID];
                $CurrRow['porcent'] = $currentPlanet[$Field];
                for ($Option = 10; $Option >= 0; $Option--) {
                    $OptValue = $Option * 10;
                    if ($Option == $CurrRow['porcent']) {
                        $OptSelected = " selected=selected";
                    } else {
                        $OptSelected = "";
                    }
                    $CurrRow['option'] .= "<option value=\"" . $OptValue . "\"" . $OptSelected . ">" . $OptValue . "%</option>";
                }
                $CurrRow['type'] = $lang['tech'][$ProdID];
                $CurrRow['level'] = ($ProdID > 200) ? $lang['quantity'] : $lang['level'];
                $CurrRow['level_type'] = $currentPlanet[$resource[$ProdID]];
                $CurrRow['metal_type'] = pretty_number($metal);
                $CurrRow['crystal_type'] = pretty_number($crystal);
                $CurrRow['deuterium_type'] = pretty_number($deuterium);
                $CurrRow['energy_type'] = pretty_number($energy);
                $CurrRow['metal_type'] = colorNumber($CurrRow['metal_type']);
                $CurrRow['crystal_type'] = colorNumber($CurrRow['crystal_type']);
                $CurrRow['deuterium_type'] = colorNumber($CurrRow['deuterium_type']);
                $CurrRow['energy_type'] = colorNumber($CurrRow['energy_type']);

                // Compléments de ligne (part des officiers, par exemple) : le Coeur d'application
                // n'en remplit aucun, un module peut en apporter en dérivant ce service.
                $CurrRow += $this->rowExtras($currentUser, array(
                    'metal' => array('base' => floor($metalRaw) * 0.01 * $post_porcent, 'value' => $metal),
                    'crystal' => array('base' => floor($crystalRaw) * 0.01 * $post_porcent, 'value' => $crystal),
                    'deuterium' => array('base' => floor($deuteriumRaw) * 0.01 * $post_porcent, 'value' => $deuterium),
                    'energy' => array('base' => floor($energyRaw) * 0.01 * $post_porcent, 'value' => $energy),
                ));

                $parse['resource_row'] .= parsetemplate($RessRowTPL, $CurrRow);
            }
        }

        $parse['Production_of_resources_in_the_planet'] =
            str_replace('%s', $currentPlanet['name'], $lang['Production_of_resources_in_the_planet']);
        if (
            $currentPlanet['energy_max'] == 0 &&
            $currentPlanet['energy_used'] > 0
        ) {
            $parse['production_level'] = 0;
        } elseif (
            $currentPlanet['energy_max'] > 0 &&
            abs($currentPlanet['energy_used']) > $currentPlanet['energy_max']
        ) {
            $parse['production_level'] = floor(($currentPlanet['energy_max']) / $currentPlanet['energy_used'] * 100);
        } elseif (
            $currentPlanet['energy_max'] == 0 &&
            abs($currentPlanet['energy_used']) > $currentPlanet['energy_max']
        ) {
            $parse['production_level'] = 0;
        } else {
            $parse['production_level'] = 100;
        }
        if ($parse['production_level'] > 100) {
            $parse['production_level'] = 100;
        }

        $parse['metal_basic_income'] = $game_config['metal_basic_income'] * $game_config['resource_multiplier'];
        $parse['crystal_basic_income'] = $game_config['crystal_basic_income'] * $game_config['resource_multiplier'];
        $parse['deuterium_basic_income'] = $game_config['deuterium_basic_income'] * $game_config['resource_multiplier'];
        $parse['energy_basic_income'] = $game_config['energy_basic_income'] * $game_config['resource_multiplier'];

        $parse['metal_max'] = '<span class="' . (($currentPlanet['metal_max'] < $currentPlanet['metal']) ? 'text-danger' : 'text-success') . '">'
            . pretty_number($currentPlanet['metal_max'] / 1000) . ' ' . $lang['k'] . '</span>';

        $parse['crystal_max'] = '<span class="' . (($currentPlanet['crystal_max'] < $currentPlanet['crystal']) ? 'text-danger' : 'text-success') . '">'
            . pretty_number($currentPlanet['crystal_max'] / 1000) . ' ' . $lang['k'] . '</span>';

        $parse['deuterium_max'] = '<span class="' . (($currentPlanet['deuterium_max'] < $currentPlanet['deuterium']) ? 'text-danger' : 'text-success') . '">'
            . pretty_number($currentPlanet['deuterium_max'] / 1000) . ' ' . $lang['k'] . '</span>';

        $parse['metal_total'] = colorNumber(pretty_number(floor(($currentPlanet['metal_perhour'] * 0.01 * $parse['production_level']) + $parse['metal_basic_income'])));
        $parse['crystal_total'] = colorNumber(pretty_number(floor(($currentPlanet['crystal_perhour'] * 0.01 * $parse['production_level']) + $parse['crystal_basic_income'])));
        $parse['deuterium_total'] = colorNumber(pretty_number(floor(($currentPlanet['deuterium_perhour'] * 0.01 * $parse['production_level']) + $parse['deuterium_basic_income'])));
        $parse['energy_total'] = colorNumber(pretty_number(floor(($currentPlanet['energy_max'] + $parse['energy_basic_income']) + $currentPlanet['energy_used'])));

        $parse['daily_metal'] = floor($currentPlanet['metal_perhour'] * 24 * 0.01 * $parse['production_level'] + $parse['metal_basic_income'] * 24);
        $parse['weekly_metal'] = floor($currentPlanet['metal_perhour'] * 24 * 7 * 0.01 * $parse['production_level'] + $parse['metal_basic_income'] * 24 * 7);
        $parse['monthly_metal'] = floor($currentPlanet['metal_perhour'] * 24 * 30 * 0.01 * $parse['production_level'] + $parse['metal_basic_income'] * 24 * 30);

        $parse['daily_crystal'] = floor($currentPlanet['crystal_perhour'] * 24 * 0.01 * $parse['production_level'] + $parse['crystal_basic_income'] * 24);
        $parse['weekly_crystal'] = floor($currentPlanet['crystal_perhour'] * 24 * 7 * 0.01 * $parse['production_level'] + $parse['crystal_basic_income'] * 24 * 7);
        $parse['monthly_crystal'] = floor($currentPlanet['crystal_perhour'] * 24 * 30 * 0.01 * $parse['production_level'] + $parse['crystal_basic_income'] * 24 * 30);

        $parse['daily_deuterium'] = floor($currentPlanet['deuterium_perhour'] * 24 * 0.01 * $parse['production_level'] + $parse['deuterium_basic_income'] * 24);
        $parse['weekly_deuterium'] = floor($currentPlanet['deuterium_perhour'] * 24 * 7 * 0.01 * $parse['production_level'] + $parse['deuterium_basic_income'] * 24 * 7);
        $parse['monthly_deuterium'] = floor($currentPlanet['deuterium_perhour'] * 24 * 30 * 0.01 * $parse['production_level'] + $parse['deuterium_basic_income'] * 24 * 30);

        $parse['daily_metal'] = colorNumber(pretty_number($parse['daily_metal']));
        $parse['weekly_metal'] = colorNumber(pretty_number($parse['weekly_metal']));
        $parse['monthly_metal'] = colorNumber(pretty_number($parse['monthly_metal']));

        $parse['daily_crystal'] = colorNumber(pretty_number($parse['daily_crystal']));
        $parse['weekly_crystal'] = colorNumber(pretty_number($parse['weekly_crystal']));
        $parse['monthly_crystal'] = colorNumber(pretty_number($parse['monthly_crystal']));

        $parse['daily_deuterium'] = colorNumber(pretty_number($parse['daily_deuterium']));
        $parse['weekly_deuterium'] = colorNumber(pretty_number($parse['weekly_deuterium']));
        $parse['monthly_deuterium'] = colorNumber(pretty_number($parse['monthly_deuterium']));

        $parse['metal_storage'] = floor($currentPlanet['metal'] / $currentPlanet['metal_max'] * 100) . $lang['o/o'];
        $parse['crystal_storage'] = floor($currentPlanet['crystal'] / $currentPlanet['crystal_max'] * 100) . $lang['o/o'];
        $parse['deuterium_storage'] = floor($currentPlanet['deuterium'] / $currentPlanet['deuterium_max'] * 100) . $lang['o/o'];

        // Pourcentages bornes (0-100) pour les barres de progression Bootstrap
        $parse['metal_storage_pourcent'] = min(100, max(0, (int) floor($currentPlanet['metal'] / max(1, $currentPlanet['metal_max']) * 100)));
        $parse['crystal_storage_pourcent'] = min(100, max(0, (int) floor($currentPlanet['crystal'] / max(1, $currentPlanet['crystal_max']) * 100)));
        $parse['deuterium_storage_pourcent'] = min(100, max(0, (int) floor($currentPlanet['deuterium'] / max(1, $currentPlanet['deuterium_max']) * 100)));
        $parse['metal_storage_bar'] = floor(($currentPlanet['metal'] / $currentPlanet['metal_max'] * 100) * 2.5);
        $parse['crystal_storage_bar'] = floor(($currentPlanet['crystal'] / $currentPlanet['crystal_max'] * 100) * 2.5);
        $parse['deuterium_storage_bar'] = floor(($currentPlanet['deuterium'] / $currentPlanet['deuterium_max'] * 100) * 2.5);

        if ($parse['metal_storage_bar'] > (100 * 2.5)) {
            $parse['metal_storage_bar'] = 250;
            $parse['metal_storage_barcolor'] = '#C00000';
        } elseif ($parse['metal_storage_bar'] > (80 * 2.5)) {
            $parse['metal_storage_barcolor'] = '#C0C000';
        } else {
            $parse['metal_storage_barcolor'] = '#00C000';
        }

        if ($parse['crystal_storage_bar'] > (100 * 2.5)) {
            $parse['crystal_storage_bar'] = 250;
            $parse['crystal_storage_barcolor'] = '#C00000';
        } elseif ($parse['crystal_storage_bar'] > (80 * 2.5)) {
            $parse['crystal_storage_barcolor'] = '#C0C000';
        } else {
            $parse['crystal_storage_barcolor'] = '#00C000';
        }

        if ($parse['deuterium_storage_bar'] > (100 * 2.5)) {
            $parse['deuterium_storage_bar'] = 250;
            $parse['deuterium_storage_barcolor'] = '#C00000';
        } elseif ($parse['deuterium_storage_bar'] > (80 * 2.5)) {
            $parse['deuterium_storage_barcolor'] = '#C0C000';
        } else {
            $parse['deuterium_storage_barcolor'] = '#00C000';
        }

        $parse['production_level_bar'] = $parse['production_level'] * 2.5;
        $parse['production_level'] = "{$parse['production_level']}%";
        $parse['production_level_barcolor'] = '#00ff00';

        // Le bloc des bonus (ce qui majore réellement la production) est apporté par un
        // module : c'est la seule page où l'écart se voit. Sans module, il est vide.
        $parse['bonus_block'] = $this->bonusBlock($currentUser, $game_config, $lang);

        if ($pourcentages !== array()) {
            $planetRepository->updatePorcents((int) $currentPlanet['id'], $pourcentages);
        }

        return parsetemplate($RessBodyTPL, $parse);
    }

    /**
     * Facteur appliqué à la production **affichée** d'une ressource (1 = bâtiments seuls).
     *
     * Le Coeur d'application n'applique aucun bonus de module : celui qui en apporte un
     * surcharge cette méthode, et `rowExtras()` explique alors l'écart à l'écran.
     *
     * @param array<string, mixed> $user
     */
    protected function productionFactor(array $user, string $resource): float
    {
        return 1.0;
    }

    /**
     * Bloc affiché sous la production pour expliquer ce qui la majore (vide sans module).
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $game_config
     * @param array<string, mixed> $lang
     */
    protected function bonusBlock(array $user, array $game_config, array $lang): string
    {
        return '';
    }
}
