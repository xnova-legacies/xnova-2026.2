<?php

namespace App\Controllers\Game;

use App\Core\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\BuddyRepository;
use App\Repositories\UserRepository;

final class BuddyController extends AbstractController
{
    public function __construct(
        private readonly BuddyRepository $buddies = new BuddyRepository(),
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function indexAction(Request $request): Response
    {
        // Fenêtre surgissante : pas de menu latéral, pas de barre de navigation.
        define('POPUP', true);

        $this->bootLegacy();

        $this->includeLang('buddy');
        $lang = $this->lang();
        $user = $this->user();

        $a = $_GET['a'] ?? null;
        $e = $_GET['e'] ?? null;
        $s = $_GET['s'] ?? null;
        $u = intval($_GET['u'] ?? 0);

        if ($s == 1 && isset($_GET['bid'])) {
            $bid = intval($_GET['bid']);

            $buddy = $this->buddies->findById($bid);
            if ($buddy['owner'] == $user['id']) {
                if ($buddy['active'] == 0 && $a == 1) {
                    $this->buddies->deleteById($bid);
                } elseif ($buddy['active'] == 1) {
                    $this->buddies->deleteById($bid);
                } elseif ($buddy['active'] == 0) {
                    $this->buddies->activate($bid);
                }
            } elseif ($buddy['sender'] == $user['id']) {
                $this->buddies->deleteById($bid);
            }
        } elseif (($_POST["s"] ?? null) == 3 && ($_POST["a"] ?? null) == 1 && ($_POST["e"] ?? null) == 1 && isset($_POST["u"])) {
            $uid = (int) $user["id"];
            $u = intval($_POST["u"]);

            $buddy = $this->buddies->findBetweenUsers($uid, $u);

            if (!$buddy) {
                if (strlen($_POST['text'] ?? '') > 5000) {
                    return $this->renderMessage("Le texte ne doit pas faire plus de 5000 caract&egrave;res !", "Erreur");
                }

                $text = trim(strip_tags((string) ($_POST['text'] ?? '')));
                $this->buddies->insertRequest($uid, $u, $text);

                return $this->renderMessage($lang['Request_sent'], $lang['Buddy_request'], '/game/buddy');
            }

            return $this->renderMessage($lang['A_request_exists_already_for_this_user'], $lang['Buddy_request'], '/game/buddy');
        }

        if ($a == 2 && $u > 0) {
            $target = $this->users->findFullById($u);

            if ($target && $target["id"] != $user["id"]) {
                $page = $this->partial('buddy_request_body', $lang + array(
                    'buddy_title' => $lang['Buddy_request'],
                    'buddy_id' => (int) $target['id'],
                    'buddy_username' => htmlspecialchars((string) $target['username'], ENT_QUOTES),
                ));

                return $this->renderPage($page, $lang['Buddy_list'], false);
            } elseif ($target && $target["id"] == $user["id"]) {
                return $this->renderMessage($lang['You_cannot_ask_yourself_for_a_request'], $lang['Buddy_request'], '/game/buddy');
            }
        }

        if ($a == 1) {
            $TableTitle = ($e == 1) ? $lang['My_requests'] : $lang['Anothers_requests'];
        } else {
            $TableTitle = $lang['Buddy_list'];
        }

        if ($a == 1) {
            $rows = ($e == 1) ? $this->buddies->findByMode(true, (int) $user["id"]) : $this->buddies->findByMode(false, (int) $user["id"]);
        } else {
            $rows = $this->buddies->findAllActive((int) $user["id"]);
        }

        $body = '';
        $i = 0;

        foreach ($rows as $b) {
            $i++;
            $uid = ($b["owner"] == $user["id"]) ? $b["sender"] : $b["owner"];

            $buddy = $this->buddies->findUserSummaryById((int) $uid);
            if (!$buddy) {
                continue;
            }

            $UserAlly = '';
            if ($buddy["ally_id"] != 0) {
                $UserAlly = $this->partial('alliance_link', array(
                    'alliance_query' => 'a=' . (int) $buddy["id"],
                    'alliance_name' => htmlspecialchars((string) $buddy["ally_name"], ENT_QUOTES),
                ));
            }

            if (isset($a)) {
                $LastOnlineClass = '';
                $LastOnline = nl2br(htmlspecialchars((string) $b["text"], ENT_QUOTES));
            } elseif ($buddy["onlinetime"] + 60 * 10 >= time()) {
                $LastOnlineClass = 'text-success';
                $LastOnline = $lang['On'];
            } elseif ($buddy["onlinetime"] + 60 * 20 >= time()) {
                $LastOnlineClass = 'text-warning';
                $LastOnline = $lang['15_min'];
            } else {
                $LastOnlineClass = 'text-danger';
                $LastOnline = $lang['Off'];
            }

            if (isset($a) && isset($e)) {
                $UserCommand = $this->buddyAction('btn-outline-danger', '?a=1&amp;e=1&amp;s=1&amp;bid=' . (int) $b["id"], $lang['Delete_request']);
            } elseif (isset($a)) {
                $UserCommand = $this->buddyAction('btn-success', '?a=1&amp;s=1&amp;bid=' . (int) $b["id"], $lang['Ok'])
                    . ' ' . $this->buddyAction('btn-outline-danger', '?a=1&amp;s=1&amp;bid=' . (int) $b["id"], $lang['Reject']);
            } else {
                $UserCommand = $this->buddyAction('btn-outline-danger', '?s=1&amp;bid=' . (int) $b["id"], $lang['Delete']);
            }

            $body .= $this->partial('buddy_row', array(
                'buddy_index' => $i,
                'buddy_id' => (int) $buddy["id"],
                'buddy_username' => htmlspecialchars((string) $buddy["username"], ENT_QUOTES),
                'buddy_alliance' => $UserAlly,
                'buddy_galaxy' => (int) $buddy["galaxy"],
                'buddy_system' => (int) $buddy["system"],
                'buddy_planet' => (int) $buddy["planet"],
                'buddy_status_class' => $LastOnlineClass,
                'buddy_status' => $LastOnline,
                'buddy_commands' => $UserCommand,
            ));
        }

        $page = $this->partial('buddy_body', $lang + array(
            'buddy_title' => $TableTitle,
            'buddy_col_user' => isset($a) ? $lang['User'] : $lang['Name'],
            'buddy_col_last' => isset($a) ? $lang['Text'] : $lang['Position'],
            'buddy_rows' => $body,
            'buddy_empty' => $lang['There_is_no_request'],
            'buddy_empty_class' => ($body === '') ? '' : 'd-none',
            'buddy_actions_class' => isset($a) ? ' d-none' : '',
            'buddy_footer_class' => ($a == 1) ? '' : ' d-none',
        ));

        return $this->renderPage($page, $lang['Buddy_list'], false);
    }

    /** Un bouton d'action de ligne : `buddy_action.tpl` est le seul balisage. */
    private function buddyAction(string $class, string $href, string $label): string
    {
        return $this->partial('buddy_action', array(
            'buddy_action_class' => $class,
            'buddy_action_href' => $href,
            'buddy_action_label' => $label,
        ));
    }
}
