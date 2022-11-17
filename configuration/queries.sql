/**
 * zzform module
 * SQL queries for core, page, auth and database IDs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


-- zzform_filetypelist --
SELECT filetype_id, UPPER(filetype) AS filetype, filetype_description FROM /*_PREFIX_*/filetypes WHERE filetype IN ('%s');
