<?php

namespace App\Core;

/**
 * Calculs de flotte (distances, durées, vitesses, consommations).
 * Porte-métier de includes/unlocalised.php.
 */
final class FleetMath
{
    public static function targetDistance(int $origGalaxy, int $destGalaxy, int $origSystem, int $destSystem, int $origPlanet, int $destPlanet): int
    {
        if (($origGalaxy - $destGalaxy) != 0) {
            return abs($origGalaxy - $destGalaxy) * 20000;
        }

        if (($origSystem - $destSystem) != 0) {
            return abs($origSystem - $destSystem) * 5 * 19 + 2700;
        }

        if (($origPlanet - $destPlanet) != 0) {
            return abs($origPlanet - $destPlanet) * 5 + 1000;
        }

        return 5;
    }

    public static function missionDuration($gameSpeed, $maxFleetSpeed, $distance, $speedFactor): int
    {
        // La vitesse des flottes vient de `fleet_speed` (game_config) : $speedFactor
        // est fourni par gameSpeedFactor().
        return (int) round(((35000 / $gameSpeed * sqrt($distance * 10 / $maxFleetSpeed) + 10) / $speedFactor));
    }

    public static function gameSpeedFactor(): float
    {
        return GameSpeed::multiplier(GameConfig::get('fleet_speed', (string) GameSpeed::NORMAL));
    }

    public static function fleetMaxSpeed($fleetArray, $fleet, array $player)
    {
        $pricelist = GameData::priceList();

        if (!is_array($fleetArray)) {
            $fleetArray = array();
        }
        if ($fleet != 0) {
            $fleetArray[$fleet] = 1;
        }

        $speedalls = array();
        foreach ($fleetArray as $ship => $count) {
            $ship = (int) $ship;
            if (!isset($pricelist[$ship]['speed'])) {
                continue;
            }
            $speed = $pricelist[$ship]['speed'];

            if ($ship == 202) {
                if (($player['impulse_motor_tech'] ?? 0) >= 5) {
                    $speedalls[$ship] = $pricelist[$ship]['speed2'] + (($speed * $player['impulse_motor_tech']) * 0.2);
                } else {
                    $speedalls[$ship] = $speed + (($speed * ($player['combustion_tech'] ?? 0)) * 0.1);
                }
            } elseif (in_array($ship, array(203, 204, 209, 210), true)) {
                $speedalls[$ship] = $speed + (($speed * ($player['combustion_tech'] ?? 0)) * 0.1);
            } elseif (in_array($ship, array(205, 206, 208), true)) {
                $speedalls[$ship] = $speed + (($speed * ($player['impulse_motor_tech'] ?? 0)) * 0.2);
            } elseif ($ship == 211) {
                if (($player['hyperspace_motor_tech'] ?? 0) >= 8) {
                    $speedalls[$ship] = $pricelist[$ship]['speed2'] + (($speed * $player['hyperspace_motor_tech']) * 0.3);
                } else {
                    $speedalls[$ship] = $speed + (($speed * ($player['impulse_motor_tech'] ?? 0)) * 0.2);
                }
            } elseif (in_array($ship, array(207, 213, 214, 215, 216), true)) {
                $speedalls[$ship] = $speed + (($speed * ($player['hyperspace_motor_tech'] ?? 0)) * 0.3);
            }
        }

        if ($fleet != 0) {
            $shipSpeed = $speedalls[$fleet] ?? 0;
            if ($shipSpeed == 0) {
                return 0;
            }

            return $shipSpeed;
        }

        return $speedalls;
    }

    public static function shipConsumption($ship, array $player)
    {
        $pricelist = GameData::priceList();

        if (($player['impulse_motor_tech'] ?? 0) >= 5) {
            return $pricelist[$ship]['consumption2'];
        }

        return $pricelist[$ship]['consumption'];
    }

    public static function fleetConsumption($fleetArray, $speedFactor, $missionDuration, $missionDistance, $fleetMaxSpeed, array $player): int
    {
        $consumption = 0;

        foreach ($fleetArray as $ship => $count) {
            if ($ship <= 0) {
                continue;
            }
            $shipSpeed = self::fleetMaxSpeed("", $ship, $player);
            $shipConsumption = self::shipConsumption($ship, $player);

            $denominator = $missionDuration * $speedFactor - 10;
            if ($shipSpeed <= 0 || $denominator == 0) {
                continue;
            }

            $spd = 35000 / $denominator * sqrt($missionDistance * 10 / $shipSpeed);
            $basicConsumption = $shipConsumption * $count;
            $consumption += $basicConsumption * $missionDistance / 35000 * (($spd / 10) + 1) * (($spd / 10) + 1);
        }

        return (int) round($consumption) + 1;
    }

    /** Délai avant le prochain saut de porte (ex GetNextJumpWaitTime). */
    public static function nextJumpWaitTime(array $curMoon): array
    {
        $resource = GameData::resource();

        $jumpGateLevel = $curMoon[$resource[43]] ?? 0;
        $lastJumpTime = $curMoon['last_jump_time'] ?? 0;

        if ($jumpGateLevel > 0) {
            $waitBetweenJmp = (60 * 60) * (1 / $jumpGateLevel);
            $nextJumpTime = $lastJumpTime + $waitBetweenJmp;

            if ($nextJumpTime >= time()) {
                $restWait = $nextJumpTime - time();
                $restString = " " . Format::prettyTime($restWait);
            } else {
                $restWait = 0;
                $restString = "";
            }
        } else {
            $restWait = 0;
            $restString = "";
        }

        return array('string' => $restString, 'value' => $restWait);
    }
}
