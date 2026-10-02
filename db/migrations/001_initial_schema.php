<?php

declare(strict_types=1);

/**
 * Migration 001 — schéma complet du jeu.
 *
 * **Fichier unique** : les migrations 002 à 024 ont été fusionnées ici, donc
 * chaque table naît avec ses colonnes définitives. Plus aucun `ALTER TABLE` :
 * une installation neuve obtient tout d'un coup, et une colonne nouvelle
 * s'ajoute à sa table au lieu d'être rattrapée par une modification.
 *
 * Le schéma est en **InnoDB / utf8mb3** (l'ancienne conversion MyISAM/latin1
 * n'a plus d'objet : le jeu naît directement dans le moteur définitif), et les
 * colonnes ne portent plus de `character set` par colonne, puisque toute la
 * table est en utf8mb3.
 *
 * **Une colonne qu'un module seul lit vit dans son module** : les colonnes d'officier
 * (`users.rpg_*`, `users.xpminier`, `xpraid`, `lvl_minier`, `lvl_raid`) sont créées par la
 * migration du module `officier`, l'appartenance d'alliance (`users.ally_id`,
 * `users.ally_name`) par celle du module `alliance`, et la candidature par ce même module —
 * toutes jouées juste après celle-ci. Le Core de l'application les atteint par **surcharge
 * de classe** (`Modules::overrideFor()`), jamais en les nommant.
 *
 * **Le reste du schéma d'un module vit dans son module** : `alliance`, `annonce`, `chat`,
 * `notes`, `market_ticks` et `market_positions` sont créés par la migration du module
 * (`modules/<nom>/db/migrations/`), jouée juste après celle-ci — comme les colonnes que
 * **son** code seul lit (`users.ally_request`, `users.ally_request_text`,
 * `users.ally_register_time`, `users.ally_rank_id`, `users.bot*`, `users.settings_bots`,
 * `planets.extractor`). Le Core ne les nomme **nulle part** : il les atteint par
 * **surcharge de classe** (`Modules::overrideFor()`), et une page qui n'existe plus sans
 * son module n'est écrite que dans le module. Un jeu qui n'a pas le module se contente de
 * ne pas avoir la fonctionnalité, et sa réinstallation plus tard crée ce qui manque
 * (`IF NOT EXISTS`, `ADD COLUMN` tolérés par le migrateur à cause du rejeu — voir
 * `Migrator::TOLERATED_ERRNOS`).
 *
 * Le journal d'erreurs (`errors`) a disparu : plus rien ne le lisait (sa page
 * d'administration était déjà retirée), il part avec `ErrorsRepository` et
 * `App\Entities\GameError`.
 *
 * Les réglages de la table `config` sont la copie de `App\Core\ConfigDefaults`
 * (même liste, mêmes valeurs) : l'installateur les sème ensuite avec
 * `GameConfig::seed()`, qui applique en plus les variables `CONFIG_*` de
 * l'environnement.
 *
 * La table de suivi (`<prefixe>migrations`) n'est pas ici : le migrateur la crée
 * lui-même (`Migrator::ensureTable()`).
 *
 * @return array{up: list<array{0: string, 1: string}>, down: list<array{0: string, 1: string}>}
 */

