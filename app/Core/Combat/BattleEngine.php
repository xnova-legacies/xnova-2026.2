<?php

namespace App\Core\Combat;

/**
 * Moteur de résolution des combats (ex `includes/ataki.php`, `walka()`).
 *
 * Portage fidèle : le corps de la méthode garde ses expressions, son ordre et sa ligne
 * `global` — il lit `$pricelist`, `$CombatCaps` et `$game_config` posés par le Coeur de
 * l'application legacy, donc aucune règle n'est réécrite ici.
 *
 * **Tout le legacy polonais a été traduit** : les variables locales (`$atakujacy` →
 * `attackerValueStart`, `$wrog` → `$defendersLeft`, `$zlom` → `$debris`, `$tarcza` → `$shield`,
 * `$runda` → `$rounds`, `$moc` → `$defenderPower`, `$ile_zdjac` / `$max_zdjac` → `$removed` /
 * `$maxRemovable`, …) comme les **clés** rendues ou posées dans les tableaux : `attacker`,
 * `defender`, `victory`, `rounds`, `debris` (et, dedans, `metal`, `crystal`, `attacker`,
 * `defender`), `armour`, `shield`, `attack` et `count`. `CombatFunctions` et les gabarits
 * `combat_report*.tpl` lisent ces clés.
 *
 * Deux comportements d'origine sont conservés à l'identique, faute de quoi les
 * combats changeraient de résultat :
 *   - le bonus de combat (`static::bonus()`) ne vient **pas** du compte : il additionne les parts
 *     que le dépôt du compte verse dans `combat_bonus`, et vaut zéro s'il n'y en a aucune — la
 *     valeur que le legacy lisait dans une variable non déclarée ;
 *   - `$defenderPower` n'est défini que dans une branche mais utilisé dans l'autre.
 *
 * Les compteurs de valeur (`$attackerValueStart`, `$defenderDefenceValueStart`,
 * `$defenderDefenceValueEnd`) sont déclarés à zéro en tête de méthode : le legacy ne les
 * alimentait que dans une seule branche (unités ≥ 300), ce que PHP signalait par un avertissement
 * à chaque combat. `null + x` valant déjà `x`, aucun chiffre ne change — les avertissements
 * disparaissent.
 *
 * Pas de `declare(strict_types=1)` : les valeurs viennent de la base et des
 * tables de jeu sous forme de chaînes numériques.
 *
 * La classe n'est **pas** `final` : un module peut en dériver pour ajouter une
 * règle de combat (convention de surcharge : même nom court, même couche), le Coeur d'application
 * continuant de
 * servir celle-ci tant qu'aucun module allumé ne la surcharge.
 */
