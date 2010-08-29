<?php 

// zzform scripts (Zugzwang Project)
// (c) Gustaf Mossakowski <gustaf@koenige.org>, 2010
// Maintenance script for database operations with zzform


/**
 * Maintenance script for zzform to do some cleanup/correction operations
 *
 * - change database name if local development uses different database names
 * for relations and translations
 * - delete files from backup-directory
 * - enter an sql query
 * @param array $params
 * @global array $zz_conf configuration variables
 * @return array $page
 *		'text' => page content, 'title', 'breadcrumbs', ...
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_maintenance($params) {
	global $zz_conf;
	global $zz_setting;

	require_once $zz_conf['dir_inc'].'/functions.inc.php';
	if (file_exists($zz_setting['custom'].'/zzbrick_tables/_common.inc.php')) {
		require_once($zz_setting['custom'].'/zzbrick_tables/_common.inc.php');
		if (isset($brick['page'])) $page = $brick['page'];
	}
	
	if (!empty($_SESSION) AND empty($zz_conf['user']) AND !empty($zz_setting['brick_username_in_session']))
		$zz_conf['user'] = $_SESSION[$zz_setting['brick_username_in_session']];
	elseif (!isset($zz_conf['user']))
		$zz_conf['user'] = 'Maintenance robot 812';

	$page['title'] = zz_text('Maintenance');
	$page['dont_show_h1'] = true;
	$page['text'] = '';

	if (empty($_GET)) {	
		$page['text'] = '<h1>'.wrap_text('Maintenance scripts').'</h1>'."\n";
		$page['text'] .= '<div id="zzform">'."\n";

		// 'relations'
		// 'translations'
		$page['text'] .= '<h2>'.zz_text('Relations and Translation Tables').'</h2>'."\n";
		$page['text'] .= zz_maintenance_tables();

	
	// 	- Backup/errors, insert, update, delete
		$page['text'] .= '<h2>'.zz_text('Backup Files').'</h2>'."\n";
		$page['text'] .= zz_maintenance_folders();

	
		$page['text'] .= '<h2>'.zz_text('Custom SQL query').'</h2>'."\n";
	//	$page['text'] .= '<form action="" method="POST">';
		$page['text'] .= '<p>...</p>'."\n";
	// 	- SQL query absetzen, Häkchen für zz_log_sql()
	} else {
		if (!empty($_GET['folder'])) {
			$page['text'] = '<h1><a href="./">'.wrap_text('Maintenance scripts').'</a>: '
				.wrap_text('Backup folder').'</h1>'."\n";
			$page['text'] .= '<div id="zzform">'."\n";
			$page['text'] .= zz_maintenance_folders();
		} else {
			$page['text'] .= '<div id="zzform">'."\n";
			$page['text'] .= 'GET should be empty, please test that: <pre>';
			foreach ($_GET as $key => $value) {
				$page['text'] .= $key.' => '.$value."\n";
			}
			$page['text'] .= '</pre>'."\n";
		}
	}
	$page['text'] .= '</div>'."\n";


	return $page;

}

function zz_maintenance_tables() {
	global $zz_conf;
	$text = false;

	if (empty($zz_conf['relations_table'])) {
		$text .= '<p>'.zz_text('No table for database relations is defined')
			.' (<code>$zz_conf["relations_table"]</code>)</p>';
	} elseif (empty($zz_conf['translations_table'])) {
		$text .= '<p>'.zz_text('No table for database relations is defined')
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
						.' SET '.$field_name.' = "'.mysql_real_escape_string($new)
						.'" WHERE '.$field_name.' = "'.mysql_real_escape_string($old).'"';
					$result = mysql_query($sql);
					if ($result) {
						zz_log_sql($sql, $zz_conf['user']);
					}
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

	return $text;
}

function zz_maintenance_folders() {
	global $zz_conf;
	$text = '';

	if (!$zz_conf['backup'] OR empty($zz_conf['backup_dir'])) {
		$text .= '<p>'.wrap_text('Backup of uploaded files is not active.').'</p>'."\n";
		return $text;
	}
	$text .= '<p>'.wrap_text('Current backup dir is:').' '.$zz_conf['backup_dir'].'</p>'."\n";
	
	$backupdir = $zz_conf['backup_dir'];
	
	if (!empty($_GET['folder']) AND !empty($_GET['file'])) {
		$file['name'] = $backupdir.'/'.$_GET['folder'].'/'.$_GET['file'];
		wrap_file_send($file);
		exit;
	}
	
	if (!empty($_POST['files']) AND !empty($_GET['folder'])) {
		$folder = str_replace('/', '', $_GET['folder']);
		foreach ($_POST['files'] as $file => $bool) {
			if ($bool != 'on') continue;
			if (file_exists($backupdir.'/'.$folder.'/'.$file)) 
				unlink($backupdir.'/'.$folder.'/'.$file);
		}
	}

	$handle = opendir($backupdir);
	while ($folder = readdir($handle)) {
		if (substr($folder, 0, 1) == '.') continue;
		$text .= '<h3><a href="?folder='.$folder.'">'.$folder.'/</a></h3>'."\n";
		if (empty($_GET['folder'])) continue;
		if ($folder != $_GET['folder']) continue;

		$folder_handle = opendir($backupdir.'/'.$folder);
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
		while ($file = readdir($folder_handle)) {
			if (substr($file, 0, 1) == '.') continue;
			$size = filesize($backupdir.'/'.$folder.'/'.$file);
			$size_total += $size;
			$ext = substr($file, strrpos($file, '.')+1);
			$time = date('Y-m-d H:i:s', filemtime($backupdir.'/'.$folder.'/'.$file));
			$tbody .= '<tr class="'.($i & 1 ? 'uneven' : 'even').'">'
				.'<td><input type="checkbox" name="files['.$file.']"></td>'
				.'<td><a href="./?folder='.urlencode($_GET['folder'])
					.'&amp;file='.urlencode($file).'">'.$file.'</a></td>'
				.'<td>'.$ext.'</td>'
				.'<td class="number">'.number_format($size).' Bytes</td>'
				.'<td>'.$time.'</td>'
				.'</tr>'."\n";
			$i++;
		}
		$text .= '<tfoot><tr><td></td><td>'.wrap_text('All Files').'</td><td>'
			.$i.'</td><td>'.number_format($size_total).' Bytes</td><td></td></tr></tfoot>';
		$text .= '<tbody>'.$tbody.'</tbody></table>'."\n"
			.'<input type="submit" value="'.wrap_text('Delete selected files').'">';
		$text .= '</form>';
	}
	closedir($handle);

	return $text;
}

?>