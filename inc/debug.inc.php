<?php 

// Project Zugzwang
// (c) Gustaf Mossakowski, <gustaf@koenige.org> 2009
// zzform Scripts: debug module


/*	About this file

	- default variables
	- list of functions in this file
		- zz_debug
		- zz_debug_htmlout
*/

// Default variables for debug module
$zz_default['debug']			= false;	// turn on/off debugging mode
$zz_default['debug_time'] 		= false;

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
 * @global array $zz_debug
 *		'function' (name of function __FUNCTION__), 'function_time' (microtime 
 *		at which function started), ...
 * @return string			HTML output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_debug($marker = false, $text = false) {
	global $zz_conf;
	global $zz_debug;
	global $zz_timer;
	
	$time = microtime_float();
	// initialize function parameters
	if (substr($marker, 0, 5) == 'start') {
		$current = array(
			'function' => $text,
			'time_start' => $time
		);
		$zz_debug['function'][] = $current;
	} elseif (substr($marker, 0, 3) == 'end') {
		// set current function to last element and remove it
		$current = array_pop($zz_debug['function']);
	} else {
		// set current function to last element and keep it
		$current = end($zz_debug['function']);
	}
	if ($marker == 'start') return true; // no output, just initialize

	$debug = array();
	$debug['time'] = $time - $zz_timer;
	$debug['time_used'] = $time - $current['time_start'];
	$debug['memory'] = memory_get_usage();
	$debug['function'] = $current['function'];
	$debug['marker'] = $marker;
	$debug['sql'] = $text;

	// HTML output
	$zz_debug['output'][] = $debug;

	// Time output for Logfile
	$zz_debug['time'][] = $zz_debug['function'].'='.round($debug['time_used'],4);
}

/**
 * HTML output of debugging information 
 * 
 * @param array $zz_debug	$zz_debug['output'] as returned from zz_debug()
 * @return string			HTML output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_debug_htmlout($zz_debug) {
	$output = '<h1>'.zz_text('Debug Information').'</h1>';
	$output .= '<table class="data debugtable"><thead>'."\n".'<tr><th>'
		.zz_text('Time').'</th><th>'
		.zz_text('Mem').'</th><th>'.zz_text('Function').'</th><th>'
		.zz_text('Marker').'</th><th>'.zz_text('SQL').'</th></tr>'."\n"
		.'</thead><tbody>';
	$i = 0;
	foreach ($zz_debug['output'] as $row) {
		$output .= '<tr class="'.($i & 1 ? 'even': 'uneven').'">';
		foreach ($row as $key => $val) {
			if ($key == 'time') $val = '<dl><dt>'.$val.'</dt>';
			elseif ($key == 'time_used') $val = '<dd>'.$val.'</dd></dl>';
			if ($key != 'time_used') $output .= '<td>';
			$output .= $val;
			if ($key != 'time') $output .= '</td>';
		}
		$output .= '</tr>'."\n";
		$i++;
	}
	$output .= '</tbody></table>'."\n";
	$output .= zz_text('Memory peak usage').': '.memory_get_peak_usage();
	return $output;
}

?>