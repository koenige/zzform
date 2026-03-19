<?php

/**
 * zzform
 * Additional thumbnail creation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/zzform
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2010, 2014-2016, 2019-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Creates images of thumbnails as if they were created while uploading
 * This can be used to create missing thumbnails or to create thumbnails
 * for different sizes than were planned for initially
 *
 * @param array $params
 *		[0]: name of zzform script
 *		[1]: mode (default false: only missing images are created; 'overwrite':
 *			existing images are being deleted)
 * @return array|false $page or false
 * @todo support $zz['conditions']
 */
function mod_zzform_make_thumbnails($params) {
	if (count($params) > 2) return false;
	if (count($params) > 1 AND $params[1] !== 'overwrite') return false;
	$mode = empty($params[1]) ? 'existing' : $params[1];
	if (strstr($params[0], '..')) return false;

	// API: POST only (same field names as before: thumbnails, token). GET never mutates.
	$api_post = $_SERVER['REQUEST_METHOD'] === 'POST'
		&& array_key_exists('thumbnails', $_POST)
		&& $_POST['thumbnails'] !== '';
	if ($api_post) {
		return mod_zzform_make_thumbnails_background($params[0], $mode);
	}

	$data = [
		'script' => $params[0],
		'mode' => $mode
	];
	$page = [];
	$page['title'] = wrap_text('Thumbnail creation');
	$page['text'] = wrap_template('make-thumbnails', $data);
	return $page;
}

/**
 * call thumbnail creation in the background
 * return data in JSON format
 *
 * @param string $script
 * @param string $mode
 * @return array
 */
function mod_zzform_make_thumbnails_background($script, $mode) {
	wrap_setting('cache', false);
	$api = mod_zzform_make_thumbnails_api($script, $mode);
	$status = $api['_api_status'] ?? 200;
	unset($api['_api_status']);
	$page = [];
	$page['content_type'] = 'json';
	$page['text'] = json_encode($api, JSON_UNESCAPED_UNICODE);
	$page['status'] = $status;
	$page['query_strings'] = ['thumbnails', 'token', 'processed', 'stopped'];
	return $page;
}

/**
 * Builds the JSON payload for XHR (init or step)
 *
 * POST body (application/x-www-form-urlencoded): thumbnails=init | thumbnails=<step> | thumbnails=finalize
 * (finalize: token, processed=jobs done, stopped=0|1). GET does not run the API.
 *
 * Optional _api_status (HTTP status); omitted means 200
 *
 * @param string $script
 * @param string $mode existing|overwrite
 * @return array
 */
