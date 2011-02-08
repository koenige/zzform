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

	// Translations
	global $text;
	if (file_exists($zz_conf['dir_inc'].'/text-'.$zz_conf['language'].'.inc.php'))
		include $zz_conf['dir_inc'].'/text-'.$zz_conf['language'].'.inc.php';
	if (file_exists($zz_conf['dir'].'/text-'.$zz_conf['language'].'.inc.php'))
		include $zz_conf['dir'].'/text-'.$zz_conf['language'].'.inc.php';

	if (!isset($zz_conf['modules'])) {
		$zz_conf['modules'] = array();
		$zz_conf['modules']['debug'] = false;
	}

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
	
	unset($_GET['no-cookie']);

	$sql = '';
	if (!empty($_POST['sql'])) {
		$sql = $_POST['sql'];

		$page['text'] = '<h1><a href="./">'.zz_text('Maintenance scripts').'</a></h1>'."\n";
		$page['text'] .= '<div id="zzform">'."\n";
		$page['text'] .= '<h2>'.zz_text('SQL query').'</h2>'."\n";
		$page['text'] .= '<pre style="font-size: 1.1em; white-space: pre-wrap;"><code>'.zz_maintenance_sql($sql).'</code></pre>';

		$tokens = explode(' ', $sql);
		switch ($tokens[0]) {
		case 'INSERT':
		case 'UPDATE':
		case 'DELETE':
			$result = zz_db_change($sql);
			$page['text'] .= '<h2>'.zz_text('Result').'</h2>'."\n";
			if (!$result['action']) {
				$page['text'] .= '<div class="error">'
					.'MySQL says: '.$result['error']['mysql'].' [Code '
					.$result['error']['mysql_errno'].']'
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
			$page['text'] .= sprintf(zz_text('Sorry, %s is not yet supported'), htmlentities($tokens[0]));
		}
	}

	if (empty($_GET)) {	
		if (!$sql) {
			$page['text'] = '<h1>'.zz_text('Maintenance scripts').'</h1>'."\n";
			$page['text'] .= '<div id="zzform">'."\n";

			// 'relations'
			// 'translations'
			$page['text'] .= '<h2>'.zz_text('Relation and Translation Tables').'</h2>'."\n";
			$page['text'] .= zz_maintenance_tables();
	
		// 	- Backup/errors, insert, update, delete
			$page['text'] .= '<h2>'.zz_text('Error Logging').'</h2>'."\n";
			$page['text'] .= zz_maintenance_errors();
		
		// 	- Backup/errors, insert, update, delete
			$page['text'] .= '<h2>'.zz_text('PHP & Server').'</h2>'."\n";
			$page['text'] .= '<p><a href="?phpinfo">'.zz_text('Show PHP info on server').'</a></p>';

		// 	- Backup/errors, insert, update, delete
			$page['text'] .= '<h2>'.zz_text('Temp and Backup Files').'</h2>'."\n";
			$page['text'] .= zz_maintenance_folders();


		}
	
		$page['text'] .= '<h2>'.zz_text('Custom SQL query').'</h2>'."\n";
	//	$page['text'] .= '<form action="" method="POST">';
		$page['text'] .= '<form method="POST" action=""><textarea cols="60" rows="10" name="sql">'
			.str_replace('%%%', '%&shy;%&shy;%', htmlentities($sql))
			.'</textarea>
			<br><input type="submit"></form>'."\n";
	// 	- SQL query absetzen, Häkchen für zz_log_sql()
	} else {
		if (!empty($_GET['folder'])) {
			$page['text'] = '<h1><a href="./">'.zz_text('Maintenance scripts').'</a>: '
				.zz_text('Backup folder').'</h1>'."\n";
			$page['text'] .= '<div id="zzform">'."\n";
			$page['text'] .= zz_maintenance_folders();
		} elseif (!empty($_GET['log'])) {
			$page['text'] = '<h1><a href="./">'.zz_text('Maintenance scripts').'</a>: '
				.zz_text('Logs').'</h1>'."\n";
			$page['text'] .= '<div id="zzform">'."\n";
			$page['text'] .= zz_maintenance_logs();
		} elseif (isset($_GET['phpinfo'])) {
			phpinfo();
			exit;
		} else {
			$page['text'] .= '<div id="zzform">'."\n";
			$page['text'] .= zz_text('GET should be empty, please test that:').' <pre>';
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
					zz_db_change($sql);
				}
			}
		}
	}
	if (!empty($zz_conf['relations_table'])) {
	// Master database
		$sql = 'SELECT DISTINCT master_db FROM '.$zz_conf['relations_table'];
		$dbs['master'] = zz_db_fetch($sql, 'master_db', 'single value');

	// Detail database	
		$sql = 'SELECT DISTINCT detail_db FROM '.$zz_conf['relations_table'];
		$dbs['detail'] = zz_db_fetch($sql, 'detail_db', 'single value');
	}

	if (!empty($zz_conf['translations_table'])) {
	// Translations database	
		$sql = 'SELECT DISTINCT db_name FROM '.$zz_conf['translations_table'];
		$dbs['translation'] = zz_db_fetch($sql, 'db_name', 'single value');
	}
	
	// All available databases
	$sql = 'SHOW DATABASES';
	$databases = zz_db_fetch($sql, 'Databases', 'single value');
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
		$out = htmlentities($token);
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
	$text = '';

	if (!isset($zz_conf['backup'])) $zz_conf['backup'] = '';
	if ((!$zz_conf['backup'] OR empty($zz_conf['backup_dir']))
		AND empty($zz_conf['temp_dir'])) {
		$text .= '<p>'.zz_text('Backup of uploaded files is not active.').'</p>'."\n";
		return $text;
	}

	$folders = array();
	if (!empty($zz_conf['tmp_dir'])) {
		if (is_dir($zz_conf['tmp_dir'])) {
			$text .= '<p>'.zz_text('Current TEMP dir is:').' '.$zz_conf['tmp_dir'].'</p>'."\n";
			$folders[] = 'TEMP';
			if (substr($zz_conf['tmp_dir'], -1) == '/')
				$zz_conf['tmp_dir'] = substr($zz_conf['tmp_dir'], 0, -1);
			if (!empty($_GET['folder']) AND substr($_GET['folder'], 0, 4) == 'TEMP') {
				$my_folder = $zz_conf['tmp_dir'].substr($_GET['folder'], 4);
			}
		} else {
			$text .= '<p>'.zz_text('Current TEMP dir does not exist:').' '.$zz_conf['tmp_dir'].'</p>'."\n";
		}
	}
	if (!empty($zz_conf['backup_dir'])) {
		$text .= '<p>'.zz_text('Current backup dir is:').' '.$zz_conf['backup_dir'].'</p>'."\n";
		$backupdir = $zz_conf['backup_dir'];
		if (substr($backupdir, -1) == '/')
			$backupdir = substr($backupdir, 0, -1);
		if (!empty($_GET['folder']) AND substr($_GET['folder'], 0, 4) != 'TEMP') {
			$my_folder = $backupdir.'/'.$_GET['folder'];
		}
	}

	if (!empty($_GET['folder']) AND !empty($_GET['file'])) {
		$file['name'] = $my_folder.'/'.$_GET['file'];
		wrap_file_send($file);
		exit;
	}

	// delete
	if (!empty($_POST['files']) AND !empty($_GET['folder'])) {
		foreach ($_POST['files'] as $file => $bool) {
			if ($bool != 'on') continue;
			if (file_exists($my_folder.'/'.$file)) {
				if (is_dir($my_folder.'/'.$file))
					rmdir($my_folder.'/'.$file);
				else
					unlink($my_folder.'/'.$file);
			}
		}
	}

	$handle = opendir($backupdir);
	while ($folder = readdir($handle)) {
		if (substr($folder, 0, 1) == '.') continue;
		$folders[] = $folder;
	}

	foreach ($folders as $folder) {
		$text .= '<h3><a href="?folder='.$folder.'">'.$folder.'/</a></h3>'."\n";
		if (empty($_GET['folder'])) continue;
		if (substr($_GET['folder'], 0, strlen($folder)) != $folder) continue;
		if ($folder != $_GET['folder']) {
			$text .= '<h4>'.htmlspecialchars($_GET['folder']).'</h4>'."\n";
		}

		$folder_handle = opendir($my_folder);
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
		$files = array();
		while ($file = readdir($folder_handle)) {
			if (substr($file, 0, 1) == '.') continue;
			$files[] = $file;
		}
		sort($files);
		foreach ($files as $file) {
			$size = filesize($my_folder.'/'.$file);
			$size_total += $size;
			if (is_dir($my_folder.'/'.$file)) 
				$ext = zz_text('Folder');
			elseif (strstr($file, '.'))
				$ext = substr($file, strrpos($file, '.')+1);
			else
				$ext = zz_text('unknown');
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
				.'<td><a href="'.$link.'">'.$file.'</a></td>'
				.'<td>'.$ext.'</td>'
				.'<td class="number">'.number_format($size).' Bytes</td>'
				.'<td>'.$time.'</td>'
				.'</tr>'."\n";
			$i++;
		}
		closedir($folder_handle);
		$text .= '<tfoot><tr><td></td><td>'.zz_text('All Files').'</td><td>'
			.$i.'</td><td>'.number_format($size_total).' Bytes</td><td></td></tr></tfoot>';
		if (!$tbody) {
			$text .= '<tbody><tr class="even"><td>&nbsp;</td><td colspan="4">&#8211; '
				.zz_text('Folder is empty').' &#8211;</td></tr></tbody></table>'."\n";
		} else {
			// show submit button only if files are there
			$text .= '<tbody>'.$tbody.'</tbody></table>'."\n"
				.'<input type="submit" value="'.zz_text('Delete selected files').'">';
		}
		$text .= '</form>';
	}
	closedir($handle);

	return $text;
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
			$logfiles[$php_log][] = 'PHP';
		$levels = array('error', 'warning', 'notice');
		foreach ($levels as $level) {
			if ($zz_conf['error_log'][$level]) {
				$logfiles[$zz_conf['error_log'][$level]][] = ucfirst($level);
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

function zz_maintenance_logs() {
	global $zz_conf;
	$levels = array('error', 'warning', 'notice');
	if (empty($_GET['log'])) {
		$text = '<p>'.zz_text('No logfile specified').'</p>'."\n";
		return $text;
	}

	$show_log = false;
	foreach ($levels as $level)
		if ($_GET['log'] == $zz_conf['error_log'][$level]) $show_log = true;
	if (!$show_log) {
		$text = '<p>'.sprintf(zz_text('This is not one of the used logfiles: %s'), htmlspecialchars($_GET['log'])).'</p>'."\n";
		return $text;
	}
	if (!file_exists($_GET['log'])) {
		$text = '<p>'.sprintf(zz_text('Logfile does not exist: %s'), htmlspecialchars($_GET['log'])).'</p>'."\n";
		return $text;
	}

	// delete
	$message = false;
	if (!empty($_POST['line'])) {
		foreach ($_POST['line'] as $file => $bool)
			if ($bool != 'on') unset($_POST['line'][$file]);
		$message = zz_delete_line_from_file($_GET['log'], array_keys($_POST['line']));
	}

	$filters['type'] = array('PHP', 'zzform', 'zzwrap');
	$filters['level'] = array('Notice', 'Warning', 'Error', 'Parse error', 'Strict error', 'Fatal error');
	$filters['group'] = array('Group entries');
	$filter_output = '';
	
	$log = file($_GET['log']);
	$text = '<h2>'.htmlspecialchars($_GET['log']).'</h2>';
	if ($message) $text .= '<p class="error">'.$message.'</p>'."\n";
	$my_uri = parse_url('http://www.example.org'.$_SERVER['REQUEST_URI']);
	parse_str($my_uri['query'], $my_query);
	$filters_set = (!empty($my_query['filter']) ? $my_query['filter'] : array());
	unset($my_query['filter']);
	$my_uri = $my_uri['path'].'?'.http_build_query($my_query);
	
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
				AND $_GET['filter'][$index] == $value))
				? true : false;
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
		$text = '<div class="zzfilter">'."\n";
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

	
	$text .= '<form action="" method="POST">';
	$text .= '<table class="data"><thead><tr>
		<th>[]</th>
		<th>'.zz_text('Date').'</th>
		'.($group ? '<th>'.zz_text('Last Date').'</th>' : '').'
		<th>'.zz_text('Type').'</th>
		<th>'.zz_text('Level').'</th>
		<th>'.zz_text('Message').'</th>
		<th>'.zz_text('User').'</th>
		'.($group ? '<th>'.zz_text('Frequency').'</th>' : '').'
		</thead>'."\n"
		.'<tbody>'."\n";
	$i = 0;
	$j = 0;
	$dont_highlight_levels = array('Notice', 'Warning');
	$content = false;
	foreach ($log as $index => $line) {
		$type = '';
		$user = '';
		$date = '';
		$level = '';
		
		$line = trim($line);
		// get date
		if (substr($line, 0, 1) == '[' AND substr($line, 21, 1) == ']') {
			$date = substr($line, 1, 20);
			$line = substr($line, 23);
		}

		// get user
		if (substr($line, -1) == ']' AND strstr($line, '[')) {
			$user = substr($line, strrpos($line, '[')+1, -1);
			$user = explode(' ', $user);
			if (count($user) > 1 AND substr($user[0], -1) == ':') {
				array_shift($user); // get rid of User: or translations of it
			}
			$user = implode(' ', $user);
			$line = substr($line, 0, strrpos($line, '['));
		}
		
		$tokens = explode(' ', $line);

		if ($tokens AND in_array($tokens[0], $filters['type'])) {
			$type = array_shift($tokens);
			$level = array_shift($tokens);
			if (substr($level, -1) == ':') $level = substr($level, 0, -1);
			else $level .= ' '.array_shift($tokens);
			if (substr($level, -1) == ':') $level = substr($level, 0, -1);
		}
		if (!empty($_GET['filter'])) {
			if (!empty($_GET['filter']['type'])) {
				if ($type != $_GET['filter']['type']) {
					$i++;
					unset($log[$index]);
					continue;
				}
			}
			if (!empty($_GET['filter']['level'])) {
				if ($level != $_GET['filter']['level']) {
					$i++;
					unset($log[$index]);
					continue;
				}
			}
		}

		if (!$user AND in_array($type, array('zzform', 'zzwrap')))
			$user = array_pop($tokens);
		
		if ($level AND !in_array($level, $dont_highlight_levels))
			$level = '<p class="error">'.$level.'</p>';
		
		$line = implode(' ', $tokens);
		// get rid of long lines with zero width space (&#8203;) - &shy; does
		// not work at least in firefox 3.6 with slashes
		$line = str_replace(';', ';&#8203;', $line);
		$line = str_replace('&', '&#8203;&', $line);
		$line = str_replace('/', '/&#8203;', $line);
		$line = str_replace('=', '=&#8203;', $line);
		$line = str_replace('%', '&#8203;%', $line);
		$line = str_replace('-at-', '&#8203;-at-', $line);
		// htmlify links
		if (strstr($line, 'http:/&#8203;/&#8203;') OR strstr($line, 'https:/&#8203;/&#8203;')) {
			$line = preg_replace_callback('~(\S+):/&#8203;/&#8203;(\S+)~', 'zz_maintenance_make_url', $line);
		}
		$line = str_replace(',', ', ', $line);

		if (!$group) {
			$text .= '<tr class="'.($j & 1 ? 'uneven' : 'even').'">'
				.'<td><label for="line'.$i.'" class="blocklabel"><input type="checkbox" name="line['
					.$i.']" id="line'.$i.'"></label></td>'
				.'<td>'.$date.'</td>'
				.'<td>'.$type.'</td>'
				.'<td>'.$level.'</td>'
				.'<td>'.$line.'</td>'
				.'<td>'.$user.'</td>'
				.'</tr>'."\n";
		} else {
			if (empty($output[$line])) {
				$output[$line] = array(
					'date_begin' => $date,
					'line' => '<td>'.$type.'</td>'
						.'<td>'.$level.'</td>'
						.'<td>'.$line.'</td>',
					'user' => array($user),
					'i' => array($i)
				);
			} else {
				$output[$line]['i'][] = $i;
				$output[$line]['date_end'] = $date;
				if (!in_array($user, $output[$line]['user']))
					$output[$line]['user'][] = $user;
			}
		}
		$content = true;
		$i++;
		$j++;
		unset($log[$index]);
	}
	if (!$content)
		$text .= '<tr><td colspan="6">'.zz_text('No lines').'</td></tr>'."\n";
	elseif ($group) {
		$j = 0;
		foreach ($output as $line) {
			$text .= '<tr class="'.($j & 1 ? 'uneven' : 'even').'">'
				.'<td><label for="line'.$j.'" class="blocklabel"><input type="checkbox" name="line['
					.implode(',', $line['i']).']" id="line'.$j.'"></label></td>'
				.'<td>'.$line['date_begin'].'</td>'
				.'<td>'.((!empty($line['date_end']) AND $line['date_end'] != $line['date_begin'])
					? $line['date_end']: '').'</td>'
				.$line['line']
				.'<td>'.implode(', ', $line['user']).'</td>'
				.'<td>'.count($line['i']).'</td>'
				.'</tr>'."\n";
			$j++;
		}
	}
	$text .= '</tbody></table>'."\n";
	$text .= '<input type="submit" value="'.zz_text('Delete selected lines').'">';
	$text .= '</form>';
	return $text;
}

function zz_maintenance_make_url($array) {
	$link = '<a href="'.str_replace('&#8203;', '', $array[0]).'">'.$array[0].'</a>'; 
	return $link;
}

function zz_delete_line_from_file($file, $lines) {
	
	// check if file exists ans is writable
	if (!is_writable($file))
		return sprintf(zz_text('File %s is not writable.'), $file);

	$content = file($file);
	foreach ($lines as $line) {
		$line = explode(',', $line);
		foreach ($line as $no) unset($content[$no]);
	}

	// open file for writing
	if (!$handle = fopen($file, 'w+'))
		return sprintf(zz_text('Cannot open %s for writing.'), $file);

	foreach($content as $line)
		fwrite($handle, $line);

	fclose($handle);
	return sprintf(zz_text('%s lines deleted.'), count($lines));
}

?>