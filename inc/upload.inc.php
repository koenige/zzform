<?php 

/*

//	echo '<pre>';
//	print_r($zz);
//	print_r($_FILES);
//	echo '</pre>';

// functions: is_uploaded_file, move_uploaded_file
// move file, or delete file!!!

zzform file upload

* subtables? bilder koennen auch in subtables sein

* ist was im upload drin?
* fehler? 
* richtiger dateityp?
* groesse auslesen, exif auslesen
* titel generieren (keine Endungen, _= , )
* kennung generieren (dateiname ohne endung, exif, was passiert bei mehreren dateien, forcefilename)
  (falls erforderlich, s. path. ist das da erkennbar?)
  kennung kann auch increment sein, ggf. vorhandene dateien checken (bauplan)
  
-- hier zurueck an script uebergeben!
* ggf. groesse anpassen
* ggf. action wie grayscale
* gibt es schon eine datei, die dann ggf. verschieben in /backup - via zz_conf
* alles ok, dann ggf. verzeichnisse erstellen check_dir, 
* datei umbenennen nach path

allowed input files
allowed output files

*/

function zz_write_upload($zz) {
	if ($_FILES) {
	
	} else return false;
}

function zz_get_upload($zz) {
	$images = false;
	if ($_FILES) {
		$images = zz_check_files($zz);
		//echo '<pre>';
		//print_r($images);
	}
	return $images;
}

function zz_check_files($my) {
	$exif_supported = array(
		'image/jpeg',
		'image/pjpeg',
		'image/tiff'
	);
	foreach ($my['fields'] as $key => $field) {
		if (substr($field['type'], 0, 7) == 'upload-') {
			if (!empty($_FILES[$field['field_name']])) {
				$myfiles = $_FILES[$field['field_name']];
				foreach ($field['image'] as $subkey => $image) {
					$images[$key][$subkey] = $image;
					if (!empty($image['field_name'])) {
						switch ($myfiles['error'][$image['field_name']]) {
							// constants since PHP 4.3.0!
							case 4: break; // no file (UPLOAD_ERR_NO_FILE)
							case 3: break; // partial upload (UPLOAD_ERR_PARTIAL)
							case 2: break; // file is too big (UPLOAD_ERR_INI_SIZE)
							case 1: break; // file is too big (UPLOAD_ERR_FORM_SIZE)
							case false: break; // everything ok. (UPLOAD_ERR_OK)
						}
						$images[$key]['title'] = (!empty($images[$key]['title']))
							? $images[$key]['title']
							: zz_make_title($myfiles['name'][$image['field_name']]);
						$images[$key]['filename'] = (!empty($images[$key]['filename']))
							? $images[$key]['filename']
							: zz_make_name($myfiles['name'][$image['field_name']]);
						$images[$key][$subkey]['upload']['name'] = $myfiles['name'][$image['field_name']];
						$images[$key][$subkey]['upload']['type'] = $myfiles['type'][$image['field_name']];
						$images[$key][$subkey]['upload']['tmp_name'] = $myfiles['tmp_name'][$image['field_name']];
						$images[$key][$subkey]['upload']['error'] = $myfiles['error'][$image['field_name']];
						$images[$key][$subkey]['upload']['size'] = $myfiles['size'][$image['field_name']];
						$sizes = getimagesize($myfiles['tmp_name'][$image['field_name']]);
						$images[$key][$subkey]['upload']['width'] = $sizes[0];
						$images[$key][$subkey]['upload']['height'] = $sizes[1];
						if (in_array($myfiles['type'][$image['field_name']], $exif_supported))
							$images[$key][$subkey]['upload']['exif'] = exif_read_data($myfiles['tmp_name'][$image['field_name']]);
							
					}
					// here: convert image, write back to array 'convert' in $images
					// size, if applicable convert to grayscale etc.
				} 
			}
		}
	}
	return $images;
}

function zz_make_title($filename) {
	$filename = preg_replace('/\..{1,4}/', '', $filename);	// remove file extension up to 4 letters
	$filename = str_replace('_', ' ', $filename);			// make output more readable
	$filename = str_replace('.', ' ', $filename);			// make output more readable
	$filename = ucfirst($filename);
	return $filename;
}

