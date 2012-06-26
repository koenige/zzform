<?php 

/**
 * zzform
 * for zzform backwards compatibility, to be removed ASAP
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 */


if (!empty($zz_conf['edit_only'])) $zz_conf['access'] = 'edit_only';
if (!empty($zz_conf['add_only'])) $zz_conf['access'] = 'add_only';
if (!empty($zz_conf['show'])) $zz_conf['access'] = 'show';

if (isset($zz_conf['list']) && $zz_conf['list'] == false)
	$zz_conf['access'] = 'add_only';


// PHP 4:
// Source: http://de.php.net/http_build_query
// mqchen at gmail dot com
// 03-Feb-2007 09:27
if(!function_exists('http_build_query')) {
    function http_build_query($data, $prefix = null, $sep = '', $key = '') {
        $ret = array();
            foreach((array)$data as $k => $v) {
                $k = urlencode($k);
                if(is_int($k) && $prefix != null) {
                    $k = $prefix.$k;
                };
                if(!empty($key)) {
                    $k = $key."[".$k."]";
                };

                if(is_array($v) || is_object($v)) {
                    array_push($ret, http_build_query($v, "", $sep, $k));
                }
                else {
                    array_push($ret, $k."=".urlencode($v));
                };
            };

        if(empty($sep)) {
            $sep = ini_get("arg_separator.output");
        };

        return implode($sep, $ret);
    };
};

// constants since PHP 4.3.0!
if (!defined('UPLOAD_ERR_FORM_SIZE'))
	define('UPLOAD_ERR_FORM_SIZE', 1);

if (!defined('UPLOAD_ERR_INI_SIZE'))
	define('UPLOAD_ERR_INI_SIZE', 2);

if (!defined('UPLOAD_ERR_PARTIAL'))
	define('UPLOAD_ERR_PARTIAL', 3);

if (!defined('UPLOAD_ERR_NO_FILE'))
	define('UPLOAD_ERR_NO_FILE', 4);

if (!defined('UPLOAD_ERR_OK'))
	define('UPLOAD_ERR_OK', 0);

?>