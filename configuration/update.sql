/**
 * zzform module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/* 2021-07-24-1 */	ALTER TABLE `_revisiondata` CHANGE `rev_action` `rev_action` enum('insert','update','delete','ignore') COLLATE 'latin1_general_ci' NOT NULL AFTER `complete_values`;
