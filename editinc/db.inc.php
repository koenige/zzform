<?php 

if (file_exists('/Users/Gustaf/')) {
	include ("/Users/pwd.inc"); //mac
	define ("DB_NAME", '');
	define ("DB_HOST", 'localhost');
	define ("DB_USER", $db_user);
} else {
	$db_passwort = '';
	define ('DB_USER', '');
	define ('DB_NAME', '');
	define ('DB_HOST', 'localhost');
}

#datenbankverbindung

	define ("DB_PWD", $db_passwort);
	
	#datenbank verbindung aufbauen
	$verbindung = mysql_connect(DB_HOST, DB_USER, DB_PWD);
	mysql_select_db(DB_NAME);

	if (isset($editinc)) $db = new mysql_db;
?>