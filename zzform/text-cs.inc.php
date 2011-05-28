<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2006-2011
// Text and labels in Czech (cs) iso-8859-2


// ----------------------------------------------------------------------------
// Page elements
// ----------------------------------------------------------------------------

$text['records'] = 'záznamù'; // Number of records as shown in page TITLE
$text['back-to-overview'] = 'Zpìt na pøehled';

// Heading
$text['Search'] = 'Hledat';


// ----------------------------------------------------------------------------
// Record form
// ----------------------------------------------------------------------------

$text['new'] = 'Nový'; // Link to add a new value for detail record via dropdown

// Record form: Heading
$text['show_record'] = 'Zobrazit záznam';
$text['review'] = 'Prohlédnout';
$text['insert'] = 'Pøidat';
$text['update'] = 'Aktualizovat';
$text['edit'] = 'Editovat';
$text['delete'] = 'Vymazat';
$text['show'] = 'Zobrazit';
$text['add'] = 'Pøidat';
$text['a_record'] = 'záznam';
$text['failed'] = 'neúspì¹né';
$text['There is no record under this ID:'] = 'V poli ID není ¾ádný záznam:';
$text['record_was_updated'] = 'Záznam byl aktualizován';
$text['record_was_deleted'] = 'Záznam byl vymazán';
$text['record_was_inserted'] = 'Pøíspìvek byl ulo¾en';

// Record form: bottom
$text['Cancel'] = 'Storno'; // Stop editing this record
$text['update_to'] = 'Aktualizovat k';
$text['delete_from'] = 'Vymazat z';
$text['add_to'] = 'Pøidat k';
$text['database'] = 'Databáze';

// Record form: field output
$text['N/A'] = '<abbr title="Not available">N/A</abbr>'; // no value available for display, should be abbreviated
$text['will_be_added_automatically'] = 'bude pøidáno automaticky';
$text['calculated_field'] = 'Generované políèko';

// Record form: Select
$text['none_selected'] = '®ádný nevybrán'; // dropdown, first entry
$text['no_selection'] = '®ádný výbìr'; // radio buttons, first entry
$text['no_source_defined'] = '®ádný zdroj nebyl definován';
$text['no_selection_possible'] = '®ádný výbìr není mo¾ný.';

// Record form: Change password
$text['Old:'] = 'Staré:';
$text['New:'] = 'Nové:';
$text['(Please confirm your new password twice)'] = '(Zadejte va¹e heslo dvakrát)';
$text['Your password has been changed!'] = 'Va¹e heslo bylo zmìnìno!';
$text['Your current password is different from what you entered. Please try again.'] = 'Va¹e heslo je odli¹né od toho, které jste právì zadali. Zkuste to je¹tì jednou.';
$text['New passwords do not match. Please try again.'] = 'Novì zadaná hesla nesedí. Zkuste je¹tì jednou.';
$text['Please enter your current password and twice your new password.'] = 'Zadejte va¹e souèasné heslo a zároveò dvakrát potvrïte nové.';
$text['New and old password are identical. Please choose a different new password.'] = 'Nové a staré heslo je stejné. Zadejte jiné nové heslo.';
$text['Rules for secure passwords'] = 'Pravidla pro tvorbu bezpeèného hesla';
$text['password-rules'] = 'Heslo musí obsahovat minimálnì osm znakù. Doporuèuje se pou¾ívat velká i malá písmena, èísla a speciální znaky jako ?=+; atd.'; 
$text['hidden'] = 'schovat';

// Record form: File upload
$text['image_not_display'] = 'Nelze zobrazit';
$text['Error: '] = 'Chyba: ';
$text['No file was uploaded.'] = '®ádný soubor nebyl nahrán.';
$text['File was only partially uploaded.'] = 'Soubor byl nahrán pouze èásteènì.';
$text['File is too big.'] = 'Soubor je pøíli¹ velký.';
$text['Maximum allowed filesize is'] = 'Maximálnì povolená velikost souboru je';
$text['Unsupported filetype:'] = 'Nepovolený formát souboru:';
$text['Supported filetypes are:'] = 'Povolené formáty souborù jsou:';
$text['Could not delete %s.'] = 'Soubor %s nelze vymazat.';
$text['Could not delete %s, file did not exist.'] = 'Soubor %s nelze vymazat, proto¾e neexistuje :-).';
$text['File: '] = 'Soubor: '; // prefix for alt-attribute
$text['no_image'] = '®ádný obrázek'; // alt-attribute if there's no image
$text['Delete this file'] = 'Smazat tento soubor';
	$text['File could not be saved. There is a problem with the user rights. We are working on it.'] = 'File could not be saved. There is a problem with the user rights. We are working on it.';
