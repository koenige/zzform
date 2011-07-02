<?php

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2005-2011
// Text and labels in German (de) us-ascii


// ----------------------------------------------------------------------------
// Page elements
// ----------------------------------------------------------------------------

$text['records'] = 'Eintr&auml;ge'; // Number of records as shown in page TITLE
$text['back-to-overview'] = 'Zur&uuml;ck zur &Uuml;bersicht';
	$text['page'] = 'Seite';

// Heading
$text['Search'] = 'Suche';


// ----------------------------------------------------------------------------
// Record form
// ----------------------------------------------------------------------------

$text['new'] = 'Neu'; // Link to add a new value for detail record via dropdown

// Record form: Heading
$text['show_record'] = 'Eintrag anzeigen';
$text['review'] = 'Anzeigen';
$text['insert'] = 'Einf&uuml;gen';
$text['update'] = 'Aktualisieren';
$text['edit'] = 'Bearbeiten';
$text['delete'] = 'L&ouml;schen';
$text['show'] = 'Anzeigen';
	$text['Copy'] = 'Kopieren';
$text['add'] = 'Hinzuf&uuml;gen';
$text['a_record'] = 'eines Eintrags';
$text['failed'] = 'fehlgeschlagen';
$text['There is no record under this ID:'] = 'Es existiert kein Eintrag unter dieser ID:';
$text['record_was_updated'] = 'Eintrag wurde aktualisiert';
	$text['Record was not updated (no changes were made)'] = 'Eintrag wurde nicht aktualisiert (es gab keine &Auml;nderungen)';
$text['record_was_deleted'] = 'Eintrag wurde gel&ouml;scht';
$text['record_was_inserted'] = 'Eintrag wurde eingef&uuml;gt';
	$text['Configuration does not allow this action: %s'] = 'Die Konfiguration erlaubt diese Aktion nicht: %s';
	$text['Configuration does not allow this mode: %s'] = 'Die Konfiguration erlaubt diesen Modus nicht: %s';

// Record form: bottom
$text['Cancel'] = 'Abbrechen'; // Stop editing this record
	$text['OK'] = 'OK';
$text['update_to'] = 'Aktualisieren der';
$text['delete_from'] = 'L&ouml;schen aus der';
$text['add_to'] = 'Hinzuf&uuml;gen zur';
$text['database'] = 'Datenbank';

// Record form: field output
$text['N/A'] = '<abbr title="keine Angabe">k.&nbsp;A.</abbr>'; // no value available for display, should be abbreviated
$text['will_be_added_automatically'] = 'wird automatisch hinzugef&uuml;gt';
$text['calculated_field'] = 'berechnetes Feld';

// Record form: Select
$text['none_selected'] = 'Nichts ausgew&auml;hlt'; // dropdown, first entry
$text['no_selection'] = 'Keine Auswahl'; // radio buttons, first entry
$text['no_source_defined'] = 'Keine Quelle angegeben';
$text['no_selection_possible'] = 'Keine Auswahl m&ouml;glich.';
	$text['(This entry is the highest entry in the hierarchy.)'] = '(Dieser Eintrag ist der oberste Eintrag in der Hierarchie.)';

// Record form: Change password
$text['Old:'] = 'Alt:';
$text['New:'] = 'Neu:';
$text['(Please confirm your new password twice)'] = '(Bitte best&auml;tigen Sie Ihr Passwort zweimal)';
$text['Your password has been changed!'] = 'Ihr Passwort wurde ge&auml;ndert.';
$text['Your current password is different from what you entered. Please try again.'] = 'Ihr aktuelles Passwort ist nicht das, was Sie eingegeben haben. Bitte versuchen Sie es nocheinmal.';
$text['New passwords do not match. Please try again.'] = 'Die Eingaben f&uuml;r das neue Passwort unterscheiden sich. Bitte versuchen Sie es nocheinmal.';
$text['Please enter your current password and twice your new password.'] = 'Bitte geben Sie Ihr aktuelles Passwort und zweimal das neue Passwort ein.';
$text['New and old password are identical. Please choose a different new password.'] = 'Neues und altes Passwort sind identisch. Bitte w&auml;hlen Sie ein anderes, neues Passwort aus.';
$text['Rules for secure passwords'] = 'Regeln f&uuml;r sichere Pa&szlig;w&ouml;rter';
$text['password-rules'] = 'Pa&szlig;w&ouml;rter sollten aus mindestens acht Zeichen bestehen. Dabei sollten Gro&szlig;- und Kleinbuchstaben, Ziffern und Sonderzeichen (?=+; etc.) enthalten sein. Damit man ein Pa&szlig;wort sich auch merken kann, sind Eselsbr&uuml;cken hilfreich.