class BattleEngine
{
    /**
     * Simule le combat et renvoie l'état final des deux camps.
     *
     * @param array|null $CurrentSet    flottes de l'attaquant (count par vaisseau)
     * @param array|null $TargetSet     défenses et flottes du défenseur
     * @param array      $CurrentTechno technologies de l'attaquant
     * @param array      $TargetTechno  technologies du défenseur
     */
    public static function resolve($CurrentSet, $TargetSet, $CurrentTechno, $TargetTechno)
    {
        global $pricelist, $CombatCaps, $game_config;
        $rounds        = array();
        $attackersLeft = array();
        $defendersLeft = array();

        // Déclarés à leur valeur **actuelle** (zéro) : voir le commentaire de classe. Le legacy
        // ne les remplissait que dans une branche (les unités ≥ 300), ce que PHP signalait par un
        // avertissement à chaque combat.
        $attackerValueStart        = array('metal' => 0, 'crystal' => 0);
        $defenderDefenceValueStart = array('metal' => 0, 'crystal' => 0);
        $defenderDefenceValueEnd   = array('metal' => 0, 'crystal' => 0);

        // Calcul des points de Structure de l'attaquant
        if (!is_null($CurrentSet)) {
            $attackerValueStart['metal']   = 0;
            $attackerValueStart['crystal'] = 0;
            foreach ($CurrentSet as $a => $b) {
                $attackerValueStart['metal']   = $attackerValueStart['metal']   + $CurrentSet[$a]['count'] * $pricelist[$a]['metal'];
                $attackerValueStart['crystal'] = $attackerValueStart['crystal'] + $CurrentSet[$a]['count'] * $pricelist[$a]['crystal'];
            }
        }

        // Calcul des points de Structure du défenseur
        $defenderValueStart['metal']   = 0;
        $defenderValueStart['crystal'] = 0;
        $defenderStart = $TargetSet;
        if (!is_null($TargetSet)) {
            foreach ($TargetSet as $a => $b) {
                if ($a < 300) {
                    $defenderValueStart['metal']   = $defenderValueStart['metal']   + $TargetSet[$a]['count'] * $pricelist[$a]['metal'];
                    $defenderValueStart['crystal'] = $defenderValueStart['crystal'] + $TargetSet[$a]['count'] * $pricelist[$a]['crystal'];
                } else {
                    $defenderDefenceValueStart['metal']   = $defenderDefenceValueStart['metal']   + $TargetSet[$a]['count'] * $pricelist[$a]['metal'];
                    $defenderDefenceValueStart['crystal'] = $defenderDefenceValueStart['crystal'] + $TargetSet[$a]['count'] * $pricelist[$a]['crystal'];
                }
            }
        }

        for ($i = 1; $i <= 7; $i++) {
            $attackerAttack  = 0;
            $defenderAttack  = 0;
            $attackerDefence = 0;
            $defenderDefence = 0;
            $attackerCount   = 0;
            $defenderCount   = 0;
            $defenderShield  = 0;
            $attackerShield  = 0;

            if (!is_null($CurrentSet)) {
                foreach ($CurrentSet as $a => $b) {
                    $CurrentSet[$a]["armour"] = $CurrentSet[$a]['count'] * ($pricelist[$a]['metal'] + $pricelist[$a]['crystal']) / 10 * (1 + (0.1 * ($CurrentTechno["defence_tech"]) + static::bonus($CurrentTechno)));
                    $rand = rand(80, 120) / 100;
                    $CurrentSet[$a]["shield"] = $CurrentSet[$a]['count'] * $CombatCaps[$a]['shield'] * (1 + (0.1 * $CurrentTechno["shield_tech"]) + static::bonus($CurrentTechno)) * $rand;
                    $unitAttack = $CombatCaps[$a]['attack'];
                    $technology = (1 + (0.1 * $CurrentTechno["military_tech"] + static::bonus($CurrentTechno)));
                    $rand = rand(80, 120) / 100;
                    $count = $CurrentSet[$a]['count'];
                    $CurrentSet[$a]["attack"] = $count * $unitAttack * $technology * $rand;
                    $attackerAttack = $attackerAttack + $CurrentSet[$a]["attack"];
                    $attackerDefence = $attackerDefence + $CurrentSet[$a]["armour"];
                    $attackerCount = $attackerCount + $CurrentSet[$a]['count'];
                }
            } else {
                $attackerCount = 0;
                break;
            }

            if (!is_null($TargetSet)) {
                foreach ($TargetSet as $a => $b) {
                    $TargetSet[$a]["armour"] = $TargetSet[$a]['count'] * ($pricelist[$a]['metal'] + $pricelist[$a]['crystal']) / 10 * (1 + (0.1 * ($TargetTechno["defence_tech"]) + static::bonus($TargetTechno)));
                    $rand = rand(80, 120) / 100;
                    $TargetSet[$a]["shield"] = $TargetSet[$a]['count'] * $CombatCaps[$a]['shield'] * (1 + (0.1 * $TargetTechno["shield_tech"]) + static::bonus($TargetTechno)) * $rand;
                    $unitAttack = $CombatCaps[$a]['attack'];
                    $technology = (1 + (0.1 * $TargetTechno["military_tech"]) + static::bonus($TargetTechno));
                    $rand = rand(80, 120) / 100;
                    $count = $TargetSet[$a]['count'];
                    $TargetSet[$a]["attack"] = $count * $unitAttack * $technology * $rand;
                    $defenderAttack = $defenderAttack + $TargetSet[$a]["attack"];
                    $defenderDefence = $defenderDefence + $TargetSet[$a]["armour"];
                    $defenderCount = $defenderCount + $TargetSet[$a]['count'];
                }
            } else {
                $defenderCount = 0;
                $rounds[$i]["attacker"] = $CurrentSet;
                $rounds[$i]["defender"] = $TargetSet;
                $rounds[$i]["attacker"]["attack"] = $attackerAttack;
                $rounds[$i]["defender"]["attack"] = $defenderAttack;
                $rounds[$i]["attacker"]['count'] = $attackerCount;
                $rounds[$i]["defender"]['count'] = $defenderCount;
                break;
            }

            $rounds[$i]["attacker"] = $CurrentSet;
            $rounds[$i]["defender"] = $TargetSet;
            $rounds[$i]["attacker"]["attack"] = $attackerAttack;
            $rounds[$i]["defender"]["attack"] = $defenderAttack;
            $rounds[$i]["attacker"]['count'] = $attackerCount;
            $rounds[$i]["defender"]['count'] = $defenderCount;

            if (($attackerCount == 0) or ($defenderCount == 0)) {
                break;
            }
            foreach ($CurrentSet as $a => $b) {
                if ($attackerCount > 0) {
                    $defenderPower = $CurrentSet[$a]['count'] * $defenderAttack / $attackerCount;
                    if ($CurrentSet[$a]["shield"] < $defenderPower) {
                        $maxRemovable = floor($CurrentSet[$a]['count'] * $defenderCount / $attackerCount);
                        $defenderPower = $defenderPower - $CurrentSet[$a]["shield"];
                        $attackerShield = $attackerShield + $CurrentSet[$a]["shield"];
                        $removed = floor(($defenderPower / (($pricelist[$a]['metal'] + $pricelist[$a]['crystal']) / 10)));
                        if ($removed > $maxRemovable) {
                            $removed = $maxRemovable;
                        }
                        $attackersLeft[$a]['count'] = ceil($CurrentSet[$a]['count'] - $removed);
                        if ($attackersLeft[$a]['count'] <= 0) {
                            $attackersLeft[$a]['count'] = 0;
                        }
                    } else {
                        $attackersLeft[$a]['count'] = $CurrentSet[$a]['count'];
                        $attackerShield = $attackerShield + $defenderPower;
                    }
                } else {
                    $attackersLeft[$a]['count'] = $CurrentSet[$a]['count'];
                    $attackerShield = $attackerShield + $defenderPower;
                }
            }

            foreach ($TargetSet as $a => $b) {
                if ($defenderCount > 0) {
                    $attackerPower = $TargetSet[$a]['count'] * $attackerAttack / $defenderCount;
                    if ($TargetSet[$a]["shield"] < $attackerPower) {
                        $maxRemovable = floor($TargetSet[$a]['count'] * $attackerCount / $defenderCount);
                        $attackerPower = $attackerPower - $TargetSet[$a]["shield"];
                        $defenderShield = $defenderShield + $TargetSet[$a]["shield"];
                        $removed = floor(($attackerPower / (($pricelist[$a]['metal'] + $pricelist[$a]['crystal']) / 10)));
                        if ($removed > $maxRemovable) {
                            $removed = $maxRemovable;
                        }
                        $defendersLeft[$a]['count'] = ceil($TargetSet[$a]['count'] - $removed);
                        if ($defendersLeft[$a]['count'] <= 0) {
                            $defendersLeft[$a]['count'] = 0;
                        }
                    } else {
                        $defendersLeft[$a]['count'] = $TargetSet[$a]['count'];
                        $defenderShield = $defenderShield + $attackerPower;
                    }
                } else {
                    $defendersLeft[$a]['count'] = $TargetSet[$a]['count'];
                    $defenderShield = $defenderShield + $attackerPower;
                }
            }

            foreach ($CurrentSet as $a => $b) {
                foreach ($CombatCaps[$a]['sd'] as $c => $d) {
                    if (isset($TargetSet[$c])) {
                        $defendersLeft[$c]['count'] = $defendersLeft[$c]['count'] - floor($d * rand(50, 100) / 100);
                        if ($defendersLeft[$c]['count'] <= 0) {
                            $defendersLeft[$c]['count'] = 0;
                        }
                    }
                }
            }

            foreach ($TargetSet as $a => $b) {
                foreach ($CombatCaps[$a]['sd'] as $c => $d) {
                    if (isset($CurrentSet[$c])) {
                        $attackersLeft[$c]['count'] = $attackersLeft[$c]['count'] - floor($d * rand(50, 100) / 100);
                        if ($attackersLeft[$c]['count'] <= 0) {
                            $attackersLeft[$c]['count'] = 0;
                        }
                    }
                }
            }

            $rounds[$i]["attacker"]["shield"] = $attackerShield;
            $rounds[$i]["defender"]["shield"] = $defenderShield;
            // print_r($rounds[$i]);
            $TargetSet = $defendersLeft;
            $CurrentSet = $attackersLeft;
        }

        if (($attackerCount == 0) or ($defenderCount == 0)) {
            if (($attackerCount == 0) and ($defenderCount == 0)) {
                $victory = "r";
            } else {
                if ($attackerCount == 0) {
                    $victory = "w";
                } else {
                    $victory = "a";
                }
            }
        } else {
            $i = sizeof($rounds);
            $rounds[$i]["attacker"] = $CurrentSet;
            $rounds[$i]["defender"] = $TargetSet;
            $rounds[$i]["attacker"]["attack"] = $attackerAttack;
            $rounds[$i]["defender"]["attack"] = $defenderAttack;
            $rounds[$i]["attacker"]['count'] = $attackerCount;
            $rounds[$i]["defender"]['count'] = $defenderCount;
            $victory = "r";
        }
        $attackerValueEnd['metal'] = 0;
        $attackerValueEnd['crystal'] = 0;
        if (!is_null($CurrentSet)) {
            foreach ($CurrentSet as $a => $b) {
                $attackerValueEnd['metal'] = $attackerValueEnd['metal'] + $CurrentSet[$a]['count'] * $pricelist[$a]['metal'];
                $attackerValueEnd['crystal'] = $attackerValueEnd['crystal'] + $CurrentSet[$a]['count'] * $pricelist[$a]['crystal'];
            }
        }
        $defenderValueEnd['metal'] = 0;
        $defenderValueEnd['crystal'] = 0;
        if (!is_null($TargetSet)) {
            foreach ($TargetSet as $a => $b) {
                if ($a < 300) {
                    $defenderValueEnd['metal'] = $defenderValueEnd['metal'] + $TargetSet[$a]['count'] * $pricelist[$a]['metal'];
                    $defenderValueEnd['crystal'] = $defenderValueEnd['crystal'] + $TargetSet[$a]['count'] * $pricelist[$a]['crystal'];
                } else {
                    $defenderDefenceValueEnd['metal'] = $defenderDefenceValueEnd['metal'] + $TargetSet[$a]['count'] * $pricelist[$a]['metal'];
                    $defenderDefenceValueEnd['crystal'] = $defenderDefenceValueEnd['crystal'] + $TargetSet[$a]['count'] * $pricelist[$a]['crystal'];
                }
            }
        }
        $defenderTotal = 0;
        $defenderDefenceLosses = 0;
        if (!is_null($TargetSet)) {
            foreach ($TargetSet as $a => $b) {
                if ($a > 300) {
                    $defenderDefenceLosses = $defenderDefenceLosses + (($defenderStart[$a]['count'] - $TargetSet[$a]['count']) * ($pricelist[$a]['metal'] + $pricelist[$a]['crystal']));
                    $TargetSet[$a]['count'] = $TargetSet[$a]['count'] + (($defenderStart[$a]['count'] - $TargetSet[$a]['count']) * rand(60, 80) / 100);
                    $defenderTotal = $defenderTotal + $TargetSet[$a]['count'];
                }
            }
        }
        if (($defenderTotal > 0) and ($attackerCount == 0)) {
            $victory = "w";
        }

        $debris['metal']    = ((($attackerValueStart['metal']   - $attackerValueEnd['metal'])   + ($defenderValueStart['metal']   - $defenderValueEnd['metal']))   * ($game_config['Fleet_Cdr'] / 100));
        $debris['crystal']  = ((($attackerValueStart['crystal'] - $attackerValueEnd['crystal']) + ($defenderValueStart['crystal'] - $defenderValueEnd['crystal'])) * ($game_config['Fleet_Cdr'] / 100));

        $debris['metal']   += ((($attackerValueStart['metal']   - $attackerValueEnd['metal'])   + ($defenderValueStart['metal']   - $defenderValueEnd['metal']))   * ($game_config['Defs_Cdr'] / 100));
        $debris['crystal'] += ((($attackerValueStart['crystal'] - $attackerValueEnd['crystal']) + ($defenderValueStart['crystal'] - $defenderValueEnd['crystal'])) * ($game_config['Defs_Cdr'] / 100));

        $debris["attacker"] = (($attackerValueStart['metal'] - $attackerValueEnd['metal']) + ($attackerValueStart['crystal'] - $attackerValueEnd['crystal']));
        $debris["defender"] = (($defenderValueStart['metal'] - $defenderValueEnd['metal']) + ($defenderValueStart['crystal'] - $defenderValueEnd['crystal']) + $defenderDefenceLosses);
        return array("attacker" => $CurrentSet, "defender" => $TargetSet, "victory" => $victory, "rounds" => $rounds, "debris" => $debris);
    }

