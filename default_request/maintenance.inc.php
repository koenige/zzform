<?php 

/**
 * zzform
 * Maintenance script for database operations with zzform
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2010, 2013-2014 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Maintenance script for zzform to do some cleanup/correction operations
 *
 * - change database name if local development uses different database names
 * for relations and translations
 * - delete files from backup-directory
 * - enter an sql query
 * @param array $params
 * @global array $zz_conf configuration variables
 * @global array $zz_setting
 * @return array $page
 *		'text' => page content, 'title', 'breadcrumbs', ...
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_maintenance($params) {
	global $zz_conf;
	global $zz_setting;

	if (!isset($zz_conf['modules'])) {
		$zz_conf['modules'] = array();
		$zz_conf['modules']['debug'] = false;
	}

	require_once $zz_conf['dir_inc'].'/zzform.php';
	require_once $zz_conf['dir_inc'].'/functions.inc.php';
	require_once $zz_conf['dir_inc'].'/database.inc.php';
	require_once $zz_conf['dir_inc'].'/output.inc.php';
	require_once $zz_conf['dir_inc'].'/list.inc.php';
	require_once $zz_conf['dir_inc'].'/search.inc.php';
	if (file_exists($zz_setting['custom'].'/zzbrick_tables/_common.inc.php')) {
		require_once($zz_setting['custom'].'/zzbrick_tables/_common.inc.php');
		if (isset($brick['page'])) $page = $brick['page'];
	}

	if (!empty($_SESSION) AND empty($zz_conf['user']) AND !empty($zz_setting['brick_username_in_session']))
		$zz_conf['user'] = $_SESSION[$zz_setting['brick_username_in_session']];
	elseif (!isset($zz_conf['user']))
		$zz_conf['user'] = 'Maintenance robot 812';

	$zz_conf['int_modules'] = array('debug', 'compatibility', 'validate', 'upload');
	zz_initialize();
	$heading_prefix = '';
	if ($zz_conf['heading_prefix']) $heading_prefix = ' ';
	$heading_prefix .= '<a href="./">'.zz_text('Maintenance').'</a>:';
	
	$page['query_strings'] = array('folder', 'log', 'integrity', 'filetree', 
		'phpinfo', 'file', 'q', 'deleteall', 'filter', 'limit', 'scope');
	$page['text'] = '<div id="zzform" class="maintenance">'."\n";
	
	$sql = '';
	if (!empty($_POST['sql'])) {
		$sql = $_POST['sql'];

		$heading = 'SQL Query';
		$page['text'] .= '<pre style="font-size: 1.1em; white-space: pre-wrap;"><code>'.zz_maintenance_sql($sql).'</code></pre>';

		$tokens = explode(' ', $sql);
		switch ($tokens[0]) {
		case 'INSERT':
		case 'UPDATE':
		case 'DELETE':
			$result = wrap_db_query($sql);
			$page['text'] .= '<h2>'.zz_text('Result').'</h2>'."\n";
			if (!$result['action']) {
				$page['text'] .= '<div class="error">'
					.'Database says: '.$result['error']['db_msg'].' [Code '
					.$result['error']['db_errno'].']'
					.'</div>'."\n";
			} elseif ($result['action'] == 'nothing') {
				$page['text'] .= '<p>'.zz_text('No changes were done to database.').'</p>'."\n";
			} else {
				$page['text'] .= '<p>'.sprintf(zz_text('%s was successful'), zz_text(ucfirst($result['action'])))
					.': '.sprintf(zz_text('%s row(s) affected'), $result['rows'])
					.($result['id_value'] ? ' (ID: '.$result['id_value'].')' : '').'</p>'."\n";
			}
			break;
		case 'SELECT':
		default:
			$page['text'] .= sprintf(zz_text('Sorry, %s is not yet supported'), zz_htmltag_escape($tokens[0]));
		}
	}

	if (empty($_GET)) {	
		if (!$sql) {
			$heading = zz_text('Maintenance');
			$heading_prefix = '';

			// 'relations'
			// 'translations'
			$page['text'] .= '<h2>'.zz_text('Relation and Translation Tables').'</h2>'."\n";
			$page['text'] .= zz_maintenance_tables();
	
			$page['text'] .= '<h2>'.zz_text('Error Logging').'</h2>'."\n";
			$page['text'] .= zz_maintenance_errors();
		
			$page['text'] .= '<h2>'.zz_text('PHP & Server').'</h2>'."\n";
			$page['text'] .= '<p><a href="?phpinfo">'.zz_text('Show PHP info on server').'</a></p>';
			if ($zz_conf['graphics_library'] === 'imagemagick') {
				require_once $zz_conf['dir'].'/image-imagemagick.inc.php';
				$page['text'] .= '<p>ImageMagick:</p><blockquote><pre>'.zz_imagick_version().'</pre></blockquote>';
				$page['text'] .= '<p>GhostScript:</p><blockquote><pre>'.zz_ghostscript_version().'</pre></blockquote>';
			}

		// 	- Backup/errors, insert, update, delete
			$page['text'] .= '<h2>'.zz_text('Temp and Backup Files').'</h2>'."\n";
			$page['text'] .= zz_maintenance_folders();

			$page['text'] .= '<h2><a href="?filetree">'.zz_text('Filetree').'</a></h2>'."\n";
		}
	
		$page['text'] .= '<h2>'.zz_text('Custom SQL query').'</h2>'."\n";
		$page['text'] .= '<form method="POST" action=""><textarea cols="60" rows="10" name="sql">'
			.str_replace('%%%', '%&shy;%&shy;%', zz_html_escape($sql))
			.'</textarea>
			<br><input type="submit"></form>'."\n";
	// 	- SQL query absetzen, Häkchen für zz_log_sql()
	} else {
		if (!empty($_GET['folder'])) {
			$heading = 'Backup folder';
			$page['text'] .= zz_maintenance_folders();
		} elseif (!empty($_GET['log'])) {
			$heading = 'Logs';
			$page['text'] .= zz_maintenance_logs();
		} elseif (isset($_GET['integrity'])) {
			$heading = 'Relational Integrity';
			$page['text'] .= zz_maintenance_integrity();
		} elseif (isset($_GET['filetree'])) {
			$heading = 'Filetree';
			$page['text'] .= zz_maintenance_filetree();
		} elseif (isset($_GET['phpinfo'])) {
			phpinfo();
			exit;
		} else {
			$heading = 'Error';
			$page['text'] .= zz_text('GET should be empty, please test that:').' <pre>';
			foreach ($_GET as $key => $value) {
				$page['text'] .= $key.' => '.$value."\n";
			}
			$page['text'] .= '</pre>'."\n";
		}
	}
	$page['text'] .= '</div>'."\n";
	if (!empty($zz_conf['footer_text'])) {
		$page['text'] .= $zz_conf['footer_text'];
	}

	$zz_conf['heading_prefix'] .= $heading_prefix;
	$page['title'] = zz_output_heading($heading);
	$page['dont_show_h1'] = true;
	$page['text'] = '<h1>'.$page['title']."</h1>\n".$page['text'];
	return $page;
}

function zz_maintenance_tables() {
	global $zz_conf;
	$text = false;

	if (empty($zz_conf['relations_table'])) {
		$text .= '<p>'.zz_text('No table for database relations is defined')
			.' (<code>$zz_conf["relations_table"]</code>)</p>';
	} elseif (empty($zz_conf['translations_table'])) {
		$text .= '<p>'.zz_text('No table for database translations is defined')
			.' (<code>$zz_conf["translations_table"]</code>)</p>';
	}
	
	if (empty($zz_conf['relations_table']) AND empty($zz_conf['translations_table']))
		return $text;
		
	// Update
	if ($_POST AND !empty($_POST['db_value'])) {
		$areas = array('master', 'detail', 'translation');
		foreach ($areas as $area) {
			if (!empty($_POST['db_value'][$area])) {
				foreach ($_POST['db_value'][$area] as $old => $new) {
					if (empty($_POST['db_set'][$area][$old])) continue;
					if ($_POST['db_set'][$area][$old] != 'change') continue;
					if ($area == 'translation') {
						$table = $zz_conf['translations_table'];
						$field_name = 'db_name';
					} else {
						$table = $zz_conf['relations_table'];
						$field_name = $area.'_db';
					}
					$sql = 'UPDATE '.$table
						.' SET '.$field_name.' = "'.zz_db_escape($new)
						.'" WHERE '.$field_name.' = "'.zz_db_escape($old).'"';
					wrap_db_query($sql);
				}
			}
		}
	}
	if (!empty($zz_conf['relations_table'])) {
	// Master database
		$sql = 'SELECT DISTINCT master_db FROM '.$zz_conf['relations_table'];
		$dbs['master'] = wrap_db_fetch($sql, 'master_db', 'single value');

	// Detail database	
		$sql = 'SELECT DISTINCT detail_db FROM '.$zz_conf['relations_table'];
		$dbs['detail'] = wrap_db_fetch($sql, 'detail_db', 'single value');
	}

	if (!empty($zz_conf['translations_table'])) {
	// Translations database	
		$sql = 'SELECT DISTINCT db_name FROM '.$zz_conf['translations_table'];
		$dbs['translation'] = wrap_db_fetch($sql, 'db_name', 'single value');
	}
	
	// All available databases
	$sql = 'SHOW DATABASES';
	$databases = wrap_db_fetch($sql, 'Databases', 'single value');
	$db_select = '';
	foreach ($databases as $db) {
		$db_select .= '<option value="'.$db.'">'.$db.'</option>'."\n";
	}

	$text .= '<form action="" method="POST">';
	$text .= '<table class="data"><thead><tr>
		<th>'.zz_text('Type').'</th>
		<th>'.zz_text('Current database').'</th>
		<th>'.zz_text('New database').'</th>
		<th class="editbutton">'.zz_text('Action').'</th>
		</thead><tbody>'."\n";
	$i = 0;
	foreach ($dbs as $category => $db_names) {
		foreach ($db_names as $db) {
			if (in_array($db, $databases)) {
				$keep = '<input type="radio" checked="checked" name="db_set['
					.$category.']['.$db.']" value="keep"> '.zz_text('Keep database')
					.' / '.'<input type="radio" name="db_set['.$category.']['
					.$db.']" value="change"> '.zz_text('Change database');
			} else {
				$keep = zz_text('(Database is not on server, you have to select a new database.)')
					.'<input type="hidden" name="db_set['.$category.']['
					.$db.']" value="change">';
			}
			$text .= '<tr class="'.($i & 1 ? 'uneven' : 'even').'">'
				.'<td>'.zz_text(ucfirst($category)).'</td>'
				.'<td>'.$db.'</td>'
				.'<td><select name="db_value['.$category.']['.$db.']">'.$db_select.'</select></td>'
				.'<td>'.$keep.'</td>'
				.'</tr>'."\n";
			$i++;
		}
	}
	$text .= '</tbody></table>'."\n"
		.'<input type="submit">';
	$text .= '</form>';

	if (!empty($zz_conf['relations_table'])) {
		$text .= '<p><a href="?integrity">Check relational integrity</a></p>';
	}

	return $text;
}

/**
 * checks all fields that have an entry in the relations_table if they
 * contain invalid values (e. g. values that do not have a corresponding value
 * in the master table
 *
 * @global array $zz_conf 'relations_table'
 * @return string text output
 * @todo add translations with wrap_text()
 */
