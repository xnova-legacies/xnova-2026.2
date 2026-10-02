<?php

declare(strict_types=1);

namespace App\Controllers\Back;

use App\Core\Api\CsrfToken;
use App\Core\Language;
use App\Core\Modules;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Services\ModuleService;
use App\Services\ModuleInstallService;

/**
 * Panneau d'administration : les modules du jeu, allumés ou éteints.
 *
 * Un module est une fonctionnalité (tchat, notes, marchand, annonces, officiers,
 * records) que l'administration peut éteindre : ses pages et ses routes JSON
 * ferment, son entrée quitte le menu du jeu, et sa permission (`module.<nom>`,
 * groupe « Modules » de la page des rôles) cesse d'ouvrir quoi que ce soit — sauf
 * pour le compte d'installation et les rôles qui portent cette page, qui doivent
 * pouvoir la rallumer.
 *
 * Rien n'est supprimé en base : éteindre un module cache, il ne détruit rien.
 */
final class ModulesController extends AdminController
{
    /** Adresse de la page, reprise par les liens et le retour des messages. */
    private const PATH = '/back/modules';

    public function __construct(
        private readonly ModuleService $modules = new ModuleService(),
    ) {
    }

    protected function requiredPermission(): string
    {
        return 'admin.modules';
    }

    public function indexAction(Request $request): Response
    {
        if (($denied = $this->guard()) !== null) {
            return $denied;
        }

        $this->includeLang('admin/modules');

        // Un bouton de ligne envoie son propre champ (`toggle`), comme les boutons
        // de la page des rôles : c'est aussi ce qui distingue une écriture d'un
        // simple affichage.
        $toggle = $request->post('toggle');

        if (is_string($toggle) && $toggle !== '') {
            return $this->toggle($request, $toggle);
        }

        // Deux gestes symétriques : **désinstaller** (archiver le module) et
        // **réinstaller** (le remettre en place). Rien n'est supprimé.
        foreach (array('archive', 'restore') as $action) {
            $name = $request->post($action);

            if (is_string($name) && $name !== '') {
                return $this->move($request, $action, $name);
            }
        }

        // Téléversement d'un module : l'archive part en quarantaine, elle est analysée,
        // et le rapport s'affiche sur la **même page** — l'installation reste un geste
        // distinct (`install`), jamais un effet de bord de l'envoi.
        if (isset($_FILES['package']) || $request->post('upload') !== null) {
            return $this->upload($request);
        }

        if (is_string($request->post('install')) && is_string($request->post('name'))) {
            return $this->install($request, (string) $request->post('install') === 'force');
        }

        return $this->page($request);
    }

    /** Allumage et extinction : la seule action de la page. */
    private function toggle(Request $request, string $name): Response
    {
        $lang = $this->lang();

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage(
                (string) ($lang['mod_bad_token'] ?? ''),
                (string) ($lang['mod_title'] ?? ''),
                self::PATH,
                3,
                'red'
            );
        }

        if (!Modules::exists($name)) {
            return $this->renderMessage(
                (string) ($lang['mod_notfound'] ?? ''),
                (string) ($lang['mod_title'] ?? ''),
                self::PATH,
                6,
                'red'
            );
        }

        // Le bouton dit ce qu'il fait : l'état bascule, il n'est pas passé en clair.
        $refusal = $this->modules->setActive($name, !$this->modules->isActive($name));

