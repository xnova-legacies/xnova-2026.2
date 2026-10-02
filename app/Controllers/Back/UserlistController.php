<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Acl;
use App\Core\Api\CsrfToken;
use App\Core\Format;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use App\Services\AclService;
use App\Services\PlayerAdminService;

/**
 * Panneau d'administration : liste des comptes.
 *
 * Reprend `admin/userlist.php`. Deux changements de fond :
 * toutes les lignes viennent d'une seule lecture par le dépôt (le tri était
 * injectable), et la suppression se faisait par un lien `?cmd=dele&user=N` —
 * elle passe désormais par un formulaire `POST` avec jeton CSRF.
 *
 * La suppression d'un compte est **logique** : elle passe par
 * `PlayerAdminService::softDelete()` (drapeau `DELETED`, sessions fermées), exactement comme
 * la fiche joueur. Le contrôleur ne fait que vérifier qui et quoi.
 */
final class UserlistController extends AdminController
{
    /** Adresse de la page, reprise par les liens de pagination. */
    private const PATH = '/back/userlist';

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly AclService $acl = new AclService(),
        private readonly PlayerAdminService $admin = new PlayerAdminService(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.userlist';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('admin');

        $lang = $this->lang();

        // Tri par colonne et taille de page : les mêmes règles que le journal.
        $sort = Paginator::sort(
            is_string($request->get('sort')) ? (string) $request->get('sort') : (is_string($request->get('type')) ? (string) $request->get('type') : null),
            is_string($request->get('order')) ? (string) $request->get('order') : null,
            UserRepository::SORTS,
            'id'
        );
        $perPage = Paginator::perPage($request->get('per_page'));
        $labels = $this->paginationLabels((string) ($lang['adm_ul_title'] ?? ''));
        $window = Paginator::window($this->users->countAll(), $request->get('page'), $perPage);
        $head = '';

        foreach (
            array(
            array('id', 'adm_ul_id'),
            array('username', 'adm_ul_name'),
            array('email', 'adm_ul_mail'),
            array('ip_at_reg', 'adm_ul_data_ip_reg'),
            array('user_lastip', 'adm_ul_adip'),
            array('register_time', 'adm_ul_regd'),
            array('onlinetime', 'adm_ul_lconn'),
            array('bana', 'adm_ul_bana'),
            ) as $column
        ) {
            $head .= Paginator::header(
                self::PATH,
                array('per_page' => (string) $perPage),
                $sort,
                $column[0],
                (string) ($lang[$column[1]] ?? $column[0]),
                $labels
            );
        }

        $rows = '';
        $previousIp = '';

        // Libellés des rôles, lus une fois : chaque ligne n'a plus qu'à nommer le sien
        // (aucune requête par compte).
        $roleLabels = array();

        foreach ($this->acl->roles() as $role) {
            $roleLabels[(int) $role['id']] = (string) $role['label'];
        }

        foreach ($this->users->findAllSorted($sort['field'], $window['per_page'], $window['offset'], $sort['order']) as $theUser) {
            $ip = (string) $theUser['user_lastip'];

            // Deux comptes consécutifs partagent une IP : signalé par la règle
            // d'affichage commune, aucun balisage dans le contrôleur.
            $sameIp = $previousIp !== '' && $previousIp === $ip;
            $previousIp = $ip;

            $banned = (int) ($theUser['bana'] ?? 0) === 1;

            $rows .= $this->adminTemplate('userlist_rows', $lang + array(
                'adm_ul_data_id' => (int) $theUser['id'],
                'adm_ul_data_name' => Format::text((string) $theUser['username']),
                'adm_ul_data_mail' => Format::text((string) $theUser['email']),
                'ip_adress_at_register' => Format::text((string) ($theUser['ip_at_reg'] ?? '')),
                'adm_ul_data_adip' => $sameIp ? Format::colorRed($ip) : $ip,
                'adm_ul_data_regd' => gmdate('d/m/Y G:i:s', (int) $theUser['register_time']),
                'adm_ul_data_lconn' => gmdate('d/m/Y G:i:s', (int) $theUser['onlinetime']),
                'adm_ul_data_banna' => $banned
                    ? $lang['adm_ul_yes'] . ' ' . gmdate('d/m/Y', (int) $theUser['banaday'])
                    : $lang['adm_ul_no'],
                'adm_ul_ban_class' => $banned ? 'badge text-bg-danger' : 'badge text-bg-secondary',
                // Le rôle décide des pages du panneau ; le compte d'installation est
                // super administrateur par définition (il n'a pas de rôle à porter).
                'adm_ul_data_role' => Acl::isSuperAdmin($theUser)
                    ? (string) (AclService::DEFAULT_ROLES['super_admin']['label'] ?? '')
                    : (string) ($roleLabels[(int) ($theUser['role_id'] ?? 0)] ?? ($lang['adm_ul_role_none'] ?? '')),
                // La fiche du joueur : ses données, ses planètes, ses flottes.
                'manage_url' => PlayerController::PATH . '?player=' . rawurlencode((string) $theUser['username']),
                'adm_ul_data_actio' => $this->adminTemplate('userlist_action', array(
                    'action_url' => '/back/userlist/delete',
                    'user_id' => (int) $theUser['id'],
                    'csrf_token' => CsrfToken::token(),
                    'delete_label' => $lang['adm_ul_delete'] ?? '',
                    'delete_confirm' => $lang['adm_ul_confirm'] ?? '',
                    // Le compte d'installation ne se supprime pas : le bouton part.
                    'delete_class' => Acl::isSuperAdmin($theUser) ? ' d-none' : '',
                )),
            ));
        }

        $body = $this->adminPanel('userlist_body', $lang + array(
            'adm_ul_table' => $rows,
            'ul_head' => $head,
            'ul_page_bar' => Paginator::sizeBar(self::PATH, array(), $window, $labels, $sort),
            'ul_pagination' => Paginator::render(self::PATH, array(), $window, $labels, $sort),
        ), (string) ($lang['adm_ul_title'] ?? 'Administration'), 'bi-people', $window['total'] . (string) ($lang['adm_ul_playe'] ?? ''));

        return $this->adminPage($body, (string) ($lang['adm_ul_title'] ?? 'Administration'));
    }

