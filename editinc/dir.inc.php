<?php 

/*

prueft, ob Verzeichnisse ueber zu erstellendem Verzeichnis bestehen
und erstellt sie ggfs.

*/


function check_dir($my_dir) {
//	entfernt / am Ende
	if (substr($my_dir, strlen($my_dir)-1) == '/') {
		$my_dir = substr($my_dir, 0, strlen($my_dir)-1);
	}
//	wenn dir nicht existiert, check rekursiv ob oberes Verzeichnis existiert
	if (!file_exists($my_dir)) {
		$upper_dir = substr($my_dir, 0, strrpos($my_dir, '/'));
		$success = check_dir($upper_dir);
		if ($success) {
			mkdir($my_dir);
			return true;
		}
		return false;
	} else return true;
}
?>