<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2005-2011
// Text and labels in English (en) us-ascii


$text = array();

// ----------------------------------------------------------------------------
// Page elements
// ----------------------------------------------------------------------------

$text['records'] = 'records'; // Number of records as shown in page TITLE
$text['back-to-overview'] = 'Back to overview';

// Heading
$text['Search'] = 'Search';


// ----------------------------------------------------------------------------
// Record form
// ----------------------------------------------------------------------------

$text['new'] = 'New'; // Link to add a new value for detail record via dropdown

// Record form: Heading
$text['show_record'] = 'Show record';
$text['review'] = 'Review';
$text['insert'] = 'Insert';
$text['update'] = 'Update';
$text['edit'] = 'Edit';
$text['delete'] = 'Delete';
$text['show'] = 'Show';
$text['add'] = 'Add';
$text['a_record'] = 'a Record';
$text['failed'] = 'failed';
$text['There is no record under this ID:'] = 'There is no record under this ID:';
$text['record_was_updated'] = 'Record was updated';
$text['record_was_deleted'] = 'Record was deleted';
$text['record_was_inserted'] = 'Record was inserted';

// Record form: bottom
$text['Cancel'] = 'Cancel'; // Stop editing this record
$text['Update record'] = 'Update record';
$text['Delete record'] = 'Delete record';
$text['Add record'] = 'Add record';

// Record form: field output
$text['N/A'] = '<abbr title="Not available">N/A</abbr>'; // no value available for display, should be abbreviated
$text['will_be_added_automatically'] = 'will be added automatically';
$text['calculated_field'] = 'calculated field';

// Record form: Select
$text['none_selected'] = 'None selected'; // dropdown, first entry
$text['no_selection'] = 'No selection'; // radio buttons, first entry
$text['no_source_defined'] = 'No source defined';
$text['no_selection_possible'] = 'No selection possible.';

// Record form: Change password
$text['Old:'] = 'Old:';
$text['New:'] = 'New:';
$text['(Please confirm your new password twice)'] = '(Please confirm your new password twice)';
$text['Your password has been changed!'] = 'Your password has been changed!';
$text['Your current password is different from what you entered. Please try again.'] = 'Your current password is different from what you entered. Please try again.';
$text['New passwords do not match. Please try again.'] = 'New passwords do not match. Please try again.';
$text['Please enter your current password and twice your new password.'] = 'Please enter your current password and twice your new password.';
$text['New and old password are identical. Please choose a different new password.'] = 'New and old password are identical. Please choose a different new password.';
$text['Rules for secure passwords'] = 'Rules for secure passwords';
$text['password-rules'] = 'Passwords should consist of a minimum of eight characters. You should use upper- and lowercase letters, numbers and special characters (?=+; etc.). It is good to use a mnemonic trick to remember your password.

Examples for good passwords:

* M\'sCMh8196wii! (Rule: Marx\'s Communist Manifesto has 8196 words in it!)
* nuit+Pog=tWi.  (Rule: these are artificial words that could be remembered)
* NYtgPw4n (Rule: generic password part that you could always use: gPw4 = good password for; NYt for New York Times, final n for news)'; 
$text['hidden'] = 'hidden';

// Record form: File upload
$text['image_not_display'] = 'Image cannot yet be displayed';
$text['Error: '] = 'Error: ';
$text['No file was uploaded.'] = 'No file was uploaded.';
$text['File was only partially uploaded.'] = 'File was only partially uploaded.';
$text['File is too big.'] = 'File is too big.';
$text['Maximum allowed filesize is'] = 'Maximum allowed filesize is';
$text['Unsupported filetype:'] = 'Unsupported filetype:';
$text['Supported filetypes are:'] = 'Supported filetypes are:';
$text['Could not delete %s.'] = 'Could not delete %s.';
$text['Could not delete %s, file did not exist.'] = 'Could not delete %s, file did not exist.';
$text['File: '] = 'File: '; // prefix for alt-attribute
$text['no_image'] = 'No Image'; // alt-attribute if there's no image
$text['Delete this file'] = 'Delete this file';
$text['File could not be saved. There is a problem with the user rights. We are working on it.'] = 'File could not be saved. There is a problem with the user rights. We are working on it.';
$text['Minimum width %s was not reached.'] = 'Minimum width %s was not reached.';
$text['Minimum height %s was not reached.'] = 'Minimum height %s was not reached.';
$text['Maximum width %s has been exceeded.'] = 'Maximum width %s has been exceeded.';
$text['Maximum height %s has been exceeded.'] = 'Maximum height %s has been exceeded.';

