<?php

/**
 * zzform
 * Text and labels in Czech (cs) iso-8859-2
 *
 * Part of "Zugzwang Project"
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright (c) 2006-2012 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// ----------------------------------------------------------------------------
// Page elements
// ----------------------------------------------------------------------------

$text['records'] = 'záznamù'; // Number of records as shown in page TITLE
$text['back-to-overview'] = 'Zpìt na pøehled';
//	$text['page'] = 'Seite';

// Heading
$text['Search'] = 'Hledat';


// ----------------------------------------------------------------------------
// Record form
// ----------------------------------------------------------------------------

$text['new'] = 'Nový'; // Link to add a new value for detail record via dropdown

// Record form: Heading
// @todo check if deprecated
$text['review'] = 'Prohlédnout';
$text['insert'] = 'Pøidat';
$text['update'] = 'Aktualizovat';
$text['edit'] = 'Editovat';
$text['delete'] = 'Vymazat';
$text['show'] = 'Zobrazit';
$text['add'] = 'Pøidat';

//	$text['Copy'] = 'Copy';

$text['Add a record'] = 'Pøidat záznam';
$text['Delete a record'] = 'Vymazat záznam';
$text['Edit a record'] = 'Editovat záznam';
$text['Show a record'] = 'Zobrazit záznam';
$text['Add several records'] = 'Mehrere Eintr&auml;ge hinzuf&uuml;gen';
$text['Delete several records'] = 'Mehrere Eintr&auml;ge l&ouml;schen';
$text['Edit several records'] = 'Mehrere Eintr&auml;ge bearbeiten';
$text['Insert failed'] = 'Pøidat neúspì¹né';
$text['Delete failed'] = 'Vymazat neúspì¹né';
$text['Update failed'] = 'Aktualizovat neúspì¹né';

$text['There is no record under this ID: %s'] = 'V poli ID není ¾ádný záznam: %s';
//	$text['Invalid ID for a record (must be an integer): %s'] = 'Invalid ID for a record (must be an integer): %s';
$text['record_was_updated'] = 'Záznam byl aktualizován';
//	$text['Record was not updated (no changes were made)'] = 'Eintrag wurde nicht aktualisiert (es gab keine &Auml;nderungen)';
$text['record_was_deleted'] = 'Záznam byl vymazán';
$text['record_was_inserted'] = 'Pøíspìvek byl ulo¾en';
//	$text['Configuration does not allow this action: %s'] = 'Die Konfiguration erlaubt diese Aktion nicht: %s';
//	$text['Configuration does not allow this mode: %s'] = 'Die Konfiguration erlaubt diesen Modus nicht: %s';

// Record form: bottom
$text['Cancel'] = 'Storno'; // Stop editing this record
//	$text['OK'] = 'OK';
$text['Update record'] = 'Aktualizovat k databáze';
$text['Delete record'] = 'Vymazat z databáze';
$text['Add record'] = 'Pøidat k databáze';

// Record form: field output
$text['N/A'] = '<abbr title="Not available">N/A</abbr>'; // no value available for display, should be abbreviated
$text['will_be_added_automatically'] = 'bude pøidáno automaticky';
$text['calculated_field'] = 'Generované políèko';

// Record form: Select
$text['none_selected'] = '®ádný nevybrán'; // dropdown, first entry
$text['no_selection'] = '®ádný výbìr'; // radio buttons, first entry
$text['no_source_defined'] = '®ádný zdroj nebyl definován';
$text['no_selection_possible'] = '®ádný výbìr není mo¾ný.';
//	$text['(This entry is the highest entry in the hierarchy.)'] = '(Dieser Eintrag ist der oberste Eintrag in der Hierarchie.)';

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
// $text['Your new password could not be saved. Please try a different one.'] = '';

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
//	$text['File could not be saved. There is a problem with the user rights. We are working on it.'] = 'File could not be saved. There is a problem with the user rights. We are working on it.';
$text['Minimum width %s was not reached.'] = 'Minimální ¹íøka %s nebyla dosa¾ena.';
$text['Minimum height %s was not reached.'] = 'Minimální vý¹ka %s nebyla dosa¾ena.';
$text['Maximum width %s has been exceeded.'] = 'Maximální ¹íøka %s byla pøekroèena.';
$text['Maximum height %s has been exceeded.'] = 'Maximální vý¹ka %s byla pøekroèena.';
//	$text['Transfer failed. Probably you sent a file that was too large.'] = '&Uuml;bertragung fehlgeschlagen. Vermutlich haben Sie eine Datei gesendet, die zu gro&szlig; war.';
//	$text['You sent: %s data.'] = 'Sie haben %s Daten gesendet.';

// Record form: Detail record
$text['Add %s'] = 'Pøidat %s'; // e. g. Add Address, Add Phone Number ...
$text['Remove %s'] = 'Odebrat %s';
//	$text['Minimum of records for table `%s` was not met (%d)'] = 'Im Feld `%s` sind mehr Daten erforderlich (min. Anzahl: %d)';

// Record form: Validation, displayed inside form
$text['Please enter more characters.'] = 'Prosím, zadejte více znakù.';
$text['No entry found. Try less characters.'] = 'Hledání bylo neúspì¹né, zkuste zadat ménì znakù.';

// Record form: Validation, displayed above form
$text['Following_errors_occured'] = 'Do¹lo k následujícím chybám';
$text['Value incorrect in field %s'] = '©patná hodnota %s';
$text['Value missing in field %s'] = 'Chybìjící hodnota %s';
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

// Record form: identifier, hidden etc.
//	$text['Record for %s does not exist.'] = 'Eintrag f&uuml;r %s existiert nicht.';
//	$text['Would be changed on update'] = 'W&uuml;rde bei Aktualisierung ge&auml;ndert.';

// ----------------------------------------------------------------------------
// List view
// ----------------------------------------------------------------------------

$text['table-empty'] = '®ádné záznamy k dispozici';
$text['- unknown -'] = '- neznámý -'; // group by unknown

// List view: Filter
$text['Selection'] = 'Výbìr';
$text['all'] = 'v¹echny';
//	$text['"%s" is not a valid value for the selection "%s". Please select a different filter.'] = '&#187;%s&#171; ist kein g&uuml;ltiger Wert f&uuml;r die Auswahl &#187;%s&#171;. Bitte treffen Sie eine andere Auswahl.';
//	$text['A filter for the selection "%s" does not exist.'] = 'Es existiert kein Filter f&uuml;r die Auswahl &#187;%s&#171;.';
//	$text['List without this filter'] = 'Die Liste ohne diesen Filter';

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
//	$text['All records on one page'] = 'Alle Eintr&auml;ge auf einer Seite';
//	$text['First page'] = 'Erste Seite';
//	$text['Previous page'] = 'Vorige Seite';
//	$text['Next page'] = 'N&auml;chste Seite';
//	$text['Last page'] = 'Letzte Seite';

// List view: Search form
//	$text['Show all records'] = 'Show all records (without search filter)';
$text['in'] = 've';
$text['all fields'] = 'v¹ech polích';
$text['search'] = 'hledat'; // Button


// ----------------------------------------------------------------------------
// Error handling
// ----------------------------------------------------------------------------

$text['Warning!'] = 'Varování!';
//	$text['incorrect value'] = 'incorrect value';
$text['database-error'] = 'Chyba databáze';
//	$text['An error occured. We are working on the solution of this problem. Sorry for your inconvenience. Please try again later.'] = 'Ein Fehler ist aufgetreten. Wir arbeiten an der L&ouml;sung des Problems. Bitte entschuldigen Sie die Unannehmlichkeiten. Versuchen Sie es bitte sp&auml;ter nocheinmal.';


// ----------------------------------------------------------------------------
// Modules: Export
// ----------------------------------------------------------------------------

$text['Export'] = 'Export'; // Export-Link


// ----------------------------------------------------------------------------
// Modules: Geo
// ----------------------------------------------------------------------------

//	$text['N'] = '<abbr title="North">N</abbr>';
//	$text['E'] = '<abbr title="East">V</abbr>';
//	$text['S'] = '<abbr title="South">J</abbr>';
//	$text['W'] = '<abbr title="West">Z</abbr>';
//	$text['It looks like this coordinate has a different orientation. Maybe latitude and longitude were interchanged?'] = 'Es sieht aus, als ob diese Koordinate eine andere Orientierung hat. Wurden L&auml;nge und Breite vertauscht?';
//	$text['Mismatch: %s signals different hemisphere than %s.'] = 'Nicht eindeutig: %s zeigt eine andere Hemisph&auml;re an als %s.';
//	$text['There are too many decimal points (or commas) in this value.'] = 'In diesem Wert sind zuviele Kommas oder Punkte.';
//	$text['Only the last number might have a decimal point (or comma).'] = 'Nur die letzte Zahl darf ein Komma enthalten (oder einen Punkt).';
//	$text['%s is too small. Please enter for minutes a positive value or 0.'] = '%s ist zu klein. Bitte geben Sie f&uuml;r Minuten einen positiven Wert oder 0 ein.';
//	$text['%s is too small. Please enter for seconds a positive value or 0.'] = '%s ist zu klein. Bitte geben Sie f&uuml;r Sekunden einen positiven Wert oder 0 ein';
//	$text['%s is too big. Please enter for minutes a value smaller than 60.'] = '%s ist zu gro&szlig;. Bitte geben Sie f&uuml;r Minuten einen Wert kleiner als 60 ein.';
//	$text['%s is too big. Please enter for seconds a value smaller than 60.'] = '%s ist zu gro&szlig;. Bitte geben Sie f&uuml;r Sekunden einen Wert kleiner als 60 ein.';
//	$text['Sorry, there are too many numbers. We cannot interpret what you entered.'] = 'Wir k&ouml;nnen leider nicht diese Koordinate korrekt interpretieren, da zuviele Zahlen eingegeben wurden.';
//	$text['Minimum value for degrees is %s. The value you entered is too small: %s.'] = 'Der kleinste Wert f&uuml;r Grad ist %s. Der Wert, den Sie eingegeben haben, ist zu klein: %s.';
//	$text['Maximum value for degrees is %s. The value you entered is too big: %s.'] = 'Der gr&ouml;&szlig;te Wert f&uuml;r Grad ist %s. Der Wert, den Sie eingegeben haben, ist zu gro&szlig;: %s.';


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
