/**
 * Zugzwang Project
 * SQL for installation of zzform module
 *
 * http://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2020 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


CREATE TABLE `_logging` (
  `log_id` int unsigned NOT NULL AUTO_INCREMENT,
  `query` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int unsigned DEFAULT NULL,
  `user` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `record_id` (`record_id`),
  KEY `last_update` (`last_update`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `_relations` (
  `rel_id` int unsigned NOT NULL AUTO_INCREMENT,
  `master_db` varchar(127) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `master_table` varchar(127) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `master_field` varchar(127) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `detail_db` varchar(127) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `detail_table` varchar(127) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `detail_field` varchar(127) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `delete` enum('delete','ask','no-delete','update') CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL DEFAULT 'no-delete',
  `detail_id_field` varchar(127) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `detail_url` varchar(63) CHARACTER SET latin1 COLLATE latin1_general_cs DEFAULT NULL,
  PRIMARY KEY (`rel_id`),
  UNIQUE KEY `master_db` (`master_db`,`master_table`,`master_field`,`detail_db`,`detail_table`,`detail_field`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs;


CREATE TABLE `_revisiondata` (
  `revisiondata_id` int unsigned NOT NULL AUTO_INCREMENT,
  `revision_id` int unsigned NOT NULL,
  `table_name` varchar(63) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `record_id` int unsigned NOT NULL,
  `changed_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `complete_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rev_action` enum('insert','update','delete') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  PRIMARY KEY (`revisiondata_id`),
  KEY `revision_id` (`revision_id`),
  KEY `table_name` (`table_name`),
  KEY `record_id` (`record_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `_revisions` (
  `revision_id` int unsigned NOT NULL AUTO_INCREMENT,
  `main_table_name` varchar(63) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `main_record_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `rev_status` enum('live','pending','historic') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `created` datetime NOT NULL,
  `script_url` varchar(63) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`revision_id`),
  KEY `rev_status` (`rev_status`),
  KEY `main_table_name_main_record_id` (`main_table_name`,`main_record_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

