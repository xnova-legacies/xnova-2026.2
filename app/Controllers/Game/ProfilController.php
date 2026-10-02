<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\GalaxyRepository;
use App\Repositories\MessageRepository;
use App\Repositories\PlanetRepository;
use App\Repositories\UserRepository;
use App\Services\MessageComposer;
use App\Services\MultiService;

/**
 * Page du profil : les onglets du jeu, dont les options du compte.
 *
 * Ce qu'un **module** possède dans ce formulaire — ses libellés, ses cases, sa colonne
 * de réglage — arrive par surcharge de classe (`moduleSettings()`, `moduleMarkers()`) :
 * le Coeur d'application ne nomme rien de ce qui ne lui appartient pas.
 */
class ProfilController extends AbstractController
{
    public function __construct(
        private readonly MessageRepository $messages = new MessageRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly PlanetRepository $planets = new PlanetRepository(),
        private readonly GalaxyRepository $galaxies = new GalaxyRepository(),
    ) {
    }

    /**
     * Réglages du compte qu'un **module** ajoute aux siens, colonne => valeur.
     *
     * Le Coeur d'application n'en connaît aucun : sans module, la page enregistre exactement ce
     * qu'elle enregistrait avant. Les clés rendues ici sont réunies à celles du Coeur d'application
     * et le Coeur d'application fait foi si une clé se retrouve des deux côtés.
     *
     * @return array<string, string>
     */
    protected function moduleSettings(array $user): array
    {
        return array();
    }

    /**
     * Marqueurs du formulaire qu'un **module** remplit (ses libellés, ses cases).
     *
     * Les valeurs rendues ici sont celles d'un jeu **sans** le module : le bloc est
     * masqué (` d-none`) et ses cases sont vides et désactivées. Le formulaire reste
     * donc complet, sans trou ni marqueur orphelin.
     *
     * @return array<string, string>
     */
    protected function moduleMarkers(array $user, array $lang): array
    {
        return array(
            'bots_section' => '',
            'bots_interaction' => '',
            'bots_interaction_tip' => '',
            'bots_section_class' => ' d-none',
            'user_settings_bots' => '',
            'user_settings_bots_disabled' => ' disabled',
            'bots_interaction_universe' => '',
        );
    }

    /**
     * L'arbre des technologies et la vue Empire sont deux onglets de la vue
     * générale : ces actions ne font plus que rediriger, pour qu'une seule
     * adresse affiche la page (le contenu est rendu par TechTreeService et
     * ImperiumService, appelés par OverviewController).
     */
    public function indexAction(Request $request): Response
    {
        return Response::redirect('/game/overview?tab=tech');
    }

    public function techtreeAction(Request $request): Response
    {
        return Response::redirect('/game/overview?tab=tech');
    }

    public function techdetailsAction(Request $request): Response
    {
        $this->bootLegacy();

        $lang = $this->lang();
        $user = $this->user();
        $planetrow = $this->planetRow();
        $resource = $this->resource();
        $requeriments = $this->requirements();
        $Id = (int) ($_GET['techid'] ?? 0);
        $PageTPL = $this->template('techtree_details');

        $parse = $lang;
        $parse['te_dt_id'] = $Id;
        $parse['te_dt_name'] = $lang['tech'][$Id] ?? $Id;
        $Liste = "";

        // Les prerequis viennent du graphe de dependances : la page fonctionne pour
        // toutes les technologies (elle n'etait renseignee que pour la centrale de fusion).
        if (isset($requeriments[$Id])) {
            foreach ($requeriments[$Id] as $ResClass => $Level) {
                $Owned = (int) ($user[$resource[$ResClass]] ?? 0);
                if ($Owned < 1 && isset($planetrow[$resource[$ResClass]])) {
                    $Owned = (int) $planetrow[$resource[$ResClass]];
                }
                $Ok = $Owned >= $Level;

                $Liste .= "<tr>";
                $Liste .= "<td class=\"xnova-label\"><a href=\"/game/profil/techdetails?techid=" . (int) $ResClass . "\">" . ($lang['tech'][$ResClass] ?? $ResClass) . "</a></td>";
                $Liste .= "<td>" . $lang['level'] . " " . (int) $Level . "</td>";
                $Liste .= "<td class=\"text-end\"><span class=\"badge text-bg-" . ($Ok ? "success" : "danger") . "\">" . $Owned . "</span></td>";
                $Liste .= "</tr>";
            }
        }

        if ($Liste === "") {
            $Liste = "<tr><td class=\"text-center text-body-secondary py-4\">–</td></tr>";
        }

        $parse['Liste'] = $Liste;
        $page = $this->parse($PageTPL, $parse);

        return $this->renderPage($page, $lang['Tech'], false, '', false);
    }

