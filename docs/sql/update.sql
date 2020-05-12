/**
 * Zugzwang Project
 * SQL updates for zzform module
 *
 * http://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2019-2020 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

/* 2019-01-10-1 */	ALTER TABLE `_logging` ADD INDEX `last_update` (`last_update`);
