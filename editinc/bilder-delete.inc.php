<?php 

/*

	bilder loeschen, wenn datensatz geloescht wird
	moegl. erweiterung: 
	- fehlermeldung, wenn bilder gar nicht existieren.
	- test, ob bilder im dateisystem herumschwirren, die nicht der datenbank zugeordnet sind

*/

$bildgroessen = array(750 => 'max', 370 => 'gross', 242 => 'mittel', 180 => 'klein');

$act_sql = 'SELECT projekte.pfad AS pfad, eltern.pfad AS elternpfad ';
$act_sql.= ', projekte.projekt_typ AS projekt_typ FROM projekte ';
$act_sql.= ' LEFT JOIN projekte AS eltern ON projekte.parent_projekt_id = eltern.projekt_id';
$act_sql.= ' WHERE projekte.projekt_id = '.$_POST['projekt_id'];
$result = mysql_query($act_sql);
if ($result) if (mysql_num_rows($result) == 1)
	while ($line = mysql_fetch_array($result)) {
		$pfad = '';
		if ($line['projekt_typ'] == 'Abgabe') $pfad = $line['elternpfad'];
		$pfad .= $line['pfad'].$_POST['datei_nr'].'.';
		foreach (array_keys($bildgroessen) as $groesse)
			if ($groesse <= $_POST['max_pixel']) {
				$loeschbild = $root_dir.$pfad.$bildgroessen[$groesse].'.jpg';
				if (file_exists($loeschbild)) unlink($loeschbild);
			}
	}

?>