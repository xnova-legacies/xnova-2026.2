<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SearchRepository;
use App\Repositories\UserRepository;
use App\Services\ModuleService;
use Modules\Alliance\Repositories\AllianceRepository;

final class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchRepository $search = new SearchRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly ModuleService $modules = new ModuleService(),
    ) {
    }

    /**
     * Dépôt des alliances, chargé **seulement** quand on s'en sert : un jeu sans
     * alliances n'a pas cette classe, et la page ne doit pas la charger.
     */
    private function alliances(): AllianceRepository
    {
        return $this->allianceRepository ??= new AllianceRepository();
    }

    private ?AllianceRepository $allianceRepository = null;

    /** Le module des alliances est-il utilisable sur cette installation ? */
    private function searchAllies(): bool
    {
        return $this->modules->available('alliance');
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $user = $this->user();
        $dpath = (!$user["dpath"]) ? DEFAULT_SKINPATH : $user["dpath"];

        // Le formulaire poste la recherche, mais la pagination passe par des liens :
        // les critères se lisent donc aussi dans l'URL (sinon tourner la page perdrait
        // la recherche).
        $searchtext = (string) ($_POST["searchtext"] ?? $_GET["searchtext"] ?? '');
        $type = $_POST['type'] ?? $_GET['type'] ?? null;

        $this->includeLang('search');
        $lang = $this->lang();
        $i = 0;

        switch ($type) {
            case "playername":
                $table = $this->template('search_user_table');
                $row = $this->template('search_user_row');
                $rows = $this->search->searchUsers($searchtext);
                break;
            case "planetname":
                $table = $this->template('search_user_table');
                $row = $this->template('search_user_row');
                $rows = $this->search->searchPlanets($searchtext);
                break;
            case "allytag":
                $table = $this->template('search_ally_table');
                $row = $this->template('search_ally_row');
                $rows = $this->searchAllies() ? $this->alliances()->searchByTag($searchtext) : array();
                break;
            case "allyname":
                $table = $this->template('search_ally_table');
                $row = $this->template('search_ally_row');
                $rows = $this->searchAllies() ? $this->alliances()->searchByName($searchtext) : array();
                break;
            default:
                $table = $this->template('search_user_table');
                $row = $this->template('search_user_row');
                $rows = $this->search->searchUsers($searchtext);
        }

        $result_list = '';
        $search_results = '';

        // On n'affiche des resultats que si une recherche a ete soumise avec un terme.
        if ($searchtext !== '' && $type !== null) {
            // Un serveur bien rempli en trouve des centaines : même pagination que
            // les autres listes (dix résultats par page par défaut).
            $labels = $this->paginationLabels((string) ($lang['Search_in_all_game'] ?? ''));
            $listQuery = array('type' => (string) $type, 'searchtext' => $searchtext);
            $window = Paginator::window(count($rows), $_GET['page'] ?? 1, Paginator::perPage($_GET['per_page'] ?? null));

            foreach (array_slice($rows, $window['offset'], $window['per_page']) as $r) {
                if ($type == 'playername' || $type == 'planetname') {
                    $s = $r;

                    if ($type == 'planetname') {
                        // La ligne est une planete : les liens doivent viser son proprietaire.
                        $owner = $this->users->findFullById((int) $s['id_owner']);
                        $s['planet_name'] = $s['name'];
                        $s['id'] = (int) $s['id_owner'];
                        $s['username'] = $owner['username'] ?? '';
                        $s['rank'] = $owner['rank'] ?? 0;
                        $s['ally_id'] = $owner['ally_id'] ?? 0;
                        $s['ally_request'] = $owner['ally_request'] ?? 0;
                    } else {
                        $s['id'] = (int) $s['id'];
                        $planet = $this->search->findPlanetNameById((int) $s['id_planet']);
                        $s['planet_name'] = $planet['name'] ?? '';
                    }

                    if (($s['ally_id'] ?? 0) != 0 && ($s['ally_request'] ?? 0) == 0) {
                        $aquery = $this->search->findAllyNameById((int) $s['ally_id']);
                    } else {
                        $aquery = array();
                    }
                    $AllyTag = (string) ($aquery['ally_name'] ?? '');
                    $s['ally_name'] = ($AllyTag !== '')
                        ? $this->partial('alliance_link', array(
                            'alliance_query' => 'tag=' . urlencode($AllyTag),
                            'alliance_name' => htmlspecialchars($AllyTag, ENT_QUOTES),
                        ))
                        : '';

                    $s['username'] = htmlspecialchars((string) $s['username'], ENT_QUOTES);
                    $s['planet_name'] = htmlspecialchars((string) $s['planet_name'], ENT_QUOTES);
                    $s['rank'] = (int) ($s['rank'] ?? 0);
                    $s['dpath'] = $dpath;
                    $s['coordinated'] = $s['galaxy'] . ':' . $s['system'] . ':' . $s['planet'];
                    $s['buddy_request'] = $lang['buddy_request'];
                    $s['write_a_messege'] = $lang['write_a_messege'];
                    $result_list .= $this->parse($row, $s);
                } elseif ($type == 'allytag' || $type == 'allyname') {
                    $s = $r;

                    $s['ally_points'] = pretty_number($s['ally_points']);
                    $s['ally_members'] = (int) ($s['ally_members'] ?? 0);
                    $s['ally_tag'] = $this->partial('alliance_link', array(
                        'alliance_query' => 'tag=' . urlencode((string) $s['ally_tag']),
                        'alliance_name' => htmlspecialchars((string) $s['ally_tag'], ENT_QUOTES),
                    ));
                    $s['ally_name'] = htmlspecialchars((string) $s['ally_name'], ENT_QUOTES);
                    $result_list .= $this->parse($row, $s);
                }
            }

            if ($result_list === '') {
                $Cols = ($type == 'allytag' || $type == 'allyname') ? 4 : 6;
                $result_list = $this->partial('table_message_row', array(
                    'colspan' => $Cols,
                    'text' => $lang['search_no_result'],
                ));
            }

            if ($result_list != '') {
                $lang['result_list'] = $result_list;
                // La barre de taille au-dessus du tableau, la navigation en dessous.
                $search_results = Paginator::sizeBar('/game/search', $listQuery, $window, $labels)
                    . $this->parse($table, $lang)
                    . Paginator::render('/game/search', $listQuery, $window, $labels);
            }
        }

        $lang['type_playername'] = ($type == "playername") ? " SELECTED" : "";
        $lang['type_planetname'] = ($type == "planetname") ? " SELECTED" : "";
        $lang['type_allytag'] = ($type == "allytag") ? " SELECTED" : "";
        $lang['type_allyname'] = ($type == "allyname") ? " SELECTED" : "";
        // Le filtre « alliance » appartient au module : éteint, ses options
        // disparaissent du formulaire (le gabarit reste le même).
        $lang['ally_options'] = $this->searchAllies()
            ? $this->partial('search_ally_options', $lang)
            : '';
        $lang['searchtext'] = htmlspecialchars(stripslashes($searchtext), ENT_QUOTES);
        $lang['search_results'] = $search_results;
        $page = $this->parse($this->template('search_body'), $lang);

        return $this->renderPage($page, $lang['Search']);
    }
}
