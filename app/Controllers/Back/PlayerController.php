<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Acl;
use App\Core\Api\CsrfToken;
use App\Core\Flags;
use App\Core\Format;
use App\Core\GameData;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\FleetRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\StatsRepository;
use App\Repositories\UserRepository;
use App\Services\AclService;
use App\Services\BanService;
use App\Services\BotService;
use App\Services\FlyingFleetService;
use App\Services\ModuleService;
use App\Services\PlayerAdminService;
use App\Services\QueueService;
use App\Services\SessionService;

/**
 * Panneau d'administration : gestion d'un joueur.
 *
 * Trois onglets, rendus par le serveur (des liens, aucun script) :
 *
 *   state    état du compte — bannir, débannir, supprimer (logiquement), rétablir
 *   planets  planètes et lunes — ressources, champs, débris, éléments, lune
 *   fleets   flottes en vol — rappel, retrait de vaisseaux, suppression
 *
 * Toutes les écritures passent par un POST portant le jeton CSRF, et la page
 * indique toujours ce qu'elle a fait avant de revenir sur l'onglet d'origine :
 * une action d'administration ne doit jamais être silencieuse.
 */
final class PlayerController extends AdminController
{
    /** Adresse de la page. */
    public const PATH = '/back/player';

    /** Onglets de la fiche joueur. */
    public const TABS = array('state', 'planets', 'fleets', 'extra');

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly FleetRepository $fleets = new FleetRepository(),
        private readonly StatsRepository $stats = new StatsRepository(),
        private readonly PlayerAdminService $admin = new PlayerAdminService(),
        private readonly SessionService $sessions = new SessionService(),
        private readonly AclService $acl = new AclService(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.player';
    }

    /** Onglet demandé (`state` par défaut). */
    public static function tabFilter(?string $tab): string
    {
        $key = strtolower(trim((string) $tab));

        return in_array($key, self::TABS, true) ? $key : 'state';
    }

    /** Onglet d'éléments demandé (bâtiments par défaut). */
    public static function kindFilter(?string $kind): string
    {
        $key = strtolower(trim((string) $kind));

        return in_array($key, PlayerAdminService::ELEMENT_TABS, true) ? $key : PlayerAdminService::ELEMENT_TABS[0];
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->loadLang();

        // Une action poste sur la même adresse : le formulaire classique reste le
        // seul chemin, la page fonctionne sans JavaScript.
        if (is_string($request->post('do')) && $request->post('do') !== '') {
            return $this->apply($request);
        }

        $lang = $this->lang();
        $target = $this->findTarget($request->get('player'));

        if ($target === false) {
            return $this->picker((string) $request->get('player'));
        }

        $tab = self::tabFilter($request->get('tab'));
        $tabs = '';

        foreach (self::TABS as $value) {
            $tabs .= $this->adminTemplate('filter_button', $lang + array(
                'filter_label' => (string) ($lang['pal_tab_' . $value] ?? $value),
                'filter_href' => $this->tabUrl($target, $value),
                'filter_active' => $value === $tab ? ' active' : '',
            ));
        }

        $content = match ($tab) {
            'planets' => $this->planetsTab($request, $target, $lang),
            'fleets' => $this->fleetsTab($target, $lang),
            'extra' => $this->extraTab($target, $lang),
            default => $this->stateTab($target, $lang),
        };

        $body = $this->adminPanel('player_body', $lang + array(
            'player_name' => Format::escape((string) $target['username']),
            'player_tabs' => $tabs,
            'player_content' => $content,
        ), (string) ($lang['pal_title'] ?? ''), 'bi-person-gear', '#' . (int) $target['id']);

        return $this->adminPage($body, (string) ($lang['pal_title'] ?? 'Administration'));
    }

    // ------------------------------------------------------------- onglet état

