<?php

if (is_dir("/Users/Gustaf/")) {
	// it's a mac
	include ("/Users/pwd.inc"); // mac
	$verbindung = mysql_connect('localhost',$db_user,$db_passwort);
	$dbname = 'photos';
} else {
	// dem2004.de
	include ("/www/dem2004.de/includes/pwd.inc.php"); //win
	$verbindung = mysql_connect('localhost',$db_user,$db_passwort);
	$dbname = 'db53010';
}

mysql_select_db($dbname);


function mysql_enum_values($table, $field)
{
   $sql = "SHOW COLUMNS FROM $table LIKE '$field'";
   $sql_res = mysql_query($sql)
       or die("Could not query:\n$sql");
   $row = mysql_fetch_assoc($sql_res);
   mysql_free_result($sql_res);
   return(explode("','",
       preg_replace("/.*\('(.*)'\)/", "\\1",
           $row["Type"])));
}
?>