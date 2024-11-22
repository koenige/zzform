<!--
# zzform module
# about logging hooks
#
# Part of »Zugzwang Project«
# https://www.zugzwang.org/modules/zzwrap
#
# @author Gustaf Mossakowski <gustaf@koenige.org>
# @copyright Copyright © 2024 Gustaf Mossakowski
# @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
#
-->

# Logging Hooks

If `zzform_logging` is active, each database change is logged. In some
cases, you might want to use this logging for other purposes, e. g. for
a seperate evaluation of the logs. To add missing data per `log_id`, you
can add logging hooks that read this data from the database and add it
to a separate logging table. To create a hook, you need the following
files in your package:

## `configuration/logging.sql`

This file consists of a list of SQL queries you would like to match that
trigger the hook.

Example:

    -- rooms_update --
    UPDATE rooms %% WHERE room_id = %d;
    SELECT event_id FROM rooms WHERE room_id = %d;

The first query is for matching the actual query. If it matches, the
(optional) second query is executed to read missing data from the
database, using the record ID of the processed entry.

## `package/logging.inc.php`

Here, you can define a function `mf_package_logging()` to write extra
log data into a separate table. This function has three parameters: the
original query, the last log ID and an array with possible extra values.

Example:

    /**
     * link logging entry to event
     *
     * @param string $query
     * @param int $log_id
     * @param array $values
     * @return string
     */
    function mf_rooms_logging($query, $log_id, $values) {
    	$tokens = explode(' ', $query);
    	$action = strtolower($tokens[0]);
    	if (!in_array($action, ['insert', 'update', 'delete'])) return false;
    	$table_name = ($action === 'update') ? $tokens[1] : $tokens[2];
    	
    	$sql = 'INSERT INTO rooms_logging (log_id, event_id, table_name, action)
    		VALUES (%d, %d, "%s", "%s")';
    	$sql = sprintf($sql, $log_id, $values['event_id'], $table_name,  $action);
    	wrap_db_query($sql);
    }