$text['Minimum width %s was not reached.'] = 'Minimální ¹íøka %s nebyla dosa¾ena.';
$text['Minimum height %s was not reached.'] = 'Minimální vý¹ka %s nebyla dosa¾ena.';
$text['Maximum width %s has been exceeded.'] = 'Maximální ¹íøka %s byla pøekroèena.';
$text['Maximum height %s has been exceeded.'] = 'Maximální vý¹ka %s byla pøekroèena.';

// Record form: Detail record
$text['Add %s'] = 'Pøidat %s'; // e. g. Add Address, Add Phone Number ...
$text['Remove %s'] = 'Odebrat %s';

// Record form: Validation, displayed inside form
$text['Please enter more characters.'] = 'Prosím, zadejte více znakù.';
$text['No entry found. Try less characters.'] = 'Hledání bylo neúspì¹né, zkuste zadat ménì znakù.';

// Record form: Validation, displayed above form
$text['Following_errors_occured'] = 'Do¹lo k následujícím chybám';
$text['Value_incorrect_in_field'] = '©patná hodnota';
$text['Value missing in field'] = 'Chybìjící hodnota';
	$text['Duplicate entry'] = 'Duplicate entry in this table. Please check whether the record you were about to enter already exists or you\'ll have to change the values you entered.';
	$text['Duplicate entry'] = 'Duplikovaný záznam v této tabulce.';
//	$text['Detail record could not be handled'] = '';

// Record form: Validation of Database Integrity, displayed above form
$text['No records in relation table'] = '®ádné záznamy v související tabulce %s. Prosím, doplòte.';
$text['Detail records exist in the following tables:'] = 'Detailní záznamy jsou v tìchto tabulkách:';
$text['This record could not be deleted because there are details about this record in other records.'] = 'Tento pøíspìvek nemù¾e být vymazán, proto¾e je vázán na ostatní záznamy.';

// Record form: foreign record
$text['edit-after-save'] = 'Nelze pøidat záznam - je tøeba ulo¾it aktuálnì otevøený pøíspìvek.';
$text['no-data-available'] = '®ádná data k dispozici.';

// ----------------------------------------------------------------------------
// List view
// ----------------------------------------------------------------------------

$text['table-empty'] = '®ádné záznamy k dispozici';
$text['- unknown -'] = '- neznámý -'; // group by unknown

// List view: Filter
$text['Selection'] = 'Výbìr';
$text['all'] = 'v¹echny';

// List view: Table head
$text['order by'] = 'Seøadit';
$text['asc'] = 'Vzestupnì';
$text['desc'] = 'Sestupnì';
$text['action'] = 'Akce';
$text['detail'] = 'Detaily';

// List view: bottom
$text['Add new record'] = 'Pøidat nový záznam';
$text['records total'] = 'záznamù celkovì';
$text['record total'] = 'celkový záznam';

// List view: Search form
	$text['Show all records'] = 'Show all records (without search filter)';
$text['all fields'] = 'v¹ech polích';
$text['in'] = 've';
$text['search'] = 'hledat'; // Button


// ----------------------------------------------------------------------------
// Error handling
// ----------------------------------------------------------------------------

$text['Warning!'] = 'Varování!';
	$text['incorrect value'] = 'incorrect value';
$text['database-error'] = 'Chyba databáze';

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
$text['Database error. This database has ambiguous values in ID field.'] ='Chyba databáze. Nelze pøeèíst záznamy ze sloupce ID databázové tabulky.';

// Development, Error mail
$text['Error during database operation'] = 'Chyba zpracování dat';
$text['The following error(s) occured in project %s:'] = 'Následující chyby se vyskytují v projektu %s:';


?>