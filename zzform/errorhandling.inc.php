<?php 

/**
 * zzform
 * error handling functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 * 
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * error logging for zzform()
 * will display error messages for the current user on HTML webpage
 * depending on settings, will log errors in logfile and/or send errors by mail
 *
 * @global array $zz_conf
 * @return bool false if no error was detected, true if error was detected
 */
function zz_error() {
	global $zz_conf;
	
	if (!wrap_setting('error_handling'))
		wrap_setting('error_handling', 'output');
	$user = [];
	$admin = [];
	$log = [];
	$message = [];
	$return = zz_error_exit() ? 'exit' : 'html';
	
	$logged_errors = zz_error_log();
	if (!$logged_errors) {
		zz_error_exit(($return === 'exit') ? true : false);
		return false;
	}
	
	$log_encoding = wrap_log_encoding();
	
	// browse through all errors
	foreach ($logged_errors as $key => $error) {
		if (!is_numeric($key)) continue;
		
		// initialize error_level
		if (empty($error['level'])) $error['level'] = '';
		if (empty($error['status'])) $error['status'] = 200;
		
		// log POST data?
		if (!isset($error['log_post_data'])) {
			$error['log_post_data'] = true;
		}
		if (!wrap_setting('error_log_post')) $error['log_post_data'] = false;
		elseif (empty($_POST)) $error['log_post_data'] = false;

		// page http status
		if ($error['status'] !== 200)
			wrap_static('page', 'status', $error['status']);

		// initialize and translate error messages
		if (!empty($error['msg'])) {
			// allow 'msg' to be an array to translate each sentence individually
			if (!is_array($error['msg'])) $error['msg'] = [$error['msg']];
			foreach ($error['msg'] as $index => $msg) {
				if (is_array($msg)) {
					$mymsg = [];
					foreach ($msg as $submsg) {
						$mymsg[] = wrap_text(trim($submsg));
					}
					$error['msg'][$index] = implode(' ', $mymsg);
				} else {
					$error['msg'][$index] = wrap_text(trim($msg));
				}
			}
			if (empty($error['html'])) {
				$error['msg'] = implode(' ', $error['msg']);
			} else {
				$mymsg = [];
				foreach ($error['html'] as $index => $html) {
					if (array_key_exists($index, $error['msg'])) {
						$mymsg[] = sprintf($html, $error['msg'][$index]);
					} else {
						$mymsg[] = $html;
					}
				}
				$error['msg'] = implode(' ', $mymsg);
			}
		} else {
			$error['msg'] = '';
		}
		if (!empty($error['msg_args'])) {
			// flatten msg_args because msg is concatenated already
			$args = [];
			foreach ($error['msg_args'] as $arg) {
				if (is_array($arg)) $args = array_merge($args, $arg);
				else $args[] = $arg;
			}
			$error['msg'] = vsprintf($error['msg'], $args);
		}
		// @todo think about translating dev messages for administrators
		// in a centrally set (not user defined) language
		$error['msg_dev'] = $error['msg_dev'] ?? '';
		if (is_array($error['msg_dev'])) $error['msg_dev'] = implode(' ', $error['msg_dev']);
		$error['msg_dev'] = trim($error['msg_dev']);
		if (!empty($error['msg_dev_args'])) {
			$error['msg_dev'] = vsprintf($error['msg_dev'], $error['msg_dev_args']);
		}

		$user[$key] = false;
		$admin[$key] = false;

		if (!empty($error['db_errno'])) {
			$error['msg'] = zz_db_error($error['db_errno'])
				.($error['msg'] ? '<br>'.$error['msg'] : '');
		}

		switch ($error['level']) {
		case E_USER_ERROR:
			if (!$error['msg']) $user[$key] .= wrap_text('An error occured.'
				.' We are working on the solution of this problem. '
				.'Sorry for your inconvenience. Please try again later.');
			$level = 'error';
			// get out of this function immediately:
			$return = 'exit';
			break;

		default:
		case E_USER_WARNING:
			$level = 'warning';
			break;

		case E_USER_NOTICE:
			$level = 'notice';
			break;
		}

		// User output
		$user[$key] .= $error['msg'];

		// Admin output
		if ($error['msg_dev']) 
			$admin[$key] .= $error['msg_dev'].'<br>';
		if (!empty($error['db_msg'])) 
			$admin[$key] .= $error['db_msg'].':<br>';
		if (!empty($error['query'])) {
			// since we have an SQL query, we do not need roughly the same
			// information from the POST data
			$error['log_post_data'] = false;
			$admin[$key] .= preg_replace("/\s+/", " ", $error['query']).'<br>';
		}
		if ($admin[$key] AND $error['msg'])
			$admin[$key] = $error['msg'].'<br>'.$admin[$key];
		elseif (!$admin[$key])
			$admin[$key] = $error['msg'];

		// Log output
		$log[$key] = trim($admin[$key]);
		// preserve &lt; for some reasons (Value incorrect in field: ... 
		// (String "<a href=" is not allowed).)
		$log[$key] = str_replace('&lt;', '&amp;lt;', $log[$key]);
		$log[$key] = html_entity_decode($log[$key], ENT_QUOTES, $log_encoding);
		$log[$key] = str_replace('<br>', "\n\n", $log[$key]);
		$log[$key] = str_replace('&lt;br class="nonewline_in_mail">', "; ", $log[$key]);
		$log[$key] = strip_tags($log[$key]);
		$log[$key] = str_replace('&lt;', '<', $log[$key]);
		// reformat log output
		if (wrap_setting('error_log['.$level.']') AND wrap_setting('log_errors')) {
			wrap_log('['.wrap_setting('request_uri').'] '.$log[$key],  $level, 'zzform');
			if ($error['log_post_data']) wrap_log('postdata', 'notice', 'zzform');
		}
		// Mail output
		if (in_array($level, wrap_setting('error_mail_level')))
			$message[$key] = $log[$key];

		// Heading
		if (!$user[$key]) {
			unset($user[$key]); // there is nothing, so nothing will be shown
		} elseif ($level === 'error' OR $level === 'warning') {
			$user[$key] = '<strong>'.wrap_text('Attention!').'</strong> '.$user[$key];
		}
		if ($admin[$key] AND ($level === 'error' OR $level === 'warning')) {
			$admin[$key] = '<strong>'.wrap_text('Attention!').'</strong> '.$admin[$key];
		}
	}
	foreach ($admin as $line) {
		$zz_conf['int']['ops_error_msg'][] = strip_tags($line);
	}

	// mail errors if said to do so
	$mail = [];
	switch (wrap_setting('error_handling')) {
	case 'mail':	
		if (!wrap_setting('error_mail_to')) break;
		if (!count($message)) break;
		$mail['message'] = wrap_text('The following error(s) occured in project %s:', ['values' => wrap_setting('project')]);
		$mail['message'] .= "\n\n".implode("\n\n", $message);
		$mail['message'] = html_entity_decode($mail['message'], ENT_QUOTES, $log_encoding);		
		$mail['message'] .= "\n\n-- \nURL: ".wrap_setting('host_base').wrap_setting('request_uri')
			."\nIP: ".wrap_setting('remote_ip')
			.(!empty($_SERVER['HTTP_USER_AGENT']) ? "\nBrowser: ".$_SERVER['HTTP_USER_AGENT'] : '');		
		if ($username = wrap_username())
			$mail['message'] .= sprintf("\nUser: %s", $username);

		$mail['subject'] = wrap_text('Database access error');
		if (!wrap_setting('mail_subject_prefix'))
			$mail['subject'] = '['.wrap_setting('project').'] '.$mail['subject'];
		$mail['to'] = wrap_setting('error_mail_to');
		$mail['queue'] = true;
		wrap_mail($mail);
		break;
	case 'output':
		$user = $admin;
		break;
	case 'save_mail':
		if (!count($mail)) break;
		$zz_conf['int']['error'][] = $mail;
		break;
	}

	// Went through all errors, so we do not need them anymore
	zz_error_log(false);
	
	zz_error_exit(($return === 'exit') ? true : false);
	zz_error_out($user);

	return true;
}

