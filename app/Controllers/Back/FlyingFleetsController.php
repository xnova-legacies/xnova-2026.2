<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Format;
use App\Core\GameData;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\FleetRepository;
use App\Services\FlyingFleetService;

/**
 * Panneau d'administration : flottes en vol de tout l'univers.
 *
 * Reprend `admin/ShowFlyingFleets.php`, qui assemblait ses lignes avec la
 * mécanique d'événements de la vue générale (popups, applets de décompte). Ici la
 * composition d'une flotte est lue par `FlyingFleetService::parseUnits()` — la
 * même règle que le bandeau du bas — et la mission vient de `$lang['type_mission'].
 */
final class FlyingFleetsController extends AdminController
{
    /** Adresse de la page, reprise par les liens de pagination. */
    private const PATH = '/back/flying-fleets';

    public function __construct(
        private readonly FleetRepository $fleets = new FleetRepository(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.fleets';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('admin/fleets');
        $this->includeLang('tech');

        $lang = $this->lang();
        $resource = GameData::resource();
        $tech = is_array($lang['tech'] ?? null) ? $lang['tech'] : array();

        $rows = '';

        // Liste potentiellement très longue : paginée et triable par colonne.
        $sort = Paginator::sort(
            is_string($request->get('sort')) ? (string) $request->get('sort') : null,
            is_string($request->get('order')) ? (string) $request->get('order') : null,
            array_keys(FleetRepository::SORTS),
            'end'
        );
        $perPage = Paginator::perPage($request->get('per_page'));
        $labels = $this->paginationLabels((string) ($lang['flt_title'] ?? ''));
        $window = Paginator::window($this->fleets->countInFlight(), $request->get('page'), $perPage);

        // Les colonnes de composition et de position ne se trient pas : elles
        // gardent un en-tête simple.
        $head = '';

        foreach (
            array(
            array('id', 'flt_id'),
            array('', 'flt_fleet'),
            array('mission', 'flt_mission'),
            array('owner', 'flt_owner'),
            array('', 'flt_planet'),
            array('start', 'flt_time_st'),
            array('', 'flt_e_owner'),
            array('', 'flt_planet'),
            array('stay', 'flt_staying'),
            array('end', 'flt_time_en'),
            ) as $column
        ) {
            $label = (string) ($lang[$column[1]] ?? $column[1]);

            $head .= $column[0] === ''
                ? $this->adminTemplate('table_head', array('th_label' => $label, 'th_class' => ''))
                : Paginator::header(self::PATH, array('per_page' => (string) $perPage), $sort, $column[0], $label, $labels);
        }

        foreach ($this->fleets->findAllInFlight($sort['field'], $sort['order'], $window['per_page'], $window['offset']) as $fleet) {
            $units = '';

            foreach (FlyingFleetService::parseUnits((string) $fleet['fleet_array']) as $unitId => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $column = (string) ($resource[$unitId] ?? '');
                $label = $column !== '' ? (string) ($tech[$unitId] ?? $column) : (string) $unitId;
                $units .= $label . ' x' . $quantity . ', ';
            }

            $rows .= $this->adminTemplate('fleet_rows', $lang + array(
                'flt_data_id' => (int) $fleet['fleet_id'],
                'flt_data_fleet' => rtrim($units, ', '),
                'flt_data_mission' => (string) ($lang['type_mission'][(int) $fleet['fleet_mission']] ?? ''),
                'flt_data_owner' => Format::escape((string) $fleet['owner_name']),
                'flt_data_start' => '[' . (int) $fleet['fleet_start_galaxy'] . ':' . (int) $fleet['fleet_start_system']
                    . ':' . (int) $fleet['fleet_start_planet'] . ']',
                'flt_data_start_time' => gmdate('d/m/Y H:i:s', (int) $fleet['fleet_start_time']),
                'flt_data_target' => Format::escape((string) ($fleet['target_name'] ?? '')),
                'flt_data_end' => '[' . (int) $fleet['fleet_end_galaxy'] . ':' . (int) $fleet['fleet_end_system']
                    . ':' . (int) $fleet['fleet_end_planet'] . ']',
                'flt_data_stay' => (int) $fleet['fleet_end_stay'] > 0
                    ? gmdate('d/m/Y H:i:s', (int) $fleet['fleet_end_stay'])
                    : '&mdash;',
                'flt_data_end_time' => gmdate('d/m/Y H:i:s', (int) $fleet['fleet_end_time']),
                'flt_data_returning' => (int) $fleet['fleet_mess'] === 1
                    ? (string) ($lang['flt_returning'] ?? '')
                    : '&mdash;',
            ));
        }

        $body = $this->adminPanel('fleet_body', $lang + array(
            'flt_table' => $rows,
            'fleet_head' => $head,
            'fleet_page_bar' => Paginator::sizeBar(self::PATH, array(), $window, $labels, $sort),
            'fleet_pagination' => Paginator::render(self::PATH, array(), $window, $labels, $sort),
        ), (string) ($lang['flt_title'] ?? ''), 'bi-rocket-takeoff', $window['total'] . ' ' . (string) ($lang['flt_count'] ?? ''));

        return $this->adminPage($body, (string) ($lang['flt_title'] ?? 'Administration'));
    }
}
