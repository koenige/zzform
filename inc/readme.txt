edit.inc
--------

Required Files
==============

include-Directory	Standard: DOCUMENT_ROOT/inc/, set via $inc = ''

edit.inc.php		Core
db.inc.php			Database connection information. 
edit-de.inc.php		German language texts
validate.inc.php	Validates values
numbers.inc.php		Validates time and date values

head.inc.php	optional, can have different name, for top of document
foot.inc.php	optional, can have different name, for bottom of document


Todo
=====

. put $db = ... in edit.inc.php (if isset DB_NAME)


field_type

	id
	number
	calculated
	date
	datetime
	time
	select
	enum ??
	hidden
		function
	foreign
	text
	memo
	url
	mail
	thumbnail
	image

	predefined	- bei where
	

auto_value = increment	.. Vorschlag: +1
calculated:		nur display




$query[3]['sql'] = 'SELECT kategorie_id, kategorie_titel, mutter_kategorie_id FROM kategorien';
$query[3]['display_field'] = 'kategorie_titel';
$query[3]['show_hierarchy'] = 'mutter_kategorie_id';

macht hierarchische selects (zumindest optisch)

