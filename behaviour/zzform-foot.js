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

/**
 * sets all checkboxes inside #zzform to checked
 */
function zz_set_checkboxes() {
	var zzform = document.getElementById("zzform");
	var checkboxes = zzform.getElementsByTagName("input");
	for (var i = 0; i < checkboxes.length; i++) {
		if (checkboxes[i].type == "checkbox") {
			checkboxes[i].checked = true;
		}
	}
	checkboxes = null;
}

function zz_toggle_elements() {
	var zzform = document.getElementById("zzform");
	var tr = zzform.getElementsByTagName("tr");
	for (var i = 0; i < tr.length; i++) {
		if (tr[i].className === "toggle_td") {
			tr[i].className = "hide_td"
			var td = tr[i].childNodes[0];
			var button = document.createElement('p');
			button.className = 'desc';
			button.innerHTML = '<a href="#" onclick="zz_toggle_element(this); return false;">'
				+ 'Show/Hide' + '</a>';
			td.appendChild(button);
		}
	}
}

function zz_toggle_element(myelement) {
	var parenttd = myelement.parentNode;
	var parentth = parenttd.parentNode;
	var parenttr = parentth.parentNode;
	if (parenttr.className === "toggle_td")
		parenttr.className = "hide_td";
	else
		parenttr.className = "toggle_td";
}

zz_toggle_elements();