function mod_zzform_make_thumbnails_api($script, $mode) {
	$bad = function (array $payload, int $status = 400) {
		if (!empty($payload['error']) && empty($payload['summary'])) {
			$payload['summary'] = $payload['error'];
		}
		return array_merge($payload, ['_api_status' => $status]);
	};

	$thumbnails = $_POST['thumbnails'] ?? '';
	$is_init = ($thumbnails === 'init');
	$is_finalize = ($thumbnails === 'finalize');
	$is_step = !$is_init && !$is_finalize && is_string($thumbnails) && preg_match('/^\d+$/', $thumbnails);
	if (!$is_init && !$is_step && !$is_finalize) {
		return $bad([
			'ok' => false,
			'fatal' => true,
			'error' => wrap_text('Invalid thumbnails request.'),
		]);
	}

	if ($is_finalize) {
		wrap_session_start();
		$sess_key = 'zzform_make_thumbnails';
		$token = preg_replace('/[^A-Za-z0-9]/', '', $_POST['token'] ?? '');
		$entry = $_SESSION[$sess_key][$token] ?? null;
		if (!$entry || ($entry['expires'] ?? 0) < time()) {
			return $bad([
				'ok' => false,
				'fatal' => true,
				'error' => wrap_text('Session expired or invalid. Please start again.'),
			]);
		}
		if ($entry['script'] !== $script || $entry['mode'] !== $mode) {
			return $bad([
				'ok' => false,
				'fatal' => true,
				'error' => wrap_text('Form script or mode does not match.'),
			]);
		}
		$n = count($entry['jobs']);
		$processed = (int) ($_POST['processed'] ?? -1);
		$stopped_raw = $_POST['stopped'] ?? '0';
		$stopped = !empty($stopped_raw) && $stopped_raw !== '0'
			&& strtolower((string) $stopped_raw) !== 'false';
		if ($processed < 0 || $processed > $n) {
			return $bad([
				'ok' => false,
				'fatal' => true,
				'error' => wrap_text('Invalid processed count.'),
			]);
		}
		if (!$stopped && $processed !== $n) {
			return $bad([
				'ok' => false,
				'fatal' => true,
				'error' => wrap_text('Invalid thumbnails finalize request.'),
			]);
		}
		$ec = (int) ($entry['error_count'] ?? 0);
		if ($stopped) {
			$summary = wrap_text(
				'Stopped after %d of %d.',
				['values' => [$processed, $n]]
			);
		} elseif ($ec > 0) {
			$summary = wrap_text(
				'Finished with %d errors (see log).',
				['values' => [$ec]]
			);
		} else {
			$summary = wrap_text('All thumbnails processed.');
		}
		unset($_SESSION[$sess_key][$token]);
		return [
			'ok' => true,
			'summary' => $summary,
		];
	}

	wrap_include('zzform.php', 'zzform');
	zz_initialize();
	zz_modules_load('upload');
	zz_upload_load_graphics_library();

	$zz = zzform_include($script);
	list($id_field_name, $upload_groups) = mod_zzform_make_thumbnails_upload_setup($zz);
	if (!$id_field_name || !$upload_groups) {
		return $bad([
			'ok' => false,
			'fatal' => true,
			'error' => wrap_text('No upload image field found in this form.'),
		]);
	}

	wrap_session_start();
	$sess_key = 'zzform_make_thumbnails';

	if ($is_init) {
		foreach ($_SESSION[$sess_key] ?? [] as $t => $entry) {
			if (($entry['expires'] ?? 0) < time()) {
				unset($_SESSION[$sess_key][$t]);
			}
		}
		$jobs = mod_zzform_make_thumbnails_collect_jobs(
			$zz,
			$id_field_name,
			$upload_groups,
			$mode
		);
		if (!$jobs) {
			return [
				'ok' => true,
				'total' => 0,
				'token' => '',
				'message' => wrap_text('No missing thumbnails were found.'),
			];
		}
		$token = wrap_random_hash(16);
		$_SESSION[$sess_key][$token] = [
			'script' => $script,
			'mode' => $mode,
			'jobs' => $jobs,
			'expires' => time() + 7200,
			'error_count' => 0,
		];
		return [
			'ok' => true,
			'total' => count($jobs),
			'token' => $token,
		];
	}

	// Allow only characters wrap_random_hash() can emit (POST body is untrusted).
	$token = preg_replace('/[^A-Za-z0-9]/', '', $_POST['token'] ?? '');
	$step_i = (int) $thumbnails;
	$entry = $_SESSION[$sess_key][$token] ?? null;
	if (!$entry || ($entry['expires'] ?? 0) < time()) {
		return $bad([
			'ok' => false,
			'fatal' => true,
			'error' => wrap_text('Session expired or invalid. Please start again.'),
		]);
	}
	if ($entry['script'] !== $script || $entry['mode'] !== $mode) {
		return $bad([
			'ok' => false,
			'fatal' => true,
			'error' => wrap_text('Form script or mode does not match.'),
		]);
	}
	$jobs = $entry['jobs'];
	if ($step_i < 0 || $step_i >= count($jobs)) {
		return $bad([
			'ok' => false,
			'fatal' => true,
			'error' => wrap_text('Invalid step index.'),
		]);
	}

	$job = $jobs[$step_i];
	$out = mod_zzform_make_thumbnails_process_job(
		$zz,
		$id_field_name,
		$upload_groups,
		$job,
		$mode
	);
	if (!empty($out['is_error'])) {
		$_SESSION[$sess_key][$token]['error_count']
			= (int) ($_SESSION[$sess_key][$token]['error_count'] ?? 0) + 1;
	}
	return $out;
}

