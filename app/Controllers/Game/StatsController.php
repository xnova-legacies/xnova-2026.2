<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Format;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\StatsRepository;
use App\Repositories\UserRepository;
use App\Services\ModuleService;
use Modules\Alliance\Repositories\AllianceRepository;

final class StatsController extends AbstractController
{
    public function __construct(
        private readonly StatsRepository $stats = new StatsRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly ModuleService $modules = new ModuleService(),
    ) {
    }

    /**
     * Dépôt des alliances, chargé **seulement** si le module est là : sans alliances,
     * le classement n'en propose pas et la classe du module n'existe pas.
     */
    private function alliances(): AllianceRepository
    {
        return $this->allianceRepository ??= new AllianceRepository();
    }

    private ?AllianceRepository $allianceRepository = null;

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('stat');

        $lang = $this->lang();
        $user = $this->user();
        $gameConfig = $this->gameConfig();
        $parse = $lang;
        $who   = (isset($_POST['who'])) ? $_POST['who'] : ($_GET['who'] ?? null);
        if (!isset($who)) {
            $who = 1;
        }
        // Ces parametres viennent de l'URL : les borner garantit que $Order est
        // toujours defini et evite les divisions sur du texte (TypeError PHP 8).
        $who = max(1, min(2, (int) $who));
        $type  = (isset($_POST['type'])) ? $_POST['type'] : ($_GET['type'] ?? null);
        if (!isset($type)) {
            $type = 1;
        }
        $type = max(1, min(5, (int) $type));

        // Le classement des alliances appartient au module : sans lui, l'option
        // disparaît et une adresse forcée retombe sur le classement des joueurs.
        $allianceRanks = $this->modules->available('alliance');

        if (!$allianceRanks && $who === 2) {
            $who = 1;
        }

        $parse['who'] = "<option value=\"1\"" . (($who == "1") ? " SELECTED" : "") . ">" . $lang['stat_player'] . "</option>";

        if ($allianceRanks) {
            $parse['who'] .= "<option value=\"2\"" . (($who == "2") ? " SELECTED" : "") . ">" . $lang['stat_allys'] . "</option>";
        }

        $parse['type'] = "<option value=\"1\"" . (($type == "1") ? " SELECTED" : "") . ">" . $lang['stat_main'] . "</option>";
        $parse['type'] .= "<option value=\"2\"" . (($type == "2") ? " SELECTED" : "") . ">" . $lang['stat_fleet'] . "</option>";
        $parse['type'] .= "<option value=\"3\"" . (($type == "3") ? " SELECTED" : "") . ">" . $lang['stat_research'] . "</option>";
        $parse['type'] .= "<option value=\"4\"" . (($type == "4") ? " SELECTED" : "") . ">" . $lang['stat_building'] . "</option>";
        $parse['type'] .= "<option value=\"5\"" . (($type == "5") ? " SELECTED" : "") . ">" . $lang['stat_defenses'] . "</option>";

        if ($type == 1) {
            $Order = "total_points";
            $Points = "total_points";
            $Counts = "total_count";
            $Rank = "total_rank";
            $OldRank = "total_old_rank";
        } elseif ($type == 2) {
            $Order = "fleet_points";
            $Points = "fleet_points";
            $Counts = "fleet_count";
            $Rank = "fleet_rank";
            $OldRank = "fleet_old_rank";
        } elseif ($type == 3) {
            $Order = "tech_count";
            $Points = "tech_points";
            $Counts = "tech_count";
            $Rank = "tech_rank";
            $OldRank = "tech_old_rank";
        } elseif ($type == 4) {
            $Order = "build_points";
            $Points = "build_points";
            $Counts = "build_count";
            $Rank = "build_rank";
            $OldRank = "build_old_rank";
        } elseif ($type == 5) {
            $Order = "defs_points";
            $Points = "defs_points";
            $Counts = "defs_count";
            $Rank = "defs_rank";
            $OldRank = "defs_old_rank";
        }

        // Même pagination que les autres listes : dix lignes par défaut, taille
        // choisissable et intervalle affiché. La sélection « range » de la page
        // historique (fenêtres de cent lignes, sans numéro de page) disparaît au
        // profit de cette barre.
        $total = $who == 2
            ? (int) ($this->stats->countAlliances()['count'] ?? 0)
            : (int) ($this->stats->countActiveUsers()['count'] ?? 0);
        $labels = $this->paginationLabels((string) ($lang['stat_title'] ?? ''));
        $statQuery = array('who' => (string) $who, 'type' => (string) $type);
        $window = Paginator::window($total, $_GET['page'] ?? 1, Paginator::perPage($_GET['per_page'] ?? null));
        $parse['stat_page_bar'] = Paginator::sizeBar('/game/stat', $statQuery, $window, $labels);
        $parse['stat_pagination'] = Paginator::render('/game/stat', $statQuery, $window, $labels);

