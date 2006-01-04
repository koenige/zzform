<?php 


function delete_file($filename) {
	echo '<form method="POST" action="files">';
	show_file($filename);
	echo '<input type="hidden" name="delete" value="'.$filename.'">';
	echo '<input type="submit" value="Delete this File">';
	echo '</form>';
}

function show_file($filename) {
	$meta = read_meta($_SERVER['DOCUMENT_ROOT'].$filename);
	$meta = show_meta($meta);
	echo '<table>';
	echo '<tr>';
	echo '<td><a href="'.$filename.'">'.$filename.'</a></td>';;
	echo '<td>'.$meta['display'].'</td>';;
	echo '</tr>';
	echo '</table>';

}

function read_meta($filename) {
	$fd = fopen($filename, "r");
	$contents = fread ($fd, filesize ($filename));
	preg_match('/\/Info ([0-9]+) ([0-9])+ /i', $contents, $info);
	$search = preg_match("/\s".$info[1].' '.$info[2].' obj.*?<<(.+?)>>.*?endobj/si', $contents, $results);
	//echo '<pre>'.$results[1].'</pre><br>';

	//$meta_fields = explode('/', $results[1]);
	//foreach ($meta_fields as $meta) {
	//	echo $meta.'<br>';
	$results[1] = str_replace('()', '( )', $results[1]); // damit Suchstring auch bei () positiv (wg. \))
	$results[1] = str_replace('<>', '< >', $results[1]); // damit Suchstring auch bei () positiv (wg. \))
	preg_match_all('/(.+?)\s*?([\(<].*?[^\\\][>\)])/s', $results[1], $fields);
	foreach (array_keys($fields[1]) as $key) {
		//echo $fields[0][$key].'<br>'.$fields[1][$key].'<br>'.$fields[2][$key].'<br><br>';
		if ($fields) {
			$field_name = trim($fields[1][$key]);
			$field_name = substr($field_name, 1, strlen($field_name)-1);
			$field_value = $fields[2][$key];
			//$field_value = chop($field_value);
			if (substr($field_value, 0, 1) == '(') {
				// not decoded
				$field_value = substr($field_value, 1, strlen($field_value)-2);
				$field_value = stripslashes($field_value);
				//$field_value = str_replace ('\\', '', $field_value);
				// Problem mit \374 = ü (uuml) - wie umrechenbar?
			} elseif (substr($field_value, 0, 1) == '<') {
				// unicode
				$field_value = strtoupper(substr($field_value, 1, strlen($field_value)-2));
				if (substr($field_value,0,4) == 'FEFF' && substr($field_value,4,4) != '0000') {
					// UTF-16, big-endian
					$decoded = false;
					while ($field_value = substr($field_value, 4,strlen($field_value)-4)) {
						$decoded .= '&#'.hexdec(substr($field_value,0,4)).';';
					}
					$field_value = $decoded;
				}
			}
			$meta['pdf'][$field_name] = $field_value;
		}
		//echo $field_name.': '.$field_value.'<br>';
	}
	fclose($fd);
	return $meta;
}

function show_form($action) {
?>
<form enctype="multipart/form-data" method="POST" action="<?php echo $action; ?>">
<input type="file" name="pdf" id="pdf">
<input type="submit">
</form>

<!-- <input type="hidden" name="MAX_FILE_SIZE" value="900000"> -->
<?php 

}

function meta2db($meta) {
	$meta2db['CreationDate'] = 'doc_date';
	$meta2db['Title'] = 'doc_title';
	$meta2db['Keywords'] = 'doc_keywords';
	$meta2db['Subject'] = 'doc_description';
	$meta2db['Author'] = 'doc_author';

	$values['db'] = '';
	foreach (array_keys($meta['pdf']) as $my_meta) {
		if (isset($meta2db[$my_meta])) {
			if ($my_meta == 'CreationDate') {
				$date = substr($meta['pdf'][$my_meta], 2, 8);
				$meta['pdf'][$my_meta] = substr($date,0,4).'-'.substr($date,4,2).'-'.substr($date,6,2);
			}
			$values['db'] .= '&amp;value['.$meta2db[$my_meta].']='.$meta['pdf'][$my_meta];
		}	 
	}
	return $values;
}

function show_filename($date, $title) {
	global $level;
	$my_dir = '/doc/'.substr(str_replace('-', '/', $date),0,7);
	include_once($level.'/inc/umlaut-convert.inc.php');
	$filename = $my_dir.'/'.forceFilename($title).'.pdf';
	return $filename;
}

function show_files() {	
	global $dir;
	global $filename;
	echo '<h2>Available Documents</h2>';
	$mydir = $dir['admin'].'/incoming/';
	if ($handle = @opendir($_SERVER['DOCUMENT_ROOT'].$mydir)) {
		while ($file = readdir($handle)) {
			echo '<table>';
			if (substr($file,0,1) != '.') {
				$fileurl = $dir['admin'].'/incoming/'.$file;
				echo '<tr';
				if ($fileurl == $filename) echo ' class="even"';
				echo '>';
				echo '<td><strong><a href="'.$fileurl.'">'.$file.'</a></strong></td>';
				$meta = read_meta($_SERVER['DOCUMENT_ROOT'].$dir['admin'].'/incoming/'.$file);
				echo '<td>';
				$meta = show_meta($meta);
				echo $meta['display'];
				echo '</td>';
				echo '<td>';
				$values = meta2db($meta);
				echo '<a href="docs?mode=add'.$values['db'].'&amp;file='.urlencode($fileurl).'">Add to database</a>';
				echo ' | <a href="files?mode=delete&amp;file='.urlencode($fileurl).'">Delete File</a>';
				echo ' | <a href="files?mode=upload&amp;file='.urlencode($fileurl).'">Replace File</a>';
				echo '</td>';
				echo '</tr>';
			
			}
			echo '</table>';
		}
	}



}