// ---------------------------------------------------------------------------
// Journal des actions (pages et routes JSON)
// ---------------------------------------------------------------------------
$QryTableActions  = "CREATE TABLE `{{table}}` ( ";
$QryTableActions .= "`id` bigint(11) NOT NULL auto_increment, ";
$QryTableActions .= "`id_owner` int(11) NOT NULL default '0', ";
$QryTableActions .= "`action` varchar(64) NOT NULL default '', ";
$QryTableActions .= "`kind` varchar(8) NOT NULL default 'page', ";
$QryTableActions .= "`method` varchar(8) NOT NULL default 'GET', ";
$QryTableActions .= "`detail` varchar(255) NOT NULL default '', ";
$QryTableActions .= "`payload` varchar(1000) NOT NULL default '', ";
$QryTableActions .= "`user_lastip` varchar(16) NOT NULL default '', ";
$QryTableActions .= "`action_time` int(11) NOT NULL default '0', ";
$QryTableActions .= "PRIMARY KEY (`id`), ";
$QryTableActions .= "KEY `id_owner` (`id_owner`,`action_time`), ";
$QryTableActions .= "KEY `action` (`action`), ";
$QryTableActions .= "KEY `action_time` (`action_time`) ";
$QryTableActions .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Attaques groupées
// ---------------------------------------------------------------------------
$QryTableAks  = "CREATE TABLE `{{table}}` ( ";
$QryTableAks .= "`id` bigint(20) unsigned NOT NULL auto_increment, ";
$QryTableAks .= "`name` varchar(50) default NULL, ";
$QryTableAks .= "`participants` mediumtext, ";
$QryTableAks .= "`fleets` mediumtext, ";
$QryTableAks .= "`arrival` int(32) default NULL, ";
$QryTableAks .= "`galaxy` int(2) default NULL, ";
$QryTableAks .= "`system` int(4) default NULL, ";
$QryTableAks .= "`planet` int(2) default NULL, ";
$QryTableAks .= "`invited` int(11) default NULL, ";
$QryTableAks .= "PRIMARY KEY (`id`) ";
$QryTableAks .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Bannissements
// ---------------------------------------------------------------------------
$QryTableBanned  = "CREATE TABLE `{{table}}` ( ";
$QryTableBanned .= "`id` bigint(11) NOT NULL auto_increment, ";
$QryTableBanned .= "`who` varchar(11) NOT NULL default '', ";
$QryTableBanned .= "`theme` mediumtext NOT NULL, ";
$QryTableBanned .= "`who2` varchar(11) NOT NULL default '', ";
$QryTableBanned .= "`time` int(11) NOT NULL default '0', ";
$QryTableBanned .= "`longer` int(11) NOT NULL default '0', ";
$QryTableBanned .= "`author` varchar(11) NOT NULL default '', ";
$QryTableBanned .= "`email` varchar(20) NOT NULL default '', ";
$QryTableBanned .= "KEY `ID` (`id`) ";
$QryTableBanned .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Liste d'amis
// ---------------------------------------------------------------------------
$QryTableBuddy  = "CREATE TABLE `{{table}}` ( ";
$QryTableBuddy .= "`id` bigint(11) NOT NULL auto_increment, ";
$QryTableBuddy .= "`sender` int(11) NOT NULL default '0', ";
$QryTableBuddy .= "`owner` int(11) NOT NULL default '0', ";
$QryTableBuddy .= "`active` tinyint(3) NOT NULL default '0', ";
$QryTableBuddy .= "`text` mediumtext, ";
$QryTableBuddy .= "PRIMARY KEY (`id`) ";
$QryTableBuddy .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Réglages du jeu
// ---------------------------------------------------------------------------
$QryTableConfig  = "CREATE TABLE `{{table}}` ( ";
$QryTableConfig .= "`config_name` varchar(64) NOT NULL default '', ";
$QryTableConfig .= "`config_value` text NOT NULL, ";
$QryTableConfig .= "PRIMARY KEY (`config_name`) ";
$QryTableConfig .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// Valeurs de base de la config — la même liste que `App\Core\ConfigDefaults::DEFAULTS`,
// que l'installateur sème ensuite (`GameConfig::seed()`).
$QryInsertConfig  = "INSERT INTO `{{table}}` ";
$QryInsertConfig .= "(`config_name`           , `config_value`) VALUES ";
$QryInsertConfig .= "('users_amount'          , '0'), ";
$QryInsertConfig .= "('game_speed'            , '2500'), ";
$QryInsertConfig .= "('fleet_speed'           , '2500'), ";
$QryInsertConfig .= "('resource_multiplier'   , '1'), ";
$QryInsertConfig .= "('Fleet_Cdr'             , '30'), ";
$QryInsertConfig .= "('Defs_Cdr'              , '30'), ";
$QryInsertConfig .= "('initial_fields'        , '163'), ";
$QryInsertConfig .= "('COOKIE_NAME'           , 'XNova'), ";
$QryInsertConfig .= "('game_name'             , 'XNova'), ";
$QryInsertConfig .= "('game_disable'          , '0'), ";
$QryInsertConfig .= "('close_reason'          , ''), ";
$QryInsertConfig .= "('metal_basic_income'    , '20'), ";
$QryInsertConfig .= "('crystal_basic_income'  , '10'), ";
$QryInsertConfig .= "('deuterium_basic_income', '0'), ";
$QryInsertConfig .= "('energy_basic_income'   , '0'), ";
$QryInsertConfig .= "('BuildLabWhileRun'      , '0'), ";
$QryInsertConfig .= "('LastSettedGalaxyPos'   , '1'), ";
$QryInsertConfig .= "('LastSettedSystemPos'   , '9'), ";
$QryInsertConfig .= "('LastSettedPlanetPos'   , '1'), ";
$QryInsertConfig .= "('vacation_mode_enforced'  , '1'), ";
$QryInsertConfig .= "('noobprotection'        , '1'), ";
$QryInsertConfig .= "('noobprotectiontime'    , '5000'), ";
$QryInsertConfig .= "('noobprotectionmulti'   , '5'), ";
$QryInsertConfig .= "('forum_url'             , 'http://board.xnova-ng.org/'), ";
$QryInsertConfig .= "('OverviewNewsFrame'     , '1'), ";
$QryInsertConfig .= "('OverviewNewsText'      , 'Vous avez correctement mis votre serveur UGamela sous XNova!'), ";
$QryInsertConfig .= "('OverviewExternChat'    , '0'), ";
$QryInsertConfig .= "('OverviewExternChatCmd' , ''), ";
$QryInsertConfig .= "('OverviewBanner'        , '0'), ";
$QryInsertConfig .= "('OverviewClickBanner'   , ''), ";
$QryInsertConfig .= "('ExtCopyFrame'          , '0'), ";
$QryInsertConfig .= "('ExtCopyOwner'          , ''), ";
$QryInsertConfig .= "('ExtCopyFunct'          , ''), ";
$QryInsertConfig .= "('ForumBannerFrame'      , '0'), ";
$QryInsertConfig .= "('stat_settings'         , '1000'), ";
$QryInsertConfig .= "('link_enable'           , '0'), ";
$QryInsertConfig .= "('link_name'             , ''), ";
$QryInsertConfig .= "('link_url'              , ''), ";
$QryInsertConfig .= "('enable_announces'      , '1'), ";
$QryInsertConfig .= "('enable_marchand'       , '1'), ";
$QryInsertConfig .= "('enable_notes'          , '1'), ";
$QryInsertConfig .= "('bot_name'              , 'XNoviana Reali'), ";
$QryInsertConfig .= "('bot_adress'            , 'xnova@xnova.fr'), ";
$QryInsertConfig .= "('banner_source_post'    , '../images/bann.png'), ";
$QryInsertConfig .= "('ban_duration'          , '30'), ";
$QryInsertConfig .= "('enable_bot'            , '0'), ";
$QryInsertConfig .= "('enable_bbcode'         , '1'), ";
$QryInsertConfig .= "('debug'                 , '0') ";
$QryInsertConfig .= ";";

