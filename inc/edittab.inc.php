<?php

/*
	zzform Scripts

	function zz_display_table
		displays table with records (limited by zz_conf['limit'])
		displays add new record, record navigation (if zz_conf['limit'] = true)
		and search form below table
	
	(c) Gustaf Mossakowski <gustaf@koenige.org> 2004-2006

*/

function zz_display_table(&$zz, $zz_conf, &$zz_error, $zz_var, $zz_lines) {
	global $text;

	// Display
	// remove elements from table which shall not be shown
	
	foreach ($zz['fields'] as $field)
		if (empty($field['hide_in_list'])) $table_query[] = $field;

	//
	// Table head
	//

	if ($zz_conf['this_limit']) $zz['sql'].= ' LIMIT '.($zz_conf['this_limit']-$zz_conf['limit']).', '.$zz_conf['limit'];
	$result = mysql_query($zz['sql']);
	if ($result) $count_rows = mysql_num_rows($result);
	else {
		$count_rows = 0;
		$zz['output'].= zz_error($zz_error = array(
			'mysql' => mysql_error(), 
			'query' => $zz['sql'], 
			'msg' => $text['error-sql-incorrect']));
	}
	if ($result && $count_rows) {
		$zz['output'].= '<table class="data">';
		$zz['output'].= '<thead>'."\n";
		$zz['output'].= '<tr>';
		foreach ($table_query as $field) {
			$zz['output'].= '<th'.check_if_class($field, (!empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : '')).'>';
			if ($field['type'] != 'calculated' && $field['type'] != 'image' && isset($field['field_name'])) { //  && $field['type'] != 'subtable'
				$zz['output'].= '<a href="';
				if (isset($field['display_field'])) $order_val = $field['display_field'];
				else $order_val = $field['field_name'];
				$uri = addvar($_SERVER['REQUEST_URI'], 'order', $order_val);
				$order_dir = 'asc';
				if (str_replace('&amp;', '&', $uri) == $_SERVER['REQUEST_URI']) {
					$uri.= '&amp;dir=desc';
					$order_dir = 'desc';
				}
				$zz['output'].= $uri;
				$zz['output'].= '" title="'.$text['order by'].' '.strip_tags($field['title']).' ('.$text[$order_dir].')">';
			}
			$zz['output'].= $field['title'];
			if ($field['type'] != 'calculated' && $field['type'] != 'image' && isset($field['field_name']))
				$zz['output'].= '</a>';
			$zz['output'].= '</th>';
		}
		if ($zz_conf['edit'] OR $zz_conf['access'] == 'show' OR $zz_conf['view'])
			$zz['output'].= ' <th class="editbutton">'.$text['action'].'</th>';
		if (isset($zz_conf['details']) && $zz_conf['details']) 
			$zz['output'].= ' <th class="editbutton">'.$text['detail'].'</th>';
		$zz['output'].= '</tr>';
		$zz['output'].= '</thead>'."\n";
		$zz['output'].= '<tbody>'."\n";
	} else {
		$zz_conf['show_list'] = false;
		$zz['output'].= '<p>'.$text['table-empty'].'</p>';
	}

	//
	// Table body
	//	
	if ($result && $count_rows) {
		$z = 0;
		while ($line = mysql_fetch_assoc($result)) {
			$zz['output'].= '<tr class="'.($z & 1 ? 'uneven':'even').
				(($z+1) == $count_rows ? ' last' : '').'">'; //onclick="Highlight();"
			$id = '';
			foreach ($table_query as $field) {
				$zz['output'].= '<td'.check_if_class($field, (!empty($zz_var['where'][$zz['table']]) ? $zz_var['where'][$zz['table']] : '')).'>';
			//	if there's a link, glue parts together
				$link = false;
				if (isset($field['link']))
					if (is_array($field['link']))
						$link = show_link($field['link'], $line).(empty($field['link_no_append']) ? $line[$field['field_name']] : '');
					else
						$link = $field['link'].$line[$field['field_name']];
				if ($link)
					$link = '<a href="'.$link.'"'.(!empty($field['link_target']) ? ' target="'.$field['link_target'].'"' : '').'>';
			//	go for type of field!
				switch ($field['type']) {
					case 'calculated':
						if ($field['calculation'] == 'hours') {
							$diff = 0;
							foreach ($field['calculation_fields'] as $calc_field)
								if (!$diff) $diff = strtotime($line[$calc_field]);
								else $diff -= strtotime($line[$calc_field]);
							if ($diff < 0) $zz['output'] .= '<em class="negative">';
							$zz['output'].= gmdate('H:i', $diff);
							if ($diff < 0) $zz['output'] .= '</em>';
							if (isset($field['sum']) && $field['sum'] == true) {
								if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
								$sum[$field['title']] += $diff;
							}
						} elseif ($field['calculation'] == 'sum') {
							$my_sum = 0;
							foreach ($field['calculation_fields'] as $calc_field)
								$my_sum += $line[$calc_field];
							$zz['output'].= $my_sum;
							if (isset($field['sum']) && $field['sum'] == true) {
								if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
								$sum[$field['title']] .= $my_sum;
							}
						} elseif ($field['calculation'] == 'sql')
							$zz['output'].= $line[$field['field_name']];
						break;
					case 'image':
					case 'upload_image':
						if (isset($field['path']))
							if ($img = show_image($field['path'], $line))
								$zz['output'].= ($link ? $link : '').$img.($link ? '</a>' : '');
						break;
					case 'subtable':
						// Subtable
						if (isset($field['display_field'])) $zz['output'].= htmlchars($line[$field['display_field']]);
						break;
					case 'url':
					case 'mail':
						$zz['output'].= '<a href="'.($field['type'] == 'mail' ? 'mailto:' : '')
							.$line[$field['field_name']].'">';
						if (!empty($field['display_field']))
							$zz['output'].= htmlchars($line[$field['display_field']]);
						elseif ($field['type'] == 'url' && strlen($line[$field['field_name']]) > $zz_conf['max_select_val_len'])
							$zz['output'].= substr(htmlchars($line[$field['field_name']]), 0, $zz_conf['max_select_val_len']).'...';
						else
							$zz['output'].= htmlchars($line[$field['field_name']]);
						$zz['output'].= '</a>';
						break;
					case 'id':
						$id = $line[$field['field_name']];
					default:
						if ($link) $zz['output'].= $link;
						if (!empty($field['display_field'])) $zz['output'].= htmlchars($line[$field['display_field']]);
						else {
							if (isset($field['factor']) && $line[$field['field_name']]) $line[$field['field_name']] /=$field['factor'];
							if ($field['type'] == 'unix_timestamp') $zz['output'].= date('Y-m-d H:i:s', $line[$field['field_name']]);
							elseif ($field['type'] == 'select' && !empty($field['set']))
								$zz['output'].= str_replace(',', ', ', $line[$field['field_name']]);
							elseif ($field['type'] == 'select' && !empty($field['enum']) && !empty($field['enum_title'])) { // show enum_title instead of enum
								foreach ($field['enum'] as $mkey => $mvalue)
									if ($mvalue == $line[$field['field_name']]) $zz['output'] .= $field['enum_title'][$mkey];
							} elseif ($field['type'] == 'date') $zz['output'].= datum_de($line[$field['field_name']]);
							elseif (isset($field['number_type']) && $field['number_type'] == 'currency') $zz['output'].= waehrung($line[$field['field_name']], '');
							elseif (isset($field['number_type']) && $field['number_type'] == 'latitude' && $line[$field['field_name']]) {
								$deg = dec2dms($line[$field['field_name']], '');
								$zz['output'].= $deg['latitude_dms'];
							} elseif (isset($field['number_type']) && $field['number_type'] == 'longitude' &&  $line[$field['field_name']]) {
								$deg = dec2dms('', $line[$field['field_name']]);
								$zz['output'].= $deg['longitude_dms'];
							} else $zz['output'].= nl2br(htmlchars($line[$field['field_name']]));
						}
						if ($link) $zz['output'].= '</a>';
						if (isset($field['sum']) && $field['sum'] == true) {
							if (!isset($sum[$field['title']])) $sum[$field['title']] = 0;
							$sum[$field['title']] += $line[$field['field_name']];
						}
					}
					if (isset($field['unit'])) 
					/* && $line[$field['field_name']]) does not work because of calculated fields*/ 
						$zz['output'].= '&nbsp;'.$field['unit'];	
					$zz['output'].= '</td>';
			}
			if ($zz_conf['edit'] OR $zz_conf['access'] == 'show' OR $zz_conf['view']) {
				if ($zz_conf['edit']) 
					$zz['output'].= '<td class="editbutton"><a href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=edit&amp;id='.$id.$zz['extraGET'].'">'.$text['edit'].'</a>';
				elseif ($zz_conf['access'] == 'show' OR $zz_conf['view'])
					$zz['output'].= '<td class="editbutton"><a href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=show&amp;id='.$id.$zz['extraGET'].'">'.$text['show'].'</a>';
				if ($zz_conf['delete']) $zz['output'].= '&nbsp;| <a href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=delete&amp;id='.$id.$zz['extraGET'].'">'.$text['delete'].'</a>';
				if (isset($zz_conf['details'])) {
					$zz['output'].= '</td><td class="editbutton">';
					$zz['output'].= show_more_actions($zz_conf['details'], $zz_conf['details_url'],  $zz_conf['details_base'], $zz_conf['details_target'], $id, $line);
				}
				$zz['output'].= '</td>';
			}
			$zz['output'].= '</tr>'."\n";
			$z++;
		}
	}
	if ($result && $count_rows) $zz['output'].= '</tbody>'."\n";
	
	//
	// Table footer
	//
	
	if ($zz_conf['tfoot'] && isset($z)) {
		$zz['output'].= '<tfoot>'."\n";
		$zz['output'].= '<tr>';
		foreach ($table_query as $field) {
			if ($field['type'] == 'id') $zz['output'].= '<td class="recordid">'.$z.'</td>';
			elseif (isset($field['sum']) AND $field['sum'] == true) {
				$zz['output'].= '<td>';
				if (isset($field['calculation']) AND $field['calculation'] == 'hours')
					$sum[$field['title']] = hours($sum[$field['title']]);
				$zz['output'].= $sum[$field['title']];
				if (isset($field['unit'])) $zz['output'].= '&nbsp;'.$field['unit'];	
				$zz['output'].= '</td>';
			}
			else $zz['output'].= '<td>&nbsp;</td>';
		}
		$zz['output'].= '<td class="editbutton">&nbsp;</td>';
		$zz['output'].= '</tr>'."\n";
		$zz['output'].= '</tfoot>'."\n";
	}

	if ($result && $count_rows) $zz['output'].= '</table>'."\n";

	//
	// Buttons below table (add, record nav, search)
	//

	if ($zz['mode'] != 'add' && $zz_conf['add'] && $zz_conf['show_list'])
		$zz['output'].= '<p class="add-new bottom-add-new"><a accesskey="n" href="'.$zz_conf['url_self'].$zz_var['url_append'].'mode=add'.$zz['extraGET'].'">'.$text['add_new_record'].'</a></p>';
	if ($zz_lines == 1) $zz['output'].= '<p class="totalrecords">'.$zz_lines.' '.$text['record total'].'</p>'; 
	elseif ($zz_lines) $zz['output'].= '<p class="totalrecords">'.$zz_lines.' '.$text['records total'].'</p>';
	$zz['output'].= zz_limit($zz_conf['limit'], $zz_conf['this_limit'], $count_rows, $zz['sql'], $zz_lines, 'body');	// NEXT, PREV Links at the end of the page
	//$zz_conf['links'] = zz_limit($zz_conf['limit'], $count_rows, $zz['sql'], $zz_lines, 'body');	// NEXT, PREV Links at the end of the page
	if ($zz_conf['search'] == true) 
		if ($zz_lines OR isset($_GET['q'])) // show search form only if there are records as a result of this query; q: show search form if empty search result occured as well
			$zz['output'].= zz_search_form($zz_conf['url_self'], $zz['fields'], $zz['table']);
	
}

?>