// Probleme:

// accord.pdf

/*
	$meta['pdf']_fields = array( 'ModDate', 'CreationDate', 'Title', 'Creator', 'Producer', 'Author', 'Subject', 'Keywords' );
	foreach ($meta['pdf']_fields as $meta_field) {
		preg_match_all('/\/'.$meta_field.' *\((.*?)\)/', $contents, $result);
		$max = (count($result[1]));
		$max--;
		if ($result[1]) $meta['pdf'][$meta_field] = $result[1][$max];
		else {
			preg_match_all('/\/'.$meta_field.' *<(.*?)\>/', $contents, $result);
			if ($result[1]) {
				$meta['pdf'][$meta_field.'-Unicode'] = $result[1][0];
				if (substr($result[1][0],0,4) == 'FEFF' && substr($result[1][0],4,4) != '0000') {
					// UTF-16, big-endian
					$decoded = false;
					while ($result[1][0] = substr($result[1][0], 4,strlen($result[1][0])-4)) {
						$decoded .= '&#'.hexdec(substr($result[1][0],0,4));
					}
					$meta['pdf'][$meta_field] = $decoded;
				} else {
					echo substr($result[1][0],0,4);
				}
			}
		}
	}
*/
function show_meta($meta) {
	if (isset($meta['pdf'])) {
		$meta['display'] = '<h2>Meta Data Found in PDF Document</h2>';
		$meta['display'] .= '<table>'."\n";
		$meta['display'] .= '<thead>';
		$meta['display'] .= '<tr> <th>Field</th> <th>Values</th> </tr>'."\n";
		$meta['display'] .= '</thead><tbody>';
		foreach (array_keys($meta['pdf']) as $my_meta) {
			$meta['display'] .= '<tr>';
			$meta['display'] .= '<th>'.$my_meta.'</th>';
			$meta['display'] .= '<td>'.nl2br($meta['pdf'][$my_meta]).'</td>';
			$meta['display'] .= '</tr>'."\n";
		}
		$meta['display'] .= '</tbody>';
		$meta['display'] .= '</table>';
	}
	return $meta;
}

//return $meta;

/*

	Date
	
	D:20040704181434Z00'00'

*/

//echo pdf_get_value($pdf, "textx", 0);

/*

XMP-Eigenschaften

4484 0 obj<</Length 3515/Type/Metadata/Subtype/XML>>stream

// remove the / from following lines

</?xpacket begin='Ôªø' id='W5M0MpCehiHzreSzNTczkc9d'?/>
</?adobe-xap-filters esc="CRLF"?/>

<x:xmpmeta xmlns:x='adobe:ns:meta/' x:xmptk='XMP toolkit 2.9.1-13, framework 1.6'>

<rdf:RDF xmlns:rdf='http://www.w3.org/1999/02/22-rdf-syntax-ns#' xmlns:iX='http://ns.adobe.com/iX/1.0/'>

<rdf:Description rdf:about='uuid:24335586-57f8-4444-bab4-60f9a58355ed' xmlns:pdf='http://ns.adobe.com/pdf/1.3/' pdf:Producer='Acrobat Distiller 6.0 (Windows)' pdf:Keywords='Dynamische PDF-Generierung'></rdf:Description>

<rdf:Description rdf:about='uuid:24335586-57f8-4444-bab4-60f9a58355ed' xmlns:xap='http://ns.adobe.com/xap/1.0/' xap:ModifyDate='2004-08-20T10:24:24Z' xap:CreateDate='2004-08-20T10:23:47Z' xap:CreatorTool='FrameMaker 7.0'></rdf:Description>

<rdf:Description rdf:about='uuid:24335586-57f8-4444-bab4-60f9a58355ed' xmlns:xapMM='http://ns.adobe.com/xap/1.0/mm/' xapMM:DocumentID='uuid:c6eb7433-5aa6-46d9-b0d3-21335df1141a'/>

// DC

<rdf:Description rdf:about='uuid:24335586-57f8-4444-bab4-60f9a58355ed' xmlns:dc='http://purl.org/dc/elements/1.1/' dc:format='application/pdf'><dc:title><rdf:Alt><rdf:li xml:lang='x-default'>PDFlib-Referenzmanual</rdf:li></rdf:Alt></dc:title><dc:creator><rdf:Seq><rdf:li>PDFlib GmbH</rdf:li></rdf:Seq></dc:creator><dc:description><rdf:Alt><rdf:li xml:lang='x-default'>API-Referenz f√ºr PDFlib, einer Bibliothek zur dynamischen Erstellung von PDF</rdf:li></rdf:Alt></dc:description></rdf:Description>

</rdf:RDF>

</x:xmpmeta>
</?xpacket end='w'?/>

endstream
endobj


obj
<< 
/Producer (Acrobat Distiller 4.05 for Windows)
/Creator ()
/ModDate (D:20000505144200+02'00')
/Title (General performance data	Chapter 2\n)
/CreationDate (D:20000417134549)
>> 
endobj

1 0 obj
<<
/Creator <FEFF0052006500670069006F002D0041006E0074007200610067002D00350039002D00550074007A00650072006100740068002E0064006F00630020002D0020004D006900630072006F0073006F0066007400200057006F00720064>
/CreationDate (D:20031013121717)
/Title <FEFF0052006500670069006F002D0041006E0074007200610067002D00350039002D00550074007A00650072006100740068002E0064006F0063>
/Author <FEFF0072006700650072007A00650072>
/Producer (Acrobat PDFWriter 5.0 f¸r Windows NT)
>>

*/
?>