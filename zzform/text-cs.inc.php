<?php

/**
 * zzform
 * Text and labels in Czech (cs) in utf-8 encoding
 *
 * Part of "Zugzwang Project"
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright (c) 2006-2012, 2015 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// ----------------------------------------------------------------------------
// Page elements
// ----------------------------------------------------------------------------

$text['records'] = 'záznamů'; // Number of records as shown in page TITLE
$text['back-to-overview'] = 'Zpět na přehled';
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
$text['insert'] = 'Přidat';
$text['update'] = 'Aktualizovat';
$text['edit'] = 'Editovat';
$text['delete'] = 'Vymazat';
$text['show'] = 'Zobrazit';
$text['add'] = 'Přidat';

//	$text['Copy'] = 'Copy';

$text['Add a record'] = 'Přidat záznam';
$text['Delete a record'] = 'Vymazat záznam';
$text['Edit a record'] = 'Editovat záznam';
$text['Show a record'] = 'Zobrazit záznam';
$text['Add several records'] = 'Mehrere Eintr&auml;ge hinzuf&uuml;gen';
$text['Delete several records'] = 'Mehrere Eintr&auml;ge l&ouml;schen';
$text['Edit several records'] = 'Mehrere Eintr&auml;ge bearbeiten';
$text['Insert failed'] = 'Přidat neúspěšné';
$text['Delete failed'] = 'Vymazat neúspěšné';
$text['Update failed'] = 'Aktualizovat neúspěšné';

$text['The record with the ID %d was already deleted.'] = 'V poli ID není žádný záznam: %d.';
$text['A record with the ID %d does not exist.'] = 'V poli ID není žádný záznam: %d.';
//	$text['Invalid ID for a record (must be an integer): %s'] = 'Invalid ID for a record (must be an integer): %s';
$text['record_was_updated'] = 'Záznam byl aktualizován';
//	$text['Record was not updated (no changes were made)'] = 'Eintrag wurde nicht aktualisiert (es gab keine &Auml;nderungen)';
$text['record_was_deleted'] = 'Záznam byl vymazán';
$text['record_was_inserted'] = 'Příspěvek byl uložen';
//	$text['Configuration does not allow this action: %s'] = 'Die Konfiguration erlaubt diese Aktion nicht: %s';
//	$text['Configuration does not allow this mode: %s'] = 'Die Konfiguration erlaubt diesen Modus nicht: %s';

// Record form: bottom
$text['Cancel'] = 'Storno'; // Stop editing this record
//	$text['OK'] = 'OK';
$text['Update record'] = 'Aktualizovat k databáze';
$text['Delete record'] = 'Vymazat z databáze';
$text['Add record'] = 'Přidat k databáze';

// Record form: field output
$text['N/A'] = '<abbr title="Not available">N/A</abbr>'; // no value available for display, should be abbreviated
$text['will_be_added_automatically'] = 'bude přidáno automaticky';
$text['calculated_field'] = 'Generované políčko';

// Record form: Select
$text['none_selected'] = 'Žádný nevybrán'; // dropdown, first entry
$text['no_selection'] = 'Žádný výběr'; // radio buttons, first entry
$text['no_source_defined'] = 'Žádný zdroj nebyl definován';
$text['no_selection_possible'] = 'Žádný výběr není možný.';
//	$text['(This entry is the highest entry in the hierarchy.)'] = '(Dieser Eintrag ist der oberste Eintrag in der Hierarchie.)';

// Record form: Change password
$text['Old:'] = 'Staré:';
$text['New:'] = 'Nové:';
$text['(Please confirm your new password twice)'] = '(Zadejte vaše heslo dvakrát)';
$text['Your password has been changed!'] = 'Vaše heslo bylo změněno!';
$text['Your current password is different from what you entered. Please try again.'] = 'Vaše heslo je odlišné od toho, které jste právě zadali. Zkuste to ještě jednou.';
$text['New passwords do not match. Please try again.'] = 'Nově zadaná hesla nesedí. Zkuste ještě jednou.';
$text['Please enter your current password and twice your new password.'] = 'Zadejte vaše současné heslo a zároveň dvakrát potvrďte nové.';
$text['New and old password are identical. Please choose a different new password.'] = 'Nové a staré heslo je stejné. Zadejte jiné nové heslo.';
$text['Rules for secure passwords'] = 'Pravidla pro tvorbu bezpečného hesla';
$text['password-rules'] = 'Heslo musí obsahovat minimálně osm znaků. Doporučuje se používat velká i malá písmena, čísla a speciální znaky jako ?=+; atd.'; 
$text['hidden'] = 'schovat';
// $text['Your new password could not be saved. Please try a different one.'] = '';

// Record form: File upload
$text['image_not_display'] = 'Nelze zobrazit';
$text['Error: '] = 'Chyba: ';
$text['No file was uploaded.'] = 'Žádný soubor nebyl nahrán.';
$text['File was only partially uploaded.'] = 'Soubor byl nahrán pouze částečně.';
$text['File is too big.'] = 'Soubor je příliš velký.';
$text['Maximum allowed filesize is'] = 'Maximálně povolená velikost souboru je';
$text['Unsupported filetype:'] = 'Nepovolený formát souboru:';
$text['Supported filetypes are:'] = 'Povolené formáty souborů jsou:';
$text['Could not delete %s.'] = 'Soubor %s nelze vymazat.';
$text['Could not delete %s, file did not exist.'] = 'Soubor %s nelze vymazat, protože neexistuje :-).';
$text['File: '] = 'Soubor: '; // prefix for alt-attribute
$text['no_image'] = 'Žádný obrázek'; // alt-attribute if there's no image
$text['Delete this file'] = 'Smazat tento soubor';
//	$text['File could not be saved. There is a problem with the user rights. We are working on it.'] = 'File could not be saved. There is a problem with the user rights. We are working on it.';
$text['Minimum width %s was not reached.'] = 'Minimální šířka %s nebyla dosažena.';
$text['Minimum height %s was not reached.'] = 'Minimální výška %s nebyla dosažena.';
$text['Maximum width %s has been exceeded.'] = 'Maximální šířka %s byla překročena.';
$text['Maximum height %s has been exceeded.'] = 'Maximální výška %s byla překročena.';
//	$text['Transfer failed. Probably you sent a file that was too large.'] = '&Uuml;bertragung fehlgeschlagen. Vermutlich haben Sie eine Datei gesendet, die zu gro&szlig; war.';
//	$text['You sent: %s data.'] = 'Sie haben %s Daten gesendet.';

// Record form: Detail record
$text['Add %s'] = 'Přidat %s'; // e. g. Add Address, Add Phone Number ...
$text['Remove %s'] = 'Odebrat %s';
//	$text['Minimum of records for table `%s` was not met (%d).'] = 'Im Feld `%s` sind mehr Daten erforderlich (min. Anzahl: %d).';

// Record form: Validation, displayed inside form
$text['Please enter more characters.'] = 'Prosím, zadejte více znaků.';
$text['No entry found. Try less characters.'] = 'Hledání bylo neúspěšné, zkuste zadat méně znaků.';

// Record form: Validation, displayed above form
$text['Following_errors_occured'] = 'Došlo k následujícím chybám';
$text['Value incorrect in field %s.'] = 'Špatná hodnota %s.';
$text['Value missing in field %s.'] = 'Chybějící hodnota %s.';
	$text['Duplicate entry'] = 'Duplicate entry in this table. Please check whether the record you were about to enter already exists or you\'ll have to change the values you entered.';
	$text['Duplicate entry'] = 'Duplikovaný záznam v této tabulce.';
//	$text['Detail record could not be handled'] = '';

// Record form: Validation of Database Integrity, displayed above form
$text['No records in relation table'] = 'Žádné záznamy v související tabulce %s. Prosím, doplňte.';
$text['Detail records exist in the following tables:'] = 'Detailní záznamy jsou v těchto tabulkách:';
$text['This record could not be deleted because there are details about this record in other records.'] = 'Tento příspěvek nemůže být vymazán, protože je vázán na ostatní záznamy.';

// Record form: foreign record
$text['edit-after-save'] = 'Nelze přidat záznam - je třeba uložit aktuálně otevřený příspěvek.';
$text['no-data-available'] = 'Žádná data k dispozici.';

// Record form: identifier, hidden etc.
//	$text['Record for %s does not exist.'] = 'Eintrag f&uuml;r %s existiert nicht.';
//	$text['Would be changed on update'] = 'W&uuml;rde bei Aktualisierung ge&auml;ndert.';

// ----------------------------------------------------------------------------
// List view
// ----------------------------------------------------------------------------

$text['table-empty'] = 'Žádné záznamy k dispozici';
$text['- unknown -'] = '- neznámý -'; // group by unknown

// List view: Filter
$text['Selection'] = 'Výběr';
$text['all'] = 'všechny';
//	$text['"%s" is not a valid value for the selection "%s". Please select a different filter.'] = '&#187;%s&#171; ist kein g&uuml;ltiger Wert f&uuml;r die Auswahl &#187;%s&#171;. Bitte treffen Sie eine andere Auswahl.';
//	$text['A filter for the selection "%s" does not exist.'] = 'Es existiert kein Filter f&uuml;r die Auswahl &#187;%s&#171;.';
//	$text['List without this filter'] = 'Die Liste ohne diesen Filter';

// List view: Table head
$text['order by'] = 'Seřadit';
$text['asc'] = 'Vzestupně';
$text['desc'] = 'Sestupně';
$text['action'] = 'Akce';
$text['detail'] = 'Detaily';

// List view: bottom
$text['Add new record'] = 'Přidat nový záznam';
$text['records total'] = 'záznamů celkově';
$text['record total'] = 'celkový záznam';
//	$text['All records on one page'] = 'Alle Eintr&auml;ge auf einer Seite';
//	$text['First page'] = 'Erste Seite';
//	$text['Previous page'] = 'Vorige Seite';
//	$text['Next page'] = 'N&auml;chste Seite';
//	$text['Last page'] = 'Letzte Seite';

// List view: Search form
//	$text['Show all records'] = 'Show all records (without search filter)';
$text['in'] = 've';
$text['all fields'] = 'všech polích';
$text['search'] = 'hledat'; // Button


// ----------------------------------------------------------------------------
// Error handling
// ----------------------------------------------------------------------------

$text['Warning!'] = 'Varování!';
//	$text['incorrect value'] = 'incorrect value';
$text['Database error'] = 'Chyba databáze';
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
$text['Database error. This database has ambiguous values in ID field.'] ='Chyba databáze. Nelze přečíst záznamy ze sloupce ID databázové tabulky.';

// Development, Error mail
$text['Error during database operation'] = 'Chyba zpracování dat';
$text['The following error(s) occured in project %s:'] = 'Následující chyby se vyskytují v projektu %s:';