function zz_maintenance_integrity() {
	global $zz_conf;

	$sql = 'SELECT * FROM '.$zz_conf['relations_table'];
	$relations = wrap_db_fetch($sql, 'rel_id');

	$results = array();
	foreach ($relations as $relation) {
		$sql = 'SELECT DISTINCT detail_table.`'.$relation['detail_id_field'].'`
				, detail_table.`'.$relation['detail_field'].'`
			FROM `'.$relation['detail_db'].'`.`'.$relation['detail_table'].'` detail_table
			LEFT JOIN `'.$relation['master_db'].'`.`'.$relation['master_table'].'` master_table
				ON detail_table.`'.$relation['detail_field'].'`
					= master_table.`'.$relation['master_field'].'`
			WHERE ISNULL(master_table.`'.$relation['master_field'].'`)
			AND !ISNULL(detail_table.`'.$relation['detail_field'].'`)
		';
		$ids = wrap_db_fetch($sql, '_dummy_', 'key/value', false, E_USER_NOTICE);
		$detail_field = $relation['detail_db'].' . '.$relation['detail_table'].' . '.$relation['detail_field'];
		if ($ids) {
			$line = '<li class="error">'.wrap_text('Error').' &#8211; <code>'
				.$detail_field.'</code> contains invalid values: ('
				.$relation['detail_id_field'].' => '.$relation['detail_field'].')<br>';
			foreach ($ids as $id => $foreign_id) {
				$line .= $id.' => '.$foreign_id.'; ';
			}
			$line .= '</li>';
			$results[] = $line;
		} else {
			$results[] = '<li class="ok">'.wrap_text('OK').' &#8211; Field <code>'
				.$detail_field.'</code> contains only valid values</li>';
		}
	}
	if ($results) {
		$text = "<ul>".implode("\n", $results)."</ul>\n";
	} else {
		$text = wrap_text('Nothing to check.');
	}
	return $text;
}