// ---------------------------------------------------------------------------
// Déclarations de multi-comptes
// ---------------------------------------------------------------------------
$QryTabledeclared  = "CREATE TABLE `{{table}}` ( ";
$QryTabledeclared .= "`declarator` mediumtext NOT NULL, ";
$QryTabledeclared .= "`declared_1` mediumtext NOT NULL, ";
$QryTabledeclared .= "`declared_2` mediumtext NOT NULL, ";
$QryTabledeclared .= "`declared_3` mediumtext NOT NULL, ";
$QryTabledeclared .= "`reason` mediumtext NOT NULL, ";
$QryTabledeclared .= "`declarator_name` mediumtext NOT NULL ";
$QryTabledeclared .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Flottes en vol
// ---------------------------------------------------------------------------
$QryTableFleets  = "CREATE TABLE `{{table}}` ( ";
$QryTableFleets .= "`fleet_id` bigint(11) NOT NULL auto_increment, ";
$QryTableFleets .= "`fleet_owner` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_mission` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_amount` bigint(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_array` mediumtext, ";
$QryTableFleets .= "`fleet_start_time` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_start_galaxy` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_start_system` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_start_planet` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_start_type` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_end_time` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_end_stay` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_end_galaxy` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_end_system` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_end_planet` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_end_type` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_taget_owner` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_resource_metal` bigint(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_resource_crystal` bigint(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_resource_deuterium` bigint(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_target_owner` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_group` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`fleet_mess` int(11) NOT NULL default '0', ";
$QryTableFleets .= "`start_time` int(11) default NULL, ";
$QryTableFleets .= "`flags` int(11) NOT NULL default '2', ";
$QryTableFleets .= "`fleet_primary` int(11) NOT NULL default '0', ";
$QryTableFleets .= "PRIMARY KEY (`fleet_id`) ";
$QryTableFleets .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Vue galaxie (champ de débris)
// ---------------------------------------------------------------------------
$QryTableGalaxy  = "CREATE TABLE `{{table}}` ( ";
$QryTableGalaxy .= "`galaxy` int(2) NOT NULL default '0', ";
$QryTableGalaxy .= "`system` int(3) NOT NULL default '0', ";
$QryTableGalaxy .= "`planet` int(2) NOT NULL default '0', ";
$QryTableGalaxy .= "`id_planet` int(11) NOT NULL default '0', ";
$QryTableGalaxy .= "`metal` bigint(11) NOT NULL default '0', ";
$QryTableGalaxy .= "`crystal` bigint(11) NOT NULL default '0', ";
// `deuterium` (la part de deutérium du champ de débris) appartient au module
// « extracteurs », qui l'ajoute par sa propre migration : le Coeur d'application n'écrit
// que le métal et le cristal, et la vue lit les champs que les modules déclarent.
$QryTableGalaxy .= "`id_luna` int(11) NOT NULL default '0', ";
$QryTableGalaxy .= "`luna` int(2) NOT NULL default '0', ";
$QryTableGalaxy .= "KEY `galaxy` (`galaxy`), ";
$QryTableGalaxy .= "KEY `system` (`system`), ";
$QryTableGalaxy .= "KEY `planet` (`planet`) ";
$QryTableGalaxy .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Registre des lunes
// ---------------------------------------------------------------------------
$QryTableLunas  = "CREATE TABLE `{{table}}` ( ";
$QryTableLunas .= "`id` bigint(11) NOT NULL auto_increment, ";
$QryTableLunas .= "`id_luna` int(11) NOT NULL default '0', ";
$QryTableLunas .= "`name` varchar(11) NOT NULL default 'Lune', ";
$QryTableLunas .= "`image` varchar(11) NOT NULL default 'moon', ";
$QryTableLunas .= "`destruyed` int(11) NOT NULL default '0', ";
$QryTableLunas .= "`id_owner` int(11) default NULL, ";
$QryTableLunas .= "`galaxy` int(11) default NULL, ";
$QryTableLunas .= "`system` int(11) default NULL, ";
$QryTableLunas .= "`lunapos` int(11) default NULL, ";
$QryTableLunas .= "`temp_min` int(11) NOT NULL default '0', ";
$QryTableLunas .= "`temp_max` int(11) NOT NULL default '0', ";
$QryTableLunas .= "`diameter` int(11) NOT NULL default '0', ";
$QryTableLunas .= "`flags` int(11) NOT NULL default '2', ";
$QryTableLunas .= "PRIMARY KEY (`id`) ";
$QryTableLunas .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Boîte de messages
// ---------------------------------------------------------------------------
$QryTableMessages  = "CREATE TABLE `{{table}}` ( ";
$QryTableMessages .= "`message_id` bigint(11) NOT NULL auto_increment, ";
$QryTableMessages .= "`message_owner` int(11) NOT NULL default '0', ";
$QryTableMessages .= "`message_sender` int(11) NOT NULL default '0', ";
$QryTableMessages .= "`message_time` int(11) NOT NULL default '0', ";
$QryTableMessages .= "`message_type` int(11) NOT NULL default '0', ";
$QryTableMessages .= "`message_from` varchar(48) default NULL, ";
$QryTableMessages .= "`message_subject` varchar(48) default NULL, ";
$QryTableMessages .= "`message_text` mediumtext, ";
$QryTableMessages .= "`flags` int(11) NOT NULL default '2', ";
$QryTableMessages .= "PRIMARY KEY (`message_id`) ";
$QryTableMessages .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Registre des modules installés
// ---------------------------------------------------------------------------
$QryTableModules  = "CREATE TABLE `{{table}}` ( ";
$QryTableModules .= "`id` int(11) NOT NULL auto_increment, ";
$QryTableModules .= "`name` varchar(50) NOT NULL, ";
$QryTableModules .= "`active` tinyint(1) NOT NULL default '1', ";
$QryTableModules .= "`settings` varchar(2000) NOT NULL default '', ";
$QryTableModules .= "`flags` int(11) NOT NULL default '2', ";
$QryTableModules .= "`created_time` int(11) NOT NULL default '0', ";
$QryTableModules .= "PRIMARY KEY (`id`), ";
$QryTableModules .= "UNIQUE KEY `name` (`name`) ";
$QryTableModules .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Multi-comptes (comptes liés)
// ---------------------------------------------------------------------------
$QryTableMulti  = "CREATE TABLE `{{table}}` ( ";
$QryTableMulti .= "`id` int(11) NOT NULL auto_increment, ";
$QryTableMulti .= "`player` bigint(11) unsigned NOT NULL, ";
$QryTableMulti .= "`sharer` bigint(11) unsigned NOT NULL, ";
$QryTableMulti .= "`reason` mediumtext NOT NULL, ";
$QryTableMulti .= "PRIMARY KEY (`id`) ";
$QryTableMulti .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Planètes (et lunes : meme table, `planet_type` = 3)
// ---------------------------------------------------------------------------
$QryTablePlanets  = "CREATE TABLE `{{table}}` ( ";
$QryTablePlanets .= "`id` bigint(11) NOT NULL auto_increment, ";
$QryTablePlanets .= "`name` varchar(255) default NULL, ";
$QryTablePlanets .= "`id_owner` int(11) default NULL, ";
$QryTablePlanets .= "`id_level` int(11) default NULL, ";
$QryTablePlanets .= "`galaxy` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`system` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`planet` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`last_update` int(11) default NULL, ";
$QryTablePlanets .= "`planet_type` int(11) NOT NULL default '1', ";
$QryTablePlanets .= "`destruyed` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`b_building` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`b_building_id` mediumtext, ";
$QryTablePlanets .= "`b_tech` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`b_tech_id` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`b_hangar` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`b_hangar_id` mediumtext, ";
$QryTablePlanets .= "`b_hangar_plus` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`image` varchar(32) NOT NULL default 'normaltempplanet01', ";
$QryTablePlanets .= "`diameter` int(11) NOT NULL default '12800', ";
$QryTablePlanets .= "`points` bigint(20) default '0', ";
$QryTablePlanets .= "`ranks` bigint(20) default '0', ";
$QryTablePlanets .= "`field_current` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`field_max` int(11) NOT NULL default '163', ";
$QryTablePlanets .= "`temp_min` int(3) NOT NULL default '-17', ";
$QryTablePlanets .= "`temp_max` int(3) NOT NULL default '23', ";
$QryTablePlanets .= "`metal` double(132,8) NOT NULL default '0.00000000', ";
$QryTablePlanets .= "`metal_perhour` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`metal_max` bigint(20) default '100000', ";
$QryTablePlanets .= "`crystal` double(132,8) NOT NULL default '0.00000000', ";
$QryTablePlanets .= "`crystal_perhour` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`crystal_max` bigint(20) default '100000', ";
$QryTablePlanets .= "`deuterium` double(132,8) NOT NULL default '0.00000000', ";
$QryTablePlanets .= "`deuterium_perhour` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`deuterium_max` bigint(20) default '100000', ";
$QryTablePlanets .= "`energy_used` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`energy_max` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`metal_mine` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`crystal_mine` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`deuterium_sintetizer` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`solar_plant` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`fusion_plant` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`robot_factory` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`nano_factory` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`particle_accelerator` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`hangar` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`metal_store` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`crystal_store` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`deuterium_store` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`laboratory` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`terraformer` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`ally_deposit` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`silo` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`small_ship_cargo` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`big_ship_cargo` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`light_hunter` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`heavy_hunter` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`crusher` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`battle_ship` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`colonizer` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`recycler` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`spy_sonde` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`bomber_ship` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`solar_satelit` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`destructor` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`dearth_star` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`battleship` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`misil_launcher` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`small_laser` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`big_laser` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`gauss_canyon` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`ionic_canyon` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`buster_canyon` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`small_protection_shield` enum('0','1') NOT NULL default '0', ";
$QryTablePlanets .= "`big_protection_shield` enum('0','1') NOT NULL default '0', ";
$QryTablePlanets .= "`interceptor_misil` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`interplanetary_misil` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`metal_mine_porcent` int(11) NOT NULL default '10', ";
$QryTablePlanets .= "`crystal_mine_porcent` int(11) NOT NULL default '10', ";
$QryTablePlanets .= "`deuterium_sintetizer_porcent` int(11) NOT NULL default '10', ";
$QryTablePlanets .= "`solar_plant_porcent` int(11) NOT NULL default '10', ";
$QryTablePlanets .= "`fusion_plant_porcent` int(11) NOT NULL default '10', ";
$QryTablePlanets .= "`solar_satelit_porcent` int(11) NOT NULL default '10', ";
$QryTablePlanets .= "`moon_base` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`phalanx` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`jump_gate` bigint(11) NOT NULL default '0', ";
$QryTablePlanets .= "`last_jump_time` int(11) NOT NULL default '0', ";
$QryTablePlanets .= "`flags` int(11) NOT NULL default '2', ";
$QryTablePlanets .= "PRIMARY KEY (`id`) ";
$QryTablePlanets .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Rôles du panneau d'administration (permissions)
// ---------------------------------------------------------------------------
$QryTableRoles  = "CREATE TABLE `{{table}}` ( ";
$QryTableRoles .= "`id` int(11) NOT NULL auto_increment, ";
$QryTableRoles .= "`name` varchar(50) NOT NULL, ";
$QryTableRoles .= "`label` varchar(100) NOT NULL, ";
$QryTableRoles .= "`description` varchar(255) NOT NULL default '', ";
$QryTableRoles .= "`position` int(11) NOT NULL default '0', ";
$QryTableRoles .= "`permissions` text NOT NULL, ";
$QryTableRoles .= "`flags` int(11) NOT NULL default '2', ";
$QryTableRoles .= "`created_time` int(11) NOT NULL default '0', ";
$QryTableRoles .= "PRIMARY KEY (`id`), ";
$QryTableRoles .= "UNIQUE KEY `name` (`name`) ";
$QryTableRoles .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Rapports de combat
// ---------------------------------------------------------------------------
$QryTableRw  = "CREATE TABLE `{{table}}` ( ";
$QryTableRw .= "`id_owner1` int(11) NOT NULL default '0', ";
$QryTableRw .= "`id_owner2` int(11) NOT NULL default '0', ";
$QryTableRw .= "`rid` varchar(72) NOT NULL, ";
$QryTableRw .= "`raport` mediumtext NOT NULL, ";
$QryTableRw .= "`struck` tinyint(3) unsigned NOT NULL default '0', ";
$QryTableRw .= "`time` int(10) unsigned NOT NULL default '0', ";
$QryTableRw .= "UNIQUE KEY `rid` (`rid`), ";
$QryTableRw .= "KEY `id_owner1` (`id_owner1`,`rid`), ";
$QryTableRw .= "KEY `id_owner2` (`id_owner2`,`rid`), ";
$QryTableRw .= "KEY `time` (`time`), ";
$QryTableRw .= "FULLTEXT KEY `raport` (`raport`) ";
$QryTableRw .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Historique des connexions
// ---------------------------------------------------------------------------
$QryTableSessions  = "CREATE TABLE `{{table}}` ( ";
$QryTableSessions .= "`id` bigint(11) NOT NULL auto_increment, ";
$QryTableSessions .= "`id_owner` int(11) NOT NULL default '0', ";
$QryTableSessions .= "`login_time` int(11) NOT NULL default '0', ";
$QryTableSessions .= "`last_activity` int(11) NOT NULL default '0', ";
$QryTableSessions .= "`logout_time` int(11) NOT NULL default '0', ";
$QryTableSessions .= "`end_reason` varchar(20) NOT NULL default '', ";
$QryTableSessions .= "`user_lastip` varchar(16) NOT NULL default '', ";
$QryTableSessions .= "PRIMARY KEY (`id`), ";
$QryTableSessions .= "KEY `id_owner` (`id_owner`,`login_time`), ";
$QryTableSessions .= "KEY `last_activity` (`last_activity`), ";
$QryTableSessions .= "KEY `login_time` (`login_time`) ";
$QryTableSessions .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Classement (recalculé par StatsService)
// ---------------------------------------------------------------------------
$QryTableStatPoints  = "CREATE TABLE `{{table}}` ( ";
$QryTableStatPoints .= "`id_owner` int(11) NOT NULL, ";
$QryTableStatPoints .= "`id_ally` int(11) NOT NULL, ";
$QryTableStatPoints .= "`stat_type` int(2) NOT NULL, ";
$QryTableStatPoints .= "`stat_code` int(11) NOT NULL, ";
$QryTableStatPoints .= "`tech_rank` int(11) NOT NULL, ";
$QryTableStatPoints .= "`tech_old_rank` int(11) NOT NULL, ";
$QryTableStatPoints .= "`tech_points` bigint(20) NOT NULL, ";
$QryTableStatPoints .= "`tech_count` int(11) NOT NULL, ";
$QryTableStatPoints .= "`build_rank` int(11) NOT NULL, ";
$QryTableStatPoints .= "`build_old_rank` int(11) NOT NULL, ";
$QryTableStatPoints .= "`build_points` bigint(20) NOT NULL, ";
$QryTableStatPoints .= "`build_count` int(11) NOT NULL, ";
$QryTableStatPoints .= "`defs_rank` int(11) NOT NULL, ";
$QryTableStatPoints .= "`defs_old_rank` int(11) NOT NULL, ";
$QryTableStatPoints .= "`defs_points` bigint(20) NOT NULL, ";
$QryTableStatPoints .= "`defs_count` int(11) NOT NULL, ";
$QryTableStatPoints .= "`fleet_rank` int(11) NOT NULL, ";
$QryTableStatPoints .= "`fleet_old_rank` int(11) NOT NULL, ";
$QryTableStatPoints .= "`fleet_points` bigint(20) NOT NULL, ";
$QryTableStatPoints .= "`fleet_count` int(11) NOT NULL, ";
$QryTableStatPoints .= "`total_rank` int(11) NOT NULL, ";
$QryTableStatPoints .= "`total_old_rank` int(11) NOT NULL, ";
$QryTableStatPoints .= "`total_points` bigint(20) NOT NULL, ";
$QryTableStatPoints .= "`total_count` int(11) NOT NULL, ";
$QryTableStatPoints .= "`stat_date` int(11) NOT NULL, ";
$QryTableStatPoints .= "KEY `TECH` (`tech_points`), ";
$QryTableStatPoints .= "KEY `BUILDS` (`build_points`), ";
$QryTableStatPoints .= "KEY `DEFS` (`defs_points`), ";
$QryTableStatPoints .= "KEY `FLEET` (`fleet_points`), ";
$QryTableStatPoints .= "KEY `TOTAL` (`total_points`) ";
$QryTableStatPoints .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

