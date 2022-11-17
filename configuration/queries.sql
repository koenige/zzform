/**
 * zzform module
 * SQL queries
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


-- zzform_relations__table --
/* _relations */

-- zzform_logging__table --
/* _logging */

-- zzform_filetypelist --
SELECT filetype_id, UPPER(filetype) AS filetype, filetype_description FROM /*_PREFIX_*/filetypes WHERE filetype IN ('%s');