/**
 * Collects the primary key field name and every upload_image field on the form.
 *
 * Each upload group holds the field index, path to the original file, and the
 * full image[] definition (source + derivatives).
 *
 * @param array $zz Table/form definition from zzform_include()
 * @return array{0: string, 1: array} id_field_name, list of upload_groups
 */
function mod_zzform_make_thumbnails_upload_setup($zz) {
	$id_field_name = '';
	foreach ($zz['fields'] as $field) {
		if (empty($field['type'])) {
			continue;
		}
		if ($field['type'] === 'id') {
			$id_field_name = $field['field_name'];
		}
	}
	$upload_groups = [];
	foreach ($zz['fields'] as $no => $field) {
		if (empty($field['type']) || $field['type'] !== 'upload_image') {
			continue;
		}
		$source_path = null;
		foreach ($field['image'] as $file) {
			if (!isset($file['source'])) {
				$source_path = $file['path'];
			}
		}
		if (!$source_path) {
			continue;
		}
		$upload_groups[] = [
			'no' => $no,
			'source_path' => $source_path,
			'image' => $field['image'],
		];
	}

	return [$id_field_name, $upload_groups];
}

/**
 * Builds the list of thumbnail jobs (one entry per missing or overwritable file).
 *
 * Walks all rows from $zz['sql']; for each derivative with an action, queues a
 * job unless mode is existing and the destination file already exists.
 *
 * @param array $zz
 * @param string $id_field_name Primary key field name
 * @param array $upload_groups From mod_zzform_make_thumbnails_upload_setup()
 * @param string $mode existing|overwrite
 * @return array<int, array{id:int,field_no:int,img:mixed}>
 */
function mod_zzform_make_thumbnails_collect_jobs($zz, $id_field_name, $upload_groups, $mode) {
	$jobs = [];
	$records = wrap_db_fetch($zz['sql'], $id_field_name);
	if (!$records) {
		return $jobs;
	}
	foreach ($records as $line) {
		if (!empty($line['filetype_id'])) {
			$filetype = wrap_filetype_id($line['filetype_id'], 'read-id');
			$filetype_conf = wrap_filetypes($filetype);
			if (empty($filetype_conf['thumbnail'])) {
				continue;
			}
		}
		foreach ($upload_groups as $g) {
			$source = mod_zzform_make_thumbnails_makelink($g['source_path'], $line);
			if (!$source || !@filesize($source)) {
				continue;
			}
			foreach ($g['image'] as $img_id => $file) {
				if (!isset($file['source'])) {
					continue;
				}
				if (empty($file['action'])) {
					continue;
				}
				$dest = zz_path_file($file['path'], $line);
				if ($dest && $mode === 'existing') {
					continue;
				}
				$jobs[] = [
					'id' => $line[$id_field_name],
					'field_no' => $g['no'],
					'img' => $img_id,
				];
			}
		}
	}

	return $jobs;
}

/**
 * Creates a single derivative for one record (one step of the XHR queue).
 *
 * Loads the row, resolves source path, calls zz_image_* for the configured
 * action, and returns a status array for the browser log.
 *
 * @param array $zz
 * @param string $id_field_name
 * @param array $upload_groups
 * @param array $job Keys id, field_no, img (derivative index)
 * @param string $mode existing|overwrite (overwrite unlinks destination first)
 * @return array Keys ok, message, is_error for JSON step response
 */