// ---------------------------------------------------------------------------
// Comptes joueurs
// ---------------------------------------------------------------------------
$QryTableUsers  = "CREATE TABLE `{{table}}` ( ";
$QryTableUsers .= "`id` bigint(11) unsigned NOT NULL auto_increment, ";
$QryTableUsers .= "`username` varchar(64) NOT NULL default '', ";
$QryTableUsers .= "`password` varchar(64) NOT NULL default '', ";
$QryTableUsers .= "`email` varchar(64) NOT NULL default '', ";
$QryTableUsers .= "`email_2` varchar(64) NOT NULL default '', ";
$QryTableUsers .= "`lang` varchar(8) NOT NULL default 'fr', ";
$QryTableUsers .= "`authlevel` tinyint(4) NOT NULL default '0', ";
$QryTableUsers .= "`role_id` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`sex` char(1) default NULL, ";
$QryTableUsers .= "`avatar` varchar(255) NOT NULL default '', ";
$QryTableUsers .= "`sign` mediumtext, ";
$QryTableUsers .= "`id_planet` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`galaxy` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`system` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`planet` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`current_planet` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`user_lastip` varchar(16) NOT NULL default '', ";
$QryTableUsers .= "`ip_at_reg` varchar(16) NOT NULL default '', ";
$QryTableUsers .= "`user_agent` mediumtext, ";
$QryTableUsers .= "`current_page` mediumtext, ";
$QryTableUsers .= "`register_time` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`onlinetime` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`dpath` varchar(255) NOT NULL default '', ";
$QryTableUsers .= "`design` tinyint(4) NOT NULL default '1', ";
$QryTableUsers .= "`noipcheck` tinyint(4) NOT NULL default '1', ";
$QryTableUsers .= "`planet_sort` tinyint(1) NOT NULL default '0', ";
$QryTableUsers .= "`planet_sort_order` tinyint(1) NOT NULL default '0', ";
$QryTableUsers .= "`spy_count` tinyint(4) NOT NULL default '1', ";
$QryTableUsers .= "`settings_tooltiptime` tinyint(4) NOT NULL default '5', ";
$QryTableUsers .= "`settings_fleetactions` tinyint(4) NOT NULL default '0', ";
$QryTableUsers .= "`settings_allylogo` tinyint(4) NOT NULL default '0', ";
$QryTableUsers .= "`settings_esp` tinyint(4) NOT NULL default '1', ";
$QryTableUsers .= "`settings_wri` tinyint(4) NOT NULL default '1', ";
$QryTableUsers .= "`settings_bud` tinyint(4) NOT NULL default '1', ";
$QryTableUsers .= "`settings_mis` tinyint(4) NOT NULL default '1', ";
$QryTableUsers .= "`settings_rep` tinyint(4) NOT NULL default '0', ";
$QryTableUsers .= "`vacation_mode` tinyint(4) NOT NULL default '0', ";
$QryTableUsers .= "`vacation_until` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`no_javascript` tinyint(4) NOT NULL default '0', ";
$QryTableUsers .= "`new_message` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`fleet_shortcut` mediumtext, ";
$QryTableUsers .= "`b_tech_planet` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`spy_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`computer_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`military_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`defence_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`shield_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`energy_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`hyperspace_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`combustion_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`impulse_motor_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`hyperspace_motor_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`laser_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`ionic_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`buster_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`intergalactic_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`expedition_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`graviton_tech` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`current_luna` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`kolorminus` varchar(11) NOT NULL default 'red', ";
$QryTableUsers .= "`kolorplus` varchar(11) NOT NULL default '#00FF00', ";
$QryTableUsers .= "`kolorpoziom` varchar(11) NOT NULL default 'yellow', ";
$QryTableUsers .= "`raids` bigint(20) NOT NULL default '0', ";
$QryTableUsers .= "`p_infligees` bigint(20) NOT NULL default '0', ";
$QryTableUsers .= "`menu_alliance` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`menu_player` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`menu_attaque` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`menu_spy` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`menu_exploit` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`menu_transport` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`menu_expedition` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`menu_general` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`menu_buildlist` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`bana` int(11) default NULL, ";
$QryTableUsers .= "`multi_validated` int(11) default NULL, ";
$QryTableUsers .= "`banaday` int(11) default NULL, ";
$QryTableUsers .= "`deleted_time` int(11) NOT NULL default '0', ";
$QryTableUsers .= "`raids1` int(11) default NULL, ";
$QryTableUsers .= "`raidswin` int(11) default NULL, ";
$QryTableUsers .= "`raidsloose` int(11) default NULL, ";
$QryTableUsers .= "`b_tech_queue` mediumtext, ";
// Les colonnes d'officier et de progression (`rpg_*`, `xpminier`, `xpraid`,
// `lvl_minier`, `lvl_raid`) sont créées par la migration du module `officier` : le
// Core de l'application ne les nomme plus.
// L'appartenance d'alliance (`ally_id`, `ally_name`) vient de la migration du module
// `alliance`, avec sa table et sa candidature.
$QryTableUsers .= "`flags` int(11) NOT NULL default '2', ";
$QryTableUsers .= "PRIMARY KEY (`id`) ";
$QryTableUsers .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

