/**
 * zzform module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/* 2021-07-24-1 */	ALTER TABLE `_revisiondata` CHANGE `rev_action` `rev_action` enum('insert','update','delete','ignore') COLLATE 'latin1_general_ci' NOT NULL AFTER `complete_values`;
/* 2021-07-25-1 */	ALTER TABLE `_revisiondata` CHANGE `record_id` `record_id` int NOT NULL AFTER `table_name`;
/* 2022-11-02-1 */	DELETE FROM `_settings` WHERE `setting_key` = 'zzform_wmd_editor_languages';