    /** Fiche du compte : ses données, puis les actions qui le concernent. */
    private function stateTab(array $target, array $lang): string
    {
        $points = $this->stats->findUserStatRow((int) $target['id']) ?: array();
        $banned = (int) ($target['bana'] ?? 0) === 1;
        $deleted = Flags::isDeleted((int) ($target['flags'] ?? 0));
        $state = $deleted ? 'deleted' : ($banned ? 'banned' : 'active');

        $rows = '';

        foreach (
            array(
                'pal_id' => (string) (int) $target['id'],
                'pal_name' => Format::text((string) $target['username']),
                'pal_email' => Format::text((string) $target['email']),
                'pal_level' => (string) (int) ($target['authlevel'] ?? 0),
                'pal_registered' => gmdate('d/m/Y H:i', (int) ($target['register_time'] ?? 0)),
                'pal_last_seen' => gmdate('d/m/Y H:i', (int) ($target['onlinetime'] ?? 0)),
                'pal_ip' => Format::text((string) ($target['user_lastip'] ?? '')),
                'pal_points' => Format::prettyNumber((float) ($points['total_points'] ?? 0)),
                'pal_alliance' => Format::text((string) ($target['ally_name'] ?? '')),
                'pal_vacation' => (int) ($target['vacation_mode'] ?? 0) === 1 ? (string) ($lang['pal_yes'] ?? '') : (string) ($lang['pal_no'] ?? ''),
            ) as $label => $value
        ) {
            $rows .= $this->adminTemplate('player_info_row', array(
                'info_label' => (string) ($lang[$label] ?? $label),
                'info_value' => $value,
            ));
        }

        $until = (int) ($target['banaday'] ?? 0);

        return $this->adminTemplate('player_state', $lang + array(
            'player_info_rows' => $rows,
            'player_action' => self::PATH,
            'player_id' => (int) $target['id'],
            'player_token' => \App\Core\Api\CsrfToken::token(),
            'player_return_tab' => 'state',
            'pal_state_label' => (string) ($lang['pal_state_' . $state] ?? $state),
            'pal_state_class' => $deleted ? 'badge text-bg-dark' : ($banned ? 'badge text-bg-danger' : 'badge text-bg-success'),
            'pal_ban_until' => $until > 0 ? gmdate('d/m/Y H:i', $until) : '',
            'pal_ban_until_class' => $banned ? '' : ' d-none',
            'pal_ban_form_class' => $banned ? ' d-none' : '',
            'pal_unban_form_class' => $banned ? '' : ' d-none',
            'pal_delete_form_class' => $deleted ? ' d-none' : '',
            'pal_restore_form_class' => $deleted ? '' : ' d-none',
            'pal_role_card' => $this->roleCard($target, $lang),
        ));
    }

    /**
     * Rendu du rôle du compte : le rôle qui décide de ses accès au panneau.
     *
     * Le compte d'installation (identifiant 1) est super administrateur **par
     * définition** : sa rendu ne propose rien, elle l'annonce (et `AclService`
     * refuserait de toute façon l'écriture).
     *
     * @param array<string, mixed> $target
     */
    private function roleCard(array $target, array $lang): string
    {
        $superAdmin = Acl::isSuperAdmin($target);
        $options = '';

        foreach ($this->acl->roles() as $role) {
            $options .= $this->adminTemplate('role_option', array(
                'role_option_id' => (int) $role['id'],
                'role_option_label' => (string) $role['label'],
                'role_option_selected' => (int) $role['id'] === (int) ($target['role_id'] ?? 0) ? ' selected' : '',
            ));
        }

        return $this->adminTemplate('player_role', $lang + array(
            'player_action' => self::PATH,
            'player_id' => (int) $target['id'],
            'player_token' => \App\Core\Api\CsrfToken::token(),
            'player_return_tab' => 'state',
            'pal_role_options' => $options,
            'pal_role_none_selected' => (int) ($target['role_id'] ?? 0) === 0 ? ' selected' : '',
            // Le compte d'installation : on annonce, on ne propose pas.
            'pal_role_super_class' => $superAdmin ? '' : ' d-none',
            'pal_role_form_class' => $superAdmin ? ' d-none' : '',
        ));
    }

    // ---------------------------------------------------------- onglet planètes

    /** Planètes et lunes, puis les formulaires d'intervention et les éléments. */
    private function planetsTab(Request $request, array $target, array $lang): string
    {
        $resource = GameData::resource();
        $tech = is_array($lang['tech'] ?? null) ? (array) $lang['tech'] : array();
        $planets = $this->planets->findAllByOwner((int) $target['id']);

        $rows = '';
        $count = 0;

        foreach ($planets as $planet) {
            $count++;
            $isMoon = (int) ($planet['planet_type'] ?? 1) === 3;

            $rows .= $this->adminTemplate('player_planet_row', $lang + array(
                'planet_id' => (int) $planet['id'],
                'planet_name' => Format::text((string) $planet['name']),
                'planet_coords' => '[' . (int) $planet['galaxy'] . ':' . (int) $planet['system'] . ':' . (int) $planet['planet'] . ']',
                'planet_type' => (string) ($lang[$isMoon ? 'pal_moon' : 'pal_planet'] ?? ''),
                'planet_fields' => (int) ($planet['field_current'] ?? 0) . ' / ' . (int) ($planet['field_max'] ?? 0),
                'planet_metal' => Format::prettyNumber((float) ($planet['metal'] ?? 0)),
                'planet_crystal' => Format::prettyNumber((float) ($planet['crystal'] ?? 0)),
                'planet_deuterium' => Format::prettyNumber((float) ($planet['deuterium'] ?? 0)),
            ));
        }

        $elements = $this->elementsCard($request, $target, $lang, $planets);

        return $this->adminTemplate('player_planets', $lang + array(
            'player_action' => self::PATH,
            'player_id' => (int) $target['id'],
            'player_token' => \App\Core\Api\CsrfToken::token(),
            'player_return_tab' => 'planets',
            'planet_rows' => $rows,
            'planet_options' => $this->planetOptions($planets, $lang, $count > 0 ? (int) $planets[0]['id'] : 0),
            'element_card' => $elements,
            'planet_empty_class' => $count === 0 ? '' : ' d-none',
            'planet_forms_class' => $count === 0 ? ' d-none' : '',
        ));
    }

    // ------------------------------------------------- onglet gestion supplémentaire

