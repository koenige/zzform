<?php

$text_N = 'N';
$text_E = 'E';
$text_S = 'S';
$text_W = 'W';

function print_editform($form_coords, $coords) {

	global $text_N;
	global $text_E;
	global $text_S;
	global $text_W;

	$form_coords_split = explode("[", $form_coords);
	$form_coords_ll = $form_coords_split[1];

	$output = '';
	$output .= '<input type="text" size="3" maxlength="3" name="'.$form_coords.'][deg]" id="'.$form_coords.'][deg]" value="'.$coords[$form_coords_ll]['deg'].'"> &deg;';
	$output .= '<input type="text" size="3" maxlength="3" name="'.$form_coords.'][min]" id="'.$form_coords.'][min]" value="'.$coords[$form_coords_ll]['min'].'"> \'';
	$output .= '<input type="text" size="4" maxlength="4" name="'.$form_coords.'][sec]" id="'.$form_coords.'][sec]" value="'.$coords[$form_coords_ll]['sec'].'"> &quot;';

	if ($form_coords_ll == "lat") {
		$orientations = array('+' => 'N', '-' => 'S');
	} elseif ($form_coords_ll == "lon") {
		$orientations = array('+' => 'E', '-' => 'W');
	} else {
		$output.= "Programmer's fault. Variable must have lat or lon in its name";
	}
	$text_pos = "text_".$orientations['+'];
	$text_neg = "text_".$orientations['-'];

	$output.= '<select name="'.$form_coords.'][orientation]" id="'.$form_coords.'][orientation]" size="1">'."\n";
	$output.= '<option '; 
	if ($coords[$form_coords_ll]['orientation'] == $orientations['+']) {
		$output.= "selected ";
	}
	$output.= 'value="+">'.$$text_pos.'</option>';
	$output.= '<option '; 
	if ($coords[$form_coords_ll]['orientation'] == $orientations['-']) {
		$output.= "selected ";
	}
	$output.= 'value="-">'.$$text_neg.'</option>
</select>';
	return $output;
}
?>