<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Acl;
use App\Core\Api\CsrfToken;
use App\Core\Language;
use App\Core\Request;
use App\Core\Response;
use App\Services\AclService;

/**
 * Panneau d'administration : les rôles et leurs permissions.
 *
 * L'accès aux pages du panneau ne se décide plus par un niveau unique
 * (`users.authlevel`) mais par un **rôle** : un libellé et une liste de
 * permissions cochées (`App\Core\Acl`). Cette page crée les rôles, règle leurs
 * permissions et les supprime — la suppression est **logique** (comme un message,
 * une note ou un vol) : un rôle supprimé quitte la liste et ne donne plus aucun
 * droit, et la page le rétablit.
 *
 * Deux garde-fous, portés par `AclService` : un rôle par défaut se modifie mais ne
 * se supprime pas (l'installation doit toujours garder un administrateur), et un
 * rôle porté par des comptes non plus (il faut d'abord leur en donner un autre).
 * Le compte d'installation (identifiant 1), lui, est super administrateur par
 * définition : son rôle ne se change pas.
 */
final class RolesController extends AdminController
{
    /** Adresse de la page, reprise par les filtres et les liens. */
    private const PATH = '/back/roles';

    public function __construct(
        private readonly AclService $acl = new AclService(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.roles';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('admin/roles');

        // Les boutons d'une ligne envoient leur propre champ (`delid`, `restid`,
        // `moveup`, `movedown`) : c'est aussi ce qui distingue une écriture d'un
        // simple affichage de la page.
        if (
            $request->post('do') !== null
            || is_numeric($request->post('delid'))
            || is_numeric($request->post('restid'))
            || is_numeric($request->post('moveup'))
            || is_numeric($request->post('movedown'))
        ) {
            return $this->apply($request);
        }

        return $this->page($request);
    }

    /** Affichage : la liste des rôles, et le détail de celui qui est ouvert. */
    private function page(Request $request): Response
    {
        $lang = $this->lang();
        $actor = $this->user();
        $deleted = self::stateFilter($request->get('state'));
        $openId = (int) ($request->get('id') ?? 0);

        $roles = $this->acl->roles($deleted);
        $last = count($roles) - 1;
        $rows = '';

        foreach ($roles as $index => $role) {
            $id = (int) $role['id'];
            $name = (string) $role['name'];
            $permissions = Acl::parse((string) $role['permissions']);
            $isDefault = AclService::isDefault($name);
            // Un rôle n'est modifiable que s'il est **sous** celui du compte : le
            // rôle « tous les droits » et le compte d'installation gèrent tout.
            $manageable = !$deleted && $this->acl->canManage($actor, $role);

            $rows .= $this->adminTemplate('roles_row', $lang + array(
                'role_action' => self::PATH,
                'role_row_id' => $id,
                'role_row_label' => (string) $role['label'],
                'role_row_name' => $name,
                'role_row_description' => (string) $role['description'],
                'role_row_position' => (int) $role['position'],
                'role_row_users' => (int) ($role['users'] ?? 0),
                'role_row_permissions' => in_array(Acl::ALL, $permissions, true)
                    ? (string) ($lang['acl_all_short'] ?? '')
                    : (string) count($permissions),
                'role_row_class' => $deleted ? ' table-warning' : '',
                // Un rôle par défaut ne se supprime pas ; un rôle porté par des
                // comptes non plus, ni un rôle au-dessus du sien : la classe
                // explique pourquoi le bouton manque.
                'role_row_del_class' => ($deleted || $isDefault || (int) ($role['users'] ?? 0) > 0 || !$manageable) ? ' d-none' : '',
                'role_row_rest_class' => ($deleted && $this->acl->canManage($actor, $role)) ? '' : ' d-none',
                'role_row_edit_class' => $manageable ? '' : ' d-none',
                // Les flèches n'existent que dans la vue des rôles existants, sur un
                // rôle qu'on gère, et seulement si le voisin visé est gérable lui
                // aussi : monter jusqu'à la place de son propre rôle est refusé, et
                // le bouton dit la même chose que le service (la liste est déjà triée
                // du plus haut au plus bas, le voisin est l'entrée précédente ou
                // suivante).
                'role_row_up_class' => ($manageable && $index > 0 && $this->acl->canManage($actor, $roles[$index - 1])) ? '' : ' d-none',
                'role_row_down_class' => ($manageable && $index < $last && $this->acl->canManage($actor, $roles[$index + 1])) ? '' : ' d-none',
                'role_row_above_class' => (!$deleted && !$manageable) ? '' : ' d-none',
                'role_row_default_class' => (!$deleted && $isDefault) ? '' : ' d-none',
                'role_row_used_class' => (!$deleted && !$isDefault && (int) ($role['users'] ?? 0) > 0) ? '' : ' d-none',
            ));
        }

        $edit = $openId > 0 ? $this->acl->find($openId) : false;
        // Un rôle supprimé ne se modifie pas : il se rétablit d'abord. Et un rôle
        // placé au-dessus du sien s'affiche en lecture seule.
        $editing = $edit !== false && !$deleted ? $edit : false;

        $body = $this->adminPanel('roles_body', $lang + array(
            'page_url' => self::PATH,
            'state' => self::stateKey($deleted),
            'csrf_token' => CsrfToken::token(),
            'role_states' => $this->stateButtons(self::PATH, $lang, $deleted, 'acl_state_live', 'acl_state_deleted'),
            'role_rows' => $rows,
            'role_rows_empty_class' => $rows === '' ? '' : ' d-none',
            'role_live_class' => $deleted ? ' d-none' : '',
            'role_restore_selection_class' => $deleted ? '' : ' d-none',
            'role_form' => $this->form($editing, $editing !== false && $this->acl->canManage($actor, $editing)),
        ), (string) ($lang['acl_title'] ?? ''), 'bi-shield-lock', (string) count($roles));

        return $this->adminPage($body, (string) ($lang['acl_title'] ?? 'Administration'));
    }

    /**
     * Formulaire du rôle ouvert, ou formulaire vide pour en créer un.
     *
     * `$manageable` vaut faux pour un rôle placé au-dessus du sien : le formulaire
     * s'affiche alors en lecture seule (champs désactivés, pas de bouton).
     *
     * @param array<string, mixed>|false $role
     */
    private function form(array|false $role, bool $manageable): string
    {
        $lang = $this->lang();
        // Le catalogue contient les permissions des modules : leurs libellés voyagent
        // avec les modules, il faut donc les charger ici aussi.
        Language::includeModules();
        $id = $role === false ? 0 : (int) $role['id'];
        $selected = $role === false ? array() : Acl::parse((string) $role['permissions']);
        $all = in_array(Acl::ALL, $selected, true);

        $groups = '';

        foreach (Acl::grouped() as $group => $permissions) {
            $boxes = '';

            foreach ($permissions as $permission => $label) {
                $boxes .= $this->adminTemplate('roles_permission_row', array(
                    'permission_name' => $permission,
                    // Un libellé vient du menu d'administration (`leftmenu.mo`, déjà
                    // chargé par la garde) ; la clé elle-même sert de repli.
                    'permission_label' => (string) ($lang[$label] ?? $label),
                    'permission_page' => Acl::page($permission),
                    'permission_checked' => in_array($permission, $selected, true) ? ' checked' : '',
                    'permission_disabled' => ($all || !$manageable) ? ' disabled' : '',
                ));
            }

            $groups .= $this->adminTemplate('roles_group', array(
                'permission_group_title' => (string) ($lang[Acl::groupLabels()[$group] ?? $group] ?? $group),
                'permission_group_rows' => $boxes,
            ));
        }

        return $this->adminTemplate('roles_form', $lang + array(
            'role_action' => self::PATH,
            'csrf_token' => CsrfToken::token(),
            'role_id' => $id,
            'role_label' => $role === false ? '' : (string) $role['label'],
            'role_description' => $role === false ? '' : (string) $role['description'],
            'role_name' => $role === false ? '' : (string) $role['name'],
            'acl_permission_groups' => $groups,
            'role_form_title' => $id > 0 ? (string) ($lang['acl_edit_title'] ?? '') : (string) ($lang['acl_new_title'] ?? ''),
            // Un rôle « tous les droits » garde sa portée : on l'annonce et les
            // cases restent désactivées (le formulaire ne peut pas la réduire).
            'acl_all_class' => $all ? '' : ' d-none',
            'acl_boxes_class' => ($all || !$manageable) ? ' text-body-secondary' : '',
            // Le nom technique n'existe qu'une fois le rôle créé.
            'acl_name_class' => $id > 0 ? '' : ' d-none',
            // Rôle au-dessus du sien : lecture seule, et le refus est annoncé.
            'role_locked_class' => ($id > 0 && !$manageable) ? '' : ' d-none',
            'role_disabled' => $manageable ? '' : ' disabled',
            'role_save_class' => $manageable ? '' : ' d-none',
        ));
    }

    /** Création, modification, suppression logique et rétablissement. */
    private function apply(Request $request): Response
    {
        $lang = $this->lang();

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage(
                (string) ($lang['acl_bad_token'] ?? ''),
                (string) ($lang['acl_title'] ?? ''),
                self::PATH,
                3,
                'red'
            );
        }

        $actor = $this->user();
        $do = (string) $request->post('do');
        $id = (int) ($request->post('id') ?? 0);
        $up = true;

        // Les boutons d'une ligne envoient leur propre champ (`delid`, `restid`,
        // `moveup`, `movedown`), comme sur la page des notes : un bouton ne porte
        // qu'un couple nom/valeur.
        if (is_numeric($request->post('delid'))) {
            $do = 'delete';
            $id = (int) $request->post('delid');
        } elseif (is_numeric($request->post('restid'))) {
            $do = 'restore';
            $id = (int) $request->post('restid');
        } elseif (is_numeric($request->post('moveup')) || is_numeric($request->post('movedown'))) {
            $do = 'move';
            $up = is_numeric($request->post('moveup'));
            $id = (int) ($up ? $request->post('moveup') : $request->post('movedown'));
        }

        $return = self::PATH . ($id > 0 && $do === 'save' ? '?id=' . $id : '');

        $refusal = match ($do) {
            'save' => $id > 0
                ? $this->acl->save($actor, $id, (string) $request->post('label'), (string) $request->post('description'), $this->posted())
                : $this->acl->create((string) $request->post('label'), (string) $request->post('description'), $this->posted()),
            'delete' => $this->acl->delete($actor, $id),
            'restore' => $this->acl->restore($actor, $id),
            'move' => $this->acl->move($actor, $id, $up),
            default => 'acl_unknown_action',
        };

        // Un refus dit ce qui manque ; sinon la page revient sur la liste (ou sur
        // le rôle qu'on vient d'enregistrer) avec le message de l'action.
        $done = array(
            'save' => 'acl_saved',
            'delete' => 'acl_deleted',
            'restore' => 'acl_restored',
            'move' => 'acl_moved',
        );

        return $this->renderMessage(
            (string) ($lang[$refusal !== '' ? $refusal : ($done[$do] ?? 'acl_saved')] ?? ''),
            (string) ($lang['acl_title'] ?? ''),
            $refusal === '' && $do === 'save' ? $return : self::PATH,
            $refusal === '' ? 3 : 6,
            $refusal === '' ? 'green' : 'red'
        );
    }

    /**
     * Permissions cochées, envoyées en `permissions[]`. Une valeur qui n'est pas
     * une chaîne est ignorée (l'URL bricolée ne doit pas casser la page).
     *
     * @return array<int, string>
     */
    private function posted(): array
    {
        $posted = $_POST['permissions'] ?? array();

        if (!is_array($posted)) {
            return array();
        }

        return array_values(array_filter($posted, 'is_string'));
    }
}
