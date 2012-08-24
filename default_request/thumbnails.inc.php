<?php 

/**
 * zzform
 * Additional thumbnail creation
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2010 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Creates images of thumbnails as if they were created while uploading
 * This can be used to create missing thumbnails or to create thumbnails
 * for different sizes than were planned for initially
 *
 * @param array $params
 *		[0]: name of script
 *		[1]: mode (default false: only missing images are created; 'overwrite':
 *			existing images are being deleted)
 * @global array $zz_conf configuration variables
 * @global array $zz_setting
 * @return array $page
 *		'text' => page content, 'title', 'breadcrumbs', ...
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @todo support $zz['conditions']
 */
function zz_thumbnails($params) {
	global $zz_conf;
	global $zz_setting;
	
	if (count($params) > 2) return false;
	if (count($params) > 1 AND $params[1] != 'overwrite') return false;
	$mode = empty($params[1]) ? 'existing' : $params[1];

	$saved_conf = $zz_conf;
	require_once $zz_conf['dir'].'/zzform.php';
	$zz_conf['int_modules'] 		= array('debug', 'compatibility', 'validate', 'upload');
	zz_initialize();
	if (!empty($zz_conf['graphics_library']))
		include_once $zz_conf['dir_inc'].'/image-'.$zz_conf['graphics_library'].'.inc.php';
	
	if (strstr($params[0], '..')) return false;

	$page = array();
	$page['title'] = wrap_text('Thumbnail creation');
	$page['dont_show_h1'] = true;
	$page['text'] = '<p>'.wrap_text('Here, you can create either missing '
		.'thumbnails, if they were not created on upload. Or you can create '
		.'completely new thumbnails if you changed the pixel size.').'</p>';

	$script = $zz_conf['form_scripts'].'/'.$params[0].'.php';
	if (!file_exists($script)) {
		$page['text'] .= '<p>'.sprintf(wrap_text('Sorry, but the table script %s could not be found.')
			, htmlspecialchars($params[0])).'</p>';
		return $page;
	}
	include $script;

	// get upload field definition, id field name
	$id_field_name = '';
	$upload_files = array();
	foreach ($zz['fields'] as $no => $field) {
		if (empty($field['type'])) continue; // not of interest
		if ($field['type'] == 'id') 
			$id_field_name = $zz['fields'][$no]['field_name'];
		if ($field['type'] == 'upload_image') {
			$upload_field = $zz['fields'][$no]['image'];
			foreach ($upload_field as $file) {
				if (isset($file['source'])) continue;
				$source_path = $file['path'];
			}
		}
	}

	// get all records, create thumbnails
	$records = wrap_db_fetch($zz['sql'], $id_field_name);
	foreach ($records as $line) {
		$title = $line[$id_field_name];
		$source = zz_thumbnails_makelink($source_path, $line);
		if (!$source) {
			$output[] = $title.': <span class="error">'.wrap_text('The original file does not exist.').'</span>';
			continue;
		}
		$size = getimagesize($source);

		foreach ($upload_field AS $id => $file) {
			if (!isset($file['source'])) continue;

			// check if destination file exists
			$dest = zz_makelink($file['path'], $line);
			// don't write a new file if old one already exists
			if ($dest AND $mode == 'existing') continue;

			$file['upload']['height'] = $size[1];
			$file['upload']['width'] = $size[0];

			// get path without checking whether file exists
			$destination = zz_thumbnails_makelink($file['path'], $line, 'inexistent');
			$dest_extension = substr($destination, strrpos($destination, '.')+1);
			zz_create_topfolders(dirname($destination));
			
			if (empty($file['action'])) continue;
			$func = 'zz_image_'.$file['action'];
			$tn = false;
			if (!function_exists($func)) {
				$output[] = $title.': <span class="error">'.sprintf(
					wrap_text('The function %s does not exist.'), htmlspecialchars($func))
					.'</span>';
				continue;
			}
			$tn = $func($source, $destination, $dest_extension, $file);
			if ($tn)
				$output[] = $title.': '.sprintf(wrap_text('Thumbnail for %s x %s x px was created.'),
					$file['width'], $file['height']);
			else
				$output[] = $title.': <span class="error">'.sprintf(
					wrap_text('Thumbnail for %s x %s x px could not be created.'), $file['width'], $file['height'])
				.'</span>';
		}
	}

	if (!$output)
		$page['text'] .= '<p>'.wrap_text('No missing thumbnails were found.').'</p>'."\n";

	$page['text'] .= "<ul>\n<li>".(implode("</li>\n<li>", $output))."</li>\n</ul>\n";
	
	$zz_conf = $saved_conf;
	return $page;
}

/**
 * checks whether a source file exists, removes webroot, adds root
 *
 * @param array $source_path
 * @param array $line (current record)
 * @param string $mode
 * @return string $source (or false, if source does not exist)
 */
function zz_thumbnails_makelink($source_path, $line, $mode = false) {
	$root = '';
	if (!empty($source_path['root'])) {
		$root = $source_path['root'];
		// don't check against root if file does not exist
		if ($mode == 'inexistent')
			unset($source_path['root']);
	}
	$source = zz_makelink($source_path, $line);
	if (!$source) return false;
	if (!empty($source_path['webroot']))
		$source = substr($source, strlen($source_path['webroot']));
	$source = $root.$source;
	return $source;
}

?>