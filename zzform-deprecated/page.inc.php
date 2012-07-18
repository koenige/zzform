<?php

/**
 * zzform
 * Page wrap function
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 *	zzform_all()	(optional call, if page['head'] and ['foot'] shall
 *					be incorporated)
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2010 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 * @deprecated
 */


global $zz_page;	// Page (Layout) variables
global $zz;			// Table description

/**
 * zzform shortcut, includes some page parameters
 *
 * @param array $glob_vals optional variables that must be declared globally
 * @global array $zz
 * @global array $zz_conf
 * @global array $zz_page
 * @global array $zz_setting
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @deprecated Use zzbricks brick_forms() instead.
 */
function zzform_all($glob_vals = false) {
//	Die folgenden globalen Definitionen der Variablen sind nur noetig, wenn man wie
//	hier die darauffolgenden vier Zeilen in einer Funktion zusammenfassen will
	global $zz;			// Table description
	global $zz_conf;	// Config variables
	global $zz_page;	// Page (Layout) variables
	global $zz_setting;	// Settings
	if ($glob_vals)		// Further variables, may be set by user
		if (is_array($glob_vals))
			foreach ($glob_vals as $glob_val)
				global $$glob_val;
		else
			global $$glob_vals;
	$zz_conf['show_output'] = false; // do not show output as it will be included after page head
	
//	Zusammenbasteln der Seite
	$ops = zzform($zz);				// Funktion aufrufen
	if ($ops['mode'] == 'export') {
		foreach ($ops['headers'] as $index) {
			foreach ($index as $bool => $header) {
				header($header, $bool);
			}
		}
		echo $ops['output'];		// Output der Funktion ausgeben
	} else {
		if (empty($zz_page['title'])) $zz_page['title'] = $zz_conf['title'];
		include $zz_page['head'];	// Seitenkopf ausgeben, teilw. mit Variablen aus Funktion
		echo $ops['output'];		// Output der Funktion ausgeben
		include $zz_page['foot'];	// Seitenfuss ausgeben
	}
}

?>