Beispiele f&uuml;r gute Pa&szlig;w&ouml;rter:

* !1Pw=ig! (Merkregel: !Ein Passwort ist immer geheim!)
* 1sPh&uuml;o&auml;o&Ouml;  (Merkregel: ein starkes Passwort hat &uuml; oder &auml; oder &ouml;)
* 17Nbg03h (Merkregel: Ich telefoniere nicht besonders gern oder besonders h&auml;ufig, Buchstaben teilweise durch &auml;hnlich aussehende Zahlen ersetzt)'; 
$text['hidden'] = 'versteckt';
$text['Your new password could not be saved. Please try a different one.'] = 'Ihr neues Pa&szlig;wort konnte nicht gespeichert werden. Bitte versuchen Sie es mit einem anderen.';

// Record form: File upload
$text['image_not_display'] = 'Bild kann (noch) nicht angezeigt werden';
$text['Error: '] = 'Fehler: ';
$text['No file was uploaded.'] = 'Es wurde keine Datei hochgeladen.';
$text['File was only partially uploaded.'] = 'Die Datei wurde nur teilweise hochgeladen.';
$text['File is too big.'] = 'Die Datei ist zu gro&szlig;.';
$text['Maximum allowed filesize is'] = 'Maximal erlaubte Dateigr&ouml;&szlig;e:';
$text['Unsupported filetype:'] = 'Nicht unterst&uuml;tzter Dateityp:';
$text['Supported filetypes are:'] = 'Diese Dateitypen werden unterst&uuml;tzt:';
$text['Could not delete %s.'] = 'Konnte %s nicht l&ouml;schen.';
$text['Could not delete %s, file did not exist.'] = 'Konnte %s nicht l&ouml;schen, da die Datei nicht existierte.';
$text['File: '] = 'Datei: '; // prefix for alt-attribute
$text['no_image'] = 'Kein Bild'; // alt-attribute if there's no image
$text['Delete this file'] = 'Diese Datei l&ouml;schen';
$text['File could not be saved. There is a problem with the user rights. We are working on it.'] = 'Die Datei konnte nicht gespeichert werden. Es gibt ein Problem, aber wir arbeiten daran.';
$text['Minimum width %s was not reached.'] = 'Minimale Breite %s nicht eingehalten.';
$text['Minimum height %s was not reached.'] = 'Minimale H&ouml;he %s nicht eingehalten.';
$text['Maximum width %s has been exceeded.'] = 'Maximale Breite %s wurde &uuml;berschritten.';
$text['Maximum height %s has been exceeded.'] = 'Maximale H&ouml;he %s wurde &uuml;berschritten.';
	$text['Transfer failed. Probably you sent a file that was too large.'] = '&Uuml;bertragung fehlgeschlagen. Vermutlich haben Sie eine Datei gesendet, die zu gro&szlig; war.';
	$text['You sent: %s data.'] = 'Sie haben %s Daten gesendet.';

// Record form: Detail record
$text['Add %s'] = '%s hinzuf&uuml;gen'; // e. g. Add Address, Add Phone Number ...
$text['Remove %s'] = '%s entfernen';
	$text['Minimum of records for table `%s` was not met (%d)'] = 'Im Feld `%s` sind mehr Daten erforderlich (min. Anzahl: %d)';

// Record form: Validation, displayed inside form
$text['Please enter more characters.'] = 'Bitte geben Sie mehr Buchstaben ein.';
$text['No entry found. Try less characters.'] = 'Keinen Eintrag gefunden. Versuchen Sie es mit weniger Zeichen.';

// Record form: Validation, displayed above form
$text['Following_errors_occured'] = 'Die folgenden Fehler sind aufgetreten';
$text['Value_incorrect_in_field'] = 'Wert ist falsch im Feld:';
$text['Value missing in field'] = 'Wert fehlt im Feld:';
$text['Duplicate entry'] = 'Doppelter Eintrag in dieser Tabelle. Bitte &uuml;berpr&uuml;fen Sie, ob entweder der gew&uuml;nschte Eintrag bereits existiert oder ob Sie Ihren Eintrag &auml;ndern k&ouml;nnen.';
$text['Detail record could not be handled'] = 'Teileintrag konnte nicht gespeichert werden.';
	$text['String <em>"%s"</em> is not allowed'] = 'Die Zeichenfolge <em>"%s"</em> ist nicht erlaubt';