return array(
    // `up` : le schéma complet, dans l'ordre historique de création. La table
    // `config` reçoit ses réglages juste après sa création.
    'up' => array(
        array('actions', $QryTableActions),
        array('aks', $QryTableAks),
        array('banned', $QryTableBanned),
        array('buddy', $QryTableBuddy),
        array('config', $QryTableConfig),
        array('config', $QryInsertConfig),
        array('declared', $QryTabledeclared),
        array('fleets', $QryTableFleets),
        array('galaxy', $QryTableGalaxy),
        array('lunas', $QryTableLunas),
        array('messages', $QryTableMessages),
        array('modules', $QryTableModules),
        array('multi', $QryTableMulti),
        array('planets', $QryTablePlanets),
        array('roles', $QryTableRoles),
        array('rw', $QryTableRw),
        array('sessions', $QryTableSessions),
        array('statpoints', $QryTableStatPoints),
        array('users', $QryTableUsers),
    ),

    // `down` : annulation explicite (supprime les tables, donc les données).
    // Aucune clé étrangère à respecter, l'ordre inverse suffit.
    'down' => array(
        array('users', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('statpoints', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('sessions', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('rw', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('roles', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('planets', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('multi', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('modules', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('messages', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('lunas', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('galaxy', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('fleets', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('declared', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('config', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('buddy', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('banned', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('aks', 'DROP TABLE IF EXISTS `{{table}}`'),
        array('actions', 'DROP TABLE IF EXISTS `{{table}}`'),
    ),
);