    public function optionsAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('options');

        $lang = $this->lang();
        $lang['PHP_SELF'] = '/game/profil/options';

        $user = $this->user();
        $gameConfig = $this->gameConfig();

        $dpath = (!$user["dpath"]) ? DEFAULT_SKINPATH : $user["dpath"];
        $mode = $_GET['mode'] ?? null;

        // Onglet « Déclaration de multi » : le POST du formulaire arrive ici
        // (l'onglet poste sur sa propre adresse), l'ancienne page `/game/multi`
        // redirige vers cet onglet.
        if ($mode === 'add' && $request->method() === 'POST') {
            (new MultiService())->declare((int) $user['id'], (string) ($_POST['texte'] ?? ''));

            return Response::redirect('/game/profil/options?tab=multi');
        }

        if ($_POST && $mode == "exit") {
            if (isset($_POST["exit_vacation"]) && $_POST["exit_vacation"] == 'on' and $user['vacation_until'] <= time()) {
                $this->users->clearVacation($user['id']);

                foreach ($this->planets->findAllByOwner((int) $user['id']) as $id) {
                    $this->planets->resetProductionPercent((int) $id['id']);
                }

                $dpath = (!$user["dpath"]) ? DEFAULT_SKINPATH : $user["dpath"];
                return $this->renderMessage($lang['succeful_save'], $lang['Options'], "/game/profil/options", 1);
            }

            $dpath = (!$user["dpath"]) ? DEFAULT_SKINPATH : $user["dpath"];
            return $this->renderMessage($lang['You_cant_exit_vmode'], $lang['Options'], "/game/profil/options", 1);
        }

