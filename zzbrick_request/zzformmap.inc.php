<?php 

/**
 * zzform module
 * show map above list
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * output map above list
 * only in list-mode and if there are records
 *
 * @param array $params
 * @param array $settings
 * @return array
 */
function mod_zzform_zzformmap($params, $settings) {
	if (empty($settings['geo_map_html'])) return false;
	if ($settings['mode'] !== 'list_only') return false;
	if (!$settings['records_total']) return false;

	$type = $settings['geo_map_export'] ?? 'kml';
	$head_tpl = $settings['geo_map_head'] ?? 'map';

	// output depending on export format
	switch ($type) {
	case 'kml':
		// outputs map based on a corresponding KML file to the table output
		// (e. g. an OpenLayers map)
		// HTML output goes into {$template}.template.txt
		$map['kml_url'] = mod_zzform_zzformmap_url('kml');
		$page['extra']['onload'] = 'init()';
		break;
	case 'geojson':
		$map['geojson'] = mod_zzform_zzformmap_url('geojson');
		$page['text'] = wrap_template($settings['geo_map_html'], $map);
		break;
	default:
		return false;
	}
	$page['head'] = wrap_template($head_tpl, $map);
	return $page;
}

/**
 * get own zzform URL stripped from unnecessary parts for map export
 *
 * @param string $type type of export
 * @return string $map_url
 */
function mod_zzform_zzformmap_url($type = 'kml') {
	$url = parse_url(wrap_setting('request_uri'));
	$map_url = $url['path'];
	if (!empty($url['query'])) {
		parse_str($url['query'], $query);
		// set no limit, default for export is to show all records
		unset($query['limit']);
		unset($query['referer']);
		unset($query['delete']);
	}
	$query['export'] = $type;
	$map_url .= '?'.str_replace('&amp;', '&', http_build_query($query));
	return $map_url;
}
