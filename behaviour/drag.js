/*
 * zzform
 * JavaScript for dragging elements
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @copyright Copyright © 2020 retrofuturistic (https://codepen.io/retrofuturistic/pen/tlbHE)
 * @copyright Copyright © 2020 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0

Copyright (c) 2020 by retrofuturistic
(https://codepen.io/retrofuturistic/pen/tlbHE)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

 */


var zzDragSrcEl = null;

function zzHandleDragStart(e) {
	// Target (this) element is the source node.
	zzDragSrcEl = this;

	e.dataTransfer.effectAllowed = 'move';
	e.dataTransfer.setData('text/html', this.outerHTML);

	this.classList.add('dragElem');
}

function zzHandleDragOver(e) {
	if (e.preventDefault) {
		e.preventDefault(); // Necessary. Allows us to drop.
	}
	this.classList.add('over');
	e.dataTransfer.dropEffect = 'move';	// See the section on the DataTransfer object.
	return false;
}

function zzHandleDragLeave(e) {
	this.classList.remove('over');	// this / e.target is previous target element.
}

/**
 * drag drop: move element to new place, save via XHR to database
 *
 * @param object e (source node)
 */
function zzHandleDrop(e) {
	// this/e.target is current target element.
	if (e.preventDefault) {
		e.preventDefault(); // do not follow links
	}

	// Don't do anything if dropping the same column we're dragging.
	if (zzDragSrcEl != this) {
		// Set the source column's HTML to the HTML of the column we dropped on.
		this.parentNode.removeChild(zzDragSrcEl);
		var dropHTML = e.dataTransfer.getData('text/html');
		this.insertAdjacentHTML('beforebegin',dropHTML);
		var dropElem = this.previousSibling;
		zzAddDnDHandlers(dropElem);
		// get sequence of element: page with limit?
		var sequence = parseInt(zz_dnd_dnd_start, 10) + 1;
		// check current position
		var newItems = document.querySelectorAll('#zzform .data li');
		for (i = 0; i < newItems.length; i++) {
			if (newItems[i].getAttribute('data-id') === this.getAttribute('data-id')) {
				sequence += i - 1;
				break;
			}
		}
		// write data to database
		var xhr = new XMLHttpRequest();
		xhr.open('POST', zz_dnd_target_url, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.send(
			'zz_action=update&' + zz_dnd_id_field + '=' + zzDragSrcEl.getAttribute('data-id') 
			+ '&' + zz_dnd_sequence_field + '=' + sequence
		);
	}
	this.classList.remove('over');
	return false;
}

/**
 * drag end: remove classes
 *
 * @param object e (source node)
 */
function zzHandleDragEnd(e) {
	// this/e.target is the source node.
	this.classList.remove('dragElem');
}

/**
 * add drag and drop handlers
 * disable links of child anchors
 *
 * @param object elem (list items)
 */
function zzAddDnDHandlers(elem) {
	elem.addEventListener('dragstart', zzHandleDragStart, false);
	elem.addEventListener('dragover', zzHandleDragOver, false);
	elem.addEventListener('dragleave', zzHandleDragLeave, false);
	elem.addEventListener('drop', zzHandleDrop, false);
	elem.addEventListener('dragend', zzHandleDragEnd, false);
}

var items = document.querySelectorAll('#zzform .data li');
[].forEach.call(items, zzAddDnDHandlers);
