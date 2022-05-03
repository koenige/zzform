/*
 * zzform
 * JavaScript to be executed at end of document
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/projects/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2009-2014, 2018, 2020-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (typeof zzformForm !== 'undefined') zzformRecordForm();
zzformRemoveSuggestions();
zzformSelections();
var zzformLoadedJS = [];
var zzformStart = true;

/**
 * initialize all functions that are in use for the record form
 */
function zzformRecordForm() {
	zzformButtons();
	zzformOptionFields();
	zzformCheckBoxes();
	zzformRadios();
	zzformAddDetails();
	zzformWmdEditor();
	zzformForm.addEventListener('submit', zzformSubmit);
}

/**
 *	remove existing auto suggestion divs
 */
function zzformRemoveSuggestions() {
	var classes = ['div.vxJS_autoSuggest'];
	for (var j = 0; j < classes.length; j++) {
		var items = document.querySelectorAll(classes[j]);
		for (var i = 0; i < items.length; i++) {
			items[i].remove();
		}
	}
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
					zzformCheckboxSelect(inputs[i]);
				}
			} else {
				inputs[i].checked = state;
				zzformCheckboxSelect(inputs[i]);
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
 * change class name of list elements if multi checkbox is selected
 */
function zzformSelections() {
	var selections = document.getElementsByName('zz_record_id[]');
	for (var i = 0; i < selections.length; i++) {
		selections[i].addEventListener('change', zzformCheckboxSelect);
	}
}

function zzformCheckboxSelect(event) {
	var checkbox = event.target ?  event.target :  event;
	var container = checkbox.closest('#zzform .data li');
	if (container === null) container = checkbox.closest('#zzform .data tr');
	if (container === null) return;
	if (checkbox.checked) {
		container.classList.add('selected');
	} else {
		container.classList.remove('selected'); 
	}
}

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

/**
 * show Edit/Add buttons, if there is an ID or not
 *
 */
function zzformAddDetails() {
	var addDetails = zzformForm.getElementsByClassName('zz_add_details_edit');
	for (var i = 0; i < addDetails.length; i++) {
		var select = addDetails[i].parentNode.getElementsByTagName('select');
		var newButton = addDetails[i].parentNode.getElementsByTagName('input');
		var display = 'none';
		if (select.length) {
			var options = select[0].getElementsByTagName('option');
			for (var j = 0; j < options.length; j++) {
				if (options[j].selected) {
					if (options[j].value) display = 'inline';
				}
			}
			select[0].addEventListener('change', zzformAddDetails);
		}
		if (display === 'inline') {
			addDetails[i].setAttribute('style', 'display: inline;');
			newButton[0].setAttribute('style', 'display: none;');
		} else {
			addDetails[i].setAttribute('style', 'display: none;');
			newButton[0].setAttribute('style', 'display: inline;');
		}
	}
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
 * submit a form with XHR, reducing size of data transmitted
 * and allowing to display upload progress bar
 */
async function zzformSubmit(event) {
	// XHR possible? if not, use normal HTML form mechanism
	try { ok = new XMLHttpRequest(); }
	catch (e) { }
	if (!ok) return;

	event.preventDefault();
	zzformSavePage();
	document.getElementById('zzform_submit').style = 'display: none;';
	document.getElementById('zzform_save').style = 'display: block;';

	var data = new FormData(zzformForm);
	data.append('zz_html_fragment', 1);
	if (zzformSubmitButton) {
		data.append(zzformSubmitButton, 1);
	}
	
	var xhr = new XMLHttpRequest();
	zzformUploadForm = document.getElementById('zzform_upload_progress');
	if (zzformUploadForm) {
		zzformUploadForm.style = 'display: block;';
		xhr.upload.addEventListener('progress', zzformUploadProgress, false);
	}
	zzformDisableElements();

	if (zzformForm.hasAttribute('data-divert-files')) {
		for (var pair of data.entries()) {
			if (typeof(pair[1]) !== 'object') continue;
			const fileUrl = await zzformDivertFiles(pair);
			if (fileUrl) {
				data.append('zz_divert_files_url', fileUrl);
				data.delete(pair[0]);
			}
		}
	}

	xhr.addEventListener('error', zzformUploadError, false);
	xhr.addEventListener('abort', zzformUploadAbort, false);
	xhr.addEventListener('load', zzformLoadPage, false);
	xhr.open('POST', zzformActionURL);
	xhr.send(data);
}

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
		if (replaceContent.id === 'zzform') {
			// avoid duplicate #zzform div
			// ignore other page elements if sent
			replaceContent.id = 'zzform222222';
			replaceContent.innerHTML = page.html;
			var newZzform = document.getElementById('zzform');
			replaceContent.innerHTML = newZzform.innerHTML;
			replaceContent.id = 'zzform';
			replaceContent = zzformDiv();
		} else {
			replaceContent.innerHTML = page.html;
		}
		// activate scripts, only inside #zzform (not zzform-foot.js!)
		var allScripts = document.getElementById('zzform').getElementsByTagName('script');
		for (i = 0; i < allScripts.length; i++) {
			var g = document.createElement('script');
			var s = allScripts[i];
			if (s.src) {
				if (!zzformLoadedJS.includes(s.src)) {
					g.src = s.src;
					g.async = false;
				}
			} else {
				if(s.getAttribute('data-js') === 'immediately') {
					g.innerHTML = s.innerHTML;
				} else {
					g.src = 'data:text/javascript,' + s.innerHTML;
					g.async = false;
					g.defer = true;
				}
			}
			s.parentNode.insertBefore(g, s);
			s.remove();
		}
	}

	// move to top of page
	if (scrollTop) scroll(0,0);
	else if (typeof replaceContent !== 'undefined') {
		var autoFocusElement = replaceContent.querySelector('input[autofocus="autofocus"]');
		if (autoFocusElement) {
			autoFocusElement.focus();
		} else {
			autoFocusElement = replaceContent.querySelector('select[autofocus="autofocus"]');
			if (autoFocusElement) {
				autoFocusElement.focus();
			} else {
				autoFocusElement = replaceContent.querySelector('textarea[autofocus="autofocus"]');
				if (autoFocusElement) {
					autoFocusElement.focus();
				}
			}
		}
	}
	if (typeof(zzformAnchor) !== 'undefined')
		location.hash = "#" + zzformAnchor;
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
	// still a form visibile? refresh it
	zzformForm = document.getElementById('zzform_form');
	if (zzformForm)
		zzformRecordForm();
	zzformRemoveSuggestions();
	zz_filters('init');
	zzform.classList.remove('saved_state');
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
	// save all script src URLs so they are not loaded again later
	var allScripts = zzformDiv().getElementsByTagName('script');
	if (allScripts.length) {
		for (i = 0; i < allScripts.length; i++) {
			if (allScripts[i].src) zzformLoadedJS[i] = allScripts[i].src;
		}
	}

	// if pushState is supported, save current page in history
	if (history.pushState) {
		var old = {
			title: document.title,
			html: '<div id="%%% setting zzform_replace_div %%%">' + zzformDiv().innerHTML + '</div>',
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
 * show upload status
 */
function zzformUploadProgress(event){
	if (event.total < 100000) return; // do not show progress bar below 100 KB
	document.getElementById("zzform_loaded").innerHTML = "%%% text Uploaded: %%% " + event.loaded + " / %%% text Total: %%% " + event.total + " %%% text Bytes %%% ";
	var percent = (event.loaded / event.total) * 100;
	document.getElementById("zzform_upload_progress_bar").value = Math.round(percent);
	document.getElementById("zzform_upload_status").innerHTML = Math.round(percent)+ "%%% text % uploaded … please wait %%%";
}

/**
 * show upload error message
 */
function zzformUploadError(event){
	document.getElementById("zzform_upload_status").innerHTML = "%%% text Upload Failed %%%";
}

/**
 * show upload abort message
 */
function zzformUploadAbort(event){
	document.getElementById("zzform_upload_status").innerHTML = "%%% text Upload Aborted %%%";
}

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

/**
 * disable input elements after saving record
 *
 */
function zzformDisableElements() {
	zzform.classList.add('saved_state');
	var disable = ['input', 'textarea', 'select'];
	for (j = 0; j < disable.length; j++) {
		var elements = zzformForm.getElementsByTagName(disable[j]);
		for (k = 0; k < elements.length; k++) {
			elements[k].setAttribute('disabled', true);
		}
	}
}


/**
 * generate instances of the WMD editor
 *
 */
function zzformWmdEditor() {
	if (typeof zzformWmdEditorInstances === 'undefined') return false;
	var converter;
	var editor;
	var instanceNo;
	var options;
	if (zzformWmdEditorLang)
		options = {strings: Markdown.local[zzformWmdEditorLang] };

	for (j = 0; j < zzformWmdEditorInstances; j++) {
		instanceNo = '-' + (j + 1);
		converter = new Markdown.Converter();
		editor = new Markdown.Editor(converter, instanceNo, options);
		editor.run();
	}
}