    /**
     * Gestion supplémentaire : ce qui touche la position elle-même — poser ou
     * détruire une lune, et déplacer la planète (avec sa lune) vers une autre
     * position.
     */
    private function extraTab(array $target, array $lang): string
    {
        $planets = $this->planets->findAllByOwner((int) $target['id']);
        $removedMoons = $this->planets->findDestroyedMoonsByOwner((int) $target['id']);
        $abandoned = $this->planets->findAbandonedPlanetsByOwner((int) $target['id']);
        $worldRows = '';

        foreach ($abandoned as $world) {
            $worldRows .= $this->adminTemplate('player_abandoned_row', $lang + array(
                'player_action' => self::PATH,
                'player_id' => (int) $target['id'],
                'player_token' => CsrfToken::token(),
                'player_return_tab' => 'extra',
                'world_id' => (int) $world['id'],
                'world_name' => Format::text((string) $world['name']),
                'world_position' => '[' . (int) $world['galaxy'] . ':' . (int) $world['system'] . ':' . (int) $world['planet'] . ']',
            ));
        }

        $moonRows = '';

        foreach ($removedMoons as $moon) {
            $moonRows .= $this->adminTemplate('player_moon_row', $lang + array(
                'player_action' => self::PATH,
                'player_id' => (int) $target['id'],
                'player_token' => CsrfToken::token(),
                'player_return_tab' => 'extra',
                'moon_id' => (int) $moon['id'],
                'moon_name' => Format::text((string) $moon['name']),
                'moon_position' => '[' . (int) $moon['galaxy'] . ':' . (int) $moon['system'] . ':' . (int) $moon['planet'] . ']',
            ));
        }

        return $this->adminTemplate('player_extra', $lang + array(
            'player_action' => self::PATH,
            'player_id' => (int) $target['id'],
            'player_token' => CsrfToken::token(),
            'player_return_tab' => 'extra',
            'planet_options' => $this->planetOptions($planets, $lang, $planets === array() ? 0 : (int) $planets[0]['id']),
            'extra_empty_class' => $planets === array() ? '' : ' d-none',
            'extra_forms_class' => $planets === array() ? ' d-none' : '',
            'player_moon_rows' => $moonRows,
            'player_world_rows' => $worldRows,
            // Blocs facultatifs par une classe, jamais par un `if` dans le gabarit.
            'player_worlds_class' => $abandoned === array() ? ' d-none' : '',
            'pal_world_empty_class' => $abandoned === array() ? '' : ' d-none',
            // Le rendu des lunes détruites n'apparaît que s'il y en a (bloc facultatif
            // par une classe, jamais par un `if` dans le gabarit).
            'player_moons_class' => $removedMoons === array() ? ' d-none' : '',
            'pal_moon_empty_class' => $removedMoons === array() ? '' : ' d-none',
        ));
    }

    /**
     * Gestion des éléments : quatre onglets (bâtiments, recherches, vaisseaux,
     * défenses), un tableau par onglet, un seul bouton de validation.
     *
     * Chaque ligne porte la valeur actuelle et une case de **variation** : le signe
     * dit le sens (négatif = retrait, positif = ajout, vide ou zéro = rien), ce qui
     * évite deux formulaires et le choix d'une opération.
     */
    private function elementsCard(Request $request, array $target, array $lang, array $planets): string
    {
        $kind = self::kindFilter($request->get('kind'));
        $isTech = $kind === PlayerAdminService::TECH_CATEGORY;
        $planet = $this->elementPlanet($request, $planets);
        $user = $this->users->findFullById((int) $target['id']) ?: array();
        $values = $isTech ? $user : ($planet === false ? array() : $planet);
        $locked = $isTech ? $this->lockedTechs($user) : array();
        $tabs = '';
        $rows = '';

        foreach (PlayerAdminService::ELEMENT_TABS as $value) {
            $tabs .= $this->adminTemplate('filter_button', $lang + array(
                'filter_label' => (string) ($lang['pal_el_tab_' . $value] ?? $value),
                'filter_href' => $this->elementsUrl($target, $value, $planet),
                'filter_active' => $value === $kind ? ' active' : '',
            ));
        }

        foreach (PlayerAdminService::elementRows(GameData::resList(), GameData::resource(), (array) ($lang['tech'] ?? array()), $kind, $values) as $element) {
            $isLocked = isset($locked[$element['id']]);

            $rows .= $this->adminTemplate('player_element_row', array(
                'el_id' => (int) $element['id'],
                'el_name' => Format::text((string) $element['label']),
                'el_value' => Format::prettyNumber((float) $element['value']),
                'el_input_class' => $isLocked ? ' d-none' : '',
                'el_lock_class' => $isLocked ? '' : ' d-none',
                'el_lock_label' => (string) ($lang['pal_el_locked'] ?? ''),
            ));
        }

        return $this->adminTemplate('player_elements', $lang + array(
            'player_action' => self::PATH,
            'player_id' => (int) $target['id'],
            'player_token' => CsrfToken::token(),
            'player_return_tab' => 'planets',
            'planet_options' => $this->planetOptions($planets, $lang, $planet === false ? 0 : (int) $planet['id']),
            'element_planet_value' => (string) ($planet === false ? 0 : (int) $planet['id']),
            'element_tabs' => $tabs,
            'element_rows' => $rows,
            'element_kind' => $kind,
            'element_planet_class' => $isTech ? ' d-none' : '',
            'element_empty_class' => $rows === '' ? '' : ' d-none',
        ));
    }