// Record form: Detail record
$text['Add %s'] = 'Add %s'; // e. g. Add Address, Add Phone Number ...
$text['Remove %s'] = 'Remove %s';

// Record form: Validation, displayed inside form
$text['Please enter more characters.'] = 'Please enter more characters.';
$text['No entry found. Try less characters.'] = 'No entry found. Try less characters';

// Record form: Validation, displayed above form
$text['Following_errors_occured'] = 'The following errors occured';
$text['Value_incorrect_in_field'] = 'Value incorrect in field:';
$text['Value missing in field'] = 'Value missing in field:';
$text['Duplicate entry'] = 'Duplicate entry in this table. Please check whether the record you were about to enter already exists or you\'ll have to change the values you entered.';

// Record form: Validation of Database Integrity, displayed above form
$text['No records in relation table'] = 'No records in relation table %s. Please fill in records.';
$text['Detail records exist in the following tables:'] = 'Detail records exist in the following tables:';
$text['This record could not be deleted because there are details about this record in other records.'] = 'This record could not be deleted because there are details about this record in other records.';

// Record form: foreign record
$text['edit-after-save'] = 'No entry possible. First save this record.';
$text['no-data-available'] = 'No data available.';


// ----------------------------------------------------------------------------
// List view
// ----------------------------------------------------------------------------

$text['table-empty'] = 'No entries available';
$text['- unknown -'] = '- unknown -'; // group by unknown

// List view: Filter
$text['Selection'] = 'Selection';
$text['all'] = 'all';

// List view: Table head
$text['order by'] = 'Order by';
$text['asc'] = 'ascending';
$text['desc'] = 'descending';
$text['action'] = 'Action';
$text['detail'] = 'Details';

// List view: bottom
$text['Add new record'] = 'Add new record';
$text['records total'] = 'records total';		// %s records total
$text['record total'] = 'record total';			// 1 record total

// List view: Search form
$text['Show all records'] = 'Show all records (without search filter)';
$text['all fields'] = 'all fields';
$text['in'] = 'in';
$text['search'] = 'search'; // Button


// ----------------------------------------------------------------------------
// Error handling
// ----------------------------------------------------------------------------

$text['Warning!'] = 'Attention!';
$text['incorrect value'] = 'incorrect value';
$text['database-error'] = 'Database error';


// ----------------------------------------------------------------------------
// Modules: Export
// ----------------------------------------------------------------------------

$text['Export'] = 'Export'; // Export-Link


// ----------------------------------------------------------------------------
// Modules: Import
// ----------------------------------------------------------------------------

$text['File could not be imported.'] = 'File could not be imported.';
$text['Folder could not be imported.'] = 'Folder could not be imported.';
$text['Import was successful.'] = 'Import was successful.';
$text['Folder OK'] = 'Folder OK';
$text['Folder "%s" does not exist.'] = 'Folder "%s" does not exist.';
$text['Warning! Insufficient access rights. Please make sure, that the source directory is writeable.'] = 'Warning! Insufficient access rights. Please make sure, that the source directory is writeable.';
$text['%s files left for import. Please wait, the script will reload itself.'] = '%s files left for import. Please wait, the script will reload itself.';


// ----------------------------------------------------------------------------
// Modules: Geo
// ----------------------------------------------------------------------------

$text['N'] = '<abbr title="North">N</abbr>';
$text['E'] = '<abbr title="East">E</abbr>';
$text['S'] = '<abbr title="South">S</abbr>';
$text['W'] = '<abbr title="West">W</abbr>';


// ----------------------------------------------------------------------------
// Backend
// ----------------------------------------------------------------------------

// Development
$text['Script configuration error. No display field set.'] = 'Script configuration error. No display field set.';
$text['Field name'] = 'Field name'; // introduces field name with wrong values
$text['no-delete'] = "Don't delete"; // from table relations
$text['Database error. This database has ambiguous values in ID field.'] = 'Database error. This table has ambiguous values in ID field.';

// Development, Error mail
$text['Error during database operation'] = 'Error from database operation';
$text['The following error(s) occured in project %s:'] = 'The following error(s) occured in project %s:';

// ----------------------------------------------------------------------------
// Admin: Logging
// ----------------------------------------------------------------------------

$text['Last update'] = 'Updated';


?>