function mod_zzform_make_thumbnails_process_job($zz, $id_field_name, $upload_groups, $job, $mode) {
	$id = (int) $job['id'];
	$parts = explode('.', $id_field_name);
	$qf = implode('.', array_map(function ($p) {
		return '`'.str_replace('`', '', $p).'`';
	}, $parts));
	$sql = wrap_edit_sql($zz['sql'], 'WHERE', sprintf('%s = %d', $qf, $id));
	$records = wrap_db_fetch($sql, $id_field_name);
	$cid = $job['id'];
	$line = $records[$cid] ?? $records[(string) $cid] ?? null;
	if (!$line && is_array($records) && count($records) === 1) {
		$line = reset($records);
	}
	if (!$line) {
		return [
			'ok' => true,
			'message' => sprintf('ID %d: %s', $id, wrap_text('Record not found.')),
			'is_error' => true,
		];
	}

	$g = null;
	foreach ($upload_groups as $ug) {
		if ($ug['no'] === $job['field_no']) {
			$g = $ug;
			break;
		}
	}
	if (!$g || !isset($g['image'][$job['img']])) {
		return [
			'ok' => true,
			'message' => sprintf('ID %d: %s', $id, wrap_text('Upload field not found.')),
			'is_error' => true,
		];
	}
	$file = $g['image'][$job['img']];

	$source = mod_zzform_make_thumbnails_makelink($g['source_path'], $line);
	if (!$source) {
		return [
			'ok' => true,
			'message' => sprintf(
				'ID %d: %s',
				$id,
				wrap_text('The original file does not exist.')
			),
			'is_error' => true,
		];
	}
	// $source is canonical (realpath via zz_path_file); strip cms root for shorter log lines
	$source_display = $source;
	$cms_dir = wrap_setting('cms_dir');
	if ($cms_dir && ($cms_real = realpath($cms_dir))
		&& str_starts_with($source_display, $cms_real)
		&& (strlen($source_display) === strlen($cms_real) || $source_display[strlen($cms_real)] === '/')) {
		$source_display = substr($source_display, strlen($cms_real));
	}
	$prefix = sprintf('ID %d (%s): ', $id, $source_display);
	if (!@filesize($source)) {
		return [
			'ok' => true,
			'message' => $prefix.wrap_text('The original file does not contain any data.'),
			'is_error' => true,
		];
	}
	$size = @getimagesize($source);
	if (empty($file['upload']) || !is_array($file['upload'])) {
		$file['upload'] = [];
	}
	$file['upload']['height'] = $size[1] ?? $line['height_px'] ?? null;
	$file['upload']['width'] = $size[0] ?? $line['width_px'] ?? null;
	// Batch jobs use the static field definition (no upload run); set filetype from
	// PHP so zz_imagick_check_multipage() sees multipage flags (e.g. png → [0]).
	if (!empty($size[2])) {
		$ft_def = zz_upload_filetype_php($size[2]);
		if (!empty($ft_def['filetype'])) {
			$file['upload']['filetype'] = $ft_def['filetype'];
			if (empty($file['upload']['ext'])) {
				$file['upload']['ext'] = $ft_def['extension'][0] ?? '';
			}
		}
	}
	zz_upload_image_derivative_defaults($file);

	$destination = mod_zzform_make_thumbnails_makelink($file['path'], $line, 'inexistent');
	if (!$destination) {
		return [
			'ok' => true,
			'message' => $prefix.wrap_text('Could not build destination path.'),
			'is_error' => true,
		];
	}
	if ($mode === 'overwrite' && file_exists($destination)) {
		@unlink($destination);
	}
	$dest_extension = substr($destination, strrpos($destination, '.') + 1);
	zz_create_topfolders(dirname($destination));

	$func = 'zz_image_'.$file['action'];
	if (!function_exists($func)) {
		return [
			'ok' => true,
			'message' => $prefix.wrap_text(
				'The function `%s` does not exist.',
				['values' => $func]
			),
			'is_error' => true,
		];
	}
	$tn = $func($source, $destination, $dest_extension, $file);
	if ($tn) {
		return [
			'ok' => true,
			'message' => $prefix.wrap_text(
				'Thumbnail for %s × %s px was created.',
				['values' => [$file['width'], $file['height']]]
			),
			'is_error' => false,
		];
	}

	return [
		'ok' => true,
		'message' => $prefix.wrap_text(
			'Thumbnail for %s × %s px could not be created.',
			['values' => [$file['width'], $file['height']]]
		),
		'is_error' => true,
	];
}

/**
 * checks whether a source file exists, removes webroot, adds root
 *
 * @param array $source_path
 * @param array $line
 * @param string|false $mode
 * @return string|false
 */
function mod_zzform_make_thumbnails_makelink($source_path, $line, $mode = false) {
	$root = '';
	if (!empty($source_path['root'])) {
		$root = $source_path['root'];
		// don't check against root if file does not exist
		if ($mode === 'inexistent')
			unset($source_path['root']);
	}
	$source = zz_path_file($source_path, $line);
	if (!$source) return false;
	if ($mode === 'inexistent')
		$source = $root.$source;
	return $source;
}