/**
 * log an error message
 *
 * @param array $msg
 *		array for each error:
 * 		mixed 'msg' message(s) that always will be sent back to browser
 *		array 'msg_args' vsprintf arguments for msg
 * 		string 'msg_dev' message that will be sent to browser, log and mail, 
 * 			depending on settings
 *		array 'msg_dev_args' vsprintf arguments for msg_dev
 * 		int 'level' for error level: currently implemented:
 * 			- E_USER_ERROR: critical error, action could not be finished,
 *				unrecoverable error
 * 			- E_USER_WARNING: error, we need some extra user input
 * 			- E_USER_NOTICE: some default settings will be used because user 
 * 				input was not enough; e. g. date formats etc.
 * 		int 'db_errno' database: error number
 * 		string 'db_msg' database: error message
 * 		string 'query' SQL-Query
 * 		bool 'log_post_data': true (default); false: do not log POST
 * @static array $errors
 * @return array
 */
function zz_error_log($msg = []) {
	static $errors = [];
	if ($msg === false) $errors = [];
	elseif ($msg) $errors[] = $msg;
	return $errors;
}

/**
 * set exit variable to signal the script to stop
 *
 * @param mixed $set
 *	true: exit script;
 *	false: do not exit script
 *	'check': print out current status (default)
 * @return bool
 */
