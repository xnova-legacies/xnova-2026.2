<?php

namespace App\Core;

/**
 * Rendu BBCode des messages (ex includes/functions/BBcodeFunction.php).
 */
final class BbCode
{
    public static function render(string $string): string
    {
        $string = nl2br(htmlspecialchars(stripslashes($string)));

        // Le modificateur /e a ete supprime en PHP 7 : preg_replace() renvoyait alors null
        // (le corps des messages ressortait vide). Chaque balise appelle desormais sa
        // fonction via preg_replace_callback, dans l'ordre de l'implementation historique.
        $string = str_replace(array("\n", "\r"), '', $string);

        $string = preg_replace_callback('/\[list\](.*?)\[\/list\]/is', fn(array $m) => self::sList($m[1]), $string);
        $string = preg_replace('/\[b\](.*?)\[\/b\]/is', '<b>\1</b>', $string);
        $string = preg_replace('/\[strong\](.*?)\[\/strong\]/is', '<strong>\1</strong>', $string);
        $string = preg_replace('/\[i\](.*?)\[\/i\]/is', '<i>\1</i>', $string);
        $string = preg_replace('/\[u\](.*?)\[\/u\]/is', '<span style="text-decoration: underline;">\1</span>', $string);
        $string = preg_replace('/\[s\](.*?)\[\/s\]/is', '<span style="text-decoration: line-through;">\1</span>', $string);
        $string = preg_replace('/\[del\](.*?)\[\/del\]/is', '<span style="text-decoration: line-through;">\1</span>', $string);
        $string = preg_replace_callback('/\[url=(.*?)\](.*?)\[\/url\]/is', fn(array $m) => self::urlfix($m[1], $m[2]), $string);
        $string = preg_replace('/\[email=(.*?)\](.*?)\[\/email\]/is', '<a href="mailto:\1" title="\1">\2</a>', $string);
        $string = preg_replace_callback('/\[img](.*?)\[\/img\]/is', fn(array $m) => self::imagefix($m[1]), $string);
        $string = preg_replace('/\[color=(.*?)\](.*?)\[\/color\]/is', '<span style="color: \1;">\2</span>', $string);
        $string = preg_replace_callback('/\[quote\](.*?)\[\/quote\]/is', fn(array $m) => self::sQuote($m[1]), $string);
        $string = preg_replace_callback('/\[code\](.*?)\[\/code\]/is', fn(array $m) => self::sCode($m[1]), $string);

        return (string) $string;
    }

    public static function smileys(string $string): string
    {
        $string = str_replace("&#39;", "'", $string);

        $emoticons = array('Smile', 'cool', 'grrr', 'love', 'msn', 'Oo', 'perdu', 'wink', 'wow');
        foreach ($emoticons as $name) {
            $string = str_replace($name, "[img]/emoticones/{$name}.png[/img]", $string);
        }

        return $string;
    }

    public static function sCode($string)
    {
        $pattern = '/\<img src=\\\"(.*?)img\/smilies\/(.*?).png\\\" alt=\\\"(.*?)\\\" \/>/s';
        $string = preg_replace($pattern, '\3', $string);

        return '<pre>' . trim($string) . '</pre>';
    }

    public static function sQuote($string)
    {
        return '<blockquote>' . $string . '</blockquote>';
    }

    public static function sList($string)
    {
        $tmp = explode('[*]', stripslashes($string));
        $out = null;

        foreach ($tmp as $list) {
            if (strlen(str_replace('', '', $list)) > 0) {
                $out .= '<li>' . trim($list) . '</li>';
            }
        }

        return '<ul>' . $out . '</ul>';
    }

    public static function imagefix($img)
    {
        // Les chemins absolus (/emoticones/...) doivent rester intacts : sous le routeur
        // MVC la page n'est plus a la racine, './images/../emoticones' ne resout plus.
        $isAbsolute = substr($img, 0, 7) === 'http://'
            || substr($img, 0, 8) === 'https://'
            || substr($img, 0, 1) === '/';

        if (!$isAbsolute) {
            $img = './images/' . $img;
        }

        return '<img src="' . $img . '" alt="' . $img . '" title="' . $img . '" />';
    }

    public static function urlfix($url, $title)
    {
        $title = stripslashes($title);

        return '<a href="' . $url . '" title="' . $title . '">' . $title . '</a>';
    }
}