    /**
     * Planète visée par la gestion des éléments : celle demandée si elle appartient
     * au joueur, sinon la première de sa liste.
     *
     * @param list<array<string, mixed>> $planets
     */
    private function elementPlanet(Request $request, array $planets): array|false
    {
        $wanted = (int) $request->get('planet');

        foreach ($planets as $planet) {
            if ($wanted > 0 && (int) $planet['id'] === $wanted) {
                return $planet;
            }
        }

        return $planets === array() ? false : $planets[0];
    }

    /**
     * Options de la liste des positions d'un joueur : la position affichée est
     * sélectionnée, les autres restent proposées.
     */
    private function planetOptions(array $planets, array $lang, int $selected = 0): string
    {
        $options = '';

        foreach ($planets as $planet) {
            $isMoon = (int) ($planet['planet_type'] ?? 1) === 3;

            $options .= $this->adminTemplate('planet_option', array(
                'option_name' => (string) (int) $planet['id'],
                'option_selected' => (int) $planet['id'] === $selected ? ' selected' : '',
                'option_label' => Format::text((string) $planet['name']) . ' '
                    . (string) ($lang[$isMoon ? 'pal_moon' : 'pal_planet'] ?? '') . ' ['
                    . (int) $planet['galaxy'] . ':' . (int) $planet['system'] . ':' . (int) $planet['planet'] . ']',
            ));
        }

        return $options;
    }

    /** Adresse d'un onglet d'éléments : la planète choisie est conservée. */
    private function elementsUrl(array $target, string $kind, array|false $planet): string
    {
        $query = array('player' => (string) (int) $target['id'], 'tab' => 'planets', 'kind' => $kind);

        if ($planet !== false && $kind !== PlayerAdminService::TECH_CATEGORY) {
            $query['planet'] = (string) (int) $planet['id'];
        }

        return self::PATH . '?' . str_replace('&', '&amp;', http_build_query($query));
    }

    /**
     * Recherches en cours ou en file : leur niveau n'est pas modifiable ici, la
     * file porte le niveau visé et une écriture à la main la ferait mentir.
     *
     * @return array<int, bool>
     */
    private function lockedTechs(array $user): array
    {
        if ($user === array()) {
            return array();
        }

        $queue = new QueueService();
        $planet = $this->planets->findCurrentById($queue->researchPlanetId($user));
        $locked = array();

        foreach ($queue->researchEntries($user, $planet === false ? null : $planet) as $entry) {
            $locked[(int) $entry['element']] = true;
        }

        return $locked;
    }

    // ----------------------------------------------------------- onglet flottes

    /** Flottes en vol du joueur, avec leur composition et les interventions. */
    private function fleetsTab(array $target, array $lang): string
    {
        $resource = GameData::resource();
        $tech = is_array($lang['tech'] ?? null) ? (array) $lang['tech'] : array();
        $fleets = $this->fleets->findByOwner((int) $target['id']);
        $removed = $this->fleets->findDeletedByOwner((int) $target['id']);

        $rows = '';
        $count = 0;

        foreach ($fleets as $fleet) {
            $count++;
            $units = '';
            $unitOptions = '';

            foreach (FlyingFleetService::parseUnits((string) $fleet['fleet_array']) as $unitId => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $column = (string) ($resource[$unitId] ?? '');
                $label = (string) ($tech[$unitId] ?? $column);
                $label = $label !== '' ? $label : (string) $unitId;

                $units .= Format::text($label) . ' x' . Format::prettyNumber((float) $quantity) . ', ';
                $unitOptions .= $this->adminTemplate('datalist_option', array(
                    'option_name' => (string) (int) $unitId,
                    'option_label' => Format::text($label) . ' (' . (int) $quantity . ')',
                ));
            }

            $rows .= $this->adminTemplate('player_fleet', $lang + array(
                'player_action' => self::PATH,
                'player_id' => (int) $target['id'],
                'player_token' => CsrfToken::token(),
                'player_return_tab' => 'fleets',
                'fleet_id' => (int) $fleet['fleet_id'],
                'fleet_mission' => (string) ($lang['type_mission'][(int) $fleet['fleet_mission']] ?? (int) $fleet['fleet_mission']),
                'fleet_from' => '[' . (int) $fleet['fleet_start_galaxy'] . ':' . (int) $fleet['fleet_start_system'] . ':' . (int) $fleet['fleet_start_planet'] . ']',
                'fleet_to' => '[' . (int) $fleet['fleet_end_galaxy'] . ':' . (int) $fleet['fleet_end_system'] . ':' . (int) $fleet['fleet_end_planet'] . ']',
                'fleet_start' => gmdate('d/m/Y H:i', (int) $fleet['fleet_start_time']),
                'fleet_end' => gmdate('d/m/Y H:i', (int) $fleet['fleet_end_time']),
                'fleet_units' => rtrim($units, ', '),
                'fleet_unit_options' => $unitOptions,
                'fleet_recall_class' => (int) ($fleet['fleet_mess'] ?? 0) === 0 ? '' : ' d-none',
            ));
        }

        $removedRows = $this->removedFleetRows($removed, $target, $lang);

        return $this->adminTemplate('player_fleets', $lang + array(
            'player_fleet_rows' => $rows,
            'player_fleet_empty_class' => $count === 0 ? '' : ' d-none',
            'player_fleet_removed_rows' => $removedRows,
            // Un bloc facultatif passe par une classe : le rendu des vols supprimés
            // n'apparaît que s'il y en a.
            'player_fleet_removed_class' => $removed === array() ? ' d-none' : '',
        ));
    }