function zz_maintenance_filetree() {
	$topdir = $_SERVER['DOCUMENT_ROOT'].'/../';
	$base = false;
	if (!empty($_GET['filetree'])) {
		$parts = explode('/', $_GET['filetree']);
		$text = array_pop($parts);
		$text = '<strong>'.zz_htmltag_escape($text).'</strong>';
		while ($parts) {
			$folder = implode('/', $parts);
			$part = array_pop($parts);
			$text = '<a href="?filetree='.$folder.'">'.zz_htmltag_escape($part).'</a> / '.$text;
		}
		$text = '<p><a href="?filetree">TOP</a> / '.$text.'</p>';
		$base = $_GET['filetree'].'/';
	} else {
		$text = '<p><strong>TOP</strong></p>';
	}
	$text .= zz_maintenance_files($topdir.$base, $base);
	return $text;
}

function zz_maintenance_files($dir, $base) {
	if (!is_dir($dir)) return false;

	$tbody = '';
	$i = 0;
	$total = 0;
	$totalfiles = 0;
	$files = array();

	$handle = opendir($dir);
	while ($file = readdir($handle)) {
		if ($file == '.' OR $file == '..') continue;
		$files[] = $file;
	}
	closedir($handle);
	sort($files);

	foreach ($files as $file) {
		$i++;
		$files_in_folder = 0;
		if (is_dir($dir.'/'.$file)) {
			list ($size, $files_in_folder) = zz_maintenance_dirsize($dir.'/'.$file);
			$link = '<strong><a href="?filetree='.$base.$file.'">';
		} else {
			$size = filesize($dir.'/'.$file);
			$files_in_folder = 1;
			$link = false;
		}
		$tbody .= '<tr class="'.($i & 1 ? 'uneven' : 'even').'">'
			.'<td>'.$link.$file.($link ? '</a></strong>' : '').'</td>'
			.'<td class="number">'.number_format($size).' Bytes</td>'
			.'<td class="number">'.number_format($files_in_folder).'</td>'
			.'</tr>'."\n";
		$total += $size;
		$totalfiles += $files_in_folder;
	}

	$text = '<table class="data"><thead><tr>
		<th>'.zz_text('Filename').'</th>
		<th>'.zz_text('Filesize').'</th>
		<th>'.zz_text('Files').'</th>
		</thead>
		<tfoot><tr><td></td><td class="number">'.number_format($total).' Bytes</td>
		<td class="number">'.number_format($totalfiles).'</td></tr></tfoot>
		<tbody>'."\n";
	$text .= $tbody;
	$text .= '</tbody></table>'."\n";
	return $text;
}

function zz_maintenance_dirsize($dir) {
	$size = 0;
	$files = 0;
	$handle = opendir($dir);
	if (!$handle) return array($size, $files);
	while ($file = readdir($handle)) {
		if ($file == '.' OR $file == '..') continue;
		if (is_dir($dir.'/'.$file)) {
			list ($mysize, $myfiles) = zz_maintenance_dirsize($dir.'/'.$file);
			$size += $mysize;
			$files += $myfiles;
		} else {
			$size += filesize($dir.'/'.$file);
			$files++;
		}
	}
	closedir($handle);
	return array($size, $files);
}

/**
 * reformats SQL query for better readability
 * 
 * @param string $sql
 * @return string $sql, formatted
 */
function zz_maintenance_sql($sql) {
	$sql = preg_replace("/\s+/", " ", $sql);
	$tokens = explode(' ', $sql);
	$sql = array();
	$keywords = array('INSERT', 'INTO', 'DELETE', 'FROM', 'UPDATE', 'SELECT',
		'UNION', 'WHERE', 'GROUP', 'BY', 'ORDER', 'DISTINCT', 'LEFT', 'JOIN',
		'RIGHT', 'INNER', 'NATURAL', 'USING', 'SET', 'CONCAT', 'SUBSTRING_INDEX',
		'VALUES');
	$newline = array('LEFT', 'FROM', 'GROUP', 'WHERE', 'SET', 'VALUES', 'SELECT');
	$newline_tab = array('ON', 'AND');
	foreach ($tokens as $token) {
		$out = zz_html_escape($token);
		if (in_array($token, $keywords)) $out = '<strong>'.$out.'</strong>';
		if (in_array($token, $newline)) $out = "\n".$out;
		if (in_array($token, $newline_tab)) $out = "\n\t".$out;
		$sql[] = $out;
	}
	$replace = array('%%%' => '%&shy;%%');
	foreach ($replace as $old => $new) {
		$sql = str_replace($old, $new, $sql);
	}
	$sql = implode(' ', $sql);
	return $sql;
}

