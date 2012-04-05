<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2006-2010
// Text and labels in Polish (pl) iso-8859-2


// ----------------------------------------------------------------------------
// Page elements
// ----------------------------------------------------------------------------

$text['records'] = 'Wpisy'; // Number of records as shown in page TITLE
$text['back-to-overview'] = 'Z powrotem do podgl�du';

// Heading
$text['Search'] = 'Wyszukiwanie';


// ----------------------------------------------------------------------------
// Record form
// ----------------------------------------------------------------------------

$text['new'] = 'Nowy'; // Link to add a new value for detail record via dropdown

// Record form: Heading
$text['show_record'] = 'Poka� wpisy';
$text['review'] = 'Poka�';
$text['insert'] = 'Wstaw';
$text['update'] = 'Aktualizacja';
$text['edit'] = 'Zmieni�';
$text['delete'] = 'Usun��';
$text['show'] = 'Pokaza�';
$text['add'] = 'Dodaj';
$text['a_record'] = 'Wpisu';
$text['failed'] = 'Nie powiod�o si�';
$text['There is no record under this ID:'] = 'Istnieje plik danych o tym ID:';
$text['record_was_updated'] = 'Wpis zaktualizowany';
$text['record_was_deleted'] = 'Wpis usuni�ty';
$text['record_was_inserted'] = 'Wpis zosta� dodany';

// Record form: bottom
$text['Cancel'] = 'Anuluj'; // Stop editing this record
$text['Update record'] = 'Zmie� na bazy danych';
$text['Delete record'] = 'Usu� z bazy danych';
$text['Add record'] = 'Do��cz do bazy danych';

// Record form: field output
	$text['N/A'] = '<abbr title="Not available">N/A</abbr>'; // no value available for display, should be abbreviated
$text['N/A'] = 'Niedost�pne';
$text['will_be_added_automatically'] = 'zostanie automatycznie dodane';
$text['calculated_field'] = 'Pole wyliczone';

// Record form: Select
$text['none_selected'] = 'Nie wybrano'; // dropdown, first entry
$text['no_selection'] = 'brak wyboru'; // radio buttons, first entry
$text['no_source_defined'] = 'Brak podanego �r�d�a';
$text['no_selection_possible'] = 'Wyb�r niemo�liwy';

// Record form: Change password
$text['Old:'] = 'Stary:';
$text['New:'] = 'Nowy:';
$text['(Please confirm your new password twice)'] = '(Prosz� potwierdzi� swoje has�o dwa razy)';
$text['Your password has been changed!'] = 'Twoje has�o zosta�o zmienione';
$text['Your current password is different from what you entered. Please try again.'] = 'Twoje aktualne has�o nie jest tym, kt�re poda�e�. Spr�buj jeszcze raz.';
$text['New passwords do not match. Please try again.'] = 'Wprowadzone dane do nowego has�a r�ni� si�. Spr�buj jeszcze raz.';
$text['Please enter your current password and twice your new password.'] = 'Prosz� wprowadzi� aktualne has�o i dwa razy nowe has�o.';
$text['New and old password are identical. Please choose a different new password.'] = 'Nowe i stare has�o s� identyczne. Prosze wybra� inne has�o.';
$text['Rules for secure passwords'] = 'Zasady bezpiecznego has�a';
$text['password-rules'] = 'Has�o musi sk�ada� si� z min. 8 znak�w i zawiera� du�e i ma�e litery, cyfry oraz znaki specjalne.

Przyk�ady bezpiecznych hase�:

* !1Pw=ig! (Zasada: !Has�o powinno by� znane tylko Tobie!)
* 1sPh&uuml;o&auml;o&Ouml;  (Zasada: bezpieczne has�o powinno zawiera� &uuml; oder &auml; oder &ouml;)';
$text['hidden'] = 'Ukryte';
// $text['Your new password could not be saved. Please try a different one.'] = '';

// Record form: File upload
$text['image_not_display'] = 'Obraz nie mo�e (jeszcze) zosta� wy�wietlony';
$text['Error: '] = 'B��d: ';
$text['No file was uploaded.'] = 'Nie za�adowano �adnego pliku.';
$text['File was only partially uploaded.'] = 'Plik zosta� za�adowany cz�ciowo.';
$text['File is too big.'] = 'Plik jest zbyt du�y.';
$text['Maximum allowed filesize is'] = 'Maksymalna wielko�� pliku wynosi';
$text['Unsupported filetype:'] = 'Nieobs�ugiwany typ pliku:';
$text['Supported filetypes are:'] = 'Obs�ugiwane typy plik�w to:';
$text['Could not delete %s.'] = 'Nie mo�na by�o usun�� %s.';
$text['Could not delete %s, file did not exist.'] = 'Nie mo�na by�o usun�� %s. Plik nie istnieje.';
$text['File: '] = 'Plik: '; // prefix for alt-attribute
$text['no_image'] = 'Brak obrazu'; // alt-attribute if there's no image
$text['Delete this file'] = 'Skazuj ten plik';
	$text['File could not be saved. There is a problem with the user rights. We are working on it.'] = 'File could not be saved. There is a problem with the user rights. We are working on it.';