    /**
     * Lignes des vols supprimés logiquement (le panneau peut les rétablir).
     *
     * @param list<array<string, mixed>> $removed
     */
    private function removedFleetRows(array $removed, array $target, array $lang): string
    {
        $rows = '';

        foreach ($removed as $fleet) {
            $rows .= $this->adminTemplate('player_fleet_removed', $lang + array(
                'player_action' => self::PATH,
                'player_id' => (int) $target['id'],
                'player_token' => CsrfToken::token(),
                'player_return_tab' => 'fleets',
                'fleet_id' => (int) $fleet['fleet_id'],
                'fleet_mission' => (string) ($lang['type_mission'][(int) $fleet['fleet_mission']] ?? (int) $fleet['fleet_mission']),
                'fleet_from' => '[' . (int) $fleet['fleet_start_galaxy'] . ':' . (int) $fleet['fleet_start_system'] . ':' . (int) $fleet['fleet_start_planet'] . ']',
                'fleet_to' => '[' . (int) $fleet['fleet_end_galaxy'] . ':' . (int) $fleet['fleet_end_system'] . ':' . (int) $fleet['fleet_end_planet'] . ']',
                'fleet_end' => gmdate('d/m/Y H:i', (int) $fleet['fleet_end_time']),
            ));
        }

        return $rows;
    }

    // ------------------------------------------------------------------ écritures

    /** Applique une action postée et revient sur l'onglet d'origine. */
    private function apply(Request $request): Response
    {
        $lang = $this->lang();
        $do = (string) $request->post('do');
        $target = $this->findTarget($request->post('player'));
        $tab = self::tabFilter(is_string($request->post('tab')) ? (string) $request->post('tab') : null);

        if ($target === false) {
            return $this->renderMessage((string) ($lang['pal_msg_notfound'] ?? ''), (string) ($lang['pal_title'] ?? ''), self::PATH, 3, 'red');
        }

        if (!\App\Core\Api\CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage((string) ($lang['sys_noaccess'] ?? ''), (string) ($lang['pal_title'] ?? ''), $this->tabUrl($target, $tab), 3, 'red');
        }

        $self = (int) ($this->user()['id'] ?? 0) === (int) $target['id'];

        // Ni sur soi-même (on se priverait de ses propres accès), ni sur le compte
        // d'installation : le rôle ou l'état ne se touchent pas depuis cette page.
        if ($self && in_array($do, array('ban', 'delete', 'role'), true)) {
            return $this->renderMessage((string) ($lang['pal_msg_self'] ?? ''), (string) ($lang['pal_title'] ?? ''), $this->tabUrl($target, $tab), 3, 'red');
        }

        if (PlayerAdminService::isProtected((int) $target['id']) && in_array($do, array('ban', 'delete', 'role'), true)) {
            return $this->renderMessage((string) ($lang['pal_msg_protected'] ?? ''), (string) ($lang['pal_title'] ?? ''), $this->tabUrl($target, $tab), 3, 'red');
        }

        // Le déplacement d'une planète a son propre rendu : un refus (attaque en
        // cours, flotte posée, position occupée) se dit en rouge, il ne passe donc
        // pas par le rendu des actions, qui fête toujours une écriture.
        if ($do === 'planet_move') {
            return $this->movePlanetAction($request, $target, $lang, $tab);
        }

        // Le rôle a le sien aussi : un refus (compte d'installation, rôle inconnu)
        // doit se dire, pas se fêter en vert.
        if ($do === 'role') {
            return $this->roleAction($request, $target, $lang, $tab);
        }

        $message = $this->run($do, $request, $target, $lang);
        $done = $message !== '';

        return $this->renderMessage(
            $done ? $message : (string) ($lang['pal_msg_nothing'] ?? ''),
            (string) ($lang['pal_title'] ?? ''),
            $this->tabUrl($target, $tab),
            3,
            $done ? 'lime' : 'red'
        );
    }

