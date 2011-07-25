/*

// zzform
// (c) Gustaf Mossakowski, <gustaf@koenige.org> 2009-2011
// javascript to be executed at end of document

*/

/*	adds &nbsp; for hierarchical form options
	Safari unfortunately does not support modifications to style of forms
*/
var isSafari = navigator.userAgent.search(/Safari.+/);
if (isSafari != -1) {
	var optionfields = document.getElementsByTagName('option');
	for (var i=0; i<optionfields.length; i++) {
		if (optionfields[i].className.substring(0, 5) == 'level') {
			var len = optionfields[i].className.substring(5, 6);
			var level = '';
			for (var k=0; k<len; k++) {
				level = level + String.fromCharCode(160) + String.fromCharCode(160) + String.fromCharCode(160) + String.fromCharCode(160);
			}
			optionfields[i].text = level + optionfields[i].text;
		}
	}
}

var zzform = document.getElementById("zzform");
/**
 * sets all checkboxes inside #zzform to checked
 */
function zz_set_checkboxes() {
	var zz_checkboxes = document.getElementsByTagName("input");
	for (var i = 0 ; i < zz_checkboxes.length ; i++){
		if (zz_checkboxes[i].type == "checkbox"){
			zz_checkboxes[i].checked = true;
		}
	}
	zz_checkboxes = null;
}
