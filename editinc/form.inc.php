<?php

/*

	$form['style'] = 'table' -- Style of form, e. g. table
	$form['title'] = 'Contact' -- Title of form, also used to identify form if more
		than one form is used per page
	$form['action']
	$form['buttons']['reset']
	$form['buttons']['submit']
	
	$elements[1]['title']
	$elements[1]['field_name']
	$elements[1]['type']

*/

// todo

/*

	size
	maxlength
	...
	hidden-value with form-title to check whether is correct form


*/

// language dependent values

$text['form'] = 'Formular';

// default values

if (!isset($form['action'])) $form['action'] = $_SERVER['PHP_SELF'];
if (!isset($form['method'])) $form['method'] = 'post';
if (!isset($form['display'])) $form['display'] = 'table';

// form output

echo '<form action="'.$form['action'].'" method="'.$form['method'].'">'."\n";
if (isset($form['legend'])) {
	echo '<fieldset>'."\n";
	echo '<legend>'.$form['legend'].'</legend>'."\n";
}
if ($form['display'] == 'table') {
	echo '<table summary="'.$text['form'].': '.$form['legend'].'"';
	if (isset($form['class'])) echo ' class="'.$form['class'].'"';
	echo '>'."\n";
}
foreach ($elements as $element) {
	// defaults
	if (!isset($element['title'])) $element['title'] = ucfirst($element['field_name']);
	if (!isset($element['type'])) $element['type'] = 'text';

	// output
	if ($form['display'] == 'table') echo "\t".'<tr>';
	if ($form['display'] == 'table') echo '<th>';
	if ($element['type'] != 'hidden') echo '<label for="'.$element['field_name'].'">';
	echo $element['title'];
	if ($element['type'] != 'hidden') echo '</label>';
	if ($form['display'] == 'table') echo '</th><td>';
	if ($element['type'] == 'text' OR $element['type'] == 'hidden') {
		echo '<input type="'.$element['type'].'" name="'.$element['field_name'].'"';
		if (isset($element['value'])) echo ' value="'.$element['value'].'"';
		echo '>';
	}
	if (isset($element['text'])) echo $element['text'];
	if ($form['display'] == 'table') echo '</td>';
	if ($form['display'] == 'table') echo '</tr>'."\n";
}
if ($form['display'] == 'table') echo "\t".'<tr><th>&nbsp;</th><td>';
foreach (array_keys($form['buttons']) as $button) {
	echo '<input type="'.$button.'" value="'.$form['buttons'][$button].'">';
} 
if ($form['display'] == 'table') echo '</td></tr>';
if ($form['display'] == 'table') echo '</table>'."\n";
foreach ($hidden_elements as $hidden_element) {
	echo '<input type="hidden" name="'.$hidden_element['field_name'].'" value="'.$hidden_element['value'].'">'."\n";
}
if (isset($form['legend'])) {
	echo '</fieldset>'."\n";
}
echo '</form>'."\n";


?>