        if ($who == 2) {
            $parse['stat_header'] = $this->parse($this->template('stat_alliancetable_header'), $parse);

            // Le rang affiché est celui de la ligne dans le classement complet :
            // la première ligne de la page porte donc le rang de son décalage.
            $start = $window['offset'] + 1;
            $parse['stat_date'] = $gameConfig['stats'];
            $parse['stat_values'] = "";
            foreach ($this->stats->findAllyStats($Order, (int) $window['offset'], (int) $window['per_page']) as $StatRow) {
                $parse['ally_rank'] = $start;

                $AllyRow = $this->alliances()->findFullById((int) $StatRow['id_owner']);

                $rank_old = $StatRow[$OldRank];
                if ($rank_old == 0) {
                    $rank_old = $start;
                    $this->stats->updateRank(2, $Rank, $OldRank, (int) $start, (int) $StatRow['id_owner']);
                } else {
                    $this->stats->updateRankOnly(2, $Rank, (int) $start, (int) $StatRow['id_owner']);
                }
                $rank_new = $start;
                $ranking = $rank_old - $rank_new;
                if ($ranking == "0") {
                    $parse['ally_rankplus'] = '<span class="text-body-secondary">*</span>';
                }
                if ($ranking < "0") {
                    $parse['ally_rankplus'] = '<span class="text-danger">' . $ranking . '</span>';
                }
                if ($ranking > "0") {
                    $parse['ally_rankplus'] = '<span class="text-success">+' . $ranking . '</span>';
                }
                $parse['ally_tag'] = Format::text((string) $AllyRow['ally_tag']);
                $parse['ally_name'] = Format::text((string) $AllyRow['ally_name']);
                $parse['ally_mes'] = '';
                $parse['ally_members'] = $AllyRow['ally_members'];
                $parse['ally_points'] = pretty_number($StatRow[$Order]);
                // ally_members peut valoir 0 : on evite la division par zero.
                $parse['ally_members_points'] = pretty_number(floor((float) $StatRow[$Order] / max(1, (int) $AllyRow['ally_members'])));

                $parse['stat_values'] .= $this->parse($this->template('stat_alliancetable'), $parse);
                $start++;
            }
        } else {
            $parse['stat_header'] = $this->parse($this->template('stat_playertable_header'), $parse);

            $start = $window['offset'] + 1;
            $parse['stat_date'] = $gameConfig['stats'];
            $parse['stat_values'] = "";
            foreach ($this->stats->findUserStats($Order, (int) $window['offset'], (int) $window['per_page']) as $StatRow) {
                $parse['stat_date'] = date("d M Y - H:i:s", $StatRow['stat_date']);
                $parse['player_rank'] = $start;

                $UsrRow = $this->users->findFullById((int) $StatRow['id_owner']);

                $rank_old = $StatRow[$OldRank];
                if ($rank_old == 0) {
                    $rank_old = $start;
                    $this->stats->updateRank(1, $Rank, $OldRank, (int) $start, (int) $StatRow['id_owner']);
                } else {
                    $this->stats->updateRankOnly(1, $Rank, (int) $start, (int) $StatRow['id_owner']);
                }
                $rank_new = $start;
                $ranking = $rank_old - $rank_new;
                if ($ranking == "0") {
                    $parse['player_rankplus'] = '<span class="text-body-secondary">*</span>';
                }
                if ($ranking < "0") {
                    $parse['player_rankplus'] = '<span class="text-danger">' . $ranking . '</span>';
                }
                if ($ranking > "0") {
                    $parse['player_rankplus'] = '<span class="text-success">+' . $ranking . '</span>';
                }
                if ($UsrRow['id'] == $user['id']) {
                    $parse['player_name'] = '<span class="text-success fw-semibold">' . Format::text((string) $UsrRow['username']) . '</span>';
                } else {
                    $parse['player_name'] = Format::text((string) $UsrRow['username']);
                }
                $parse['player_mes'] = '<a href="/game/profil/messages?mode=write&amp;id=' . $UsrRow['id'] . '"><img src="' . $this->skinPath() . 'img/m.gif" alt="' . $lang['Ecrire'] . '"></a>';
                if ($UsrRow['ally_name'] == $user['ally_name']) {
                    $parse['player_alliance'] = '<span class="text-info">' . Format::text((string) $UsrRow['ally_name']) . '</span>';
                } else {
                    $parse['player_alliance'] = Format::text((string) $UsrRow['ally_name']);
                }
                $parse['player_points'] = pretty_number($StatRow[$Order]);
                $parse['stat_values'] .= $this->parse($this->template('stat_playertable'), $parse);
                $start++;
            }
        }

        $page = $this->parse($this->template('stat_body'), $parse);

        return $this->renderPage($page, $lang['stat_title']);
    }
}