        return $this->renderMessage(
            (string) ($lang[$refusal !== '' ? $refusal : 'mod_saved'] ?? ''),
            (string) ($lang['mod_title'] ?? ''),
            self::PATH,
            $refusal === '' ? 3 : 6,
            $refusal === '' ? 'green' : 'red'
        );
    }

    /**
     * Liste des modules : recherche, filtre d'état, tri par colonne et pagination.
     *
     * Les modules **déposés** et les modules **archivés** partagent la même liste, la
     * même ligne et les mêmes outils : seul l'état décide de ce qui est montré (écrire
     * une seconde page pour les archives aurait dupliqué tout cela).
     */
    private function page(Request $request, array $report = array()): Response
    {
        // Les libellés viennent des modules (`modules/<nom>/language/`) : chargés avant
        // de composer les lignes, sinon le panneau afficherait des clés brutes.
        Language::include('modules');
        Language::includeModules();

        // Les libellés d'un module **archivé** voyagent avec lui, dans `modules/.archive/`,
        // qui n'est plus découvert : ils se chargent à part — et avant de lire les
        // libellés, sinon la liste afficherait la clé du module au lieu de son nom.
        foreach (array_keys(Modules::archived()) as $archivedName) {
            Language::includeArchived((string) $archivedName);
        }

        $lang = $this->lang();
        $filter = ModuleService::listFilter($request->get('state'));
        $search = self::searchTerm($request->get('q'));
        $sort = Paginator::sort(
            is_string($request->get('sort')) ? (string) $request->get('sort') : null,
            is_string($request->get('order')) ? (string) $request->get('order') : null,
            array_keys(ModuleService::SORTS),
            'name'
        );
        $perPage = Paginator::perPage($request->get('per_page'));
        $labels = $this->paginationLabels((string) ($lang['mod_title'] ?? ''));

        // Le filtre, la recherche et le tri passent **avant** la pagination : le tableau
        // ne montre que la page demandée d'une liste déjà rangée.
        $modules = ModuleService::sort($this->modules->listing($filter, $search, $lang), $sort['field'], $sort['order']);
        $window = Paginator::window(count($modules), $request->get('page', '1'), $perPage);

        // Ces filtres doivent survivre dans **chaque** lien de la page (tri, taille de
        // page, pagination) : le Paginator y ajoute les siens (il pose lui-même
        // `per_page`, le tri et le sens, qui ne sont donc pas repris ici).
        $query = array('state' => $filter, 'per_page' => (string) $window['per_page'])
            + ($search === '' ? array() : array('q' => $search));
        $rows = '';

        foreach (array_slice($modules, $window['offset'], $window['per_page']) as $module) {
            $rows .= $this->adminTemplate('modules_row', $lang + $this->row($module, $lang));
        }

        $head = Paginator::header(self::PATH, $query, $sort, 'label', (string) ($lang['mod_hdr_module'] ?? ''), $labels)
            . Paginator::header(self::PATH, $query, $sort, 'description', (string) ($lang['mod_hdr_description'] ?? ''), $labels)
            . Paginator::header(self::PATH, $query, $sort, 'permission', (string) ($lang['mod_hdr_permission'] ?? ''), $labels)
            . Paginator::header(self::PATH, $query, $sort, 'address', (string) ($lang['mod_hdr_page'] ?? ''), $labels)
            . $this->adminTemplate('table_head', array(
                'th_label' => (string) ($lang['mod_hdr_dependencies'] ?? ''),
                'th_class' => '',
            ))
            . $this->adminTemplate('table_head', array(
                'th_label' => (string) ($lang['mod_hdr_state'] ?? ''),
                'th_class' => '',
            ))
            . $this->adminTemplate('table_head', array(
                'th_label' => (string) ($lang['mod_hdr_action'] ?? ''),
                'th_class' => 'text-end',
            ));

        $body = $this->adminPanel('modules_body', $lang + array(
            'module_action' => self::PATH,
            'module_state' => $filter,
            'module_states' => $this->stateLinks($lang, $filter, $search),
            'module_search' => $search,
            'module_per_page' => (string) $window['per_page'],
            'module_head' => $head,
            'module_rows' => $rows,
            'module_rows_empty_class' => $rows === '' ? '' : ' d-none',
            // Sans filtre ni recherche, une liste vide veut dire « rien de déposé » ; un
            // filtre vide, « rien dans cette liste » ; une recherche, « rien qui corresponde ».
            'module_rows_empty_label' => (string) ($lang[$search !== ''
                ? 'mod_no_match'
                : ($filter === ModuleService::FILTER_DEFAULT ? 'mod_no_module' : 'mod_no_filter')] ?? ''),
            'module_page_bar' => Paginator::sizeBar(self::PATH, $query, $window, $labels, $sort),
            'module_pagination' => Paginator::render(self::PATH, $query, $window, $labels, $sort),
            'csrf_token' => CsrfToken::token(),
            // Le bloc de téléversement vit dans son propre gabarit : la page ne porte
            // qu'un marqueur, et le rapport est déjà monté (lignes comprises). Il contient
            // **ses propres formulaires**, donc son propre jeton : un gabarit est rempli
            // une fois pour toutes (`TemplateEngine::parse()` remplace un marqueur inconnu
            // par une chaîne vide) et le jeton du corps de page ne redescend pas dans un
            // bloc déjà rendu. Sans cette ligne, les deux boutons du bloc envoyaient un
            // `_token` vide — « Jeton de sécurité manquant » à chaque envoi.
            'module_upload' => $this->adminTemplate('modules_upload', $lang + $this->uploadMarkers($report, $lang) + array(
                'csrf_token' => CsrfToken::token(),
            )),
        ), (string) ($lang['mod_title'] ?? ''), 'bi-box-seam', (string) $window['total']);

        return $this->adminPage($body, (string) ($lang['mod_title'] ?? 'Administration'));
    }

    /**
     * Marqueurs d'une ligne de module.
     *
     * Un module déposé et un module archivé partagent la même ligne : c'est une
     * **classe** qui cache les gestes qui n'ont pas de sens (` d-none`), jamais un `if`
     * dans le gabarit — un module archivé se réinstalle, il ne s'allume ni ne se
     * désinstalle.
     *
     * @param array<string, mixed> $module
     * @param array<string, mixed> $lang
     * @return array<string, string>
     */
    private function row(array $module, array $lang): array
    {
        $archived = ($module['archived'] ?? false) === true;
        $active = ($module['active'] ?? false) === true;
        // Version et auteur viennent du manifeste : ils n'ont de sens que si le module
        // les déclare (un module déposé les annonce, un module du jeu les porte aussi).
        $version = trim((string) ($module['version'] ?? '') . ' ' . (string) ($module['author'] ?? ''));
        $problems = (array) ($module['dependency_problems'] ?? array());

        return array(
            'module_row_name' => (string) $module['name'],
            'module_row_label' => (string) ($module['label_text'] ?? $lang[(string) $module['label']] ?? $module['label']),
            'module_row_description' => (string) ($module['description_text'] ?? $lang[(string) $module['description']] ?? $module['description']),
            'module_row_version' => $version,
            'module_row_version_class' => $version === '' ? ' d-none' : '',
            'module_row_permission' => (string) $module['permission'],
            'module_row_page' => (string) $module['page'],
            'module_row_dependencies' => $this->dependencies((array) $module['dependencies'], $lang),
            'module_row_warning' => $problems === array()
                ? ''
                : (string) ($lang['mod_dep_problem'] ?? '') . ' ' . $this->modules->dependencyText((string) $module['name']),
            'module_row_warning_class' => $problems === array() ? ' d-none' : '',
            'module_row_class' => $active && !$archived ? '' : ' text-body-secondary',
            'module_row_state' => (string) ($lang[$archived ? 'mod_archived_badge' : ($active ? 'mod_state_on' : 'mod_state_off')] ?? ''),
            'module_row_state_class' => $archived
                ? ' text-bg-warning'
                : ($active ? ' text-bg-success' : ' text-bg-secondary'),
            'module_row_native_class' => ($module['native'] ?? false) === true ? '' : ' d-none',
            'module_row_toggle_label' => $archived ? '' : (string) ($lang[$active ? 'mod_turn_off' : 'mod_turn_on'] ?? ''),
            'module_row_toggle_class' => ($archived ? ' d-none' : '')
                . ($active ? ' btn-outline-danger' : ' btn-outline-success'),
            'module_row_archive_class' => $archived ? ' d-none' : '',
            'module_row_restore_class' => $archived ? '' : ' d-none',
        );
    }

    /**
     * Téléversement d'un module : quarantaine, analyse, rapport affiché sur la page.
     *
     * Le fichier reçu n'est **jamais** déplacé directement dans `modules/` : il est
     * extrait en quarantaine, chaque entrée vérifiée avant écriture, puis analysé.
     */
    private function upload(Request $request): Response
    {
        $lang = $this->lang();

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->message($lang, 'mod_bad_token', 'red');
        }

        // `is_uploaded_file()` : le service ne lit un fichier que si le serveur l'a
        // reçu par un envoi de formulaire, jamais un chemin fabriqué.
        $archive = $_FILES['package']['tmp_name'] ?? '';

        if (!is_string($archive) || $archive === '' || !is_uploaded_file($archive)) {
            return $this->message($lang, 'mod_upload_none', 'red');
        }

        // `PharData` reconnaît le format à l'**extension** — et PHP nomme le fichier
        // reçu `/tmp/phpXXXX`, sans extension. On en fait donc une copie nommée, qu'on
        // retire après analyse : le nom d'origine ne sert qu'à choisir le suffixe.
        $original = strtolower((string) ($_FILES['package']['name'] ?? ''));
        $suffix = '';

        foreach (array('.tar.gz', '.tar.bz2', '.zip', '.tar', '.tgz') as $candidate) {
            if (str_ends_with($original, $candidate)) {
                $suffix = $candidate === '.tgz' ? '.tar.gz' : $candidate;

                break;
            }
        }

        $named = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xnova_pkg_' . bin2hex(random_bytes(8)) . $suffix;

        if (!@move_uploaded_file($archive, $named)) {
            return $this->message($lang, 'mod_upload_none', 'red');
        }

        try {
            $report = (new ModuleInstallService())->inspect($named);
        } finally {
            @unlink($named);
        }

        return $this->page($request, $report);
    }

    /**
     * Installation du module préparé.
     *
     * Seul le **nom** vient de la demande : le dossier de quarantaine est reconstruit
     * ici, et son contenu **réanalysé** par le service avant tout déplacement. Un refus
     * arrête tout ; les alertes demandent une case cochée (`force`).
     */
    private function install(Request $request, bool $force): Response
    {
        $lang = $this->lang();

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->message($lang, 'mod_bad_token', 'red');
        }

        $service = new ModuleInstallService();
        $name = (string) $request->post('name');
        $staging = $service->stagingPath($name);

        if ($staging === '' || !is_dir($staging)) {
            return $this->message($lang, 'mod_upload_stale', 'red');
        }

        $sha256 = trim((string) @file_get_contents($staging . DIRECTORY_SEPARATOR . '.sha256'));
        $result = $service->install($staging, $name, $sha256, $force);

        if (!$result['installed']) {
            return $this->message($lang, $result['refusal'], 'red');
        }

        // Le service joue le schéma du module une fois posé : un échec n'annule pas
        // l'installation — les fichiers sont en place — mais il se dit, sinon le module
        // répondrait des 500 « table inexistante » sans que rien ne l'annonce.
        if (($result['migration_error'] ?? '') !== '') {
            return $this->message($lang, 'mod_upload_migrate_failed', 'red');
        }

        return $this->message($lang, 'mod_upload_installed', 'green');
    }

    /** Message d'erreur de cette page, avec retour sur la liste. */
    private function message(array $lang, string $key, string $color): Response
    {
        return $this->renderMessage(
            (string) ($lang[$key] ?? ''),
            (string) ($lang['mod_title'] ?? ''),
            self::PATH,
            3,
            $color
        );
    }

    /**
     * Marqueurs du bloc de téléversement et de son rapport.
     *
     * Une ligne de rapport = `modules_report_row.tpl` rempli une fois par constat :
     * refus d'abord (ils bloquent), alertes ensuite (elles n'empêchent pas). Les
     * classes décident de ce qui se voit, jamais un `if` dans le gabarit.
     *
     * @param array<string, mixed> $report
     * @param array<string, mixed> $lang
     * @return array<string, string>
     */
    private function uploadMarkers(array $report, array $lang): array
    {
        // `inspect()` rend le tableau (nom, quarantaine, empreinte) et le rapport
        // **imbriqué** (`report`) : refus et alertes sont dedans, pas à la racine.
        $details = (array) ($report['report'] ?? array());
        $errors = (array) ($details['errors'] ?? array());
        $warnings = (array) ($details['warnings'] ?? array());
        $known = $report !== array() && $report['report'] !== null;
        $rows = '';

        foreach (array($errors, $warnings) as $group) {
            foreach ($group as $finding) {
                $rows .= $this->adminTemplate('modules_report_row', $lang + $this->reportRow((array) $finding));
            }
        }

        $clean = $known && $errors === array() && $warnings === array();

        return array(
            'report_class' => $known ? '' : ' d-none',
            'report_name' => (string) ($report['name'] ?? ''),
            'report_sha256' => (string) ($report['sha256'] ?? ''),
            'report_files' => (string) ($details['files'] ?? 0),
            'report_rows' => $rows,
            'report_clean_class' => $clean ? '' : ' d-none',
            'report_errors_class' => $errors === array() ? ' d-none' : '',
            'report_warnings_class' => $warnings === array() ? ' d-none' : '',
            // Avec un refus, on n'installe pas ; avec une alerte, la case à cocher
            // donne le bouton « installer malgré les alertes ». Sans rien, un seul bouton.
            'report_install_class' => $errors === array() && $warnings === array() ? '' : ' d-none',
            'report_force_class' => $errors === array() && $warnings !== array() ? '' : ' d-none',
        );
    }

    /**
     * Marqueurs d'une ligne de rapport.
     *
     * @param array<string, mixed> $finding
     * @return array<string, string>
     */
    private function reportRow(array $finding): array
    {
        $file = (string) ($finding['file'] ?? '');
        $line = (int) ($finding['line'] ?? 0);
        // Un constat sans fichier parle du module entier (taille, quarantaine) : la
        // ligne de fichier disparaît alors, plutôt que d'afficher un chemin vide.
        $location = $file === '' ? '' : $file . ($line > 0 ? ':' . $line : '');
        $blocking = in_array((string) ($finding['code'] ?? ''), array('missing_manifest', 'invalid_json', 'zip_slip', 'executable_extension', 'server_config', 'symlink', 'too_large', 'too_many_files', 'missing_directory', 'missing_archive', 'bad_archive', 'staging_failed'), true);

        return array(
            'report_row_class' => $blocking ? ' list-group-item-danger' : ' list-group-item-warning',
            'report_row_icon' => $blocking ? 'bi-x-octagon' : 'bi-exclamation-triangle',
            'report_row_code' => (string) ($finding['code'] ?? ''),
            'report_row_message' => (string) ($finding['message'] ?? ''),
            'report_row_file' => $location,
            'report_row_file_class' => $location === '' ? ' d-none' : '',
        );
    }

    /**
     * Boutons du filtre d'état : les modules déposés, ceux qui sont archivés, ou les
     * deux — le même gabarit, rempli trois fois.
     *
     * @param array<string, mixed> $lang
     */
    private function stateLinks(array $lang, string $current, string $search): string
    {
        $html = '';

        foreach (ModuleService::FILTERS as $filter) {
            $query = array('state' => $filter) + ($search === '' ? array() : array('q' => $search));

            $html .= $this->adminTemplate('filter_button', $lang + array(
                'filter_label' => (string) ($lang['mod_filter_' . $filter] ?? $filter),
                'filter_href' => self::PATH . '?' . str_replace('&', '&amp;', http_build_query($query)),
                'filter_active' => $filter === $current ? ' active' : '',
            ));
        }

        return $html;
    }

    /** Recherche demandée, bornée : une saisie libre n'a pas besoin d'être plus longue. */
    private static function searchTerm(mixed $value): string
    {
        return mb_substr(is_scalar($value) ? trim((string) $value) : '', 0, 64);
    }

    /**
     * Désinstalle (archive) ou réinstalle un module : le module change de dossier.
     *
     * Aucun fichier n'est supprimé — la désinstallation range le module dans
     * `modules/.archive/`, la réinstallation le remet en place, avec ses réglages en base.
     */
    private function move(Request $request, string $action, string $name): Response
    {
        $lang = $this->lang();
        $title = (string) ($lang['mod_title'] ?? '');

        if (!CsrfToken::validate(is_string($request->post('_token')) ? (string) $request->post('_token') : null)) {
            return $this->renderMessage((string) ($lang['mod_bad_token'] ?? ''), $title, self::PATH, 3, 'red');
        }

        $refusal = $action === 'archive' ? $this->modules->archive($name) : $this->modules->restore($name);
        $done = $action === 'archive' ? 'mod_archive_saved' : 'mod_restore_saved';

        return $this->renderMessage(
            (string) ($lang[$refusal !== '' ? $refusal : $done] ?? ''),
            $title,
            self::PATH,
            $refusal === '' ? 3 : 6,
            $refusal === '' ? 'green' : 'red'
        );
    }

    /**
     * Dépendances déclarées par un module, en clair : « Coeur d'application ≥ 2026.6 », « Marchand ».
     *
     * @param array<string, mixed> $declared
     * @param array<string, mixed> $lang
     */
    private function dependencies(array $declared, array $lang): string
    {
        $parts = array();
        $core = trim((string) ($declared['core'] ?? ''));

        if ($core !== '') {
            // `>=` et `<=` s'affichent en signes (le texte part dans du HTML).
            $parts[] = (string) ($lang['mod_dep_core'] ?? '') . ' ' . str_replace(
                array('>=', '<='),
                array('&ge;', '&le;'),
                $core
            );
        }

        foreach ((array) ($declared['modules'] ?? array()) as $module) {
            $label = (string) ($lang[Modules::label((string) $module)] ?? $module);
            $parts[] = (string) ($lang['mod_dep_module'] ?? '') . ' ' . $label;
        }

        return implode(', ', $parts);
    }
}
