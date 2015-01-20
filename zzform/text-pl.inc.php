<?php

/**
 * zzform
 * Text and labels in Polish (pl) in utf-8 encoding
 *
 * Part of Zugzwang Project
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright (c) 2006-2012, 2015 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// ----------------------------------------------------------------------------
// Page elements
// ----------------------------------------------------------------------------

$text['records'] = 'Wpisy'; // Number of records as shown in page TITLE
$text['back-to-overview'] = 'Z powrotem do podglądu';

// Heading
$text['Search'] = 'Wyszukiwanie';


// ----------------------------------------------------------------------------
// Record form
// ----------------------------------------------------------------------------

$text['new'] = 'Nowy'; // Link to add a new value for detail record via dropdown

// Record form: Heading
// @todo check if deprecated
$text['review'] = 'Pokaż';
$text['insert'] = 'Wstaw';
$text['update'] = 'Aktualizacja';
$text['edit'] = 'Zmienić';
$text['delete'] = 'Usunąć';
$text['show'] = 'Pokazać';
$text['add'] = 'Dodaj';

//	$text['Copy'] = 'Copy';

$text['Add a record'] = 'Dodaj wpisy';
$text['Delete a record'] = 'Usunąć wpisu';
$text['Edit a record'] = 'Zmienić wpisu';
$text['Show a record'] = 'Pokaż wpisy';
//	$text['Add several records'] = '';
//	$text['Delete several records'] = '';
//	$text['Edit several records'] = '';
$text['Insert failed'] = 'Nie powiodło się wstaw';
$text['Delete failed'] = 'Nie powiodło się usunąć';
$text['Update failed'] = 'Nie powiodło się aktualizacja';

$text['There is no record under this ID: %s'] = 'Istnieje plik danych o tym ID: %s';
//	$text['Invalid ID for a record (must be an integer): %s'] = 'Invalid ID for a record (must be an integer): %s';
$text['record_was_updated'] = 'Wpis zaktualizowany';
$text['record_was_deleted'] = 'Wpis usunięty';
$text['record_was_inserted'] = 'Wpis został dodany';

// Record form: bottom
$text['Cancel'] = 'Anuluj'; // Stop editing this record
$text['Update record'] = 'Zmień na bazy danych';
$text['Delete record'] = 'Usuń z bazy danych';
$text['Add record'] = 'Dołącz do bazy danych';

// Record form: field output
	$text['N/A'] = '<abbr title="Not available">N/A</abbr>'; // no value available for display, should be abbreviated
$text['N/A'] = 'Niedostępne';
$text['will_be_added_automatically'] = 'zostanie automatycznie dodane';
$text['calculated_field'] = 'Pole wyliczone';

// Record form: Select
$text['none_selected'] = 'Nie wybrano'; // dropdown, first entry
$text['no_selection'] = 'brak wyboru'; // radio buttons, first entry
$text['no_source_defined'] = 'Brak podanego źródła';
$text['no_selection_possible'] = 'Wybór niemożliwy';

// Record form: Change password
$text['Old:'] = 'Stary:';
$text['New:'] = 'Nowy:';
$text['(Please confirm your new password twice)'] = '(Proszę potwierdzić swoje hasło dwa razy)';
$text['Your password has been changed!'] = 'Twoje hasło zostało zmienione';
$text['Your current password is different from what you entered. Please try again.'] = 'Twoje aktualne hasło nie jest tym, które podałeś. Spróbuj jeszcze raz.';
$text['New passwords do not match. Please try again.'] = 'Wprowadzone dane do nowego hasła różnią się. Spróbuj jeszcze raz.';
$text['Please enter your current password and twice your new password.'] = 'Proszę wprowadzić aktualne hasło i dwa razy nowe hasło.';
$text['New and old password are identical. Please choose a different new password.'] = 'Nowe i stare hasło są identyczne. Prosze wybrać inne hasło.';
$text['Rules for secure passwords'] = 'Zasady bezpiecznego hasła';
$text['password-rules'] = 'Hasło musi składać się z min. 8 znaków i zawierać duże i małe litery, cyfry oraz znaki specjalne.

Przykłady bezpiecznych haseł:

* !1Pw=ig! (Zasada: !Hasło powinno być znane tylko Tobie!)
* 1sPh&uuml;o&auml;o&Ouml;  (Zasada: bezpieczne hasło powinno zawierać &uuml; oder &auml; oder &ouml;)';
$text['hidden'] = 'Ukryte';
// $text['Your new password could not be saved. Please try a different one.'] = '';

// Record form: File upload
$text['image_not_display'] = 'Obraz nie może (jeszcze) zostać wyświetlony';
$text['Error: '] = 'Błąd: ';
$text['No file was uploaded.'] = 'Nie załadowano żadnego pliku.';
$text['File was only partially uploaded.'] = 'Plik został załadowany częściowo.';
$text['File is too big.'] = 'Plik jest zbyt duży.';
$text['Maximum allowed filesize is'] = 'Maksymalna wielkość pliku wynosi';
$text['Unsupported filetype:'] = 'Nieobsługiwany typ pliku:';
$text['Supported filetypes are:'] = 'Obsługiwane typy plików to:';
$text['Could not delete %s.'] = 'Nie można było usunąć %s.';
$text['Could not delete %s, file did not exist.'] = 'Nie można było usunąć %s. Plik nie istnieje.';
$text['File: '] = 'Plik: '; // prefix for alt-attribute
$text['no_image'] = 'Brak obrazu'; // alt-attribute if there's no image
$text['Delete this file'] = 'Skazuj ten plik';
	$text['File could not be saved. There is a problem with the user rights. We are working on it.'] = 'File could not be saved. There is a problem with the user rights. We are working on it.';
$text['Minimum width %s was not reached.'] = 'Minimalna szerokość %s nie została osiągnięta.';
$text['Minimum height %s was not reached.'] = 'Minimalna wysokość %s nie została osiągnięta.';
$text['Maximum width %s has been exceeded.'] = 'Maksymalna szerokość %s została przekroczona.';
$text['Maximum height %s has been exceeded.'] = 'Maksymalna wysokość %s została przekroczona.';

// Record form: Detail record
$text['Add %s'] = 'Dodaj %s'; // e. g. Add Address, Add Phone Number ...
$text['Remove %s'] = 'Usunąć %s';

// Record form: Validation, displayed inside form
$text['Please enter more characters.'] = 'Prosze podać więcej liter.';
$text['No entry found. Try less characters.'] = 'Nie odnaleziono. Prosze spróbować z większą ilością znaków.';

// Record form: Validation, displayed above form
$text['Following_errors_occured'] = 'Wystąpiły następujące błędy';
$text['Value incorrect in field %s.'] = 'Błąd danych %s.';
$text['Value missing in field %s.'] = 'Brak danych %s.';
	$text['Duplicate entry'] = 'Duplicate entry in this table. Please check whether the record you were about to enter already exists or you\'ll have to change the values you entered.';
$text['Duplicate entry'] = 'Podwójny wpis.';
$text['Detail record could not be handled'] = 'Wpis nie może być zapisany.';

// Record form: Validation of Database Integrity, displayed above form
	$text['No records in relation table'] = 'No records in relation table %s. Please fill in records.';
$text['No records in relation table'] = 'Brak wpisów w tabeli. Uzupełnij';
$text['Detail records exist in the following tables:'] = 'Szczegółpwe wpisy występują w następujących tabelach:';
$text['This record could not be deleted because there are details about this record in other records.'] = 'Nie można usunąć wpisu, ponieważ jego elementy występują w innych wpisach.';

// Record form: foreign record
$text['edit-after-save'] = 'Zapisz przed edycją.';
$text['no-data-available'] = 'Brak danych';


// ----------------------------------------------------------------------------
// List view
// ----------------------------------------------------------------------------

$text['table-empty'] = 'Brak zapisów.';
$text['- unknown -'] = '- nieznany -'; // group by unknown

// List view: Filter
$text['Selection'] = 'Wybór';
$text['all'] = 'Wszystkie';
//	$text['"%s" is not a valid value for the selection "%s". Please select a different filter.'] = '&#187;%s&#171; ist kein g&uuml;ltiger Wert f&uuml;r die Auswahl &#187;%s&#171;. Bitte treffen Sie eine andere Auswahl.';
//	$text['A filter for the selection "%s" does not exist.'] = 'Es existiert kein Filter f&uuml;r die Auswahl &#187;%s&#171;.';
//	$text['List without this filter'] = 'Die Liste ohne diesen Filter';

// List view: Table head
$text['order by'] = 'Sortuj według';
$text['asc'] = 'Rosnąco';
$text['desc'] = 'Malejąco';
$text['action'] = 'Akcja';
$text['detail'] = 'Szczegóły';

// List view: bottom
$text['Add new record'] = 'Nowy wpis';
$text['records total'] = 'Wszystkie wpisy';
$text['record total'] = 'Cały wpis';

// List view: Search form
	$text['Show all records'] = 'Show all records (without search filter)';
$text['all fields'] = 'wszystkich polach';
$text['in'] = 'w';
$text['search'] = 'Szukaj'; // Button


// ----------------------------------------------------------------------------
// Error handling
// ----------------------------------------------------------------------------

$text['Warning!'] = 'Ostrzeżenie!';
	$text['incorrect value'] = 'incorrect value';
$text['database-error'] = 'Błąd bazy danych';


// ----------------------------------------------------------------------------
// Modules: Export
// ----------------------------------------------------------------------------

$text['Export'] = 'Eksport'; // Export-Link


// ----------------------------------------------------------------------------
// Modules: Geo
// ----------------------------------------------------------------------------

	$text['N'] = '<abbr title="na północ">N</abbr>';
	$text['E'] = '<abbr title="wschód">E</abbr>';
	$text['S'] = '<abbr title="południe">S</abbr>';
	$text['W'] = '<abbr title="zachód">W</abbr>';
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
	$text['Script configuration error. No display field set.'] = 'Script configuration error. No display field set.';
	$text['Field name'] = 'Field name'; // introduces field name with wrong values
	$text['no-delete'] = "Don't delete"; // from table relations
$text['Database error. This query has ambiguous values in ID field.'] = 'Błąd bazy. Zapytanie zawiera niejednoznaczne wartości';

// Development, Error mail
$text['Error during database operation'] = 'Błąd bazy danych';
$text['The following error(s) occured in project %s:'] = 'Projekt wystąpił w projekcie %s:';

