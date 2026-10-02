<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;

final class UserSettingsController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $this->bootLegacy();

        $this->includeLang('options');

        $lang = $this->lang();
        $user = $this->user();

        if ($_POST && ($mode ?? null) == "change") {
            $iduser = $user["id"];
            $avatar = $_POST["avatar"] ?? null;
            $dpath = $_POST["dpath"] ?? null;
            $design = (isset($_POST["design"]) && $_POST["design"] == 'on') ? "1" : "0";
            $noipcheck = (isset($_POST["noipcheck"]) && $_POST["noipcheck"] == 'on') ? "1" : "0";
            $username = (isset($_POST["db_character"]) && $_POST["db_character"] != '') ? $_POST['db_character'] : $user['username'];
            $spyCount = (isset($_POST["spy_count"]) && is_numeric($_POST["spy_count"])) ? $_POST["spy_count"] : "1";
            $settings_tooltiptime = (isset($_POST["settings_tooltiptime"]) && is_numeric($_POST["settings_tooltiptime"])) ? $_POST["settings_tooltiptime"] : "1";
            $settings_fleetactions = (isset($_POST["settings_fleetactions"]) && is_numeric($_POST["settings_fleetactions"])) ? $_POST["settings_fleetactions"] : "1";
            $settings_allylogo = (isset($_POST["settings_allylogo"]) && $_POST["settings_allylogo"] == 'on') ? "1" : "0";
            $settings_esp = (isset($_POST["settings_esp"]) && $_POST["settings_esp"] == 'on') ? "1" : "0";
            $settings_wri = (isset($_POST["settings_wri"]) && $_POST["settings_wri"] == 'on') ? "1" : "0";
            $settings_bud = (isset($_POST["settings_bud"]) && $_POST["settings_bud"] == 'on') ? "1" : "0";
            $settings_mis = (isset($_POST["settings_mis"]) && $_POST["settings_mis"] == 'on') ? "1" : "0";
            $settings_rep = (isset($_POST["settings_rep"]) && $_POST["settings_rep"] == 'on') ? "1" : "0";
            $vacationMode = (isset($_POST["vacation_mode"]) && $_POST["vacation_mode"] == 'on') ? "1" : "0";
            $noJavascript = (isset($_POST["no_javascript"]) && $_POST["no_javascript"] == 'on') ? "1" : "0";

            $this->users->updateSettingsFull(array(
                'email' => $db_email ?? '',
                'avatar' => $avatar,
                'dpath' => $dpath,
                'design' => $design,
                'noipcheck' => $noipcheck,
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
            ), $iduser);

            if (isset($_POST["db_password"]) && md5($_POST["db_password"]) == $user["password"]) {
                if ($_POST["newpass1"] == $_POST["newpass2"]) {
                    $newpass = md5($_POST["newpass1"]);
                    $this->users->updatePassword($user['id'], $newpass);
                    setcookie(COOKIE_NAME, "", time() - 100000, "/", "", 0);
                    return $this->renderMessage($lang['succeful_changepass'], $lang['changue_pass']);
                }
            }
            if ($user['username'] != $_POST["db_character"]) {
                $query = $this->users->usernameTaken($_POST["db_character"]);
                if (!$query) {
                    $this->users->updateUsername($user['id'], $username);
                    setcookie(COOKIE_NAME, "", time() - 100000, "/", "", 0);
                    return $this->renderMessage($lang['succeful_changename'], $lang['changue_name']);
                }
            }
            return $this->renderMessage($lang['succeful_save'], $lang['Options']);
        }

        $parse = $lang;

        $parse['dpath'] = $this->skinPath();
        $parse['user_username'] = $user['username'];
        $parse['user_email'] = $user['email'];
        $parse['user_email_2'] = $user['email_2'];
        $parse['user_dpath'] = $user['dpath'];
        $parse['user_avatar'] = $user['avatar'];
        $parse['user_spy_count'] = $user['spy_count'];
        $parse['user_settings_tooltiptime'] = $user['settings_tooltiptime'];
        $parse['user_settings_fleetactions'] = $user['settings_fleetactions'];
        $parse['user_design'] = ($user['design'] == 1) ? " checked='checked'" : '';
        $parse['user_noipcheck'] = ($user['noipcheck'] == 1) ? " checked='checked'" : '';
        $parse['user_settings_allylogo'] = ($user['settings_allylogo'] == 1) ? " checked='checked'/" : '';
        $parse['user_no_javascript'] = ($user['no_javascript'] == 1) ? " checked='checked'/" : '';
        $parse['user_vacation_mode'] = ($user['vacation_mode'] == 1) ? " checked='checked'/" : '';
        $parse['user_settings_rep'] = ($user['settings_rep'] == 1) ? " checked='checked'/" : '';
        $parse['user_settings_esp'] = ($user['settings_esp'] == 1) ? " checked='checked'/" : '';
        $parse['user_settings_wri'] = ($user['settings_wri'] == 1) ? " checked='checked'/" : '';
        $parse['user_settings_mis'] = ($user['settings_mis'] == 1) ? " checked='checked'/" : '';
        $parse['user_settings_bud'] = ($user['settings_bud'] == 1) ? " checked='checked'/" : '';
        $parse['kolorminus'] = $user['kolorminus'];
        $parse['kolorplus'] = $user['kolorplus'];
        $parse['kolorpoziom'] = $user['kolorpoziom'];

        $page = $this->parse($this->template('usuw_body'), $parse);

        return $this->renderPage($page, $lang['Usuw']);
    }
}
