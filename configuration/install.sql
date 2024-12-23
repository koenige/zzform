/**
 * zzform
 * SQL for installation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2020-2021, 2023-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


CREATE TABLE `_revisiondata` (
  `revisiondata_id` int unsigned NOT NULL AUTO_INCREMENT,
  `revision_id` int unsigned NOT NULL,
  `table_name` varchar(63) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `record_id` int NOT NULL,
  `changed_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `complete_values` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rev_action` enum('insert','update','delete','ignore') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
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
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`revision_id`),
  KEY `rev_status` (`rev_status`),
  KEY `main_table_name_main_record_id` (`main_table_name`,`main_record_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO categories (`category`, `description`, `main_category_id`, `path`, `parameters`, `sequence`, `last_update`) VALUES ('Thumbnails', NULL, (SELECT category_id FROM categories c WHERE path = 'jobs'), 'jobs/thumbnails', '&alias=jobs/thumbnails&max_requests=2', NULL, NOW());