        if ($_POST && $mode == "change") {
            $iduser = $user["id"];
            $avatar = $_POST["avatar"] ?? null;

            if (($_POST["dpath"] ?? null) != "") {
                $dpath = $_POST["dpath"];
            } else {
                $dpath = (!$user["dpath"]) ? DEFAULT_SKINPATH : $user["dpath"];
            }

            if ($user['authlevel'] > 0) {
                if (($_POST['adm_pl_prot'] ?? null) == 'on') {
                    $this->planets->setOwnerLevel((int) $user['id'], (int) $user['authlevel']);
                } else {
                    $this->planets->setOwnerLevel((int) $user['id'], 0);
                }
            }

            $design = (isset($_POST["design"]) && $_POST["design"] == 'on') ? "1" : "0";
            $noipcheck = (isset($_POST["noipcheck"]) && $_POST["noipcheck"] == 'on') ? "1" : "0";
            $username = (isset($_POST["db_character"]) && $_POST["db_character"] != '') ? CheckInputStrings($_POST['db_character']) : $user['username'];
            $db_email = (isset($_POST["db_email"]) && $_POST["db_email"] != '') ? CheckInputStrings($_POST['db_email']) : $user['email'];
            $spyCount = (isset($_POST["spy_count"]) && is_numeric($_POST["spy_count"])) ? $_POST["spy_count"] : "1";
            $settings_tooltiptime = (isset($_POST["settings_tooltiptime"]) && is_numeric($_POST["settings_tooltiptime"])) ? $_POST["settings_tooltiptime"] : "1";
            $settings_fleetactions = (isset($_POST["settings_fleetactions"]) && is_numeric($_POST["settings_fleetactions"])) ? $_POST["settings_fleetactions"] : "1";
            $settings_allylogo = (isset($_POST["settings_allylogo"]) && $_POST["settings_allylogo"] == 'on') ? "1" : "0";
            $settings_esp = (isset($_POST["settings_esp"]) && $_POST["settings_esp"] == 'on') ? "1" : "0";
            $settings_wri = (isset($_POST["settings_wri"]) && $_POST["settings_wri"] == 'on') ? "1" : "0";
            $settings_bud = (isset($_POST["settings_bud"]) && $_POST["settings_bud"] == 'on') ? "1" : "0";
            $settings_mis = (isset($_POST["settings_mis"]) && $_POST["settings_mis"] == 'on') ? "1" : "0";
            $settings_rep = (isset($_POST["settings_rep"]) && $_POST["settings_rep"] == 'on') ? "1" : "0";
            if (isset($_POST["vacation_mode"]) && $_POST["vacation_mode"] == 'on') {
                $fleet = $this->users->countFleetsByOwner((int) $user['id']);
                $build = $this->users->countBuildingByOwner((int) $user['id']);
                $tech = $this->users->countTechInProgress((int) $user['id']);
                $attack = $this->users->countIncomingFleets((int) $user['id']);

                if ($fleet['actcnt'] == '0' && $build['building'] == '0' && $tech['tech'] == '0' && $attack['attack'] == '0') {
                    $vacationMode = "1";
                    $time = time() + 172800;
                    $this->users->setVacation($iduser, $time);
                } else {
                    return $this->renderMessage('<strong>' . $lang['Vaccation_mode_taken'] . '</strong>', $lang['Options'], "/game/profil/options", 2);
                }

                foreach ($this->planets->findAllByOwner((int) $user['id']) as $id) {
                    $this->planets->zeroProductionPercent((int) $id['id'], $gameConfig);
                }
            } else {
                $vacationMode = "0";
            }

            $noJavascript = (isset($_POST["no_javascript"]) && $_POST["no_javascript"] == 'on') ? "1" : "0";
            $SetSort = $_POST['settings_sort'] ?? null;
            $SetOrder = $_POST['settings_order'] ?? null;

// Les réglages qu'un module possède s'ajoutent aux nôtres (colonnes qu'il a dans
        // `users`) : `+` garde la clé de gauche, donc le Coeur d'application fait foi en cas de doublon.
            $this->users->updateSettingsFull(array(
            'email' => $db_email,
            'avatar' => $avatar,
            'dpath' => $dpath,
            'design' => $design,
            'noipcheck' => $noipcheck,
            'planet_sort' => $SetSort,
            'planet_sort_order' => $SetOrder,
            'spy_count' => $spyCount,
            'settings_tooltiptime' => $settings_tooltiptime,
            'settings_fleetactions' => $settings_fleetactions,
            'settings_allylogo' => $settings_allylogo,
            'settings_esp' => $settings_esp,
            'settings_wri' => $settings_wri,
            'settings_bud' => $settings_bud,
            'settings_mis' => $settings_mis,
            'settings_rep' => $settings_rep,
            'vacation_mode' => $vacationMode,
            'no_javascript' => $noJavascript,
            'kolorminus' => $kolorminus ?? '',
            'kolorplus' => $kolorplus ?? '',
            'kolorpoziom' => $kolorpoziom ?? '',
            ) + $this->moduleSettings($user), $iduser);

            if (isset($_POST["db_password"]) && md5($_POST["db_password"]) == $user["password"]) {
                if (!empty($_POST['newpass1']) && !empty($_POST['newpass2']) && $_POST["newpass1"] == $_POST["newpass2"]) {
                    $newpass = md5($_POST["newpass1"]);
                    $this->users->updatePassword((int) $user['id'], $newpass);
                    setcookie(COOKIE_NAME, "", time() - 100000, "/", "", 0);
                    return $this->renderMessage($lang['succeful_changepass'], $lang['changue_pass'], "/front/login", 1);
                }
            }
            if ($user['username'] != ($_POST["db_character"] ?? null)) {
                $query = $this->users->usernameTaken($_POST["db_character"]);
                if (!$query) {
                    $this->users->updateUsername((int) $user['id'], $username);
                    setcookie(COOKIE_NAME, "", time() - 100000, "/", "", 0);
                    return $this->renderMessage($lang['succeful_changename'], $lang['changue_name'], "/front/login", 1);
                }
            }
            return $this->renderMessage($lang['succeful_save'], $lang['Options'], "/game/profil/options", 1);
        }

        $parse = $lang;

        $parse['dpath'] = $dpath;
        // Chemins absolus : le theme du compte est prefixe aux images et aux feuilles
        // de style de toutes les pages, a des profondeurs d'URL variables.
        $parse['opt_lst_skin_data'] = "<option value =\"/public/xnova/\">/public/xnova/</option>";
        $parse['opt_lst_skin_data'] .= "<option value =\"/public/xnova_modern/\">/public/xnova_modern/</option>";
        $parse['opt_lst_ord_data'] = "<option value =\"0\"" . (($user['planet_sort'] == 0) ? " selected" : "") . ">" . $lang['opt_lst_ord0'] . "</option>";
        $parse['opt_lst_ord_data'] .= "<option value =\"1\"" . (($user['planet_sort'] == 1) ? " selected" : "") . ">" . $lang['opt_lst_ord1'] . "</option>";
        $parse['opt_lst_ord_data'] .= "<option value =\"2\"" . (($user['planet_sort'] == 2) ? " selected" : "") . ">" . $lang['opt_lst_ord2'] . "</option>";