function zz_maintenance_folders() {
	global $zz_conf;
	global $zz_setting;
	$text = '';

	if (!isset($zz_conf['backup'])) $zz_conf['backup'] = '';
	if ((!$zz_conf['backup'] OR empty($zz_conf['backup_dir']))
		AND empty($zz_conf['tmp_dir']) AND empty($zz_setting['cache'])) {
		$text .= '<p>'.zz_text('Backup of uploaded files is not active.').'</p>'."\n";
		return $text;
	}

	$folders = array();
	$dirs = array(
		'TEMP' => $zz_conf['tmp_dir'],
		'BACKUP' => $zz_conf['backup_dir'],
		'CACHE' => $zz_setting['cache']
	);
	$text .= '<ul>';
	foreach ($dirs as $key => $dir) {
		$exists = file_exists($dir) ? true : false;
		$text .= '<li>'.sprintf(zz_text('Current %s dir is: %s'), $key, realpath($dir))
			.(!$exists AND $dir ? ' &#8211; <span class="error">but this directory does not exist</span>' : '')
			.'</li>'."\n";
		if (!$exists) continue;
		$folders[] = $key;
		if (substr($dir, -1) == '/') $dir = substr($dir, 0, -1);
		if (!empty($_GET['folder']) AND substr($_GET['folder'], 0, strlen($key)) == $key) {
			$my_folder = $dir.substr($_GET['folder'], strlen($key));
		}
	}
	$text .= '</ul>';

	if (!empty($_GET['folder']) AND !empty($_GET['file'])) {
		$file['name'] = $my_folder.'/'.$_GET['file'];
		wrap_file_send($file);
		exit;
	}

	// delete
	$deleted = 0;
	if (!empty($_POST['files']) AND !empty($_GET['folder'])) {
		foreach ($_POST['files'] as $file => $bool) {
			if ($bool != 'on') continue;
			if (file_exists($my_folder.'/'.$file)) {
				if (is_dir($my_folder.'/'.$file)) {
					rmdir($my_folder.'/'.$file);
					$deleted++;
				} else {
					unlink($my_folder.'/'.$file);
					$deleted++;
				}
			}
		}
	}

	foreach ($folders as $folder) {
		$text .= '<h3><a href="?folder='.$folder.'">'.$folder.'/</a></h3>'."\n";
		if (empty($_GET['folder'])) continue;
		if (substr($_GET['folder'], 0, strlen($folder)) != $folder) continue;
		if ($folder != $_GET['folder']) {
			$text .= '<h4>'.zz_htmltag_escape($_GET['folder']).'</h4>'."\n";
		}

		$folder_handle = opendir($my_folder);

		$files = array();
		$total_files_q = 0;
		while ($file = readdir($folder_handle)) {
			if (substr($file, 0, 1) == '.') continue;
			if (!empty($_POST['deleteall'])) {
				$deleted += zz_maintenance_folders_deleteall($my_folder, $file);
				continue;
			}
			$files[] = $file;
			if (zz_maintenance_searched($file)) {
				$total_files_q++;
			}
		}
		sort($files);

		if ($deleted) {
			$text .= '<p class="error">'.sprintf(wrap_text('%s files deleted'), $deleted).'</p>';
		}
		$form = zz_maintenance_deleteall_form('files');
		if ($form) {
			$text .= $form;
			return $text;
		}

		$text .= '<form action="" method="POST">';
		$text .= '<table class="data"><thead><tr>
			<th>[]</th>
			<th>'.zz_text('Filename').'</th>
			<th>'.zz_text('Filetype').'</th>
			<th>'.zz_text('Size').'</th>
			<th>'.zz_text('Timestamp').'</th>
			</thead>'."\n";
		$i = 0;
		$size_total = 0;
		$tbody = '';
		$total_rows = 0;
		foreach ($files as $file) {
			if (!empty($_GET['q']) AND !zz_maintenance_searched($file)) {
				continue;
			}
			if ($i < $zz_conf['int']['this_limit'] - $zz_conf['limit']) {
				$i++;
				continue;
			}
			$size = filesize($my_folder.'/'.$file);
			$size_total += $size;
			if (is_dir($my_folder.'/'.$file)) {
				$ext = zz_text('Folder');
			} elseif (strrpos($file, '.') > strlen($file) - 10) {
				// treat part behind last dot as file extension
				// normally, file extensions won't be longer than 10 characters
				// not 100% correct of course
				$ext = substr($file, strrpos($file, '.')+1);
			} else {
				$ext = zz_text('unknown');
			}
			$time = date('Y-m-d H:i:s', filemtime($my_folder.'/'.$file));
			$files_in_dir = 0;
			if (is_dir($my_folder.'/'.$file)) {
				$link = './?folder='.urlencode($_GET['folder']).'/'.urlencode($file);
				$subfolder_handle = opendir($my_folder.'/'.$file);
				while ($subdir = readdir($subfolder_handle)) {
					if (substr($subdir, 0, 1) == '.') continue;
					$files_in_dir ++;
				}
				closedir($subfolder_handle);
			} else {
				$link = './?folder='.urlencode($_GET['folder'])
					.'&amp;file='.urlencode($file);
			}
			$tbody .= '<tr class="'.($i & 1 ? 'uneven' : 'even').'">'
				.'<td>'.($files_in_dir ? '' : '<input type="checkbox" name="files['.$file.']">').'</td>'
				.'<td><a href="'.$link.'">'.zz_mark_search_string(str_replace('%', '%&shy;', zz_html_escape(urldecode($file)))).'</a></td>'
				.'<td>'.$ext.'</td>'
				.'<td class="number">'.number_format($size).' Bytes</td>'
				.'<td>'.$time.'</td>'
				.'</tr>'."\n";
			$i++;
			$total_rows++;
			if ($i == $zz_conf['int']['this_limit']) break;
		}
		closedir($folder_handle);
		$text .= '<tfoot><tr><td></td><td>'.zz_text('All Files').'</td><td>'
			.$total_rows.'</td><td>'.number_format($size_total).' Bytes</td><td></td></tr></tfoot>';
		if (!$tbody) {
			$text .= '<tbody><tr class="even"><td>&nbsp;</td><td colspan="4">&#8211; '
				.zz_text('Folder is empty').' &#8211;</td></tr></tbody></table>'."\n";
		} else {
			// show submit button only if files are there
			$text .= '<tbody>'.$tbody.'</tbody></table>'."\n"
				.'<p style="float: right;"><a href="'.zz_html_escape($_SERVER['REQUEST_URI'])
				.'&amp;deleteall">Delete all files</a></p>
				<p><input type="submit" value="'.zz_text('Delete selected files').'">'
				.' &#8211; <a onclick="zz_set_checkboxes(true); return false;" href="#">'.zz_text('Select all').'</a> |
				<a onclick="zz_set_checkboxes(false); return false;" href="#">'.zz_text('Deselect all').'</a>
				</p>';
		}
		$text .= '</form>';
		$shown_records = count($files);
		if (!empty($_GET['q'])) $shown_records = $total_files_q;
		$text .= zz_list_total_records($shown_records);
		$text .= zz_list_pages($zz_conf['limit'], $zz_conf['int']['this_limit'], $shown_records);
		$zz_conf['search_form_always'] = true;
		$searchform = zz_search_form(array(), '', $total_rows, $shown_records);
		$text .= $searchform['bottom'];
	}

	return $text;
}

