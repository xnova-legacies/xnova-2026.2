<?php

namespace App\Core;

/**
 * Formatage des nombres/durées et couleurs HTML (ex strings.php + unlocalised.php).
 */
final class Format
{
    public static function prettyNumber($n, bool $floor = true): string
    {
        if ($floor) {
            $n = floor($n);
        }

        return number_format($n, 0, ",", ".");
    }

    /** Nombre maximal de chiffres après la virgule affichés par le jeu. */
    public const MAX_DECIMALS = 3;

    /**
     * Nombre décimal au format français : virgule, séparateurs de milliers, et
     * zéros inutiles retirés (« 2,5 » plutôt que « 2,500 », « 100.000 » pour un
     * montant rond). Trois chiffres après la virgule au maximum, jamais plus.
     *
     * Les cotes du marché, le prix d'une part et les quantités de ressources ne
     * sont pas des entiers : les arrondir à l'unité effacerait l'information.
     */
    /**
     * Texte destiné à l'affichage : entités décodées puis échappées une seule fois.
     *
     * Les libellés et les pseudos du jeu sont stockés en latin1, souvent déjà en
     * entités HTML : les afficher bruts donne du double échappement
     * (« Tour de contr&ocirc;le »). On décode donc une fois, on répare les octets
     * latin1 (ils ne forment pas de l'UTF-8 valide, et `htmlspecialchars()`
     * renverrait une chaîne vide), puis on échappe une fois.
     *
     * Ne pas passer ici les **noms de planètes** : doublement encodés, ils
     * demandent `legacyPlanetName()`.
     */
    public static function text(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML401, 'UTF-8');

        if (!mb_check_encoding($decoded, 'UTF-8')) {
            $decoded = mb_convert_encoding($decoded, 'UTF-8', 'Windows-1252');
        }

        return htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Texte libre saisi par un joueur, affiché tel quel.
     *
     * Contrairement à `text()`, on ne décode rien : les tables sont en latin1,
     * mais les formulaires du jeu envoient de l'UTF-8, donc les octets stockés
     * sont de l'UTF-8. Décoder donnerait du mojibake ; il ne reste qu'à échapper.
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Nom de planète ou de lune lu par la connexion moderne.
     *
     * Ces noms ont toujours été écrits en UTF-8 dans une colonne `latin1` (double
     * encodage historique : l'installation, les robots et le renommage passent
     * tous par là). La connexion moderne, en UTF-8, les ré-encode donc une
     * seconde fois — « Boréal » ressort en « BorÃ©al » — alors que les pages
     * historiques, en latin1, rendaient les octets d'origine. On rétablit ces
     * octets pour l'affichage.
     *
     * Sans effet sur les noms ASCII, qui restent la grande majorité. À ne pas
     * appliquer aux colonnes réellement en latin1 (pseudos, courriels) : celles-ci
     * sortent déjà correctement de la connexion moderne.
     */
    public static function legacyPlanetName(string $value): string
    {
        return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    }

    public static function decimal($value, int $decimals = self::MAX_DECIMALS): string
    {
        $decimals = max(0, min(self::MAX_DECIMALS, $decimals));
        $text = number_format((float) $value, $decimals, ',', '.');

        if ($decimals > 0 && str_contains($text, ',')) {
            $text = rtrim(rtrim($text, '0'), ',');
        }

        return $text === '' || $text === '-' || $text === '-0' ? '0' : $text;
    }

    /** Variation signée en pourcentage, au format français (« +8,4 % »). */
    public static function signedPercent($value, int $decimals = 1): string
    {
        $number = (float) $value;
        $sign = $number > 0.0 ? '+' : ($number < 0.0 ? '-' : '');

        return $sign . self::decimal(abs($number), $decimals) . ' %';
    }

    public static function colorNumber($n, $s = ''): string
    {
        if ($n > 0) {
            return $s != '' ? self::colorGreen($s) : self::colorGreen($n);
        }

        if ($n < 0) {
            return $s != '' ? self::colorRed($s) : self::colorRed($n);
        }

        return $s != '' ? $s : (string) $n;
    }

    public static function colorRed($n): string
    {
        return '<span class="text-danger">' . $n . '</span>';
    }

    public static function colorGreen($n): string
    {
        return '<span class="text-success">' . $n . '</span>';
    }

    /** Durée sous forme xj xxh xxm xxs (ex pretty_time). */
    public static function prettyTime($seconds): string
    {
        $day = floor($seconds / (24 * 3600));
        // L'opérateur % convertit implicitement ses opérandes en int : en PHP 8.3
        // cela déclenche une dépréciation quand la valeur flottante perd de la
        // précision. On tronque donc explicitement, comme le faisait le %.
        $hs = ((int) ($seconds / 3600)) % 24;
        $ms = ((int) ($seconds / 60)) % 60;
        $sr = ((int) $seconds) % 60;

        $hh = $hs < 10 ? '0' . $hs : $hs;
        $mm = $ms < 10 ? '0' . $ms : $ms;
        $ss = $sr < 10 ? '0' . $sr : $sr;

        $time = '';
        if ($day != 0) {
            $time .= $day . 'j ';
        }
        if ($hs != 0) {
            $time .= $hh . 'h ';
        } else {
            $time .= '00h ';
        }
        if ($ms != 0) {
            $time .= $mm . 'm ';
        } else {
            $time .= '00m ';
        }
        $time .= $ss . 's';

        return $time;
    }

    /** Durée sous forme xxxmin (ex pretty_time_hour). */
    public static function prettyTimeHour($seconds): string
    {
        // Même raison que dans prettyTime() : troncature explicite avant le %.
        $min = ((int) ($seconds / 60)) % 60;
        $time = '';

        if ($min != 0) {
            $time .= $min . 'min ';
        }

        return $time;
    }

    public static function showBuildTime($time): string
    {
        $lang = Language::all();

        return "<br>" . ($lang['ConstructionTime'] ?? 'Construction') . ": " . self::prettyTime($time);
    }
}
