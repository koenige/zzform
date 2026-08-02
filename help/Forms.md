<!--
# zzform
# about form scripts
#
# Part of »Zugzwang Project«
# https://www.zugzwang.org/modules/zzform
#
# @author Gustaf Mossakowski <gustaf@koenige.org>
# @copyright Copyright © 2026 Gustaf Mossakowski
# @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
#
# Variables
# audience = programmer
-->

# Forms

Form scripts live in a module’s `/zzbrick_forms/` folder. They build on
table definitions in `/zzbrick_tables/` and are included on webpages via
zzbrick:

    %%% forms tasks %%%

or via a named route in `routes.cfg`:

    brick = "forms tasks *"

The `forms` brick loads `zzbrick_forms/{script}.php`, which usually calls
`zzform_include()` to load the underlying table definition and then adjusts
the returned `$zz` array before zzform renders the list or edit form.

## Table definitions and form scripts

| Location | Purpose |
|----------|---------|
| `zzbrick_tables/{table}.php` | Base definition: fields, SQL, filters, record modes |
| `zzbrick_forms/{script}.php` | User-facing form: include a table script and modify `$zz` |

A form script is thin by design: reuse the table definition, then change only
what differs for this page or audience.

Minimal example (`work/zzbrick_forms/tasks.php`):

```php
if (empty($brick['data']['event_id'])) wrap_quit(404);

$zz = zzform_include('tasks');
$zz['where']['event_id'] = $brick['data']['event_id'];
```

## User-facing modifications

After `zzform_include()`, change the `$zz` array to adapt what editors or
visitors see. Typical changes:

- **List scope** — `$zz['where'][…]`, `$zz['sql']`, or filter SQL via
  `wrap_edit_sql()`
- **Fields** — `hide_in_list`, `hide_in_form`, `type` (`display`, …),
  `title`, `explanation`
- **Record behaviour** — `$zz['record']['add']`, `$zz['record']['delete']`,
  `$zz['access']`
- **Page chrome** — `$zz['title']`, `$zz['page']['breadcrumbs']`,
  `$zz['page']['referer']`, `$zz['explanation']`
- **Settings** — e.g. `$zz['setting']['zzform_show_list_while_edit']`

When the scoped view needs more than a foreign-key filter (extra joins,
field subsets, access rules), use a dedicated form script. Example:
`finance/zzbrick_forms/documents-project.php` limits the contact field to
client contacts of the current project.

## Filter on a foreign key

Many forms list records belonging to one parent record (project, bank account,
contact, …). The parent is resolved before the form runs — usually by a
`zzbrick_placeholder` script — and passed in `$brick['data']`.

Pattern:

1. Guard: quit with 404 if the foreign key is missing.
2. Include the table script.
3. Set `$zz['where']` on the foreign-key column.
4. Restrict list filters the same way so filter dropdowns cannot escape the
   scope.

```php
if (empty($brick['data']['bankaccount_id'])) wrap_quit(404);

$zz = zzform_include('transactions');
$zz['where']['bankaccount_id'] = $brick['data']['bankaccount_id'];

$zz['filter'][1]['sql'] = wrap_edit_sql(
	$zz['filter'][1]['sql'],
	'WHERE',
	sprintf('bankaccount_id = %d', $brick['data']['bankaccount_id'])
);
```

If a table has only one scoped view and the changes are limited to a
`where` clause (and matching filters), keep one form script named after the
table (`tasks.php`, `worklogs.php`) and use a route with `*` so URL
placeholders supply the parent context.

Create a separate form script when:

- the same table has several scoped views with different rules, or
- the scoped view changes fields, access, or SQL beyond a simple `where`.

## Naming form scripts

Use hyphens in filenames (zzform maps `_` to `-` internally).

### Default: table name

If the form lists one table without a scope suffix, name the file after that
table (plural, as in the database):

- `tasks.php` → `zzform_include('tasks')`
- `transactions.php` → `zzform_include('transactions')`

### Scoped or variant forms: `{table}-{qualifier}`

When several form scripts exist for the same table, name them
**table first, qualifier second**:

- **table** — plural, matches the included table script (`transactions`,
  `documents`, `persons`)
- **qualifier** — singular scope or variant word (`project`, `bankaccount`,
  `contact`, `general`, `edit`)

Examples:

| Filename | Includes | Qualifier means |
|----------|----------|-----------------|
| `transactions-project.php` | `transactions` | scoped to one project (`event_id`) |
| `transactions-bankaccount.php` | `transactions` | scoped to one bank account |
| `documents-project.php` | `documents` | scoped to one project, extra field rules |
| `contacts-general.php` | `contacts` | variant for general contact editing |

The first word answers *what this form lists*; the second narrows *which
view* of that table this script provides.

Do **not** reverse the order (`project-transactions`, `bankaccount-transactions`).
That reads well as English but scatters one table’s forms across the folder.
