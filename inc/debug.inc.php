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

/** HTML output of debugging information 
 * 
 *	start of function
 *		if ($zz_conf['modules']['debug']) $zz_debug_time_this_function = microtime_float();
 *	end of function
 *		if ($zz_conf['modules']['debug']) zz_debug(__FUNCTION__, $zz_debug_time_this_function);
 * @param $function(string)	name of function (__FUNCTION__)
 * @param $function_time(string)	microtime at which function started
 * @param $marker(string)	optional: marker to define position in function
 * @param $sql(string)		optional: SQL query
 * @return (string)			HTML output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
 function zz_debug($function, $function_time, $marker = false, $sql = false) {
	global $zz_conf;
	global $zz_debug;
	global $zz_timer;

	$end = microtime_float();
	$debug = array();
	$debug['time'] = $end - $zz_timer;
	$debug['time_used'] = $end - $function_time;
	$debug['memory'] = memory_get_usage();
	$debug['function'] = $function;
	$debug['marker'] = $marker;
	$debug['sql'] = $sql;

	// HTML output
	$zz_debug['output'][] = $debug;

	// Time output for Logfile
	$zz_debug['time'][] = $function.'='.round($debug['time_used'],4);
}

/** HTML output of debugging information 
 * 
 * @param $zzdebug(array)	$zz_debug['output'] as returned from zz_debug()
 * @return (string)			HTML output
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 */
function zz_debug_htmlout($zz_debug) {
	$output = '<h1>'.zz_text('Debug Information').'</h1>';
	$output .= '<table class="data"><thead>'."\n".'<tr><th>'.zz_text('Time').'</th><th>'
		.zz_text('Mem').'</th><th>'.zz_text('Function').'</th><th>'
		.zz_text('Marker').'</th><th>'.zz_text('SQL').'</th></tr>'."\n"
		.'</thead><tbody>';
	$i = 0;
	foreach ($zz_debug['output'] as $row) {
		$output .= '<tr class="'.($i & 1 ? 'even': 'uneven').'">';
		foreach ($row as $key => $val) {
			if ($key != 'time_used') $output .= '<td>';
			$output .= $val;
			if ($key != 'time') $output .= '</td>';
			else $output .= '<br>';
		}
		$output .= '</tr>'."\n";
		$i++;
	}
	$output .= '</tbody></table>'."\n";
	$output .= zz_text('Memory peak usage').': '.memory_get_peak_usage();
	return $output;
}

?>