<?php

function print_editform($form_coords, $coords) {

	global $text_N;
	global $text_E;
	global $text_S;
	global $text_W;

	$form_coords_split = explode("_", $form_coords);
	#$form_coords_array_name = "coords_".$form_coords_split[0];
	#$form_coords_array = $$form_coords_array_name;
	$form_coords_ll = $form_coords_split[1];

?>
<input type="text" size="3" maxlength="3" name="<?php echo $form_coords; ?>_deg" id="<?php echo $form_coords; ?>_deg" value="<?php echo $coords[$form_coords_ll]['deg'];?>"> &deg;
<input type="text" size="3" maxlength="3" name="<?php echo $form_coords; ?>_min" id="<?php echo $form_coords; ?>_min" value="<?php echo $coords[$form_coords_ll]['min'];?>"> '
<input type="text" size="4" maxlength="4" name="<?php echo $form_coords; ?>_sec" id="<?php echo $form_coords; ?>_sec" value="<?php echo $coords[$form_coords_ll]['sec'];?>"> &quot;
<?php 

	if ($form_coords_ll == "lat") {
		$orientations = array('+' => 'N', '-' => 'S');
	} elseif ($form_coords_ll == "lon") {
		$orientations = array('+' => 'E', '-' => 'W');
	} else {
		echo "Programmer's fault. Variable must have lat or lon in its name";
	}

	$text_pos = "text_".$orientations['+'];
	$text_neg = "text_".$orientations['-'];

?>
<select name="<?php echo $form_coords; ?>_orientation" id="<?php echo $form_coords; ?>_orientation" size="1">
	<option <?php 
	
	if ($coords[$form_coords_ll]['orientation'] == $orientations['+']) {
		echo "selected ";
	}
	
	 ?>value="+"><?php echo $$text_pos; ?></option>
	<option <?php 
	
	if ($coords[$form_coords_ll]['orientation'] == $orientations['-']) {
		echo "selected ";
	}
	
	 ?>value="-"><?php echo $$text_neg; ?></option>
</select>

<?php
}
?>