    /**
     * Attribue un rôle au compte.
     *
     * La règle vit dans `AclService::assign()` (le compte d'installation est super
     * administrateur par définition) : ici, on dit seulement ce qui s'est passé.
     *
     * @param array<string, mixed> $target
     * @param array<string, mixed> $lang
     */
    private function roleAction(Request $request, array $target, array $lang, string $tab): Response
    {
        $back = $this->tabUrl($target, $tab);
        // L'identifiant du rôle vient d'un `<select>` : une valeur non scalaire (URL
        // bricolée) vaut « aucun rôle », et `AclService` refuse le reste.
        $posted = $request->post('role_id');
        $roleId = is_scalar($posted) ? max(0, (int) $posted) : 0;
        $refusal = $this->acl->assign((int) $target['id'], $roleId);

        if ($refusal !== '') {
            return $this->renderMessage(
                (string) ($lang[$refusal] ?? ''),
                (string) ($lang['pal_title'] ?? ''),
                $back,
                6,
                'red'
            );
        }

        $label = $roleId > 0 ? (string) ($this->acl->find($roleId)['label'] ?? '') : (string) ($lang['pal_role_none'] ?? '');

        return $this->renderMessage(
            sprintf((string) ($lang['pal_msg_role'] ?? ''), Format::escape((string) $target['username']), $label),
            (string) ($lang['pal_title'] ?? ''),
            $back,
            3,
            'lime'
        );
    }

    /**
     * Déplace une planète (et sa lune) vers une autre position.
     *
     * Le refus est dit, pas subi : la règle du service renvoie un motif
     * (`attack`, `parked`, `occupied`…) que la page traduit, pour que
     * l'administrateur sache quoi corriger avant de réessayer.
     */
    private function movePlanetAction(Request $request, array $target, array $lang, string $tab): Response
    {
        $back = $this->tabUrl($target, $tab);
        $planet = $this->planetOrNull((int) $request->post('planet'));

        if ($planet === false) {
            return $this->renderMessage((string) ($lang['pal_msg_notfound'] ?? ''), (string) ($lang['pal_title'] ?? ''), $back, 3, 'red');
        }

        $galaxy = PlayerAdminService::coordinate($this->postString($request, 'galaxy'));
        $system = PlayerAdminService::coordinate($this->postString($request, 'system'));
        $position = PlayerAdminService::coordinate($this->postString($request, 'position'));
        $refusal = $this->admin->movePlanet($planet, $galaxy, $system, $position);

        if ($refusal !== PlayerAdminService::MOVE_OK) {
            return $this->renderMessage(
                sprintf(
                    (string) ($lang['pal_msg_move_refused'] ?? '%s'),
                    (string) ($lang['pal_move_' . $refusal] ?? $refusal)
                ),
                (string) ($lang['pal_title'] ?? ''),
                $back,
                3,
                'red'
            );
        }

        return $this->renderMessage(
            sprintf(
                (string) ($lang['pal_msg_moved'] ?? '%s'),
                Format::escape((string) $planet['name']),
                '[' . $galaxy . ':' . $system . ':' . $position . ']'
            ),
            (string) ($lang['pal_title'] ?? ''),
            $back,
            3,
            'lime'
        );
    }

    /** Champ posté, lu comme texte (les coordonnées arrivent d'un formulaire). */
    private function postString(Request $request, string $field): ?string
    {
        $value = $request->post($field);

        return is_string($value) ? $value : null;
    }

