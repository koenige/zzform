<?php 

/**
 * zzform
 * Additional thumbnail creation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2010, 2014-2016, 2019-2026 Gustaf Mossakowski
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
 * @return array $page
 *		'text' => page content, 'title', 'breadcrumbs', ...
 * @todo support $zz['conditions']
 */
function mod_zzform_make_thumbnails($params) {
	global $zz_conf;
	
	if (count($params) > 2) return false;
	if (count($params) > 1 AND $params[1] !== 'overwrite') return false;
	$mode = empty($params[1]) ? 'existing' : $params[1];

	$saved_conf = $zz_conf;
	wrap_include('zzform.php', 'zzform');
	$zz_conf['int_modules'] = ['debug', 'validate', 'upload'];
	zz_initialize();
	if ($graphics_library = wrap_setting('zzform_graphics_library'))
		wrap_include('image-'.$graphics_library, 'zzform');
	
	if (strstr($params[0], '..')) return false;

	$page = [];
	$page['title'] = wrap_text('Thumbnail creation');
	$page['dont_show_h1'] = true;
	$page['text'] = '<p>'.wrap_text('Here, you can create either missing '
		.'thumbnails, if they were not created on upload. Or you can create '
		.'completely new thumbnails if you changed the pixel size.').'</p>';

	$zz = zzform_include($params[0]);

	// get upload field definition, id field name
	$id_field_name = '';
	$upload_files = [];
	foreach ($zz['fields'] as $no => $field) {
		if (empty($field['type'])) continue; // not of interest
		if ($field['type'] === 'id') 
			$id_field_name = $zz['fields'][$no]['field_name'];
		if ($field['type'] === 'upload_image') {
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
		// do we have a field called filetype_id?
		if (!empty($line['filetype_id'])) {
			$filetype = wrap_filetype_id($line['filetype_id'], 'read-id');
			$filetype_conf = wrap_filetypes($filetype);
			if (empty($filetype_conf['thumbnail'])) continue;
		}
		$title = $line[$id_field_name];
		$source = mod_zzform_make_thumbnails_makelink($source_path, $line);
		if (!$source) {
			$output[] = $title.': <span class="error">'.wrap_text('The original file does not exist.').'</span>';
			continue;
		}
		$source_display = $source;
		if (str_starts_with($source_display, wrap_setting('cms_dir')))
			$source_display = substr($source_display, strlen(wrap_setting('cms_dir')));
		$title = sprintf('ID %d (%s): ', $title, $source_display);
		if (!filesize($source)) {
			$output[] = $title.'<span class="error">'.wrap_text('The original file does not contain any data.').'</span>';
			continue;
		}
		$size = getimagesize($source);

		foreach ($upload_field AS $id => $file) {
			if (!isset($file['source'])) continue;

			// check if destination file exists
			$dest = zz_path_file($file['path'], $line);
			// don't write a new file if old one already exists
			if ($dest AND $mode === 'existing') continue;

			$file['upload']['height'] = $size[1] ?? $line['height_px'] ?? NULL;
			$file['upload']['width'] = $size[0] ?? $line['width_px'] ?? NULL;

			// get path without checking whether file exists
			$destination = mod_zzform_make_thumbnails_makelink($file['path'], $line, 'inexistent');
			$dest_extension = substr($destination, strrpos($destination, '.')+1);
			zz_create_topfolders(dirname($destination));
			
			if (empty($file['action'])) continue;
			$func = 'zz_image_'.$file['action'];
			if (!function_exists($func)) {
				$output[] = $title.'<span class="error">'.sprintf(
					wrap_text('The function %s does not exist.'), wrap_html_escape($func))
					.'</span>';
				continue;
			}
			$tn = $func($source, $destination, $dest_extension, $file);
			if ($tn)
				$output[] = $title.wrap_text('Thumbnail for %s × %s px was created.',
					 ['values' => [$file['width'], $file['height']]]);
			else
				$output[] = $title.'<span class="error">'.
					wrap_text('Thumbnail for %s × %s px could not be created.', ['values' => [$file['width'], $file['height']]])
				.'</span>';
		}
	}

	if (empty($output)) {
		$page['text'] .= '<p>'.wrap_text('No missing thumbnails were found.').'</p>'."\n";
	} else {
		$page['text'] .= "<ul>\n<li>".(implode("</li>\n<li>", $output))."</li>\n</ul>\n";
	}
	
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
function mod_zzform_make_thumbnails_makelink($source_path, $line, $mode = false) {
	$root = '';
	if (!empty($source_path['root'])) {
		$root = $source_path['root'];
		// don't check against root if file does not exist
		if ($mode === 'inexistent')
			unset($source_path['root']);
	}
	$source = zz_path_file($source_path, $line);
	if (!$source) return false;
	if ($mode === 'inexistent')
		$source = $root.$source;
	return $source;
}
