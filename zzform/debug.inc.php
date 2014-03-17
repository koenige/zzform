<?php 

/**
 * zzform
 * Debugging module
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2010, 2014 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Default settings for debug module
 */
function zz_debug_config() {
	$default['debug']			= false;	// turn on/off debugging mode
	$default['debug_time'] 		= false;
	zz_write_conf($default);
}

/**
 * HTML output of debugging information 
 * 
 *	start of function
 *		if ($zz_conf['modules']['debug']) {
 *			global $zz_debug;
 *			$zz_debug['function'] = __FUNCTION__;
 *			$zz_debug['function_time'] = microtime_float();
 *		}
 *	end of function
 *		if ($zz_conf['modules']['debug']) zz_debug();
 * @param string $marker	optional: marker to define position in function
 * @param string $text		optional: SQL query or function name
 * @param int $id			optional: Random ID for function, allows to log in different functions
 * @global array $zz_debug
 *		'function' (name of function __FUNCTION__), 'function_time' (microtime 
 *		at which function started), 'timer', ...
 * @return string			HTML output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_debug($marker = false, $text = false, $id = false) {
	global $zz_debug;
	global $zz_conf;

	if (!$id) $id = $zz_conf['id'];

	// initialize
	if (empty($zz_debug[$id])) {
		$zz_debug[$id] = array();					// debug module
		$zz_debug[$id]['timer'] = microtime_float();	// debug module
	}

	$time = microtime_float();
	// initialize function parameters
	if (substr($marker, 0, 5) === 'start') {
		$current = array(
			'function' => $text,
			'time_start' => $time
		);
		$zz_debug[$id]['function'][] = $current;
	} elseif (substr($marker, 0, 3) === 'end') {
		// set current function to last element and remove it
		$current = array_pop($zz_debug[$id]['function']);
	} else {
		// set current function to last element and keep it
		$current = end($zz_debug[$id]['function']);
	}
	if ($marker === 'start') return true; // no output, just initialize

	$debug = array();
	$debug['time'] = $time - $zz_debug[$id]['timer'];
	$debug['time_used'] = $time - $current['time_start'];
	$debug['memory'] = memory_get_usage();
	$debug['function'] = $current['function'];
	$debug['marker'] = $marker;
	$debug['sql'] = $text;

	// HTML output
	$zz_debug[$id]['output'][] = $debug;

	// Time output for Logfile
	$zz_debug[$id]['time'][] = $current['function'].'='.round($debug['time_used'],4);
}

/**
 * HTML output of debugging information 
 * 
 * @global array $zz_debug	$zz_debug['output'] as returned from zz_debug()
 * @return string			HTML output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_debug_htmlout($id = false) {
	global $zz_debug;
	if (!$id) {
		global $zz_conf;
		$id = $zz_conf['id'];
	}

	$output = '<h1>'.zz_text('Debug Information').'</h1>';
	$output .= '<table class="data debugtable"><thead>'."\n".'<tr><th>'
		.zz_text('Time').'</th><th>'
		.zz_text('Mem').'</th><th>'.zz_text('Function').'</th><th>'
		.zz_text('Marker').'</th><th>'.zz_text('SQL').'</th></tr>'."\n"
		.'</thead><tbody>';
	$i = 0;
	foreach ($zz_debug[$id]['output'] as $row) {
		$output .= '<tr class="'.($i & 1 ? 'even': 'uneven').'">';
		foreach ($row as $key => $val) {
			if ($key === 'time') {
				$val = '<dl><dt>'.$val.'</dt>';
			} elseif ($key === 'time_used') {
				if ($val > 0.1) $class = 'error';
				elseif ($val > 0.01) $class = 'warning';
				else $class = '';
				if ($class) $val = '<span class="'.$class.'">'.$val.'</span>';
				$val = '<dd>'.$val.'</dd></dl>';
			}
			if ($key !== 'time_used') $output .= '<td>';
			$output .= $val;
			if ($key !== 'time') $output .= '</td>';
		}
		$output .= '</tr>'."\n";
		$i++;
	}
	$output .= '</tbody></table>'."\n";
	$output .= zz_text('Memory peak usage').': '.memory_get_peak_usage();
	return $output;
}

/**
 * Logs time from different debug-markers in logfile
 * 
 * @param array $return (optional, $ops['return']);
 * @global array $zz_error
 * @global array $zz_debug
 */
function zz_debug_time($return = array()) {
	global $zz_error;
	global $zz_debug;
	global $zz_conf;

	$rec = '';
	if ($return) $rec = $return[0]['action'].' '.$return[0]['table'].' '
		.$return[0]['id_value'].' (mem pk: '.memory_get_peak_usage().') ';
	
	$zz_error[] = array(
		'msg_dev' => '[DEBUG] '.$rec.'time: '.implode(' ', $zz_debug[$zz_conf['id']]['time']),
		'level' => E_USER_NOTICE
	);
	zz_error();
}

/**
 * Return current Unix timestamp with microseconds as float
 * = microtime(true) in PHP 5
 *
 * @return float
 * @deprecated
 * @todo move into compatiblity.inc.php
 */
function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function zz_debug_unset($id = false) {
	global $zz_debug;
	global $zz_conf;

	if (!$id) $id = $zz_conf['id'];
	unset($zz_debug[$id]);
}

?>