// Record form: Validation of Database Integrity, displayed above form
$text['No records in relation table'] = 'Keine Eintr&auml;ge in Tabelle %s. Bitte f&uuml;llen Sie diese Tabelle mit Werten.';
$text['Detail records exist in the following tables:'] = 'Detaileintr&auml;ge existieren in den folgenden Tabellen:';
$text['This record could not be deleted because there are details about this record in other records.'] = 'Dieser Eintrag konnte nicht gel&ouml;scht werden, da es Details zu diesem Eintrag in anderen Eintr&auml;gen gibt.';

// Record form: foreign record
$text['edit-after-save'] = 'Kein Eintrag m&ouml;glich. Bitte speichern Sie zuerst den Eintrag.';
$text['no-data-available'] = 'Keine Daten vorhanden';

// Record form: identifier, hidden etc.
	$text['Record for %s does not exist.'] = 'Eintrag f&uuml;r %s existiert nicht.';
	$text['Would be changed on update'] = 'W&uuml;rde bei Aktualisierung ge&auml;ndert.';


// ----------------------------------------------------------------------------
// List view
// ----------------------------------------------------------------------------

$text['table-empty'] = 'Keine Eintr&auml;ge vorhanden';
$text['- unknown -'] = '- unbekannt -'; // group by unknown

// List view: Filter
$text['Selection'] = 'Auswahl';
$text['all'] = 'Alle';

// List view: Table head
$text['order by'] = 'Sortiere nach';
$text['asc'] = 'aufsteigend';
$text['desc'] = 'absteigend';
$text['action'] = 'Aktion';
$text['detail'] = 'Details';

// List view: bottom
$text['Add new record'] = 'Neuer Eintrag';
$text['records total'] = 'Eintr&auml;ge gesamt';
$text['record total'] = 'Eintrag gesamt';
	$text['All records on one page'] = 'Alle Eintr&auml;ge auf einer Seite';
	$text['First page'] = 'Erste Seite';
	$text['Previous page'] = 'Vorige Seite';
	$text['Next page'] = 'N&auml;chste Seite';
	$text['Last page'] = 'Letzte Seite';

// List view: Search form
$text['Show all records'] = 'Zeige alle Eintr&auml;ge (ohne Suchfilter)';
$text['in'] = 'in';
$text['all fields'] = 'allen Feldern';
$text['search'] = 'suche';// Button


// ----------------------------------------------------------------------------
// Error handling
// ----------------------------------------------------------------------------

$text['Warning!'] = 'Achtung!';
$text['incorrect value'] = 'falscher Wert';
$text['database-error'] = 'Datenbankfehler';
	$text['An error occured. We are working on the solution of this problem. Sorry for your inconvenience. Please try again later.'] = 'Ein Fehler ist aufgetreten. Wir arbeiten an der L&ouml;sung des Problems. Bitte entschuldigen Sie die Unannehmlichkeiten. Versuchen Sie es bitte sp&auml;ter nocheinmal.';


// ----------------------------------------------------------------------------
// Modules: Export
// ----------------------------------------------------------------------------

$text['Export'] = 'Export'; // Export-Link


// ----------------------------------------------------------------------------
// Modules: Import
// ----------------------------------------------------------------------------

$text['Import data'] = 'Import Daten';
$text['File could not be imported.'] = 'Datei konnte nicht importiert werden.';
$text['Folder could not be imported.'] = 'Verzeichnis konnte nicht importiert werden.';
$text['Import was successful.'] = 'Import war erfolgreich.';
$text['Folder OK'] = 'Verzeichnis OK';
$text['Folder "%s" does not exist.'] = 'Das Verzeichnis "%s" existiert nicht.';
$text['Warning! Insufficient access rights. Please make sure, that the source directory is writeable.'] = 'Warnung! Die Zugriffsrechte reichen nicht aus. Bitte stellen Sie sicher, dass das Quellverzeichnis schreibbar ist.';
$text['%s files left for import. Please wait, the script will reload itself.'] = '%s Dateien m&uuml;ssen noch importiert werden. Bitte warten Sie, das Skript ruft sich erneut auf.';


