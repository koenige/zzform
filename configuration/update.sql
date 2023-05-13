/**
 * zzform module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/* 2021-07-24-1 */	ALTER TABLE `_revisiondata` CHANGE `rev_action` `rev_action` enum('insert','update','delete','ignore') COLLATE 'latin1_general_ci' NOT NULL AFTER `complete_values`;
/* 2021-07-25-1 */	ALTER TABLE `_revisiondata` CHANGE `record_id` `record_id` int NOT NULL AFTER `table_name`;
/* 2022-11-02-1 */	DELETE FROM `_settings` WHERE `setting_key` = 'zzform_wmd_editor_languages';
/* 2023-03-27-1 */	UPDATE `_settings` SET `setting_key` = 'zzform_upload_binary_folder' WHERE `setting_key` = 'zzform_imagemagick_path_unchecked';
/* 2023-03-27-2 */	UPDATE `_settings` SET `setting_key` = 'zzform_upload_binary_folder_local' WHERE `setting_key` = 'zzform_imagemagick_path_unchecked_local';
/* 2023-05-13-1 */	INSERT INTO categories (`category`, `description`, `main_category_id`, `path`, `parameters`, `sequence`, `last_update`) VALUES ('thumbnails', NULL, (SELECT category_id FROM categories c WHERE path = 'jobs'), 'jobs/thumbnails', '&alias=jobs/thumbnails&max_records=1', NULL, NOW());