    /** Exécute l'action demandée. @return string message affiché, '' si rien n'a été fait */
    private function run(string $do, Request $request, array $target, array $lang): string
    {
        $id = (int) $target['id'];
        $name = (string) $target['username'];

        switch ($do) {
            case 'ban':
                $seconds = BanService::duration(
                    (int) $request->post('days', 0),
                    (int) $request->post('hours', 0),
                    (int) $request->post('minutes', 0),
                    (int) $request->post('seconds', 0)
                );

                if ($seconds <= 0) {
                    return '';
                }

                $until = $this->admin->ban(
                    $target,
                    mb_substr(trim((string) $request->post('reason', '')), 0, PlayerAdminService::MAX_REASON),
                    $seconds,
                    $this->user(),
                    $lang
                );

                return sprintf((string) ($lang['pal_msg_banned'] ?? ''), Format::escape($name), gmdate('d/m/Y H:i', $until));

            case 'unban':
                $this->admin->unban($name);

                return sprintf((string) ($lang['pal_msg_unbanned'] ?? ''), Format::escape($name));

            case 'delete':
                $this->admin->softDelete($id);

                return sprintf((string) ($lang['pal_msg_deleted'] ?? ''), Format::escape($name));

            case 'restore':
                $this->admin->restore($id);

                return sprintf((string) ($lang['pal_msg_restored'] ?? ''), Format::escape($name));

            case 'resources':
                $planet = $this->planetOrNull((int) $request->post('planet'));

                if ($planet === false) {
                    return '';
                }

                // « Vider » est une action à part : elle met les trois ressources à
                // zéro, ce qu'un montant négatif ne peut pas garantir (stock partiel).
                $changed = $request->post('empty_resources') !== null
                    ? $this->admin->clearResources($planet)
                    : $this->admin->changeResources(
                        $planet,
                        PlayerAdminService::amount($request->post('metal')),
                        PlayerAdminService::amount($request->post('crystal')),
                        PlayerAdminService::amount($request->post('deuterium'))
                    );

                if (is_string($request->post('field_max')) && trim((string) $request->post('field_max')) !== '') {
                    $changed = $this->admin->setFields($planet, PlayerAdminService::quantity($request->post('field_max'), PlayerAdminService::MAX_FIELDS)) || $changed;
                }

                $diameter = PlayerAdminService::diameter(is_string($request->post('diameter')) ? (string) $request->post('diameter') : null);

                if ($diameter > 0) {
                    $changed = $this->admin->setDiameter($planet, $diameter) || $changed;
                }

                $name = is_string($request->post('planet_name')) ? (string) $request->post('planet_name') : '';

                if (trim($name) !== '') {
                    $changed = $this->admin->rename($planet, $name) || $changed;
                }

                if ($request->post('clear_debris') !== null) {
                    $changed = $this->admin->clearDebris($planet) || $changed;
                }

                return $changed ? sprintf((string) ($lang['pal_msg_planet'] ?? ''), Format::escape((string) $planet['name'])) : '';

            case 'element':
                $kind = self::kindFilter(is_string($request->post('kind')) ? (string) $request->post('kind') : null);
                $deltas = PlayerAdminService::deltas($request->post('delta'));

                if ($deltas === array()) {
                    return '';
                }

                if ($kind === PlayerAdminService::TECH_CATEGORY) {
                    // Les recherches de la file gardent la main : on ne touche pas
                    // au niveau d'une recherche en cours.
                    $row = $this->users->findFullById($id) ?: false;

                    if ($row === false) {
                        return '';
                    }

                    foreach (array_keys($this->lockedTechs($row)) as $lockedId) {
                        unset($deltas[$lockedId]);
                    }
                } else {
                    $row = $this->planetOrNull((int) $request->post('planet'));
                }

                if ($row === false) {
                    return '';
                }

                $changed = $this->admin->changeElements($row, $kind, $deltas);

                return $changed > 0 ? sprintf((string) ($lang['pal_msg_elements'] ?? ''), $changed) : '';

            case 'moon_add':
                $planet = $this->planetOrNull((int) $request->post('planet'));

                if ($planet === false || !$this->admin->addMoon($planet, (string) $request->post('moon_name', ''), $lang)) {
                    return '';
                }

                return sprintf((string) ($lang['pal_msg_moon_added'] ?? ''), Format::escape((string) $planet['name']));

            case 'moon_remove':
                $planet = $this->planetOrNull((int) $request->post('planet'));

                if ($planet === false || !$this->admin->deleteMoon($planet)) {
                    return '';
                }

                return sprintf((string) ($lang['pal_msg_moon_removed'] ?? ''), Format::escape((string) $planet['name']));

            case 'planet_restore':
                $planet = $this->abandonedPlanetOrNull($target, (int) $request->post('world'));

                if ($planet === false) {
                    return '';
                }

                $raison = $this->admin->restorePlanet($planet);

                if ($raison !== '') {
                    return (string) ($lang['pal_msg_planet_restore_' . $raison] ?? '');
                }

                return sprintf((string) ($lang['pal_msg_planet_restored'] ?? ''), Format::escape((string) $planet['name']));

            case 'moon_restore':
                $moon = $this->destroyedMoonOrNull($target, (int) $request->post('moon'));

                if ($moon === false) {
                    return '';
                }

                $raison = $this->admin->restoreMoon($moon);

                if ($raison !== '') {
                    return (string) ($lang['pal_msg_moon_restore_' . $raison] ?? '');
                }

                return sprintf((string) ($lang['pal_msg_moon_restored'] ?? ''), Format::escape((string) $moon['name']));

            case 'fleet_recall':
                $fleet = $this->fleetOrNull((int) $request->post('fleet'));

                if ($fleet === false || !$this->admin->recall($fleet, $id)) {
                    return '';
                }

                return sprintf((string) ($lang['pal_msg_recall'] ?? ''), (int) $fleet['fleet_id']);

            case 'fleet_units':
                $fleet = $this->fleetOrNull((int) $request->post('fleet'));

                if ($fleet === false) {
                    return '';
                }

                $quantity = PlayerAdminService::quantity($request->post('quantity'));

                if ($quantity <= 0 || !$this->admin->removeFleetUnits($fleet, array((int) $request->post('unit') => $quantity))) {
                    return '';
                }

                return sprintf((string) ($lang['pal_msg_units'] ?? ''), (int) $fleet['fleet_id']);

            case 'fleet_delete':
                $fleet = $this->fleetOrNull((int) $request->post('fleet'));

                if ($fleet === false) {
                    return '';
                }

                $this->admin->deleteFleet($fleet);

                return sprintf((string) ($lang['pal_msg_fleet_deleted'] ?? ''), (int) $fleet['fleet_id']);

            case 'fleet_restore':
                $fleet = $this->fleetOrNull((int) $request->post('fleet'));

                if ($fleet === false) {
                    return '';
                }

                $this->admin->restoreFleet($fleet);

                return sprintf((string) ($lang['pal_msg_fleet_restored'] ?? ''), (int) $fleet['fleet_id']);
        }

        return '';
    }

    // ------------------------------------------------------------------- lecture