// ----------------------------------------------------------------------------
// Modules: Geo
// ----------------------------------------------------------------------------

$text['N'] = '<abbr title="Nord">N</abbr>';
$text['E'] = '<abbr title="Ost">O</abbr>';
$text['S'] = '<abbr title="S&uuml;d">S</abbr>';
$text['W'] = '<abbr title="West">W</abbr>';
$text['It looks like this coordinate has a different orientation. Maybe latitude and longitude were interchanged?'] = 'Es sieht aus, als ob diese Koordinate eine andere Orientierung hat. Wurden L&auml;nge und Breite vertauscht?';
$text['Mismatch: %s signals different hemisphere than %s.'] = 'Nicht eindeutig: %s zeigt eine andere Hemisph&auml;re an als %s.';
$text['There are too many decimal points (or commas) in this value.'] = 'In diesem Wert sind zuviele Kommas oder Punkte.';
$text['Only the last number might have a decimal point (or comma).'] = 'Nur die letzte Zahl darf ein Komma enthalten (oder einen Punkt).';
$text['%s is too small. Please enter for minutes a positive value or 0.'] = '%s ist zu klein. Bitte geben Sie f&uuml;r Minuten einen positiven Wert oder 0 ein.';
$text['%s is too small. Please enter for seconds a positive value or 0.'] = '%s ist zu klein. Bitte geben Sie f&uuml;r Sekunden einen positiven Wert oder 0 ein';
$text['%s is too big. Please enter for minutes a value smaller than 60.'] = '%s ist zu gro&szlig;. Bitte geben Sie f&uuml;r Minuten einen Wert kleiner als 60 ein.';
$text['%s is too big. Please enter for seconds a value smaller than 60.'] = '%s ist zu gro&szlig;. Bitte geben Sie f&uuml;r Sekunden einen Wert kleiner als 60 ein.';
$text['Sorry, there are too many numbers. We cannot interpret what you entered.'] = 'Wir k&ouml;nnen leider nicht diese Koordinate korrekt interpretieren, da zuviele Zahlen eingegeben wurden.';
$text['Minimum value for degrees is %s. The value you entered is too small: %s.'] = 'Der kleinste Wert f&uuml;r Grad ist %s. Der Wert, den Sie eingegeben haben, ist zu klein: %s.';
$text['Maximum value for degrees is %s. The value you entered is too big: %s.'] = 'Der gr&ouml;&szlig;te Wert f&uuml;r Grad ist %s. Der Wert, den Sie eingegeben haben, ist zu gro&szlig;: %s.';


// ----------------------------------------------------------------------------
// Modules: Upload (Table filetypes)
// ----------------------------------------------------------------------------

$text['Filetypes'] = 'Dateitypen';
$text['MIME Content Type'] = 'MIME Inhaltstyp';
$text['MIME Subtype'] = 'MIME Untertyp';
$text['Extension'] = 'Dateierweiterung';
$text['Ext.'] = 'Ext.';
$text['Count'] = 'Anzahl';


// ----------------------------------------------------------------------------
// Backend
// ----------------------------------------------------------------------------

// Development
$text['Script configuration error. No display field set.'] = 'Script configuration error. No display field set.';
$text['Field name'] = 'Feldname'; // introduces field name with wrong values
$text['no-delete'] = 'Nicht l&ouml;schen'; // from table relations
$text['Database error. This query has ambiguous values in ID field.'] = 'Datenbankfehler. Diese Abfrage hat kein eindeutiges ID-Feld.';

// Development, Error mail
$text['Error during database operation'] = 'Fehler bei Datenbankzugriff';
$text['The following error(s) occured in project %s:'] = 'Folgende(r) Fehler sind im Projekt %s aufgetaucht:';


// ----------------------------------------------------------------------------
// Admin: Thumbnail creation (request/thumbnail)
// ----------------------------------------------------------------------------

$text['Thumbnail creation'] = 'Erstellen von Vorschaubildern';
$text['Here, you can create either missing '
		.'thumbnails, if they were not created on upload. Or you can create '
		.'completely new thumbnails if you changed the pixel size.']
	= 'Hier k&ouml;nnen Sie entweder fehlende Vorschaubilder erstellen, falls diese nicht beim Hochladen erstellt wurden. Oder Sie k&ouml;nnen komplett neue Vorschaubilder erstellen, z. B. falls Sie die Bildgr&ouml;&szlig;e ge&auml;ndert haben.';
