<?php

/**
 * zzform
 * Text and labels in German (de) in utf-8 encoding
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2005-2015 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// ----------------------------------------------------------------------------
// Page elements
// ----------------------------------------------------------------------------

$text['records'] = 'Einträge'; // Number of records as shown in page TITLE
$text['back-to-overview'] = 'Zurück zur Übersicht';
	$text['page'] = 'Seite';

// Heading
$text['Search'] = 'Suche';


// ----------------------------------------------------------------------------
// Record form
// ----------------------------------------------------------------------------

$text['new'] = 'Neu'; // Link to add a new value for detail record via dropdown

// Record form: Heading
// @todo check if deprecated
$text['review'] = 'Anzeigen';
$text['insert'] = 'Einfügen';
$text['update'] = 'Aktualisieren';
$text['edit'] = 'Bearbeiten';
$text['delete'] = 'Löschen';
$text['show'] = 'Anzeigen';
$text['add'] = 'Hinzufügen';

	$text['Copy'] = 'Kopieren';
	$text['Merge'] = 'Zusammenfassen';

$text['Add a record'] = 'Eintrag hinzufügen';
$text['Delete a record'] = 'Eintrag löschen';
$text['Edit a record'] = 'Eintrag bearbeiten';
$text['Show a record'] = 'Eintrag anzeigen';
$text['Add several records'] = 'Mehrere Einträge hinzufügen';
$text['Delete several records'] = 'Mehrere Einträge löschen';
$text['Edit several records'] = 'Mehrere Einträge bearbeiten';
$text['Insert failed'] = 'Einfügen fehlgeschlagen';
$text['Delete failed'] = 'Löschen fehlgeschlagen';
$text['Update failed'] = 'Aktualisieren fehlgeschlagen';

$text['The record with the ID %d was already deleted.'] = 'Der Eintrag mit der ID %d wurde bereits gelöscht.';
$text['A record with the ID %d does not exist.'] = 'Es existiert kein Eintrag unter der ID %d.';
$text['Invalid ID for a record (must be an integer): %s'] = 'Ungültige ID für einen Eintrag (muß Zahl sein): %s';
$text['record_was_updated'] = 'Eintrag wurde aktualisiert';
	$text['Record was not updated (no changes were made)'] = 'Eintrag wurde nicht aktualisiert (es gab keine Änderungen)';
$text['record_was_deleted'] = 'Eintrag wurde gelöscht';
$text['%s records were deleted'] = '%s Einträge wurden gelöscht';
$text['record_was_inserted'] = 'Eintrag wurde eingefügt';
	$text['Configuration does not allow this action: %s'] = 'Die Konfiguration erlaubt diese Aktion nicht: %s';
	$text['Configuration does not allow this mode: %s'] = 'Die Konfiguration erlaubt diesen Modus nicht: %s';

// Record form: bottom
$text['Cancel'] = 'Abbrechen'; // Stop editing this record
	$text['OK'] = 'OK';
$text['Update record'] = 'Eintrag aktualisieren';
$text['Update records'] = 'Einträge aktualisieren';
$text['Delete record'] = 'Eintrag löschen';
$text['Delete records'] = 'Einträge löschen';
$text['Add record'] = 'Eintrag hinzufügen';
$text['Add records'] = 'Einträge hinzufügen';
$text['Edit record'] = 'Eintrag bearbeiten';
$text['Edit records'] = 'Einträge bearbeiten';

// Record form: field output
$text['N/A'] = '<abbr title="keine Angabe">k.&nbsp;A.</abbr>'; // no value available for display, should be abbreviated
$text['will_be_added_automatically'] = 'wird automatisch hinzugefügt';
$text['calculated_field'] = 'berechnetes Feld';

// Record form: Select
$text['none_selected'] = 'Nichts ausgewählt'; // dropdown, first entry
$text['no_selection'] = 'Keine Auswahl'; // radio buttons, first entry
$text['no_source_defined'] = 'Keine Quelle angegeben';
$text['no_selection_possible'] = 'Keine Auswahl möglich.';
	$text['(This entry is the highest entry in the hierarchy.)'] = '(Dieser Eintrag ist der oberste Eintrag in der Hierarchie.)';
// select/deselect checkboxes
$text['Select all'] = 'Alle auswählen';
$text['Deselect all'] = 'Auswahl entfernen';


// Record form: Change password
$text['Old:'] = 'Alt:';
$text['New:'] = 'Neu:';
$text['(Please confirm your new password twice)'] = '(Bitte bestätigen Sie Ihr Passwort zweimal)';
$text['Your password has been changed!'] = 'Ihr Passwort wurde geändert.';
$text['Your current password is different from what you entered. Please try again.'] = 'Ihr aktuelles Passwort ist nicht das, was Sie eingegeben haben. Bitte versuchen Sie es nocheinmal.';
$text['New passwords do not match. Please try again.'] = 'Die Eingaben für das neue Passwort unterscheiden sich. Bitte versuchen Sie es nocheinmal.';
$text['Please enter your current password and twice your new password.'] = 'Bitte geben Sie Ihr aktuelles Passwort und zweimal das neue Passwort ein.';
$text['New and old password are identical. Please choose a different new password.'] = 'Neues und altes Passwort sind identisch. Bitte wählen Sie ein anderes, neues Passwort aus.';
$text['Rules for secure passwords'] = 'Regeln für sichere Paßwörter';
$text['password-rules'] = 'Paßwörter sollten aus mindestens acht Zeichen bestehen. Dabei sollten Groß- und Kleinbuchstaben, Ziffern und Sonderzeichen (?=+; etc.) enthalten sein. Damit man ein Paßwort sich auch merken kann, sind Eselsbrücken hilfreich.

Beispiele für gute Paßwörter:

* !1Pw=ig! (Merkregel: !Ein Passwort ist immer geheim!)
* 1sPhüoäoÖ  (Merkregel: ein starkes Passwort hat ü oder ä oder ö)
* 17Nbg03h (Merkregel: Ich telefoniere nicht besonders gern oder besonders häufig, Buchstaben teilweise durch ähnlich aussehende Zahlen ersetzt)'; 
$text['hidden'] = 'versteckt';
$text['Your new password could not be saved. Please try a different one.'] = 'Ihr neues Paßwort konnte nicht gespeichert werden. Bitte versuchen Sie es mit einem anderen.';

// Record form: File upload
$text['image_not_display'] = 'Bild kann (noch) nicht angezeigt werden';
$text['Error: '] = 'Fehler: ';
$text['No file was uploaded.'] = 'Es wurde keine Datei hochgeladen.';
$text['File was only partially uploaded.'] = 'Die Datei wurde nur teilweise hochgeladen.';
$text['File is too big.'] = 'Die Datei ist zu groß.';
$text['Maximum allowed filesize is'] = 'Maximal erlaubte Dateigröße:';
$text['Unsupported filetype:'] = 'Nicht unterstützter Dateityp:';
$text['Supported filetypes are:'] = 'Diese Dateitypen werden unterstützt:';
$text['Could not delete %s.'] = 'Konnte %s nicht löschen.';
$text['Could not delete %s, file did not exist.'] = 'Konnte %s nicht löschen, da die Datei nicht existierte.';
$text['File: '] = 'Datei: '; // prefix for alt-attribute
$text['no_image'] = 'Kein Bild'; // alt-attribute if there's no image
$text['Delete this file'] = 'Diese Datei löschen';
$text['File could not be saved. There is a problem with the user rights. We are working on it.'] = 'Die Datei konnte nicht gespeichert werden. Es gibt ein Problem, aber wir arbeiten daran.';
$text['Minimum width %s was not reached.'] = 'Minimale Breite %s nicht eingehalten.';
$text['Minimum height %s was not reached.'] = 'Minimale Höhe %s nicht eingehalten.';
$text['Maximum width %s has been exceeded.'] = 'Maximale Breite %s wurde überschritten.';
$text['Maximum height %s has been exceeded.'] = 'Maximale Höhe %s wurde überschritten.';
	$text['Transfer failed. Probably you sent a file that was too large.'] = 'Übertragung fehlgeschlagen. Vermutlich haben Sie eine Datei gesendet, die zu groß war.';
	$text['You sent: %s data.'] = 'Sie haben %s Daten gesendet.';
	$text['The file %s is empty. If you are uploading from a Mac, please check if the data is not only available in the so-called “resource fork” of the file.'] = 'Die Datei %s ist leer. Falls Sie von einem Mac hochladen, prüfen Sie bitte, ob die Daten nicht ausschließlich im sog. »Resource Fork« liegen.';

// Record form: Detail record
$text['Add %s'] = '%s ergänzen'; // e. g. Add Address, Add Phone Number ...
$text['Remove %s'] = '%s entfernen';
$text['Remove'] = 'Entfernen';
	$text['Minimum of records for table `%s` was not met (%d).'] = 'Im Feld `%s` sind mehr Daten erforderlich (min. Anzahl: %d).';

// Record form: Validation, displayed inside form
$text['Please enter more characters.'] = 'Bitte geben Sie mehr Buchstaben ein.';
$text['No entry found. Try less characters.'] = 'Keinen Eintrag gefunden. Versuchen Sie es mit weniger Zeichen.';

// Record form: Validation, displayed above form
$text['Following_errors_occured'] = 'Die folgenden Fehler sind aufgetreten';
$text['Value incorrect in field %s.'] = 'Angabe im Feld %s erscheint falsch.';
$text['Value missing in field %s.'] = 'Angabe im Feld %s fehlt.';
$text['Nothing was uploaded in field %s.'] = 'Es wurde nichts im Feld %s hochgeladen.';
$text['Duplicate entry'] = 'Doppelter Eintrag in dieser Tabelle. Bitte überprüfen Sie, ob entweder der gewünschte Eintrag bereits existiert oder ob Sie Ihren Eintrag ändern können.';
$text['Detail record could not be handled'] = 'Teileintrag konnte nicht gespeichert werden.';
	$text['String <em>"%s"</em> is not allowed'] = 'Die Zeichenfolge <em>"%s"</em> ist nicht erlaubt';
	$text['Please check these values again'] = 'Bitte überprüfen Sie diese Werte nocheinmal';
	$text['Please select one of the values for field %s'] = 'Bitte wählen Sie aus einem der Werte für das Feld %s aus';
$text['Creation of directory %s failed: Parent directory is not writable.'] = 'Erstellung des Verzeichnisses %s ist fehlgeschlagen: Hauptverzeichnis ist nicht schreibbar.';
$text['Creation of directory %s failed.'] = 'Erstellung des Verzeichnisses %s ist fehlgeschlagen.';


// Record form: Validation of Database Integrity, displayed above form
$text['No records in relation table'] = 'Keine Einträge in Tabelle %s. Bitte füllen Sie diese Tabelle mit Werten.';
$text['Detail records exist in the following tables:'] = 'Detaileinträge existieren in den folgenden Tabellen:';
$text['This record could not be deleted because there are details about this record in other records.'] = 'Dieser Eintrag konnte nicht gelöscht werden, da es Details zu diesem Eintrag in anderen Einträgen gibt.';

// Record form: foreign record
$text['edit-after-save'] = 'Kein Eintrag möglich. Bitte speichern Sie zuerst den Eintrag.';
$text['no-data-available'] = 'Keine Daten vorhanden';

// Record form: identifier, hidden etc.
	$text['Record for %s does not exist.'] = 'Eintrag für %s existiert nicht.';
	$text['Would be changed on update'] = 'Würde bei Aktualisierung geändert.';

// Record form: Merge
$text['%d records merged successfully'] = '%d Einträge erfolgreich zusammengefasst';
$text['For merging, the field %s has to be equal in all records.'] = 'Für die Zusammenfassung der Einträge muß das Feld %s in allen Einträgen gleich sein.';
$text['For merging, the fields %s and %s have to be equal in all records.'] = 'Für die Zusammenfassung der Einträge müssen die Felder %s und %s in allen Einträgen gleich sein.';
$text['Merge not complete, records are different:'] = 'Zusammenfassung nicht komplett, die Einträge sind unterschiedlich:';


// ----------------------------------------------------------------------------
// List view
// ----------------------------------------------------------------------------

$text['table-empty'] = 'Keine Einträge vorhanden';
$text['- unknown -'] = '- unbekannt -'; // group by unknown

// List view: Filter
$text['Selection'] = 'Auswahl';
$text['all'] = 'Alle';
$text['"%s" is not a valid value for the selection "%s". Please select a different filter.'] = '»%s« ist kein gültiger Wert für die Auswahl »%s«. Bitte treffen Sie eine andere Auswahl.';
$text['A filter for the selection "%s" does not exist.'] = 'Es existiert kein Filter für die Auswahl »%s«.';
$text['List without this filter'] = 'Die Liste ohne diesen Filter';
$text['(no value)'] = '(kein Wert)';

// List view: Table head
$text['order by'] = 'Sortiere nach';
$text['asc'] = 'aufsteigend';
$text['desc'] = 'absteigend';
$text['action'] = 'Aktion';
$text['detail'] = 'Details';

// List view: bottom
$text['Add new record'] = 'Neuer Eintrag';
$text['records total'] = 'Einträge gesamt';
$text['record total'] = 'Eintrag gesamt';
	$text['All records on one page'] = 'Alle Einträge auf einer Seite';
	$text['First page'] = 'Erste Seite';
	$text['Previous page'] = 'Vorige Seite';
	$text['Next page'] = 'Nächste Seite';
	$text['Last page'] = 'Letzte Seite';

// List view: Search form
$text['Show all records'] = 'Zeige alle Einträge (ohne Suchfilter)';
$text['in'] = 'in';
$text['all fields'] = 'allen Feldern';
$text['search'] = 'suche';// Button


// ----------------------------------------------------------------------------
// Error handling
// ----------------------------------------------------------------------------

$text['Warning!'] = 'Achtung!';
$text['incorrect value'] = 'falscher Wert';
$text['database-error'] = 'Datenbankfehler';
	$text['An error occured. We are working on the solution of this problem. Sorry for your inconvenience. Please try again later.'] = 'Ein Fehler ist aufgetreten. Wir arbeiten an der Lösung des Problems. Bitte entschuldigen Sie die Unannehmlichkeiten. Versuchen Sie es bitte später nocheinmal.';


// ----------------------------------------------------------------------------
// Modules: Export
// ----------------------------------------------------------------------------

$text['Export'] = 'Export'; // Export-Link
$text['Please don\'t mess with the URL parameters. <code>%s</code> is not allowed here.']
	= 'Bitte ändern Sie nicht eigenhändig die URL-Parameter. <code>%s</code> ist hier nicht erlaubt.';


// ----------------------------------------------------------------------------
// Modules: Geo
// ----------------------------------------------------------------------------

$text['N'] = '<abbr title="Nord">N</abbr>';
$text['E'] = '<abbr title="Ost">O</abbr>';
$text['S'] = '<abbr title="Süd">S</abbr>';
$text['W'] = '<abbr title="West">W</abbr>';
$text['It looks like this coordinate has a different orientation. Maybe latitude and longitude were interchanged?'] = 'Es sieht aus, als ob diese Koordinate eine andere Orientierung hat. Wurden Länge und Breite vertauscht?';
$text['Mismatch: %s signals different hemisphere than %s.'] = 'Nicht eindeutig: %s zeigt eine andere Hemisphäre an als %s.';
$text['There are too many decimal points (or commas) in this value.'] = 'In diesem Wert sind zuviele Kommas oder Punkte.';
$text['Only the last number might have a decimal point (or comma).'] = 'Nur die letzte Zahl darf ein Komma enthalten (oder einen Punkt).';
$text['%s is too small. Please enter for minutes a positive value or 0.'] = '%s ist zu klein. Bitte geben Sie für Minuten einen positiven Wert oder 0 ein.';
$text['%s is too small. Please enter for seconds a positive value or 0.'] = '%s ist zu klein. Bitte geben Sie für Sekunden einen positiven Wert oder 0 ein';
$text['%s is too big. Please enter for minutes a value smaller than 60.'] = '%s ist zu groß. Bitte geben Sie für Minuten einen Wert kleiner als 60 ein.';
$text['%s is too big. Please enter for seconds a value smaller than 60.'] = '%s ist zu groß. Bitte geben Sie für Sekunden einen Wert kleiner als 60 ein.';
$text['Sorry, there are too many numbers. We cannot interpret what you entered.'] = 'Wir können leider nicht diese Koordinate korrekt interpretieren, da zuviele Zahlen eingegeben wurden.';
$text['Minimum value for degrees is %s. The value you entered is too small: %s.'] = 'Der kleinste Wert für Grad ist %s. Der Wert, den Sie eingegeben haben, ist zu klein: %s.';
$text['Maximum value for degrees is %s. The value you entered is too big: %s.'] = 'Der größte Wert für Grad ist %s. Der Wert, den Sie eingegeben haben, ist zu groß: %s.';


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
$text['no-delete'] = 'Nicht löschen'; // from table relations
$text['Database error. This query has ambiguous values in ID field.'] = 'Datenbankfehler. Diese Abfrage hat kein eindeutiges ID-Feld.';

// Development, Error mail
$text['Error during database operation'] = 'Fehler bei Datenbankzugriff';
$text['The following error(s) occured in project %s:'] = 'Folgende(r) Fehler sind im Projekt %s aufgetaucht:';
