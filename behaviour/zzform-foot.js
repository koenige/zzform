/*
 * zzform
 * JavaScript to be executed at end of document
 *
 * Part of »Zugzwang Project«
 * http://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2014, 2018, 2020-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (typeof zzformForm !== 'undefined') zzformRecordForm();

/**
 * initialize all functions that are in use for the record form
 */
function zzformRecordForm() {
	zzformButtons();
	zzformOptionFields();
	zzformCheckBoxes();
	zzformRadios();
}

/**
 *	adds &nbsp; for hierarchical form options
 *	Safari and since 2018 Firefox as well
 *  unfortunately does not support modifications to style of forms
 */
function zzformOptionFields() {
	var optionfields = zzformForm.getElementsByTagName('option');
	for (var i=0; i<optionfields.length; i++) {
		if (optionfields[i].className.substring(0, 5) == 'level') {
			var len = optionfields[i].className.substring(5, 6);
			var level = '';
			for (var k=0; k<len; k++) {
				level = level + String.fromCharCode(160).repeat(4);
			}
			optionfields[i].text = level + optionfields[i].text;
		}
	}
}

/**
 * sets all checkboxes inside #zzform to checked
 * @param bool state true: checked, false: unchecked
 * @param string name: just select/deselect elements with this name
 */
function zz_set_checkboxes(state, name) {
	var zzform = document.getElementById("zzform");
	var inputs = zzform.getElementsByTagName("input");
	for (var i = 0; i < inputs.length; i++) {
		if (inputs[i].type == "checkbox") {
			if (name) {
				if (inputs[i].name == name) {
					inputs[i].checked = state;
				}
			} else {
				inputs[i].checked = state;
			}
		}
	}
	inputs = null;
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

/**
 * show only a part of a very long text in list view
 */
function zzformMoreTexts() {
	var moretexts = document.getElementsByClassName("moretext");
	if (!moretexts.length) return;
	for (var i = 0; i < moretexts.length; i++) {
		moretexts[i].className = "moretext moretext_hidden";
		moretexts[i].onclick = function() {
			if (this.className == "moretext moretext_hidden") {
				this.className = "moretext";
			} else {
				this.className = "moretext moretext_hidden";
			}
		};
	}
}
zzformMoreTexts();


/**
 * for a subrecord set that is grouped, allow to check/uncheck all entries
 * inside the group
 */
function zzformCheckBoxes() {
	var checkboxes = zzformForm.getElementsByClassName('js-zz_set_group');
	if (checkboxes.length) {
		for (i = 0; i < checkboxes.length; i++) {
			var new_checkbox = document.createElement('input');
			new_checkbox.type = "checkbox";
			(function(counter){
				new_checkbox.addEventListener('click', function(){
					var checkboxList = checkboxes[counter].getElementsByTagName('input');
					for (j = 0; j < checkboxList.length; j++) {
						if (checkboxList[j].type == 'checkbox') {
							checkboxList[j].checked = this.checked;
						}
					}
				}, false);
   			})(i);
			checkboxes[i].insertBefore(new_checkbox, checkboxes[i].firstChild);
		}
	}
}

/**
 * check a radio button if a corresponding input is filled
 */
function zzformRadios() {
	var zz_inputs = zzformForm.getElementsByClassName('js-checkable');
	for (i = 0; i < zz_inputs.length; i++) {
		(function(counter){
			zz_inputs[i].addEventListener('keydown', function(){zz_selectRadio(counter)}, false);
		})(i);
	}
}

function zz_selectRadio(counter) {
	var checkbox = zzformForm.getElementById(zz_inputs[counter].getAttribute('data-check-id'));
	if (!checkbox.checked) checkbox.checked = 'checked';
}

/*
 * check if filters are too long and hide them
 *
 * @param string action
 * @param string field_id
 * @return void
 */
function zz_filters(action, field_id) {
	var filters = document.getElementsByClassName('zzfilter_dropdown');
	if (!filters.length) return;
	for (i = 0; i < filters.length; i++) {
		filter_id = filters[i].id;
		if (action == 'init') {
			(function(filter_id){
				filters[i].addEventListener('click', function() {zz_filters('toggle', filter_id); });
			})(filter_id);
		}
		var j = 0;
		if (field_id == filter_id || action == 'init') {
			var js_collapse = filters[i].getElementsByClassName('js_collapse');
			var js_expand = filters[i].getElementsByClassName('js_expand');
			if (action == 'init' || js_collapse[0].style.display == 'inline') {
				js_collapse[0].style.display = 'none';
				js_expand[0].style.display = 'inline';
				while (dd = document.getElementById(filter_id + '-' + j)) {
					if (!dd.className) dd.style.display = 'none';
					j++;
				}
			} else {
				js_collapse[0].style.display = 'inline';
				js_expand[0].style.display = 'none';
				while (dd = document.getElementById(filter_id + '-' + j)) {
					if (!dd.className) dd.style.display = 'block';
					j++;
				}
			}
		}
	}
}
zz_filters('init');

/**
 * replaces zzform ID element (or other) and HTML title
 *
 * @param object page (page.title, page.html)
 * @param bool scrollTop: scroll to top of page after update of contents (default yes)
 */
function zzformReplacePage(page, scrollTop = true) {
	document.title = page.title;

	if (page.noFragment) {
		document.open();
        document.write(page.html);
        document.close();
	} else {
		var replaceContent = zzformDiv();
		replaceContent.parentNode.innerHTML = page.html;
		replaceContent = zzformDiv();
		// activate scripts
		var allScripts = replaceContent.getElementsByTagName('script');
		for (i = 0; i < allScripts.length; i++) {
			var g = document.createElement('script');
			var s = allScripts[i];
			g.text = s.innerHTML;
			s.parentNode.insertBefore(g, s);
			s.remove();
		}
	}

	// move to top of page
	if (scrollTop) scroll(0,0);
}

/**
 * reload page after post
 *
 * @param object event
 */
function zzformLoadPage(event){
	try {
		var page = JSON.parse(event.target.responseText);
	} catch (e) {
		var page = {
			'html': event.target.responseText,
			'title': null,
			'url': event.target.responseURL,
			'noFragment': true
		}
	}

	if (page.url && page.url !== zzformActionURL) {
		if (history.pushState) {
			window.history.pushState(page, page.title, page.url);
		} else {
			window.location.replace(page.url);
			return false;
		}
	}
	if (zzformSubmitButton) {
		zzformReplacePage(page, false);
	} else {
		zzformReplacePage(page);
	}
	zzformRecordForm();
}

/**
 * get the element where all zzform related content is in
 */
function zzformDiv() {
	return document.getElementById('%%% setting zzform_replace_div %%%');
}

/**
 * save the current page for popstate event when going back
 */
function zzformSavePage() {
	if (history.pushState) {
		var old = {
			title: window.title,
			html: '<div id="zzform">' + zzformDiv().innerHTML + '</div>',
			url: window.location + ''
		}
		window.history.replaceState(old, old.title, old.url);
	}
}

window.onpopstate = function(event){
	if (event.state) {
		zzformReplacePage(event.state);
	}
};

/**
 * send name of buttons for adding/removing sub records or detail records
 *
 */
function zzformButtons() {
	var subrecordButtons = zzformForm.querySelectorAll('input[formnovalidate]');
	for (j = 0; j < subrecordButtons.length; j++) {
		subrecordButtons[j].onclick = function(ev) {
			zzformSubmitButton = this.name;
		};
	}
}