$text['Sorry, but the table script %s could not be found.'] = 'Entschuldigung, aber das Tabellenskript %s konnte nicht gefunden werden.';
$text['The original file does not exist.'] = 'Die Originaldatei existiert nicht.';
$text['The function %s does not exist.'] = 'Die Funktion %s existiert nicht.';
$text['Thumbnail for %s x %s x px was created.'] = 'Ja! Ein Vorschaubild f&uuml;r %sx%s px wurde erstellt.';
$text['Thumbnail for %s x %s x px could not be created.'] = 'Nein. Ein Vorschaubild f&uuml;r %sx%s px konnte nicht erstellt werden.';
$text['No missing thumbnails were found.'] = 'Es wurden keine fehlenden Vorschaubilder gefunden';


// ----------------------------------------------------------------------------
// Admin: Relational Integrity (tables/relations)
// ----------------------------------------------------------------------------

$text['Database Table Relations'] = 'Tabellenbeziehungen der Datenbank';
$text['Master Table'] = 'Haupttabelle';
$text['Detail Table'] = 'Detailtabelle';
$text['Foreign Key'] = 'Fremdschl&uuml;ssel';
$text['Database of Master Table'] = 'Datenbank der Haupttabelle';
$text['Name of Master Table'] = 'Name der Haupttabelle';
$text['Primary Key of Master Table'] = 'Prim&auml;rschl&uuml;ssel der Haupttabelle';
$text['Database of Detail Table'] = 'Datenbank der Detailtabelle';
$text['Name of Detail Table'] = 'Name der Detailtabelle';
$text['Primary Key of Detail Table'] = 'Prim&auml;rschl&uuml;ssel der Detailtabelle';
$text['Foreign Key of Detail Table'] = 'Fremdschl&uuml;ssel der Detailtabelle';
$text['If main record will be deleted, what should happen with detail record?'] = '';


// ----------------------------------------------------------------------------
// Admin: Logging
// ----------------------------------------------------------------------------

$text['Query'] = 'Abfrage';
$text['Record'] = 'ID';
$text['Last update'] = 'Stand';
$text['Last Update'] = 'Stand';


// ----------------------------------------------------------------------------
// Admin: Translations
// ----------------------------------------------------------------------------

$text['Translations'] = '&Uuml;bersetzungen';
$text['Database'] = 'Datenbank';
$text['Table'] = 'Tabelle';
$text['Field'] = 'Feld';
$text['Data type'] = 'Datentyp';
$text['Data Type'] = 'Datentyp';


// ----------------------------------------------------------------------------
// Admin: Maintenance
// ----------------------------------------------------------------------------

$text['Maintenance'] = 'Wartung';
$text['Maintenance scripts'] = 'Wartungsskripte';
$text['Type'] = 'Typ';
$text['Action'] = 'Aktion';
$text['GET should be empty, please test that:'] = 'GET sollte leer sein, bitte &uuml;berpr&uuml;fen Sie das:';
$text['Setting'] = 'Einstellung';
$text['Value'] = 'Wert';
$text['Show PHP info on server'] = 'Zeige Informationen zu PHP auf dem Server';

// SQL queries
$text['SQL query'] = 'SQL-Abfrage';
$text['Result'] = 'Ergebnis';
$text['No changes were done to database.'] = 'Es wurden keine &Auml;nderungen an der Datenbank vorgenommen.';
$text['%s was successful'] = '%s war erfolgreich';
$text['Insert'] = 'Einf&uuml;gen';
$text['Update'] = 'Aktualisierung';
$text['Delete'] = 'L&ouml;schen';
$text['%s row(s) affected'] = '%s Eintr&auml;ge bearbeitet';
$text['Sorry, %s is not yet supported'] = 'Entschuldigung, aber %s wird (noch) nicht unterst&uuml;tzt';
$text['Custom SQL query'] = 'Eigene SQL-Abfrage';

