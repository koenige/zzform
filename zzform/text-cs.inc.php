<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2006-2011
// Text and labels in Czech (cs) iso-8859-2


// ----------------------------------------------------------------------------
// Page elements
// ----------------------------------------------------------------------------

$text['records'] = 'z�znam�'; // Number of records as shown in page TITLE
$text['back-to-overview'] = 'Zp�t na p�ehled';

// Heading
$text['Search'] = 'Hledat';


// ----------------------------------------------------------------------------
// Record form
// ----------------------------------------------------------------------------

$text['new'] = 'Nov�'; // Link to add a new value for detail record via dropdown

// Record form: Heading
$text['show_record'] = 'Zobrazit z�znam';
$text['review'] = 'Prohl�dnout';
$text['insert'] = 'P�idat';
$text['update'] = 'Aktualizovat';
$text['edit'] = 'Editovat';
$text['delete'] = 'Vymazat';
$text['show'] = 'Zobrazit';
$text['add'] = 'P�idat';
$text['a_record'] = 'z�znam';
$text['failed'] = 'ne�sp�n�';
$text['There is no record under this ID:'] = 'V poli ID nen� ��dn� z�znam:';
$text['record_was_updated'] = 'Z�znam byl aktualizov�n';
$text['record_was_deleted'] = 'Z�znam byl vymaz�n';
$text['record_was_inserted'] = 'P��sp�vek byl ulo�en';

// Record form: bottom
$text['Cancel'] = 'Storno'; // Stop editing this record
$text['update_to'] = 'Aktualizovat k';
$text['delete_from'] = 'Vymazat z';
$text['add_to'] = 'P�idat k';
$text['database'] = 'Datab�ze';

// Record form: field output
$text['N/A'] = '<abbr title="Not available">N/A</abbr>'; // no value available for display, should be abbreviated
$text['will_be_added_automatically'] = 'bude p�id�no automaticky';
$text['calculated_field'] = 'Generovan� pol��ko';

// Record form: Select
$text['none_selected'] = '��dn� nevybr�n'; // dropdown, first entry
$text['no_selection'] = '��dn� v�b�r'; // radio buttons, first entry
$text['no_source_defined'] = '��dn� zdroj nebyl definov�n';
$text['no_selection_possible'] = '��dn� v�b�r nen� mo�n�.';

// Record form: Change password
$text['Old:'] = 'Star�:';
$text['New:'] = 'Nov�:';
$text['(Please confirm your new password twice)'] = '(Zadejte va�e heslo dvakr�t)';
$text['Your password has been changed!'] = 'Va�e heslo bylo zm�n�no!';
$text['Your current password is different from what you entered. Please try again.'] = 'Va�e heslo je odli�n� od toho, kter� jste pr�v� zadali. Zkuste to je�t� jednou.';
$text['New passwords do not match. Please try again.'] = 'Nov� zadan� hesla nesed�. Zkuste je�t� jednou.';
$text['Please enter your current password and twice your new password.'] = 'Zadejte va�e sou�asn� heslo a z�rove� dvakr�t potvr�te nov�.';
$text['New and old password are identical. Please choose a different new password.'] = 'Nov� a star� heslo je stejn�. Zadejte jin� nov� heslo.';
$text['Rules for secure passwords'] = 'Pravidla pro tvorbu bezpe�n�ho hesla';
$text['password-rules'] = 'Heslo mus� obsahovat minim�ln� osm znak�. Doporu�uje se pou��vat velk� i mal� p�smena, ��sla a speci�ln� znaky jako ?=+; atd.'; 
$text['hidden'] = 'schovat';

// Record form: File upload
$text['image_not_display'] = 'Nelze zobrazit';
$text['Error: '] = 'Chyba: ';
$text['No file was uploaded.'] = '��dn� soubor nebyl nahr�n.';
$text['File was only partially uploaded.'] = 'Soubor byl nahr�n pouze ��ste�n�.';
$text['File is too big.'] = 'Soubor je p��li� velk�.';
$text['Maximum allowed filesize is'] = 'Maxim�ln� povolen� velikost souboru je';
$text['Unsupported filetype:'] = 'Nepovolen� form�t souboru:';
$text['Supported filetypes are:'] = 'Povolen� form�ty soubor� jsou:';
$text['Could not delete %s.'] = 'Soubor %s nelze vymazat.';
$text['Could not delete %s, file did not exist.'] = 'Soubor %s nelze vymazat, proto�e neexistuje :-).';
$text['File: '] = 'Soubor: '; // prefix for alt-attribute
$text['no_image'] = '��dn� obr�zek'; // alt-attribute if there's no image
$text['Delete this file'] = 'Smazat tento soubor';
	$text['File could not be saved. There is a problem with the user rights. We are working on it.'] = 'File could not be saved. There is a problem with the user rights. We are working on it.';
$text['Minimum width %s was not reached.'] = 'Minim�ln� ���ka %s nebyla dosa�ena.';
$text['Minimum height %s was not reached.'] = 'Minim�ln� v��ka %s nebyla dosa�ena.';
$text['Maximum width %s has been exceeded.'] = 'Maxim�ln� ���ka %s byla p�ekro�ena.';
$text['Maximum height %s has been exceeded.'] = 'Maxim�ln� v��ka %s byla p�ekro�ena.';

// Record form: Detail record
$text['Add %s'] = 'P�idat %s'; // e. g. Add Address, Add Phone Number ...
$text['Remove %s'] = 'Odebrat %s';

// Record form: Validation, displayed inside form
$text['Please enter more characters.'] = 'Pros�m, zadejte v�ce znak�.';
$text['No entry found. Try less characters.'] = 'Hled�n� bylo ne�sp�n�, zkuste zadat m�n� znak�.';