function zz_error_exit($set = 'check') {
	static $exit = false;
	if ($set === true) $exit = true;
	elseif ($set === false) $exit = false;
	return $exit;
}

/**
 * save error message output for later
 *
 * @param mixed
 *	array: add to output array
 *	bool: false deletes or initializes the output
 * @return array
 */
function zz_error_out($data = []) {
	static $output = [];
	if ($data === false) $output = [];
	elseif ($data) $output = array_merge($output, $data);
	return $output;
}

/**
 * outputs error messages
 *
 * @return string
 */
function zz_error_output() {
	$text = zz_error_out();
	if (!$text) return '';
	$text = '<div class="error">'.implode('<br><br>', $text).'</div>'."\n";
	zz_error_out(false);
	return $text;
}

/**
 * log validation errors
 *
 * @param string $key
 *		'msg', 'msg_args', 'msg_dev', 'msg_dev_args', log_post_data' => log for this key
 *		'delete' => delete all values
 * @param mixed $value
 *		bool, array, string
 * @return array
 */
function zz_error_validation_log($key = false, $value = []) {
	static $errors = [];
	if (!$errors OR $key === 'delete') {
		$errors = [
			'msg' => [], 'msg_args' => [], 'msg_dev' => [],
			'msg_dev_args' => [], 'log_post_data' => false
		];
		if ($key === 'delete') $key = false;
	}
	if ($key) {
		if (is_bool($value)) $errors[$key] = $value;
		elseif (is_array($value)) $errors[$key] = array_merge($errors[$key], $value);
		else $errors[$key][] = $value;
	}
	return $errors;
}

/**
 * output and log validation error messages
 *
 * @return void
 */
function zz_error_validation() {
	$errors = zz_error_validation_log();
	if (!$errors['msg']) return false;
	if (wrap_static('zzform_output', 'batch_mode')) return false;

	// user error message, visible to everyone
	// line breaks \n important for mailing errors
	$errors['html'][] = "<p>%s</p>\n<ul>";
	foreach ($errors['msg'] as $msg) {
		$errors['html'][] = "<li>%s</li>\n";
	}
	$errors['html'][] = "</ul>\n";
	array_unshift($errors['msg'], 'These problems occured:');
	// if we got wrong values entered, put this into a developer message
	$dev_msgs = $errors['msg_dev'];
	unset($errors['msg_dev']);
	foreach ($dev_msgs as $msg_dev) {
		$errors['msg_dev'][] = 'Field name: %s / ';
		$errors['msg_dev'][] = $msg_dev;
	}
	$errors['level'] = E_USER_NOTICE;
	zz_error_log($errors);
	zz_error_validation_log('delete');
}

/**
 * log errors in $ops['error'] if zzform_multi() was called, because errors
 * won't be shown on screen in this mode
 *
 * @param array $errors = $ops['error']
 * @global array $zz_conf
 * @return array $errors
 */
function zz_error_multi($errors) {
	if (!wrap_static('zzform_output', 'batch_mode')) return $errors;

	$logged_errors = zz_error_log();
	foreach ($logged_errors as $index => $error) {
		if (empty($error['msg_dev'])) continue;
		if (!empty($error['msg_dev_args'])) {
			$error['msg_dev'] = vsprintf($error['msg_dev'], $error['msg_dev_args']);
		}
		$errors[] = $error['msg_dev'];
	}
	$validation_errors = zz_error_validation_log();
	if ($validation_errors['msg']) {
		foreach ($validation_errors['msg'] as $index => $msg)
			$validation_errors['msg'][$index] = strip_tags($msg);
		if (!empty($validation_errors['msg_args'])) {
			$glue = 'SOME_NEVER_APPEARING_SEQUENCE_IN_ERROR_MSG';
			$msgs = implode($glue, $validation_errors['msg']);
			$msgs = vsprintf($msgs, $validation_errors['msg_args']);
			$validation_errors['msg'] = explode($glue, $msgs);
		}
		$errors = array_merge($errors, $validation_errors['msg']);
	}
	return $errors;
}

/**
 * Generate error message if POST is too big
 *
 * @return bool
 */
function zz_trigger_error_too_big() {
	global $zz_conf;
	
	if (empty($zz_conf['int']['post_too_big'])) return true;
	zz_error_log([
		'msg' => [
			'Transfer failed. Probably you sent a file that was too large.',
			'<br>',
			'Maximum allowed filesize is %s.',
			' – You sent: %s data.'
		],
		'msg_args' => [
			wrap_bytes(zz_upload_max_filesize()),
			wrap_bytes($_SERVER['CONTENT_LENGTH'])
		],
		'level' => E_USER_NOTICE
	]);
	return false;
}