// Database tables
$text['Relation and Translation Tables'] = 'Beziehungs- und &Uuml;bersetzungstabellen';
$text['No table for database relations is defined'] = 'Es wurde keine Tabelle f&uuml;r Datenbankbeziehungen angegeben';
$text['No table for database translations is defined'] = 'Es wurde keine Tabelle f&uuml;r &Uuml;bersetzungen angegeben';
$text['Current database'] = 'Aktuelle Datenbank';
$text['New database'] = 'Neue Datenbank';
$text['Keep database'] = 'Datenbank behalten';
$text['Change database'] = 'Datenbank wechseln';
$text['(Database is not on server, you have to select a new database.)'] = '(Datenbank ist nicht auf dem Server, daher m&uuml;ssen Sie eine neue Datenbank ausw&auml;hlen)';
$text['Translation'] = '&Uuml;bersetzung';
$text['Master'] = 'Haupt';
$text['Detail'] = 'Detail';

// Files
$text['Temp and Backup Files'] = 'Tempor&auml;re und Backup-Dateien';
$text['Backup folder'] = 'Backupverzeichnis';
$text['Backup of uploaded files is not active.'] = 'Backup von hochgeladenen Dateien ist nicht aktiv.';
$text['Current TEMP dir is:'] = 'Aktuelles tempor&auml;res Verzeichnis:';
$text['Current TEMP dir does not exist:'] = 'Aktuelles tempor&auml;res Verzeichnis existiert nicht:';
$text['Current backup dir is:'] = 'Aktuelles Backup-Verzeichnis:';
$text['Filename'] = 'Dateiname';
$text['Filetype'] = 'Dateityp';
$text['Size'] = 'Gr&ouml;&szlig;e';
$text['Timestamp'] = 'Zeitstempel';
$text['Folder'] = 'Ordner';
$text['unknown'] = 'unbekannt';
$text['All Files'] = 'Alle Dateien';
$text['Folder is empty'] = 'Ordner ist leer';
$text['Delete selected files'] = 'L&ouml;sche ausgew&auml;hlte Dateien';

// Error handling
$text['Error handling'] = 'Fehlerbehandlung';
$text['Errors will be shown on webpage'] = 'Fehler werden auf der Webseite angezeigt';
$text['Errors will be sent via mail'] = 'Fehler werden per Mail versendet';
$text['Errors won\'t be shown'] = 'Fehler werden nicht angezeigt';
$text['Send mail for these error levels'] = 'Fehler dieser Ebenen werden versendet';
$text['Send mail (From:)'] = 'Absender der E-Mail';
$text['not set'] = 'keine Angabe';
$text['Send mail (To:)'] = 'Empf&auml;nger der E-Mail';
$text['Logging'] = 'Aufzeichnung';
$text['Errors will be logged'] = 'Fehler werden aufgezeichnet';
$text['Errors will not be logged'] = 'Fehler werden nicht aufgezeichnet';
$text['Logfile for %s'] = 'Aufzeichnungen f&uuml;r %s';
$text['Maximum length of single error log entry'] = 'Maximale L&auml;nge eines einzelnen Eintrags';
$text['Log POST variables when errors occur'] = 'Bei Fehlern auch POST-Variablen aufzeichnen';
$text['POST variables will be logged'] = 'POST-Variablen werden aufgezeichnet';
$text['POST variables will not be logged'] ='POST-Variablen werden nicht aufgezeichnet';

// Error handling: Logfiles
$text['Error Logging'] = 'Fehlerlogs';
$text['Logs'] = 'Logs';
$text['No logfile specified'] = 'Keine Logdatei angegeben';
$text['This is not one of the used logfiles: %s'] = 'Die Datei %s ist keine der benutzten Logdateien.';
$text['Logfile does not exist: %s'] = 'Logdatei %s existiert nicht';
$text['Date'] = 'Datum';
$text['Last Date'] = 'Letztes Datum';
$text['Level'] = 'Ebene';
$text['User'] = 'Benutzer';
$text['Frequency'] = 'H&auml;ufigkeit';
$text['No lines'] = 'Keine Eintr&auml;ge';
$text['Delete selected lines'] = 'L&ouml;sche ausgew&auml;hlte Eintr&auml;ge';
$text['File %s is not writable.'] = 'Datei %s kann nicht beschreibbar';
$text['Cannot open %s for writing.'] = 'Datei %s kann nicht zum Schreiben ge&ouml;ffnet werden';
$text['%s lines deleted.'] = '%s Eintr&auml;ge gel&ouml;scht';
$text['Please choose one of the filters.'] = 'Bitte w&auml;hlen Sie aus den Filtern aus.';


?>