/**
 * checks if 'search' is active, then tests string against search string
 *
 * @param string $string
 * @return bool false: no search active or string not found; true: search is
 *		active and string was found
 */
function zz_maintenance_searched($string) {
	global $zz_conf;
	if (empty($_GET['q'])) return false;
	if (stristr($string, $_GET['q'])) return true;
	if (stristr(urldecode($string), $_GET['q'])) return true;

	// allow for searching ignoring replaced zero width space
	// PHPs char does not support unicode
	$q = urlencode($_GET['q']);
	if ($zz_conf['character_set'] === 'utf-8') {
		$char8203 = '%E2%80%8B';
	} else {
		// this does not work for all other character sets
		// but it works for iso-8859-1 and -2
		$char8203 = '%26%238203%3B';
	}
	$q = str_replace($char8203, '', $q);
	$q = urldecode($q);
	$_GET['q'] = $q;
	if (stristr($string, $q)) return true;
	return false;
}

/**
 * HTML output of form with button to delete all lines, files etc. in list
 * 'q'-search filter will be regarded
 *
 * @param string $type
 * @global array $zz_conf
 * @return string HTML output
 */
function zz_maintenance_deleteall_form($type) {
	global $zz_conf;
	if (!empty($_POST['deleteall'])) return false;
	if (!isset($_GET['deleteall'])) return false;

	$filter = '';
	if (!empty($_GET['q']))
		$filter = ' Search: '.zz_html_escape($_GET['q']);
	$unwanted_keys = array('deleteall');
	$qs = zz_edit_query_string($zz_conf['int']['url']['qs_zzform'], $unwanted_keys);
	$url = $zz_conf['int']['url']['full'].$qs;
	$text = '<form action="'.$url.'" method="POST"><input type="submit" name="deleteall" value="Delete all '.$type.'?'.$filter.'"></form>';
	return $text;
}

function zz_maintenance_folders_deleteall($my_folder, $file) {
	if (!empty($_GET['q'])) {
		if (zz_maintenance_searched($file)) {
			$success = unlink($my_folder.'/'.$file);
			return $success;
		}
	} else {
		$success = unlink($my_folder.'/'.$file);
		return $success;
	}
}

function zz_maintenance_errors() {
	global $zz_conf;

	$lines[0]['th'] = zz_text('Error handling');
	$lines[0]['td'] = (!empty($zz_conf['error_handling']) ? $zz_conf['error_handling'] : '');
	$lines[0]['explanation']['output'] = zz_text('Errors will be shown on webpage');
	$lines[0]['explanation']['mail'] = zz_text('Errors will be sent via mail');
	$lines[0]['explanation'][false] = zz_text('Errors won\'t be shown');

	$lines[1] = array(
		'th' => zz_text('Send mail for these error levels'),
		'td' => (is_array($zz_conf['error_mail_level']) ? implode(', ', $zz_conf['error_mail_level']) : $zz_conf['error_mail_level'])
	);
	$lines[3] = array(
		'th' => zz_text('Send mail (From:)'),
		'td' => (!empty($zz_conf['error_mail_from']) ? $zz_conf['error_mail_from'] : ''),
		'explanation' => array(false => zz_text('not set')),
		'class' => 'level1'
	);
	$lines[5] = array(
		'th' => zz_text('Send mail (To:)'),
		'td' => (!empty($zz_conf['error_mail_to']) ? $zz_conf['error_mail_to'] : ''),
		'explanation' => array(false => zz_text('not set')),
		'class' => 'level1'
	);

	$lines[6]['th'] = zz_text('Logging');
	$lines[6]['td'] = $zz_conf['log_errors'];
	$lines[6]['explanation'][1] = zz_text('Errors will be logged');
	$lines[6]['explanation'][false] = zz_text('Errors will not be logged');

	if ($zz_conf['log_errors']) {

		// get logfiles
		if ($php_log = ini_get('error_log'))
			$logfiles[realpath($php_log)][] = 'PHP';
		$levels = array('error', 'warning', 'notice');
		foreach ($levels as $level) {
			if ($zz_conf['error_log'][$level]) {
				$logfile = realpath($zz_conf['error_log'][$level]);
				if (!$logfile) continue;
				$logfiles[$logfile][] = ucfirst($level);
			}
		}
		$no = 8;
		foreach ($logfiles as $file => $my_levels) {
			$lines[$no] = array(
				'th' => sprintf(zz_text('Logfile for %s'), '<strong>'
				.implode(', ' , $my_levels).'</strong>'),
				'td' => '<a href="?log='.urlencode($file)
				.'&amp;filter[type]=none">'.$file.'</a>',
				'class' => 'level1'
			);
			$no = $no +2;
		}

		$lines[20]['th'] = zz_text('Maximum length of single error log entry');
		$lines[20]['td'] = $zz_conf['log_errors_max_len'];
		$lines[20]['class'] = 'level1';
	
		$lines[22]['th'] = zz_text('Log POST variables when errors occur');
		$lines[22]['td'] = (!empty($zz_conf['error_log_post']) ? $zz_conf['error_log_post'] : false);
		$lines[22]['explanation'][1] = zz_text('POST variables will be logged');
		$lines[22]['explanation'][false] = zz_text('POST variables will not be logged');
		$lines[22]['class'] = 'level1';

	}

	$lines[23]['th'] = zz_text('Logging (Upload)');
	$lines[23]['td'] = !empty($zz_conf['upload_log']) ? '<a href="?log='.urlencode(realpath($zz_conf['upload_log']))
				.'">'.realpath($zz_conf['upload_log']).'</a>' : zz_text('disabled');

	$text = '<table class="data"><thead><tr><th>'.zz_text('Setting').'</th>'
		.'<th>'.zz_text('Value').'</th>'
		.'</tr></thead><tbody>'."\n";
	foreach ($lines as $index => $line) {
		if (!$line['td']) $line['td'] = false;
		$text .= '<tr class="'.($index & 1 ? 'uneven' : 'even').'">'
			.'<td'.(!empty($line['class']) ? ' class="'.$line['class'].'"' : '').'>'.$line['th']
			.'</td><td><strong>'.$line['td'].'</strong>'
			.(!empty($line['explanation'][$line['td']]) ? ' ('.$line['explanation'][$line['td']].')' : '') 
			.'</td></tr>'."\n";
	}
	$text .= '</tbody></table>'."\n";
	return $text;
}

