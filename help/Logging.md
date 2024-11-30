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

# Logging

If `zzform_logging` is active, every database change is logged in the
logging table.

## Use the logging table

The logging table is a read-only table. The data can be viewed via the
logging table script in the `default`-module. Access to this table can
be restricted via `default_logging` access right.

## Customizing logging

### Deactivate logging inside zzform table script

You can disable logging using a table script:

    $zz['setting']['zzform_logging'] = false;
    
If you’d like to disable all database logging, set `zzform_logging` to
`false`.

### Change the name of the logging table

The default name for the logging table is `_logging`. You can change it
in a custom SQL file `configuration/overwrite-queries.sql`. The default
value is as follows:

    -- zzform_logging__table --
    /* _logging */

### Using multiple databases

If you use tables from multiple databases, the logging table of the
corresponding database will be used. If you prefer a central logging
table, add a database name `zzform_logging__table`, e. g. `/*
LOG._logging */`.

## Scripts and functions

### Archive log data

You can archive old log data using the `make loggingarchive` script.
Corresponding settings are:

- `zzform_logging_keep_months`
- `zzform_logging_max_read`

### Synchronize data

The `make serversync` script allows you to synchronize the development
and production databases. New logging entries will either be sent to or
received from the production server. This only works if no changes have
been made on either server between synchronizations. File changes are
not synchronized. Accessible separately or via the maintenance script
from the `default`-module.

### Custom log data

If you want to log queries that are not made via zzform(), you can use
`zz_db_log()` separately:

    wrap_include('database', 'zzform');
    zz_db_log($sql, $record_id);
    
The ID `$record_id` is optional. It should be the ID of the
corresponding record that was updated, deleted or inserted via the
`$sql` query.

### Logging hooks

If you want to use logging data for other purposes, you can add a hook
to post-process the logging data after saving it. See the help text.
