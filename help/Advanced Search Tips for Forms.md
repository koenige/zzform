<!--
# zzform
# help: advanced search tips
#
# Part of »Zugzwang Project«
# https://www.zugzwang.org/modules/zzform
#
# @author Gustaf Mossakowski <gustaf@koenige.org>
# @copyright Copyright © 2025 Gustaf Mossakowski
# @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
#
-->

# Advanced Search for Forms

Use these tips to refine your search below forms and get more accurate
results.

## How Search Works

By default, your search terms are matched **anywhere within the text**.
That means if you search for:

    apple

It will return anything that contains the word “apple”, like “green
apple”, “pineapple”, or “apple pie“.

## Special Operators

You can refine your search with special characters or patterns placed
**before or after your search terms**. In this text, `<SP>` stands for
a space.

Operator              | Meaning
--------------------- | ------------------------------------------------
`<SP>search term<SP>` | Exact match — entries where the full phrase matches
`= search term`       | Same as above (exact match)
`<SP>search term`     | Finds entries that **start with** the term
`search term<SP>`     | Finds entries that **end with** the term
`! search term `      | Excludes entries that contain this term
`- search1 search2`   | Finds entries **between** the two values

## Field-Specific (Scoped) Search

When searching within a specific field (e.g. a number or date column),
you can use comparison operators:

Operator         | Meaning
---------------- | -----------------------------------------------------
`> value`        | Greater than
`< value`        | Less than
`>= value`       | Greater than or equal to
`<= value`       | Less than or equal to
`NULL`           | Finds entries without a value in this field
`!NULL`          | Finds entries with a value in this field

## Date Searches

You can search for both **localized** and **international** date
formats. Examples:

Search Term      | Meaning
---------------- | -----------------------------------------------------
`03/2025`        | Entries in **March 2025**
`Q1/2025`        | Entries in the **first quarter** of 2025 (January–March)
`> 01/01/2023`   | Dates after January 1, 2023
`< 15.11.2023`   | Dates before November 11, 2023
`<= 2024-12-31`  | Dates up to and including December 31, 2024