$text['Minimum width %s was not reached.'] = 'Minimalna szeroko�� %s nie zosta�a osi�gni�ta.';
$text['Minimum height %s was not reached.'] = 'Minimalna wysoko�� %s nie zosta�a osi�gni�ta.';
$text['Maximum width %s has been exceeded.'] = 'Maksymalna szeroko�� %s zosta�a przekroczona.';
$text['Maximum height %s has been exceeded.'] = 'Maksymalna wysoko�� %s zosta�a przekroczona.';

// Record form: Detail record
$text['Add %s'] = 'Dodaj %s'; // e. g. Add Address, Add Phone Number ...
$text['Remove %s'] = 'Usun�� %s';

// Record form: Validation, displayed inside form
$text['Please enter more characters.'] = 'Prosze poda� wi�cej liter.';
$text['No entry found. Try less characters.'] = 'Nie odnaleziono. Prosze spr�bowa� z wi�ksz� ilo�ci� znak�w.';

// Record form: Validation, displayed above form
$text['Following_errors_occured'] = 'Wyst�pi�y nast�puj�ce b��dy';
$text['Value_incorrect_in_field'] = 'B��d danych';
$text['Value missing in field'] = 'Brak danych';
	$text['Duplicate entry'] = 'Duplicate entry in this table. Please check whether the record you were about to enter already exists or you\'ll have to change the values you entered.';
$text['Duplicate entry'] = 'Podw�jny wpis.';
$text['Detail record could not be handled'] = 'Wpis nie mo�e by� zapisany.';

// Record form: Validation of Database Integrity, displayed above form
	$text['No records in relation table'] = 'No records in relation table %s. Please fill in records.';
$text['No records in relation table'] = 'Brak wpis�w w tabeli. Uzupe�nij';
$text['Detail records exist in the following tables:'] = 'Szczeg�pwe wpisy wyst�puj� w nast�puj�cych tabelach:';
$text['This record could not be deleted because there are details about this record in other records.'] = 'Nie mo�na usun�� wpisu, poniewa� jego elementy wyst�puj� w innych wpisach.';

// Record form: foreign record
$text['edit-after-save'] = 'Zapisz przed edycj�.';
$text['no-data-available'] = 'Brak danych';


// ----------------------------------------------------------------------------
// List view
// ----------------------------------------------------------------------------

$text['table-empty'] = 'Brak zapis�w.';
$text['- unknown -'] = '- nieznany -'; // group by unknown

// List view: Filter
$text['Selection'] = 'Wyb�r';
$text['all'] = 'Wszystkie';
//	$text['"%s" is not a valid value for the selection "%s". Please select a different filter.'] = '&#187;%s&#171; ist kein g&uuml;ltiger Wert f&uuml;r die Auswahl &#187;%s&#171;. Bitte treffen Sie eine andere Auswahl.';
//	$text['A filter for the selection "%s" does not exist.'] = 'Es existiert kein Filter f&uuml;r die Auswahl &#187;%s&#171;.';
//	$text['List without this filter'] = 'Die Liste ohne diesen Filter';

// List view: Table head
$text['order by'] = 'Sortuj wed�ug';
$text['asc'] = 'Rosn�co';
$text['desc'] = 'Malej�co';
$text['action'] = 'Akcja';
$text['detail'] = 'Szczeg�y';

// List view: bottom
$text['Add new record'] = 'Nowy wpis';
$text['records total'] = 'Wszystkie wpisy';
$text['record total'] = 'Ca�y wpis';

// List view: Search form
	$text['Show all records'] = 'Show all records (without search filter)';
$text['all fields'] = 'wszystkich polach';
$text['in'] = 'w';
$text['search'] = 'Szukaj'; // Button


// ----------------------------------------------------------------------------
// Error handling
// ----------------------------------------------------------------------------

$text['Warning!'] = 'Ostrze�enie!';
	$text['incorrect value'] = 'incorrect value';
$text['database-error'] = 'B��d bazy danych';


// ----------------------------------------------------------------------------
// Modules: Export
// ----------------------------------------------------------------------------

$text['Export'] = 'Eksport'; // Export-Link


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

	$text['N'] = '<abbr title="na p�noc">N</abbr>';
	$text['E'] = '<abbr title="wsch�d">E</abbr>';
	$text['S'] = '<abbr title="po�udnie">S</abbr>';
	$text['W'] = '<abbr title="zach�d">W</abbr>';
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
$text['Database error. This query has ambiguous values in ID field.'] = 'B��d bazy. Zapytanie zawiera niejednoznaczne warto�ci';

// Development, Error mail
$text['Error during database operation'] = 'B��d bazy danych';
$text['The following error(s) occured in project %s:'] = 'Projekt wyst�pi� w projekcie %s:';


?>