// Record form: Validation, displayed above form
$text['Following_errors_occured'] = 'Do�lo k n�sleduj�c�m chyb�m';
$text['Value_incorrect_in_field'] = '�patn� hodnota';
$text['Value missing in field'] = 'Chyb�j�c� hodnota';
	$text['Duplicate entry'] = 'Duplicate entry in this table. Please check whether the record you were about to enter already exists or you\'ll have to change the values you entered.';
	$text['Duplicate entry'] = 'Duplikovan� z�znam v t�to tabulce.';
//	$text['Detail record could not be handled'] = '';

// Record form: Validation of Database Integrity, displayed above form
$text['No records in relation table'] = '��dn� z�znamy v souvisej�c� tabulce %s. Pros�m, dopl�te.';
$text['Detail records exist in the following tables:'] = 'Detailn� z�znamy jsou v t�chto tabulk�ch:';
$text['This record could not be deleted because there are details about this record in other records.'] = 'Tento p��sp�vek nem��e b�t vymaz�n, proto�e je v�z�n na ostatn� z�znamy.';

// Record form: foreign record
$text['edit-after-save'] = 'Nelze p�idat z�znam - je t�eba ulo�it aktu�ln� otev�en� p��sp�vek.';
$text['no-data-available'] = '��dn� data k dispozici.';

// ----------------------------------------------------------------------------
// List view
// ----------------------------------------------------------------------------

$text['table-empty'] = '��dn� z�znamy k dispozici';
$text['- unknown -'] = '- nezn�m� -'; // group by unknown

// List view: Filter
$text['Selection'] = 'V�b�r';
$text['all'] = 'v�echny';

// List view: Table head
$text['order by'] = 'Se�adit';
$text['asc'] = 'Vzestupn�';
$text['desc'] = 'Sestupn�';
$text['action'] = 'Akce';
$text['detail'] = 'Detaily';

// List view: bottom
$text['Add new record'] = 'P�idat nov� z�znam';
$text['records total'] = 'z�znam� celkov�';
$text['record total'] = 'celkov� z�znam';

// List view: Search form
	$text['Show all records'] = 'Show all records (without search filter)';
$text['all fields'] = 'v�ech pol�ch';
$text['in'] = 've';
$text['search'] = 'hledat'; // Button


// ----------------------------------------------------------------------------
// Error handling
// ----------------------------------------------------------------------------

$text['Warning!'] = 'Varov�n�!';
	$text['incorrect value'] = 'incorrect value';
$text['database-error'] = 'Chyba datab�ze';

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
	$text['E'] = '<abbr title="East">V</abbr>';
	$text['S'] = '<abbr title="South">J</abbr>';
	$text['W'] = '<abbr title="West">Z</abbr>';
// $text['It looks like this coordinate has a different orientation. Maybe latitude and longitude were interchanged?'] = 'Es sieht aus, als ob diese Koordinate eine andere Orientierung hat. Wurden L&auml;nge und Breite vertauscht?';
// $text['Mismatch: %s signals different hemisphere than %s.'] = 'Nicht eindeutig: %s zeigt eine andere Hemisph&auml;re an als %s.';
// $text['There are too many decimal points (or commas) in this value.'] = 'In diesem Wert sind zuviele Kommas oder Punkte.';
// $text['Only the last number might have a decimal point (or comma).'] = 'Nur die letzte Zahl darf ein Komma enthalten (oder einen Punkt).';
// $text['%s is too small. Please enter for minutes a positive value or 0.'] = '%s ist zu klein. Bitte geben Sie f&uuml;r Minuten einen positiven Wert oder 0 ein.';
// $text['%s is too small. Please enter for seconds a positive value or 0.'] = '%s ist zu klein. Bitte geben Sie f&uuml;r Sekunden einen positiven Wert oder 0 ein';
// $text['%s is too big. Please enter for minutes a value smaller than 60.'] = '%s ist zu gro&szlig;. Bitte geben Sie f&uuml;r Minuten einen Wert kleiner als 60 ein.';
// $text['%s is too big. Please enter for seconds a value smaller than 60.'] = '%s ist zu gro&szlig;. Bitte geben Sie f&uuml;r Sekunden einen Wert kleiner als 60 ein.';
// $text['Sorry, there are too many numbers. We cannot interpret what you entered.'] = 'Wir k&ouml;nnen leider nicht diese Koordinate korrekt interpretieren, da zuviele Zahlen eingegeben wurden.';
// $text['Minimum value for degrees is %s. The value you entered is too small: %s.'] = 'Der kleinste Wert f&uuml;r Grad ist %s. Der Wert, den Sie eingegeben haben, ist zu klein: %s.';
// $text['Maximum value for degrees is %s. The value you entered is too big: %s.'] = 'Der gr&ouml;&szlig;te Wert f&uuml;r Grad ist %s. Der Wert, den Sie eingegeben haben, ist zu gro&szlig;: %s.';


// ----------------------------------------------------------------------------
// Backend
// ----------------------------------------------------------------------------

// Development
$text['Script configuration error. No display field set.'] = 'Chyba konfigurace skriptu.';
	$text['Field name'] = 'Field name'; // introduces field name with wrong values
	$text['no-delete'] = "Don't delete"; // from table relations
$text['Database error. This database has ambiguous values in ID field.'] ='Chyba datab�ze. Nelze p�e��st z�znamy ze sloupce ID datab�zov� tabulky.';

// Development, Error mail
$text['Error during database operation'] = 'Chyba zpracov�n� dat';
$text['The following error(s) occured in project %s:'] = 'N�sleduj�c� chyby se vyskytuj� v projektu %s:';


?>