/**
 * output of logfile per line or grouped with the possibility to delete lines
 *
 * @global array $zz_conf
 * @return string HTML output
 */
function zz_maintenance_logs() {
	global $zz_conf;
	$levels = array('error', 'warning', 'notice');
	if (empty($_GET['log'])) {
		$text = '<p>'.zz_text('No logfile specified').'</p>'."\n";
		return $text;
	}

	$show_log = false;
	foreach ($levels as $level) {
		if ($_GET['log'] === realpath($zz_conf['error_log'][$level])) {
			$show_log = true;
		}
	}
	if ($_GET['log'] === realpath(ini_get('error_log'))) {
		$show_log = true;
	}
	if (!empty($zz_conf['upload_log']) AND $_GET['log'] === realpath($zz_conf['upload_log'])) {
		$show_log = true;
	}
	if (!$show_log) {
		$text = '<p>'.sprintf(zz_text('This is not one of the used logfiles: %s'), zz_html_escape($_GET['log'])).'</p>'."\n";
		return $text;
	}
	if (!file_exists($_GET['log'])) {
		$text = '<p>'.sprintf(zz_text('Logfile does not exist: %s'), zz_html_escape($_GET['log'])).'</p>'."\n";
		return $text;
	}

	// delete
	$message = false;
	if (!empty($_POST['line'])) {
		$message = zz_delete_line_from_file($_GET['log'], $_POST['line']);
	}

	$filters['type'] = array('PHP', 'zzform', 'zzwrap');
	$filters['level'] = array('Notice', 'Deprecated', 'Warning', 'Error', 'Parse error', 'Strict error', 'Fatal error');
	$filters['group'] = array('Group entries');
	$filter_output = '';
	
	$text = '<h2>'.zz_html_escape($_GET['log']).'</h2>';

	parse_str($zz_conf['int']['url']['qs_zzform'], $my_query);
	$filters_set = (!empty($my_query['filter']) ? $my_query['filter'] : array());
	$unwanted_keys = array('filter', 'limit');
	$my_uri = $zz_conf['int']['url']['self'].zz_edit_query_string($zz_conf['int']['url']['qs_zzform'], $unwanted_keys);
	
	foreach ($filters as $index => $filter) {
		$filter_output[$index] = '<dt>'.zz_text('Selection').' '.ucfirst($index).':</dt>';
		$my_link = $my_uri;
		if ($filters_set) {
			foreach ($filters_set as $which => $filter_set) {
				if ($which != $index) $my_link .= '&amp;filter['.$which.']='.urlencode($filter_set);
			}
		}
		foreach ($filter as $value) {
			$is_selected = ((isset($_GET['filter'][$index]) 
				AND $_GET['filter'][$index] == $value)) ? true : false;
			$link = $my_link.'&amp;filter['.$index.']='.urlencode($value);
			$filter_output[$index] .= '<dd>'
				.(!$is_selected ? '<a href="'.$link.'">' : '<strong>')
				.$value
				.(!$is_selected ? '</a>' : '</strong>')
				.'</dd>'."\n";
		}
		$filter_output[$index] .= '<dd class="filter_all">&#8211;&nbsp;'
			.(isset($_GET['filter'][$index]) ? '<a href="'.$my_link.'">' : '<strong>')
			.zz_text('all')
			.(isset($_GET['filter'][$index]) ? '</a>' : '</strong>')
			.'&nbsp;&#8211;</dd>'."\n";
	}
	if ($filter_output) {
		$text .= '<div class="zzfilter">'."\n";
		$text .= '<dl>'."\n";
		$text .= implode("", $filter_output);
		$text .= '</dl><br clear="all"></div>'."\n";
	}

	if (!empty($_GET['filter']) AND !empty($_GET['filter']['type'])
		AND $_GET['filter']['type'] == 'none') {
		$text .= '<p><strong>'.zz_text('Please choose one of the filters.').'</strong></p>';
		return $text;
	}

	if (!empty($_GET['filter']) AND !empty($_GET['filter']['group'])
		AND $_GET['filter']['group'] == 'Group entries') {
		$group = true;	
		$output = array();
	} else 
		$group = false;

	$form = zz_maintenance_deleteall_form('lines');
	if ($form) {
		$text .= $form;
		return $text;
	}


	// get lines
	$j = 0;
	$delete = array();
	$content = '';
	$dont_highlight_levels = array('Notice', 'Deprecated', 'Warning', 'Upload');
	$tbody = '';
	$log = array();
	$handle = fopen($_GET['log'], 'r');
	if ($handle) {
		$total_rows = 0;
		$index = 0;
		$i = 0;
		$write_log = true;
		while (($line = fgets($handle, $zz_conf['log_errors_max_len']+2)) !== false) {
			$line = trim($line);

			if (!empty($_GET['q']) AND !zz_maintenance_searched($line)) {
				$index++;
				continue;
			}

			$data = array();
			$data['type'] = '';
			$data['user'] = '';
			$data['date'] = '';
			$data['level'] = '';
			$data['time'] = '';

			// get date
			if (substr($line, 0, 1) == '[' AND $rightborder = strpos($line, ']')) {
				$data['date'] = substr($line, 1, $rightborder - 1);
				$line = substr($line, $rightborder + 2);
			}
			// get user
			if (substr($line, -1) == ']' AND strstr($line, '[')) {
				$data['user'] = substr($line, strrpos($line, '[')+1, -1);
				$data['user'] = explode(' ', $data['user']);
				if (count($data['user']) > 1 AND substr($data['user'][0], -1) == ':') {
					array_shift($data['user']); // get rid of User: or translations of it
				}
				$data['user'] = implode(' ', $data['user']);
				$line = substr($line, 0, strrpos($line, '['));
			}

			$tokens = explode(' ', $line);
			if ($tokens AND in_array($tokens[0], $filters['type'])) {
				$data['type'] = array_shift($tokens);
				$data['level'] = array_shift($tokens);
				if (substr($data['level'], -1) == ':') $data['level'] = substr($data['level'], 0, -1);
				else $data['level'] .= ' '.array_shift($tokens);
				if (substr($data['level'], -1) == ':') $data['level'] = substr($data['level'], 0, -1);
			}

			if (!empty($_GET['filter'])) {
				if (!empty($_GET['filter']['type'])) {
					if ($data['type'] != $_GET['filter']['type']) {
						$index++;
						continue;
					}
				}
				if (!empty($_GET['filter']['level'])) {
					if ($data['level'] != $_GET['filter']['level']) {
						$index++;
						continue;
					}
				}
			}
			if (in_array($data['type'], array('zzform', 'zzwrap'))) {
				if (!$data['user'])
					$data['user'] = array_pop($tokens);
				$time = '';
				while (!$time) {
					// ignore empty tokens
					$time = trim(end($tokens));
					if (!$time) array_pop($tokens);
					if (!$tokens) break;
				}
				if (substr($time, 0, 1) === '{'
					AND substr($time, -1) === '}'
					AND is_numeric(substr($time, 1, -1))
				) {
					array_pop($tokens);
					$data['time'] = substr($time, 1, -1);
					// shorten time to make it more readable
					$data['time'] = substr($data['time'], 0, 6);
				}
			}

			$data['status'] = false;
			if ($tokens AND substr($tokens[0], 0, 1) == '[' AND substr($tokens[0], -1) == ']') {
				$data['link'] = array_shift($tokens);
				$data['link'] = substr($data['link'], 1, -1);
				if (intval($data['link'])."" === $data['link']) {
					// e. g. 404 has no link repeated as it's already in the
					// error message	
					$data['status'] = $data['link'];
					$data['link'] = false;
				}
			} elseif ($tokens AND substr($tokens[0], 0, 1) == '[' AND substr($tokens[1], -1) == ']'
				AND strlen($tokens[0]) == 4) {
				$data['status'] = array_shift($tokens);
				$data['status'] = substr($data['status'], 1);
				$data['link'] = array_shift($tokens);
				$data['link'] = substr($data['link'], 0, -1);
			} else {
				$data['link'] = false;
			}
			$data['error'] = implode(' ', $tokens);
			
			if (!empty($_POST['deleteall'])) {
				$delete[] = $index;
			} elseif (!$group) {
				if ($i < ($zz_conf['int']['this_limit'] - $zz_conf['limit'])) {
					$index++;
					$total_rows++;
					$i++;
					continue;
				}
				if ($write_log) {
					$data['index'] = $index;
					$log[$index] = $data;
					$i++;
				}
				if ($zz_conf['int']['this_limit']
					AND $i >= $zz_conf['int']['this_limit']) $write_log = false;
				$total_rows++;
			} else {
				if (empty($log[$data['error']])) {
					$log[$data['error']] = array(
						'date_begin' => $data['date'],
						'type' => $data['type'],
						'level' => $data['level'],
						'error' => $data['error'],
						'user' => array($data['user']),
						'index' => array($index),
						'link' => array($data['link']),
						'status' => array($data['status']),
						'time' => array($data['time'])
					);
					$total_rows++;
				} else {
					$log[$data['error']]['index'][] = $index;
					$log[$data['error']]['date_end'] = $data['date'];
					$fields = array('user', 'link', 'status');
					foreach ($fields as $field) {
						if (!in_array($data[$field], $log[$data['error']][$field]))
							$log[$data['error']][$field][] = $data[$field];
					}
				}
			}
			$index++;
		}
		fclose($handle);
	}
	if ($group) {
		if ($zz_conf['int']['this_limit']) {
			$log = array_slice($log, ($zz_conf['int']['this_limit'] - $zz_conf['limit']), $zz_conf['limit']);
		}
	}

	if (!empty($_POST['deleteall'])) {
		$message .= zz_delete_line_from_file($_GET['log'], $delete);
	}

	if ($message) $text .= '<p class="error">'.$message.'</p>'."\n";

	// output lines
	foreach ($log as $index => $line) {
		if ($line['level'] AND !in_array($line['level'], $dont_highlight_levels))
			$line['level'] = '<p class="error">'.$line['level'].'</p>';

		$post = false;
		if (substr($line['error'], 0, 5) == 'POST ') {
			$post = @unserialize(substr($line['error'], 5));
			if ($post)
				$line['error'] = 'POST '.wrap_print($post);
		} elseif (substr($line['error'], 0, 11) === 'POST[json] ') {
			$post = @json_decode(substr($line['error'], 11));
			if ($post)
				$line['error'] = 'POST '.wrap_print($post);
		}
		if (!$post) {
			$no_html = false;
			if (in_array($line['type'], array('zzform', 'zzwrap')))
				$no_html = true;
			$line['error'] = zz_maintenance_splits($line['error'], $no_html);
		}
		// htmlify links
		if (stristr($line['error'], 'http:/&#8203;/&#8203;') OR stristr($line['error'], 'https:/&#8203;/&#8203;')) {
			$line['error'] = preg_replace_callback('~(\S+):/&#8203;/&#8203;(\S+)~', 'zz_maintenance_make_url', $line['error']);
		}
		$line['error'] = str_replace(',', ', ', $line['error']);
		$line['error'] = zz_mark_search_string($line['error']);

		if (!$group) {
			$tbody .= '<tr class="'.($j & 1 ? 'uneven' : 'even').'">'
				.'<td><label for="line'.$index.'" class="blocklabel"><input type="checkbox" name="line['
					.$index.']" value="'.$index.'" id="line'.$index.'"></label></td>'
				.'<td>'.$line['date'].'</td>'
				.'<td>'.$line['type'].'</td>'
				.'<td>'.$line['level'].'</td>'
				.'<td>'.($line['status'] ? '<strong>'.$line['status'].'</strong>' : '')
					.' '.($line['link'] ? '[<a href="'.str_replace('&', '&amp;', $line['link']).'">'
					.zz_maintenance_splits($line['link'], true).'</a>]<br>' : '').$line['error'].'</td>'
				.'<td>'.$line['user'].($line['time'] ? '<br>'.$line['time'] : '').'</td>'
				.'</tr>'."\n";
		} else {
			$links = '';
			if ($line['status']) {
				foreach ($line['status'] as $status) {
					if (!$status) continue;
					$links .= '<strong>'.$status.'</strong> ';
				}
			}
			if ($line['link']) {
				foreach ($line['link'] as $link) {
					if (!$link) continue;
					$links .= '[<a href="'.str_replace('&', '&amp;', $link).'">'.zz_maintenance_splits($link, true).'</a>]<br>';
				}
			}
			$tbody .= '<tr class="'.($j & 1 ? 'uneven' : 'even').'">'
				.'<td><label for="line'.$j.'" class="blocklabel"><input type="checkbox" name="line['
					.$j.']" value="'.implode(',', $line['index']).'" id="line'.$j.'"></label></td>'
				.'<td>'.$line['date_begin'].'</br>'
				.((!empty($line['date_end']) AND $line['date_end'] != $line['date_begin'])
					? '&#8211;'.$line['date_end']: '').'</td>'
				.'<td>'.$line['type'].'</td>'
				.'<td>'.$line['level'].'</td>'
				.'<td>'.$links.$line['error'].'</td>'
				.'<td>'.implode(', ', $line['user'])
					.($line['time'] ? '<br>'.implode(', ', $line['time']) : '').'</td>'
				.'<td>'.count($line['index']).'</td>'
				.'</tr>'."\n";
		}
		$j++;
	}

	$text .= '<form action="" method="POST">'
		.'<table class="data"><thead><tr>
		<th>[]</th>
		<th>'.zz_text('Date').'
		'.($group ? '<br>'.zz_text('Last Date').'' : '').'</th>
		<th>'.zz_text('Type').'</th>
		<th>'.zz_text('Level').'</th>
		<th>'.zz_text('Message').'</th>
		<th>'.zz_text('User').'</th>
		'.($group ? '<th>'.zz_text('Frequency').'</th>' : '').'
		</thead>'."\n"
		.'<tbody>'."\n".$tbody;
	if (!$tbody)
		$text .= '<tr><td colspan="6">'.zz_text('No lines').'</td></tr>'."\n";
	$text .= '</tbody></table>'."\n";
	if ($total_rows) {
		// show this only if there are deletable lines
		$text .= '<p style="float: right;"><a href="'.zz_html_escape($_SERVER['REQUEST_URI'])
			.'&amp;deleteall">Delete all lines</a></p>'
			.'<p><input type="submit" value="'.zz_text('Delete selected lines').'">'
			.' &#8211; <a onclick="zz_set_checkboxes(true); return false;" href="#">'.zz_text('Select all').'</a> |
			<a onclick="zz_set_checkboxes(false); return false;" href="#">'.zz_text('Deselect all').'</a></p>';
	}
	$text .= '</form>';

	$shown_records = $total_rows;
	$text .= zz_list_total_records($shown_records);
	$text .= zz_list_pages($zz_conf['limit'], $zz_conf['int']['this_limit'], $shown_records);
	$zz_conf['search_form_always'] = true;
	$searchform = zz_search_form(array(), '', $total_rows, $shown_records);
	$text .= $searchform['bottom'];

	return $text;
}

