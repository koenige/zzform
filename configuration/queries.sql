/**
 * zzform module
 * SQL queries
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022, 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


-- zzform_relations__table --
/* _relations */

-- zzform_logging__table --
/* _logging */

-- zzform_logging_log_id_read --
SELECT *
FROM /*_TABLE zzform_logging _*/
WHERE log_id >= %d ORDER BY log_id
LIMIT /*_SETTING zzform_logging_max_read _*/;
		
-- zzform_logging_log_id_count --
SELECT COUNT(*)
FROM /*_TABLE zzform_logging _*/
WHERE log_id >= %d;

-- zzform_logging_month_read --
SELECT *
FROM /*_TABLE zzform_logging _*/
WHERE EXTRACT(YEAR_MONTH FROM last_update) = %d
LIMIT /*_SETTING zzform_logging_max_read _*/;
		
-- zzform_logging_month_count --
SELECT COUNT(*)
FROM /*_TABLE zzform_logging _*/
WHERE EXTRACT(YEAR_MONTH FROM last_update) = %d;

-- zzform_filetypelist --
SELECT filetype_id, UPPER(filetype) AS filetype, filetype_description
FROM /*_PREFIX_*/filetypes WHERE filetype IN ('%s');
