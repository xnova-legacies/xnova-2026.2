<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlanetRepository;

/**
 * Vue « Empire » : toutes les planètes du joueur, colonne par colonne
 * (ex `ProfilController::imperiumAction` / `imperium.php`).
 *
 * Le contenu est rendu ici pour que l'onglet « Empire » de la vue générale et
 * l'ancienne adresse `/game/profil/imperium` affichent exactement la même page.
 */
final class ImperiumService
{
    public function buildPage(array $user): string
    {
        global $lang, $resource, $reslist;

        includeLang('imperium');

        $planetRepository = new PlanetRepository();

        $order = ($user['planet_sort_order'] == 1) ? 'DESC' : 'ASC';
        $sort = $user['planet_sort'];

        $planets = $planetRepository->findAllByOwner((int) $user['id'], (int) $sort, $order);

        $row = gettemplate('imperium_row');
        $row2 = gettemplate('imperium_row2');

        $parse = array(
            'mount' => count($planets) + 1,
            'file_images' => '',
            'file_names' => '',
            'file_coordinates' => '',
            'file_fields' => '',
            'file_metal' => '',
            'file_crystal' => '',
            'file_deuterium' => '',
            'file_energy' => '',
            'building_row' => '',
            'technology_row' => '',
            'fleet_row' => '',
            'defense_row' => '',
        );

        $cells = array();

        foreach ($planets as $planet) {
            PlanetResourceUpdate($user, $planet, time());

            $data = array();
            $data['text'] = '<a href="/game/overview?cp=' . $planet['id'] . '&amp;re=0"><img class="rounded" src="/public/xnova/planeten/small/s_' . $planet['image'] . '.jpg" alt="' . htmlspecialchars((string) $planet['name'], ENT_QUOTES) . '" height="71" width="75"></a>';
            $parse['file_images'] .= parsetemplate($row, $data);
            $data['text'] = $planet['name'];
            $parse['file_names'] .= parsetemplate($row2, $data);
            $data['text'] = "[<a href=\"/game/galaxy?mode=3&galaxy={$planet['galaxy']}&system={$planet['system']}\">{$planet['galaxy']}:{$planet['system']}:{$planet['planet']}</a>]";
            $parse['file_coordinates'] .= parsetemplate($row2, $data);
            $data['text'] = $planet['field_current'] . '/' . $planet['field_max'];
            $parse['file_fields'] .= parsetemplate($row2, $data);
            $data['text'] = '<a href="/game/overview?tab=resources&amp;cp=' . $planet['id'] . '&amp;re=0&amp;planettype=' . $planet['planet_type'] . '">' . pretty_number($planet['metal']) . '</a> / ' . pretty_number($planet['metal_perhour']);
            $parse['file_metal'] .= parsetemplate($row2, $data);
            $data['text'] = '<a href="/game/overview?tab=resources&amp;cp=' . $planet['id'] . '&amp;re=0&amp;planettype=' . $planet['planet_type'] . '">' . pretty_number($planet['crystal']) . '</a> / ' . pretty_number($planet['crystal_perhour']);
            $parse['file_crystal'] .= parsetemplate($row2, $data);
            $data['text'] = '<a href="/game/overview?tab=resources&amp;cp=' . $planet['id'] . '&amp;re=0&amp;planettype=' . $planet['planet_type'] . '">' . pretty_number($planet['deuterium']) . '</a> / ' . pretty_number($planet['deuterium_perhour']);
            $parse['file_deuterium'] .= parsetemplate($row2, $data);
            $data['text'] = pretty_number($planet['energy_max'] - $planet['energy_used']) . ' / ' . pretty_number($planet['energy_max']);
            $parse['file_energy'] .= parsetemplate($row2, $data);

            foreach ($resource as $elementId => $column) {
                if (in_array($elementId, $reslist['build'])) {
                    $data['text'] = ($planet[$column] == 0) ? '-' : "<a href=\"/game/buildings?cp={$planet['id']}&amp;re=0&amp;planettype={$planet['planet_type']}\">{$planet[$column]}</a>";
                } elseif (in_array($elementId, $reslist['tech'])) {
                    $data['text'] = ($user[$column] == 0) ? '-' : "<a href=\"/game/buildings?mode=research&cp={$planet['id']}&amp;re=0&amp;planettype={$planet['planet_type']}\">{$user[$column]}</a>";
                } elseif (in_array($elementId, $reslist['fleet'])) {
                    $data['text'] = ($planet[$column] == 0) ? '-' : "<a href=\"/game/buildings?mode=fleet&cp={$planet['id']}&amp;re=0&amp;planettype={$planet['planet_type']}\">{$planet[$column]}</a>";
                } elseif (in_array($elementId, $reslist['defense'])) {
                    $data['text'] = ($planet[$column] == 0) ? '-' : "<a href=\"/game/buildings?mode=defense&cp={$planet['id']}&amp;re=0&amp;planettype={$planet['planet_type']}\">{$planet[$column]}</a>";
                } else {
                    $data['text'] = '';
                }

                $cells[$elementId] = ($cells[$elementId] ?? '') . parsetemplate($row2, $data);
            }
        }

        foreach ($reslist['build'] as $elementId) {
            $data['text'] = $lang['tech'][$elementId];
            $parse['building_row'] .= '<tr>' . parsetemplate($row2, $data) . ($cells[$elementId] ?? '') . '</tr>';
        }
        foreach ($reslist['tech'] as $elementId) {
            $data['text'] = $lang['tech'][$elementId];
            $parse['technology_row'] .= '<tr>' . parsetemplate($row2, $data) . ($cells[$elementId] ?? '') . '</tr>';
        }
        foreach ($reslist['fleet'] as $elementId) {
            $data['text'] = $lang['tech'][$elementId];
            $parse['fleet_row'] .= '<tr>' . parsetemplate($row2, $data) . ($cells[$elementId] ?? '') . '</tr>';
        }
        foreach ($reslist['defense'] as $elementId) {
            $data['text'] = $lang['tech'][$elementId];
            $parse['defense_row'] .= '<tr>' . parsetemplate($row2, $data) . ($cells[$elementId] ?? '') . '</tr>';
        }

        return parsetemplate(gettemplate('imperium_table'), $parse + $lang);
    }
}