/**
 * get rid of long lines with zero width space (&#8203;) - &shy; does
 * not work at least in firefox 3.6 with slashes
 *
 * @param string $string
 * @param bool $no_html
 * @return string
 */
function zz_maintenance_splits($string, $no_html) {
	$string = str_replace(';', ';&#8203;', $string);
	$string = str_replace('&', '&#8203;&amp;', $string);
	$string = str_replace('&amp;#8203;', '&#8203;', $string);
	$string = str_replace('/', '/&#8203;', $string);
	$string = str_replace('=', '=&#8203;', $string);
	$string = str_replace('%', '&#8203;%', $string);
	$string = str_replace('-at-', '&#8203;-at-', $string);
	if ($no_html) {
		$string = str_replace('<', '&lt;', $string);
	}
	return $string;
}

function zz_maintenance_make_url($array) {
	$href = str_replace('&#8203;', '', $array[0]);
	$linktext = $array[0];
	$link = '<a href="'.$href.'">'.$linktext.'</a>'; 
	return $link;
}

/**
 * deletes lines from a file
 *
 * @param string $file path to file
 * @param array $lines list of line numbers to be deleted
 */
function zz_delete_line_from_file($file, $lines) {

	// check if file exists and is writable
	if (!is_writable($file))
		return sprintf(zz_text('File %s is not writable.'), $file);

	$deleted = 0;
	$content = file($file);
	foreach ($lines as $line) {
		$line = explode(',', $line);
		foreach ($line as $no) {
			unset($content[$no]);
			$deleted++;
		}
	}

	// open file for writing
	if (!$handle = fopen($file, 'w+'))
		return sprintf(zz_text('Cannot open %s for writing.'), $file);

	foreach($content as $line)
		fwrite($handle, $line);

	fclose($handle);
	return sprintf(zz_text('%s lines deleted.'), $deleted);
}

?>