        $parse['opt_lst_cla_data'] = "<option value =\"0\"" . (($user['planet_sort_order'] == 0) ? " selected" : "") . ">" . $lang['opt_lst_cla0'] . "</option>";
        $parse['opt_lst_cla_data'] .= "<option value =\"1\"" . (($user['planet_sort_order'] == 1) ? " selected" : "") . ">" . $lang['opt_lst_cla1'] . "</option>";

        if ($user['authlevel'] > 0) {
            $FrameTPL = $this->template('options_admadd');
            $IsProtOn = $this->planets->findProtectionLevel((int) $user['id']);
            $bloc['opt_adm_title'] = $lang['opt_adm_title'];
            $bloc['opt_adm_planet_prot'] = $lang['opt_adm_planet_prot'];
            $bloc['adm_pl_prot_data'] = ($IsProtOn['id_level'] > 0) ? " checked='checked'/" : '';
            $parse['opt_adm_frame'] = $this->parse($FrameTPL, $bloc);
        }

        $parse['opt_usern_data'] = $user['username'];
        $parse['opt_mail1_data'] = $user['email'];
        $parse['opt_mail2_data'] = $user['email_2'];
        $parse['opt_dpath_data'] = $user['dpath'];
        $parse['opt_avata_data'] = $user['avatar'];
        $parse['opt_probe_data'] = $user['spy_count'];
        $parse['opt_toolt_data'] = $user['settings_tooltiptime'];
        $parse['opt_fleet_data'] = $user['settings_fleetactions'];
        $parse['opt_sskin_data'] = ($user['design'] == 1) ? " checked='checked'" : '';
        $parse['opt_noipc_data'] = ($user['noipcheck'] == 1) ? " checked='checked'" : '';
        $parse['opt_allyl_data'] = ($user['settings_allylogo'] == 1) ? " checked='checked'/" : '';
        $parse['opt_delac_data'] = ($user['no_javascript'] == 1) ? " checked='checked'/" : '';
        $parse['opt_modev_data'] = ($user['vacation_mode'] == 1) ? " checked='checked'/" : '';
        $parse['opt_modev_exit'] = ($user['vacation_mode'] == 0) ? " checked='1'/" : '';
        $parse['Vaccation_mode'] = $lang['Vaccation_mode'];
        $parse['vacation_until'] = date("d.m.Y G:i:s", $user['vacation_until']);
        $parse['user_settings_rep'] = ($user['settings_rep'] == 1) ? " checked='checked'/" : '';
        $parse['user_settings_esp'] = ($user['settings_esp'] == 1) ? " checked='checked'/" : '';
        $parse['user_settings_wri'] = ($user['settings_wri'] == 1) ? " checked='checked'/" : '';
        $parse['user_settings_mis'] = ($user['settings_mis'] == 1) ? " checked='checked'/" : '';
        $parse['user_settings_bud'] = ($user['settings_bud'] == 1) ? " checked='checked'/" : '';
        // Ce qu'un module possède dans ce formulaire (libellés, cases, bloc facultatif)
        // arrive par surcharge de classe : le Coeur d'application pose ses marqueurs vides.
        $parse = array_merge($parse, $this->moduleMarkers($user, $lang));
        $parse['kolorminus'] = $user['kolorminus'];
        $parse['kolorplus'] = $user['kolorplus'];
        $parse['kolorpoziom'] = $user['kolorpoziom'];

        // Deux onglets rendus côté serveur : les options du joueur et la déclaration
        // de multi-comptes, qui y a été déportée. Le formulaire des options garde son
        // POST classique, celui de la déclaration poste sur sa propre adresse.
        $this->includeLang('multi');

        // Le libellé de l'onglet vient du fichier de langue qui vient d'être chargé
        // ($lang, copie prise plus haut, ne le connaît pas encore).
        $multiLang = $this->lang();

        $tab = (($_GET['tab'] ?? '') === 'multi') ? 'multi' : 'options';
        $parse['tab_label_options'] = $lang['Options'];
        $parse['tab_label_multi'] = $multiLang['Declaration'] ?? '';
        $parse['tab_options_active'] = ($tab === 'options') ? ' active' : '';
        $parse['tab_multi_active'] = ($tab === 'multi') ? ' active' : '';
        $parse['tab_options_hidden'] = ($tab === 'options') ? '' : ' d-none';
        $parse['tab_multi_hidden'] = ($tab === 'multi') ? '' : ' d-none';
        $parse['options_multi'] = ($tab === 'multi')
            ? (new MultiService())->buildForm('/game/profil/options?tab=multi&amp;mode=add')
            : '';