    /**
     * Suppression d'un compte (formulaire de la liste).
     *
     * Le compte visé est relu avant l'appel : un identifiant inconnu ne doit pas
     * faire tourner la primitive de suppression sur du vide, et un administrateur
     * ne se supprime pas lui-même depuis cette page.
     */
    public function deleteAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('admin');

        $lang = $this->lang();
        $current = $this->user();

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage(
                (string) ($lang['sys_noaccess'] ?? ''),
                (string) ($lang['sys_noalloaw'] ?? ''),
                '/back/userlist',
                3,
                'red'
            );
        }

        $id = is_numeric($request->post('user')) ? (int) $request->post('user') : 0;
        $target = $id > 0 ? $this->users->findFullById($id) : false;

        if ($target === false) {
            return $this->renderMessage(
                (string) ($lang['adm_ul_notfound'] ?? ''),
                (string) ($lang['adm_ul_title'] ?? ''),
                '/back/userlist',
                3,
                'red'
            );
        }

        if ((int) $target['id'] === (int) ($current['id'] ?? 0)) {
            return $this->renderMessage(
                (string) ($lang['adm_ul_self'] ?? ''),
                (string) ($lang['adm_ul_title'] ?? ''),
                '/back/userlist',
                3,
                'red'
            );
        }

        $this->admin->softDelete($id);

        return $this->renderMessage(
            (string) ($lang['adm_ul_deleted'] ?? '') . ' ' . Format::text((string) $target['username']),
            (string) ($lang['adm_ul_title'] ?? ''),
            '/back/userlist',
            3,
            'lime'
        );
    }
}