function zz_make_name($filename) {
	$filename = preg_replace('/\..{1,4}/', '', $filename);	// remove file extension up to 4 letters
	$filename = forceFilename($filename);
	return $filename;
}

/*

exif_imagetype()
When a correct signature is found, the appropriate constant value will be
returned otherwise the return value is FALSE. The return value is the same
value that getimagesize() returns in index 2 but exif_imagetype() is much
faster.

Value	Constant
1	IMAGETYPE_GIF
2	IMAGETYPE_JPEG
3	IMAGETYPE_PNG
4	IMAGETYPE_SWF
5	IMAGETYPE_PSD
6	IMAGETYPE_BMP
7	IMAGETYPE_TIFF_II (intel byte order)
8	IMAGETYPE_TIFF_MM (motorola byte order)
9	IMAGETYPE_JPC
10	IMAGETYPE_JP2
11	IMAGETYPE_JPX
12	IMAGETYPE_JB2
13	IMAGETYPE_SWC
14	IMAGETYPE_IFF
15	IMAGETYPE_WBMP
16	IMAGETYPE_XBM

http://gustaf.local/phpdoc/function.exif-read-data.html
http://gustaf.local/phpdoc/function.exif-thumbnail.html

bei umbenennen und file_exists true:
filetype um zu testen, ob es sich um eine datei oder ein verzeichnis etc. handelt.

is_uploaded_file -- PrŸft, ob die Datei mittels HTTP POST upgeloaded wurde
move_uploaded_file -- ggf. vorher Zieldatei auf Existenz ŸberprŸfen und sichern

tmpfile ( void ) um eine temporŠre Datei anzulegen, ggf. s. tempnam
(neues Bild anlegen)

test: function_exists fuer php-imagick-funktionen, sonst ueber exec

lesen:
http://gustaf.local/phpdoc/ref.image.html

max_size:
ini_get('post_max_size'), wenn das kleiner ist, Warnung ausgeben! 
(oder geht das per ini_set einzustellen?)

mime_content_type -- Detect MIME Content-type for a file

set_time_limit, falls safe_mode off ggf. anwenden

imagick shell:
- escapeshellarg()
- wie programm im hintergrund laufen lassen?

ggf. mehrere Dateien hochladbar, die gleich behandelt werden (Massenupload)?

*/

/*

//	get_image_size etc.-Operationen durchfŸhren _vor_ insert
	damit entsprechende Hoehe, Breite, Kennung etc. in Datenbank geschrieben werden kann
//	Bild an richtigen Ort schieben: _nach_ Insert
	damit Bild nicht irrtuemlich irgendwo liegt

_POST
(
    [where] => Array
        (
            [svd_projekte.projekt_id] => 12
        )

    [limit] => 20
    [referer] => /intern/projekte
    [reihenfolge] => 9
    [projekt_id] => 12
    [titel] => test
    [kennung] => test
    [breite] => 1
    [hoehe] => 1
    [letzte_aenderung] => 
    [MAX_FILE_SIZE] => 1500000
    [action] => insert
)

_FILES
Array
(
    [bild] => Array
        (
            [name] => Array
                (
                    [gross] => nachts.jpg
                    [klein] => nachts.klein.jpg
                )

            [type] => Array
                (
                    [gross] => image/jpeg
                    [klein] => image/jpeg
                )

            [tmp_name] => Array
                (
                    [gross] => /var/tmp/phpgTQiK8
                    [klein] => /var/tmp/phpi0u2Y9
                )

            [error] => Array
                (
                    [gross] => 0
                    [klein] => 0
                )

            [size] => Array
                (
                    [gross] => 59211
                    [klein] => 4417
                )

        )

)

Kuer:
-------

testen, ob verzeichnis schreibbar fuer eigene gruppe/eigenen user
(777 oder gruppenspezifisch)
filegroup ($filename)
posix_getgrgid($group_id) 
fileowner()
posix_getpwuid() 
fileperms($filename)
get_current_user -- zeigt den aktuellen User an, der PHP nutzt, getmygid Gruppe, getmyuid

disk_free_space($dir) testen, ob's reicht?!

*/

?>