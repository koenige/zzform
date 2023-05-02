<?php 

/**
 * zzform
 * Debugging module
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2010, 2014, 2016-2017, 2022-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * HTML output of debugging information 
 * 
 * @param string $marker	optional: marker to define position in function or to call a sub function
 * @param string $text		optional: SQL query or function name
 * @param int $id			optional: Random ID for function, allows to log in different functions
 * @return void
 */
function zz_debug($marker = false, $text = false, $id = false) {
	static $zz_debug;
	static $process_id;
	if (empty($process_id)) $process_id = wrap_random_hash(6);
	if (!$id) $id = $process_id;
	
	switch ($marker) {
		case '_output': return zz_debug_htmlout($zz_debug[$id]['output']);
		case '_clear': unset($zz_debug[$id]); return;
		case '_time': return zz_debug_time($zz_debug[$id]['time'], $text);
	}

	// initialize
	if (empty($zz_debug[$id])) {
		$zz_debug[$id] = [];					// debug module
		$zz_debug[$id]['timer'] = microtime(true);	// debug module
	}

	$time = microtime(true);
	// initialize function parameters
	if (str_starts_with($marker, 'start')) {
		$current = [
			'function' => $text,
			'time_start' => $time
		];
		$zz_debug[$id]['function'][] = $current;
	} elseif (str_starts_with($marker, 'end')) {
		// set current function to last element and remove it
		$current = array_pop($zz_debug[$id]['function']);
	} else {
		// set current function to last element and keep it
		$current = end($zz_debug[$id]['function']);
	}
	if (!is_array($current)) {
		$current = [
			'function' => 'unknown',
			'time_start' => $time
		];
	}
	if ($marker === 'start') return true; // no output, just initialize

	$debug = [];
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
 * @param array $data
 * @return string
 * @todo use wrap_template()
 */
function zz_debug_htmlout($data) {
	$output = '<h1>'.zz_text('Debug Information').'</h1>';
	$output .= '<table class="data debugtable"><thead>'."\n".'<tr><th>'
		.zz_text('Time').'</th><th>'
		.zz_text('Mem').'</th><th>'.zz_text('Function').'</th><th>'
		.zz_text('Marker').'</th><th>'.zz_text('SQL').'</th></tr>'."\n"
		.'</thead><tbody>';
	$i = 0;
	foreach ($data as $row) {
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
 * @param array $time
 * @param array $return (optional, $ops['return']);
 */
function zz_debug_time($time, $return = []) {
	if (!wrap_setting('zzform_debug_time')) return;

	$rec = '';
	if ($return) $rec = $return[0]['action'].' '.$return[0]['table'].' '
		.$return[0]['id_value'].' (mem pk: '.memory_get_peak_usage().') ';
	
	zz_error_log([
		'msg_dev' => '[DEBUG] %stime: %s',
		'msg_dev_args' => [$rec, implode(' ', $time)],
		'level' => E_USER_NOTICE
	]);
	zz_error();
}