    /**
     * Bonus de combat d'un camp, en fraction (le Coeur de l'application n'en applique aucun).
     *
     * Le Coeur d'application ne connaît aucun officier : le moteur ne recevait pas le compte, et
     * la variable d'origine valait donc `0` — la valeur rendue ici, à l'identique. La clé
     * `combat_bonus` de la ligne de technologies est le point de passage : c'est **le
     * dépôt qui l'écrit** (`UserRepository::findTechnologies()`), exactement comme les
     * colonnes qu'un module ajoute à une table du jeu.
     *
     * Un module peut aussi dériver cette classe et remplacer la méthode — mais deux
     * modules ne peuvent pas surcharger la même classe, donc la clé est le chemin
     * normal. Elle est `protected` **et** appelée en `static::` : PHP refuse qu'un
     * enfant redéfinisse une méthode `private`, et un appel en `self::` viserait
     * toujours celle du Coeur d'application.
     *
     * @param array $Techno technologies du camp (attaquant ou défenseur)
     */
    protected static function bonus(array $Techno): float
    {
        // Un **total**, pas une valeur : la clé porte une contribution ou la liste des
        // contributions déjà réunies, et elles s'additionnent. Sans contribution, zéro —
        // la valeur que le legacy appliquait. Deux modules ne pouvant pas surcharger la
        // même classe (les tests le refusent), celui qui tient le dépôt réunit les parts
        // et un module qui ajoute la sienne écrit la liste : `$parts[] = 0.05 * $niveau`
        // (voir `UserRepository::findTechnologies()` du module officier).
        $total = 0.0;
        foreach ((array) ($Techno['combat_bonus'] ?? array()) as $part) {
            $total += (float) $part;
        }

        return $total;
    }
}