    /** Sélection du joueur : par identifiant, sinon par pseudo. */
    private function findTarget(mixed $player): array|false
    {
        if (!is_string($player) || trim($player) === '') {
            return false;
        }

        $player = trim($player);

        if (ctype_digit($player)) {
            $found = $this->users->findFullById((int) $player);

            return $found === false ? false : $found;
        }

        $found = $this->users->findByUsername($player);

        return $found === false ? false : $found;
    }

    /** Planète (ou lune) visée, à partir de l'identifiant posté. */
    private function planetOrNull(int $planetId): array|false
    {
        return $planetId > 0 ? $this->planets->findCurrentById($planetId) : false;
    }

    /** Flotte visée, à partir de l'identifiant posté. */
    private function fleetOrNull(int $fleetId): array|false
    {
        return $fleetId > 0 ? $this->fleets->findById($fleetId) : false;
    }

    /**
     * Colonie abandonnée visée : la ligne existe encore, elle doit appartenir au
     * compte affiché et porter le drapeau `DELETED`.
     */
    private function abandonedPlanetOrNull(array $target, int $planetId): array|false
    {
        if ($planetId <= 0) {
            return false;
        }

        $planet = $this->planets->findCurrentById($planetId);

        if ($planet === false || (int) ($planet['planet_type'] ?? 0) !== 1) {
            return false;
        }

        if ((int) ($planet['id_owner'] ?? 0) !== (int) $target['id']) {
            return false;
        }

        return Flags::isDeleted((int) ($planet['flags'] ?? 0)) ? $planet : false;
    }

    /**
     * Lune détruite visée : la ligne existe encore, mais elle doit appartenir au
     * compte affiché et porter le drapeau `DELETED` (sinon, rien à rétablir).
     */
    private function destroyedMoonOrNull(array $target, int $moonId): array|false
    {
        if ($moonId <= 0) {
            return false;
        }

        $moon = $this->planets->findCurrentById($moonId);

        if ($moon === false || (int) ($moon['planet_type'] ?? 0) !== 3) {
            return false;
        }

        if ((int) ($moon['id_owner'] ?? 0) !== (int) $target['id']) {
            return false;
        }

        return Flags::isDeleted((int) ($moon['flags'] ?? 0)) ? $moon : false;
    }

    /** Saisie du joueur, quand aucun n'est encore choisi. */
    private function picker(string $player): Response
    {
        $lang = $this->lang();
        $options = '';

        foreach ($this->sessions->accounts() as $account) {
            $options .= $this->adminTemplate('datalist_option', array(
                'option_name' => Format::text((string) $account['username']),
                'option_label' => Format::text((string) $account['username']),
            ));
        }

        // Chiffres de l'univers : de quoi situer le compte que l'on cherche. Le dépôt est
        // **résolu** — un module qui possède la colonne des robots le dérive, et la tuile
        // se compte alors sur sa colonne.
        $counts = ModuleService::instance(StatsRepository::class)->universeCounts(time() - 900);
        $stats = '';

        foreach (
            array(
                'pal_stat_accounts' => $counts['accounts'],
                'pal_stat_online' => $counts['online'],
                'pal_stat_active' => $counts['active'],
                'pal_stat_inactive' => $counts['inactive'],
                'pal_stat_banned' => $counts['banned'],
                'pal_stat_removed' => $counts['removed'],
                'pal_stat_bots' => $counts['bots'],
                'pal_stat_planets' => $counts['planets'],
                'pal_stat_moons' => $counts['moons'],
                'pal_stat_fleets' => $counts['fleets'],
                'pal_stat_fields' => $counts['fields'],
                'pal_stat_metal' => $counts['metal'],
                'pal_stat_crystal' => $counts['crystal'],
                'pal_stat_deuterium' => $counts['deuterium'],
            ) as $label => $value
        ) {
            $stats .= $this->adminTemplate('player_stat', array(
                'stat_label' => (string) ($lang[$label] ?? $label),
                'stat_value' => Format::prettyNumber((float) $value),
                // Sans le module des robots, la tuile disparaît : il n'y a plus de
                // population à compter (le dépôt renvoie déjà 0).
                'stat_class' => $label === 'pal_stat_bots' && !BotService::present() ? ' d-none' : '',
            ));
        }

        $body = $this->adminPanel('player_pick', $lang + array(
            'player_action' => self::PATH,
            'player_value' => Format::text($player),
            'player_options' => $options,
            'player_stats' => $stats,
        ), (string) ($lang['pal_title'] ?? ''), 'bi-person-gear');

        return $this->adminPage($body, (string) ($lang['pal_title'] ?? 'Administration'));
    }

    /** Adresse d'un onglet pour un compte donné. */
    private function tabUrl(array $target, string $tab): string
    {
        return self::PATH . '?' . str_replace('&', '&amp;', http_build_query(array(
            'player' => (string) $target['username'],
            'tab' => $tab,
        )));
    }

    /** Langues de la fiche : l'administration, la fiche elle-même et les noms d'éléments. */
    private function loadLang(): void
    {
        $this->includeLang('admin');
        $this->includeLang('admin/player');
        $this->includeLang('tech');
    }
}