        if ($user['vacation_mode']) {
            return $this->renderPage($this->parse($this->template('options_body_vmode'), $parse), 'Options');
        }

        return $this->renderPage($this->parse($this->template('options_body'), $parse), 'Options');
    }

    /**
     * Couleur Bootstrap associee a un type de message.
     */
    private function messageTypeColor(int $Type): string
    {
        $Colors = array(
            0 => 'text-warning',
            1 => 'text-danger',
            2 => 'text-danger',
            3 => 'text-warning',
            4 => 'text-info',
            5 => 'text-success',
            15 => 'text-primary',
            99 => 'text-info',
        );

        return $Colors[$Type] ?? 'text-body-secondary';
    }

    /**
     * Ligne du menu de selection des messages (mode par defaut).
     */
    private function messageTypeRow(int $Type, array $Waiting, array $Total, array $lang): string
    {
        $Color = $this->messageTypeColor($Type);
        $Unread = (int) ($Waiting[$Type] ?? 0);
        $TotalCount = (int) ($Total[$Type] ?? 0);

        $Row  = "<tr>";
        $Row .= "<td><a class=\"text-decoration-none " . $Color . "\" href=\"/game/profil/messages?mode=show&amp;messcat=" . $Type . "\">" . ($lang['type'][$Type] ?? $Type) . "</a></td>";
        $Row .= "<td class=\"text-end\">";
        $Row .= ($Unread > 0)
            ? "<span class=\"badge text-bg-danger\">" . $Unread . "</span>"
            : "<span class=\"text-body-secondary\">0</span>";
        $Row .= "</td>";
        $Row .= "<td class=\"text-end text-body-secondary\">" . $TotalCount . "</td>";
        $Row .= "</tr>";

        return $Row;
    }

    /**
     * Nom ou objet d'un message : la règle d'affichage vit dans `Format::text()`
     * (entités décodées puis échappées une seule fois), partagée avec l'admin.
     */
    private static function plainText(string $value): string
    {
        return \App\Core\Format::text(stripslashes($value));
    }

    /**
     * Ligne d'un message dans la liste (mode afficher) : entete + corps.
     */
    private function messageRow(array $Message, array $lang): string
    {
        $Id      = (int) $Message['message_id'];
        $Type    = (int) $Message['message_type'];
        // Les messages sont stockés en HTML d'entités (`&ocirc;`) : on les décode
        // avant de les ré-échapper, sinon « Tour de contrôle » s'affiche
        // « Tour de contr&ocirc;le ».
        $From    = self::plainText((string) $Message['message_from']);
        $Subject = self::plainText((string) $Message['message_subject']);
        $Body    = stripslashes((string) $Message['message_text']);

        // Le bouton « Répondre » n'existe que pour un message d'un joueur (type 1) :
        // le gabarit le masque par une classe, le balisage reste au même endroit.
        $AnswerHref = '/game/profil/messages?mode=write&amp;id=' . (int) $Message['message_sender']
            . '&amp;subject=' . rawurlencode($lang['mess_answer_prefix'] . ' ' . $Subject);

        return $this->partial('messages_row', $lang + array(
            'message_id' => $Id,
            'message_date' => date('d.m.Y H:i:s', (int) $Message['message_time']),
            'message_from' => htmlspecialchars($From, ENT_QUOTES),
            'message_subject' => htmlspecialchars($Subject, ENT_QUOTES),
            // Le corps garde son balisage : c'est le conteneur qui est mis en forme.
            'message_body' => $Body,
            'message_answer_href' => $AnswerHref,
            'message_answer_class' => ($Type == 1) ? '' : ' d-none',
        ));
    }

    public function messagesAction(Request $request): Response
    {
        $this->bootLegacy();


        $user = $this->user();
        $this->includeLang('messages');

        $lang = $this->lang();
        $gameConfig = $this->gameConfig();
        $dpath = $this->skinPath();
        $messfields = $GLOBALS['messfields'] ?? [];
        $forceRedirect = false;

        if ($user['authlevel'] != "1" & $user['authlevel'] != "3" & $user['authlevel'] != "0") {
            // Adresse absolue de la page de connexion : `/game/login` n'existe pas.
            header('Location: /front/login');
            $forceRedirect = true;
        }

        $this->includeLang('messages');

        $OwnerID = $_GET['id'] ?? null;
        $MessCategory = $_GET['messcat'] ?? null;
        $MessPageMode = $_GET["mode"] ?? null;
        $DeleteWhat = $_POST['deletemessages'] ?? null;
        if (isset($DeleteWhat)) {
            $MessPageMode = "delete";
        }

        $UsrMess = $this->messages->findAllByOwner((int) $user['id']);
        $UnRead = $this->users->findFullById((int) $user['id']);

        $MessageType = array(0, 1, 2, 3, 4, 5, 15, 99, 100);

        $WaitingMess = array();
        $TotalMess = array();
        for ($MessType = 0; $MessType < 101; $MessType++) {
            if (in_array($MessType, $MessageType)) {
                $WaitingMess[$MessType] = $UnRead[$messfields[$MessType]];
                $TotalMess[$MessType] = 0;
            }
        }

        foreach ($UsrMess as $CurMess) {
            $MessType = $CurMess['message_type'];
            $TotalMess[$MessType] = ($TotalMess[$MessType] ?? 0) + 1;
            $TotalMess[100] = ($TotalMess[100] ?? 0) + 1;
        }

        $page = '';
        $PageTitle = $lang['mess_pagetitle'];
        switch ($MessPageMode) {
            case 'write':
                if (!is_numeric($OwnerID)) {
                    return $this->renderMessage($lang['mess_no_ownerid'], $lang['mess_error']);
                }

                $OwnerRecord = $this->users->findFullById((int) $OwnerID);

                if (!$OwnerRecord) {
                    return $this->renderMessage($lang['mess_no_owner'], $lang['mess_error']);
                }

                $OwnerHome = $this->galaxies->findHomeByPlanetId((int) $OwnerRecord["id_planet"]);
                if (!$OwnerHome) {
                    return $this->renderMessage($lang['mess_no_ownerpl'], $lang['mess_error']);
                }

                $subject = (string) ($_GET['subject'] ?? '');
                $text = '';

                if ($_POST) {
                    $error = 0;
                    if (!($_POST["subject"] ?? null)) {
                        $error++;
                        $page .= "<div class=\"alert alert-danger\" role=\"alert\">" . $lang['mess_no_subject'] . "</div>";
                    }
                    if (!($_POST["text"] ?? null)) {
                        $error++;
                        $page .= "<div class=\"alert alert-danger\" role=\"alert\">" . $lang['mess_no_text'] . "</div>";
                    }
                    if ($error == 0) {
                        $page .= "<div class=\"alert alert-success\" role=\"alert\"><i class=\"bi bi-check-circle\" aria-hidden=\"true\"></i> " . $lang['mess_sended'] . "</div>";

                        $Owner = $OwnerID;
                        $Sender = $user['id'];
                        $From = $user['username'] . " [" . $user['galaxy'] . ":" . $user['system'] . ":" . $user['planet'] . "]";
                        $Subject = $_POST['subject'];
                        // Chaîne unique de composition du corps : MessageComposer::body().
                        $Message = MessageComposer::body($_POST['text'], $gameConfig['enable_bbcode'] == 1);
                        SendSimpleMessage($Owner, $Sender, '', 1, $From, $Subject, $Message);
                        $subject = "";
                        $text = "";
                    } else {
                        $subject = (string) ($_POST['subject'] ?? '');
                        $text = (string) ($_POST['text'] ?? '');
                    }
                }
                $parse['Send_message'] = $lang['mess_pagetitle'];
                $parse['Recipient'] = $lang['mess_recipient'];
                $parse['Subject'] = $lang['mess_subject'];
                $parse['Message'] = $lang['mess_message'];
                $parse['characters'] = $lang['mess_characters'];
                $parse['Envoyer'] = $lang['mess_envoyer'];
                $parse['mess_reset'] = $lang['mess_reset'];
                $parse['mess_wait'] = $lang['mess_wait'];
                $parse['mess_bbcode_toggle'] = $lang['mess_bbcode_toggle'];
                $parse['mess_bbcode_help'] = $lang['mess_bbcode_help'];

                $parse['id'] = $OwnerID;
                $parse['to'] = htmlspecialchars($OwnerRecord['username'], ENT_QUOTES) . " [" . $OwnerHome['galaxy'] . ":" . $OwnerHome['system'] . ":" . $OwnerHome['planet'] . "]";
                $parse['subject'] = htmlspecialchars($subject, ENT_QUOTES);
                $parse['text'] = htmlspecialchars($text, ENT_QUOTES);
                if ($gameConfig['enable_bbcode'] == 1) {
                    $page .= $this->parse($this->template('messages_pm_form_bb'), $parse);
                } else {
                    $page .= $this->parse($this->template('messages_pm_form'), $parse);
                }
                break;

            case 'delete':
                $DeleteWhat = $_POST['deletemessages'] ?? null;
                // Suppression **logique** : le message est marque d'un drapeau
                // (`App\Core\Flags::DELETED`), il quitte la boite du joueur mais
                // reste visible et retablissable dans l'administration.
                if ($DeleteWhat == 'deleteall') {
                    $this->messages->markDeletedByOwner((int) $user['id']);
                } elseif ($DeleteWhat == 'deletemarked') {
                    foreach ($_POST as $Message => $Answer) {
                        if (preg_match("/delmes/i", $Message) && $Answer == 'on') {
                            $MessId = str_replace("delmes", "", $Message);
                            $MessHere = $this->messages->existsByIdAndOwner((int) $MessId, (int) $user['id']);
                            if ($MessHere) {
                                $this->messages->markDeletedById((int) $MessId);
                            }
                        }
                    }
                } elseif ($DeleteWhat == 'deleteunmarked') {
                    foreach ($_POST as $Message => $Answer) {
                        $MessId = str_replace("showmes", "", $Message);
                        $Selected = "delmes" . $MessId;
                        $IsSelected = $_POST[$Selected] ?? null;
                        if (preg_match("/showmes/i", $Message) && !isset($IsSelected)) {
                            $MessHere = $this->messages->existsByIdAndOwner((int) $MessId, (int) $user['id']);
                            if ($MessHere) {
                                $this->messages->markDeletedById((int) $MessId);
                            }
                        }
                    }
                }
                $MessCategory = $_POST['category'] ?? null;
                // no break — la suppression enchaîne volontairement sur l'affichage : le
                // `case 'show'` qui suit relit la catégorie posée juste au-dessus.

            case 'show':
                $PageTitle = $lang['type'][$MessCategory] ?? $lang['title'];
                $page  = "<script>\n";
                $page .= "function f(target_url, win_name) {\n";
                $page .= "var new_win = window.open(target_url, win_name, 'resizable=yes,scrollbars=yes,menubar=no,toolbar=no,width=550,height=280,top=0,left=0');\n";
                $page .= "new_win.focus();\n";
                $page .= "}\n";
                $page .= "</script>\n";

                $ShowAll = ($MessCategory == 100);
                $DeleteOptions = "<option value=\"deletemarked\">" . $lang['mess_deletemarked'] . "</option>"
                    . "<option value=\"deleteunmarked\">" . $lang['mess_deleteunmarked'] . "</option>"
                    . "<option value=\"deleteall\">" . $lang['mess_deleteall'] . "</option>";
                $DeleteButton = "<button type=\"submit\" class=\"btn btn-sm btn-outline-danger\"><i class=\"bi bi-trash\" aria-hidden=\"true\"></i> " . $lang['mess_its_ok'] . "</button>";

                $page .= "<div class=\"card xnova-panel border-0 shadow-sm mb-3\">";
                $page .= "<form action=\"/game/profil/messages\" method=\"post\">";
                $page .= "<input type=\"hidden\" name=\"messages\" value=\"1\">";
                $page .= "<input type=\"hidden\" name=\"category\" value=\"" . htmlspecialchars((string) $MessCategory, ENT_QUOTES) . "\">";
                $page .= "<div class=\"card-header fw-semibold\"><i class=\"bi bi-envelope-open\" aria-hidden=\"true\"></i> " . ($lang['type'][$MessCategory] ?? $lang['title']) . "</div>";
                $page .= "<div class=\"card-body pb-2\">";
                $page .= "<div class=\"d-flex flex-wrap align-items-center gap-2\">";
                $page .= "<label class=\"visually-hidden\" for=\"deletemessages\">" . $lang['mess_action'] . "</label>";
                $page .= "<select class=\"form-select form-select-sm xnova-mess-select\" id=\"deletemessages\" name=\"deletemessages\">" . $DeleteOptions . "</select>";
                $page .= $DeleteButton;
                $page .= "<div class=\"form-check mb-0 ms-sm-auto\">";
                $page .= "<input class=\"form-check-input\" type=\"checkbox\" id=\"fullreports\" name=\"fullreports\" value=\"1\">";
                $page .= "<label class=\"form-check-label\" for=\"fullreports\">" . $lang['mess_partialreport'] . "</label>";
                $page .= "</div>";
                $page .= "</div>";
                $page .= "</div>";
                $page .= "<div class=\"table-responsive\">";
                $page .= "<table class=\"table table-sm table-hover align-middle mb-0\">";
                $page .= "<thead><tr>";
                $page .= "<th>" . $this->checkAll('messages', (string) ($lang['mess_select_all'] ?? '')) . "</th>";
                $page .= "<th class=\"text-nowrap\">" . $lang['mess_date'] . "</th>";
                $page .= "<th class=\"text-nowrap\">" . $lang['mess_from'] . "</th>";
                $page .= "<th>" . $lang['mess_subject'] . "</th>";
                $page .= "</tr></thead>";
                $page .= "<tbody>";

                if ($ShowAll) {
                    $UsrMess = $this->messages->findAllByOwner((int) $user['id']);
                    $this->messages->markAllRead((int) $user['id'], $messfields, $MessageType);
                } else {
                    $UsrMess = $this->messages->findByOwnerAndType((int) $user['id'], (int) $MessCategory);
                    if (($WaitingMess[$MessCategory] ?? '') <> '') {
                        $this->messages->markTypeRead((int) $user['id'], $messfields, (int) $MessCategory, (int) $WaitingMess[$MessCategory]);
                    }
                }

                // Même pagination que les listes de l'administration : la boîte peut
                // être pleine, et seule une page s'affiche à la fois.
                $listUrl = '/game/profil/messages';
                $listQuery = array('mode' => 'show', 'messcat' => (string) $MessCategory);
                $labels = $this->paginationLabels($PageTitle);
                $window = Paginator::window(count($UsrMess), $_GET['page'] ?? 1, Paginator::perPage($_GET['per_page'] ?? null));

                $rows = "";
                foreach (array_slice($UsrMess, $window['offset'], $window['per_page']) as $CurMess) {
                    if (!$ShowAll && $CurMess['message_type'] != $MessCategory) {
                        continue;
                    }
                    $rows .= $this->messageRow($CurMess, $lang);
                }

                // La barre de taille vit au-dessus du tableau, la navigation en dessous.
                $page .= Paginator::sizeBar($listUrl, $listQuery, $window, $labels);

                if ($rows === "") {
                    $page .= "<tr><td colspan=\"4\" class=\"text-center text-body-secondary py-4\">" . $lang['mess_nomessages'] . "</td></tr>";
                } else {
                    $page .= $rows;
                }

                $page .= "</tbody></table>";
                $page .= "</div>";
                $page .= Paginator::render($listUrl, $listQuery, $window, $labels);
                $page .= "<div class=\"card-footer text-end\">" . $DeleteButton . "</div>";
                $page .= "</form>";
                $page .= "</div>";
                break;

            default:
                $PageTitle = $lang['title'];
                $page  = "<script>\n";
                $page .= "function f(target_url, win_name) {\n";
                $page .= "var new_win = window.open(target_url, win_name, 'resizable=yes, scrollbars=yes, menubar=no, toolbar=no, width=550, height=280, top=0, left=0');\n";
                $page .= "new_win.focus();\n";
                $page .= "}\n";
                $page .= "</script>\n";

                $page .= "<div class=\"card xnova-panel border-0 shadow-sm mb-3\">";
                $page .= "<div class=\"card-header fw-semibold\"><i class=\"bi bi-envelope\" aria-hidden=\"true\"></i> " . $lang['title'] . "</div>";
                $page .= "<div class=\"table-responsive\">";
                $page .= "<table class=\"table table-hover align-middle mb-0\">";
                $page .= "<thead><tr>";
                $page .= "<th>" . $lang['head_type'] . "</th>";
                $page .= "<th class=\"text-end\">" . $lang['head_count'] . "</th>";
                $page .= "<th class=\"text-end\">" . $lang['head_total'] . "</th>";
                $page .= "</tr></thead>";
                $page .= "<tbody>";
                $page .= $this->messageTypeRow(100, $WaitingMess, $TotalMess, $lang);
                foreach ($MessageType as $MessType) {
                    if ($MessType == 100) {
                        continue;
                    }
                    $page .= $this->messageTypeRow((int) $MessType, $WaitingMess, $TotalMess, $lang);
                }
                $page .= "</tbody></table>";
                $page .= "</div>";
                $page .= "</div>";
                break;
        }

        if ($forceRedirect) {
            return $this->redirect('/front/login');
        }

        return $this->renderPage($page, $PageTitle);
    }

    public function imperiumAction(Request $request): Response
    {
        return Response::redirect('/game/overview?tab=empire');
    }
}
