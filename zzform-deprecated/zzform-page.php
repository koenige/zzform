<?php

/**
 * zzform
 * include deprecated zzform_all() function
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2004-2010 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

//	Required Variables, global so they can be used by the including script after
//	processing as well

global $zz_conf;

/**
 * include deprecated page function, it's recommended to use zzbrick instead
 * @deprecated
 */
if (file_exists($zz_conf['dir'].'/page.inc.php'))
	require_once $zz_conf['dir'].'/page.inc.php';
elseif (file_exists($zz_conf['dir'].'/inc/page.inc.php'))
	require_once $zz_conf['dir'].'/inc/page.inc.php';

?>