<?php 

/*

$zz_conf['upload']['MAX_FILE_SIZE'] = 1500000;
$zz['fields'][8]['image'][0]['max_width'] = 600;
$zz['fields'][8]['image'][0]['max_height'] = 560;
$zz['fields'][8]['image'][0]['path'] = $zz['fields'][8]['path'];
$zz['fields'][8]['image'][0]['path']['string3'] = '.jpg';

$zz['fields'][8]['image'][1]['max_width'] = 85;
$zz['fields'][8]['image'][1]['max_height'] = 85;
$zz['fields'][8]['image'][1]['path'] = $zz['fields'][8]['path'];

$zz['fields'][8]['image'][2]['max_width'] = 85;
$zz['fields'][8]['image'][2]['max_height'] = 85;
$zz['fields'][8]['image'][2]['action'] = 'gray';
$zz['fields'][8]['image'][2]['source'] = $zz['fields'][8]['image'][1];
$zz['fields'][8]['image'][2]['path'] = $zz['fields'][8]['path'];
$zz['fields'][8]['image'][2]['path']['string3'] = '.klein-grau.jpg';

*/

/*	where[projekte.projekt_id]=11
	referer=/intern/bilder.php?where[projekte.projekt_id]=11&referer=%2Fintern%2Fprojekte.php
*/


// include ($level.'/inc/db.inc.php');


function zz_upload() {
	global $zz_conf;

	if ($empty($_GET['where'])) {
		echo $text['Please choose a related database record first'];
		exit;
	}
	
	//	Configuration
	if (empty($zz_conf['referer']))
		$zz_conf['referer'] = isset($_GET['referer']) ? $_GET['referer'] : '';
	$zz_upload['id_field'] = $_GET['where'];
	// todo: test, ob array, was passiert, wenn !isset?
	$zz_upload['id'] = $_GET['where'][$zz_upload['id_field']];
	$zz_default['url_self'] = $_SERVER['REQUEST_URI']; // $_SERVER['PHP_SELF'].'?referer='.urlencode($zz_conf['referer'])
	$zz_default['max_file_size'] = 1500000;
	$zz_default['max_upload'] = 8;
	foreach (array_keys($zz_default) as $key)
		if (!isset($zz_conf[$key])) $zz_conf[$key] = $zz_default[$key];

	//	Do something
	if (isset($_POST[$zz_upload['id_field']])) zz_upload_img;
	else echo zz_upload_show_form($zz_conf, $zz_upload);

	//	Script foot
	if ($zz_conf['referer']) echo '<p id="back-overview"><a href="'.$zz_conf['referer'].'">'.$text['back-to-overview'].'</a></p>'."\n";
}

function zz_upload_img() {
	$h1 = 'Hochladen';
	include ($level.'/inc/bilder-erstellen.inc.php');
	if ($_FILES) {
		//
	} else
		echo 'Kein Bild';
}

function zz_upload_show_form($zz_conf, $zz_upload) {
	// generates an upload form for images
	global $text;
	$output.= '<div id="zzform">'."\n";
	$output.= '<h2>'.$zz_conf['heading'].'</h2>'."\n"; 
	// todo: sublevel heading like in zzform
	$output.= '<h3>'.$text['Add Image'].'</h3>'."\n";
	$output.= '<form method="POST" enctype="multipart/formdata" action="'.$zz_conf['url_self'].'">'."\n";
	$output.= '<fieldset><legend></legend>'."\n";
	$output.= '<input type="hidden" name="MAX_FILE_SIZE" value="'.$zz_conf['max_filesize'].'"> ';
	$output.= '<input type="hidden" name="'.$zz_upload['id_field'].'" value="'.$zz_upload['id'].'">'."\n";
	$output.= '<ul class="upload">'."\n";
	for ($i = 1; $i<=$zz_conf['max_upload']; $i++) 
		$output.= "\t".'<li><label>'.$text['Image'].' '.$i.' <input type="file" name="img'.$i.'"></label></li>'."\n";
	$output.= '</ul>'."\n";
	$output.= '<input type="submit">';
	$output.= '</fieldset>'."\n";
	$output.= '</form>'."\n";
	$output.= '</div>'."\n";
	return $output;
}

$text['Add Image'] = 'Add Image';
$text['back-to-overview'] = 'Back to overview.';
$text['Image'] = 'Image';
$text['Please choose a related database record first'] = 'Please choose a related database record first';




?>