<?php 

function check_dir($my_dir) {
	if (!file_exists($my_dir)) {
		$upper_dir = substr($my_dir, 0, strrpos($my_dir, '/'));
		$success = check_dir($upper_dir);
		if ($success) {
			mkdir($my_dir);
			return true;
		}
		return false;
	} else {
		return true;
	}
}

function forceLast($filename) {
	$old_filename = substr($filename, strrpos($filename, '/')+1, strlen($filename));
	$old_filename = substr($old_filename, 0, strlen($old_filename) -4); // remove .pdf
	$old_filename = forceFilename($old_filename);
	$filename = substr($filename, 0, strrpos($filename, '/'));
	$old_filename = $filename.'/'.$old_filename.'.pdf';
	return $old_filename;
}

?>