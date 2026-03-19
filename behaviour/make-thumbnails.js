/*
 * zzform
 * Batch thumbnail creation (XHR progress)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

(function () {
	var root = document.getElementById('make-thumbnails-app');
	if (!root) return;
	var btnStart = document.getElementById('make-thumbnails-start');
	var btnStop = document.getElementById('make-thumbnails-stop');
	var statusEl = document.getElementById('make-thumbnails-status');
	var countEl = document.getElementById('make-thumbnails-count');
	var progressEl = document.getElementById('make-thumbnails-progress');
	var logEl = document.getElementById('make-thumbnails-log');
	var summaryEl = document.getElementById('make-thumbnails-summary');
	var stopFlag = false;
	var token = '';
	var total = 0;

	function apiBody(thumbnailsValue, extra) {
		var params = new URLSearchParams();
		params.set('thumbnails', thumbnailsValue);
		if (extra) {
			Object.keys(extra).forEach(function (k) {
				params.set(k, extra[k]);
			});
		}
		return params.toString();
	}

	function appendLog(text, isError) {
		if (!logEl) return;
		var li = document.createElement('li');
		if (isError) li.className = 'error';
		li.textContent = text;
		logEl.appendChild(li);
		while (logEl.children.length > 200) {
			logEl.removeChild(logEl.firstChild);
		}
	}

	function setProgress(done, tot) {
		if (!countEl) return;
		countEl.textContent = done + ' / ' + tot;
		if (!progressEl) return;
		if (tot > 0) {
			progressEl.max = tot;
			progressEl.value = done;
			progressEl.textContent = Math.round((100 * done) / tot) + '%';
		} else {
			progressEl.max = 1;
			progressEl.value = 0;
			progressEl.textContent = '0%';
		}
	}

	async function fetchJson(thumbnailsValue, extra) {
		var r = await fetch(window.location.href, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: apiBody(thumbnailsValue, extra)
		});
		var t = await r.text();
		try {
			var parsed = JSON.parse(t);
			var inner = parsed.json;
			if (inner === undefined) {
				return { ok: false, error: '%%% text Response has no `json` key. %%%' };
			}
			if (typeof inner === 'string') {
				try { inner = JSON.parse(inner); } catch (e2) {
					return { ok: false, error: '%%% text `json` value is not valid JSON. %%%' };
				}
			}
			if (typeof inner !== 'object' || inner === null) {
				return { ok: false, error: '%%% text `json` value is not an object. %%%' };
			}
			return inner;
		} catch (e) {
			return { ok: false, error: t.slice(0, 200) || '%%% text Invalid JSON. %%%' };
		}
	}

	btnStop.addEventListener('click', function () {
		stopFlag = true;
		btnStop.disabled = true;
	});

	btnStart.addEventListener('click', async function () {
		stopFlag = false;
		btnStart.disabled = true;
		btnStop.disabled = false;
		if (logEl) logEl.innerHTML = '';
		if (summaryEl) {
			summaryEl.hidden = true;
			summaryEl.textContent = '';
		}
		if (statusEl) statusEl.hidden = false;

		var init = await fetchJson('init');
		if (!init.ok) {
			appendLog(init.error || '%%% text Init failed. %%%', true);
			btnStart.disabled = false;
			btnStop.disabled = true;
			return;
		}
		token = init.token || '';
		total = init.total || 0;
		if (!total) {
			if (summaryEl) {
				summaryEl.textContent = init.message || '';
				summaryEl.hidden = false;
			}
			setProgress(0, 0);
			btnStart.disabled = false;
			btnStop.disabled = true;
			return;
		}
		setProgress(0, total);
		var i = 0;
		var fatalBreak = false;
		var lastSummary = '';
		while (i < total && !stopFlag) {
			var step = await fetchJson(String(i), { token: token });
			if (!step.ok && step.fatal) {
				appendLog(step.error || '%%% text Step failed. %%%', true);
				lastSummary = step.summary || step.error || '';
				fatalBreak = true;
				break;
			}
			if (step.message) {
				appendLog(step.message, !!step.is_error);
			}
			i++;
			setProgress(i, total);
		}
		btnStart.disabled = false;
		btnStop.disabled = true;
		if (summaryEl) {
			if (token && total > 0 && !fatalBreak) {
				var fin = await fetchJson('finalize', {
					token: token,
					processed: String(i),
					stopped: stopFlag ? '1' : '0'
				});
				summaryEl.textContent = fin.summary || fin.error || '';
			} else {
				summaryEl.textContent = lastSummary;
			}
			summaryEl.hidden = false;
		}
	});
})();
