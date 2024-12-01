<?php
/**
* copyright            : (C) 2001-2024 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

function get_preview_img($id)
{
	return db_saq('SELECT mm.mime_hdr, a.original_name, a.location, 0, 0, 0, a.fsize FROM {SQL_TABLE_PREFIX}attach a LEFT JOIN {SQL_TABLE_PREFIX}mime mm ON mm.id=a.mime_type WHERE a.message_id=0 AND a.id='. $id);
}

/* main */
	if (!isset($_GET['id']) || !($id = (int)$_GET['id'])) {
		// Previously: invl_inp_err();
		header($_SERVER['SERVER_PROTOCOL'] .' 400 Bad Request', true, 400);
		echo 'Bad Request';
		exit;
	}
	if (empty($_GET['private'])) { /* Non-private upload. */
		$r = db_saq('SELECT mm.mime_hdr, a.original_name, a.location, m.id, mo.id,
			'. q_bitand(_uid ? 'COALESCE(g2.group_cache_opt, g1.group_cache_opt)' : '(g1.group_cache_opt)', 2) .',
			a.fsize
			FROM {SQL_TABLE_PREFIX}attach a
			INNER JOIN {SQL_TABLE_PREFIX}msg m ON a.message_id=m.id AND a.attach_opt=0
			INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='. (_uid ? 2147483647 : 0) .' AND g1.resource_id=t.forum_id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mo ON mo.forum_id=t.forum_id AND mo.user_id='. _uid .'
			LEFT JOIN {SQL_TABLE_PREFIX}mime mm ON mm.id=a.mime_type
			'. (_uid ? 'LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='. _uid .' AND g2.resource_id=t.forum_id' : '') .'
			WHERE a.id='. $id);
		if (!$r) {
			if (!($r = get_preview_img($id))) {
				// Previously: invl_inp_err();
				header($_SERVER['SERVER_PROTOCOL'] .' 404 Not Found', true, 404);
				echo 'Not Found';
				exit;
			}
		} else if (!$is_a && !$r[4] && !$r[5]) {
			// Previously: std_error('access');
			header($_SERVER['SERVER_PROTOCOL'] .' 401 Unauthorized', true, 401);
			echo 'Unauthorized';
			exit;
		}
	} else {
		$r = db_saq('SELECT mm.mime_hdr, a.original_name, a.location, pm.id, a.owner, a.fsize
			FROM {SQL_TABLE_PREFIX}attach a
			INNER JOIN {SQL_TABLE_PREFIX}pmsg pm ON a.message_id=pm.id AND a.attach_opt=1
			LEFT JOIN {SQL_TABLE_PREFIX}mime mm ON mm.id=a.mime_type
			WHERE a.attach_opt=1 AND a.id='. $id);
		if (!$r) {
			if (!($r = get_preview_img($id))) {
				// Previously: invl_inp_err();
				header($_SERVER['SERVER_PROTOCOL'] .' 404 Not Found', true, 404);
				echo 'Not Found';
				exit;
			}
		} else if (!$is_a && $r[4] != _uid) {
			// Previously: std_error('access');
			header($_SERVER['SERVER_PROTOCOL'] .' 401 Unauthorized', true, 401);
			echo 'Unauthorized';
			exit;
		}
	}

	// DWLND_REF_CHK
	$WWW_ROOT = preg_replace('#^\/\/|^https?:\/\/|www\.#', '', $WWW_ROOT);	// Remove www, \\ and http/https before referer checking.
	if ($FUD_OPT_2 & 4194304 && !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $WWW_ROOT) === false) {
		header($_SERVER['SERVER_PROTOCOL'] .' 403 Forbidden', true, 403);
		echo 'Forbidden - bad referer';
		exit;
	}

	$r[1] = reverse_fmt($r[1]);
	if (!$r[2]) {	// Empty location means we are previewing a message with an attachment.
		$r[2] = $GLOBALS['FILE_STORE'] . $id .'.atch';
	}

	if (!strncmp($r[0], 'image/', 6)) {
		$s = getimagesize($r[2]);
		$r[0] = $s['mime'];
	}

	if (!$r[0]) {
		$r[0] = 'application/octet-stream';
		$append = 'attachment; ';
	} else if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') && preg_match('!^(?:audio|video|image)/!i', $r[0])) {
		$append = 'inline; ';
	} else if (strncmp($r[0], 'image/', 6)) {
		$append = 'attachment; ';
	} else {
		$append = '';
	}

	/* If we encounter a compressed file and PHP's output compression is enabled do not
	 * try to compress images & already compressed files. */
	if ($FUD_OPT_2 & 16384 && $append) {
		$comp_ext = array('zip', 'gz', 'rar', 'tgz', 'bz2', 'tar');
		$ext = strtolower(substr(strrchr($r[1], '.'), 1));
		if (!in_array($ext, $comp_ext)) {
			ob_start('ob_gzhandler', (int)$PHP_COMPRESSION_LEVEL);
		}
	}

	/* This is a hack for IE browsers when working on HTTPs.
	 * The no-cache headers appear to cause problems as indicated by the following
	 * MS advisories:
	 *	http://support.microsoft.com/?kbid=812935
	 *	http://support.microsoft.com/default.aspx?scid=kb;en-us;316431
	 */
	if ($_SERVER['SERVER_PORT'] == '443' && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)) {
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0', 1);
		header('Pragma: public', 1);
	} else if (__fud_cache(filemtime($r[2]))) {
		return;
	}

	header('Content-Type: '. $r[0]);
	header('Content-Disposition: '. $append .'filename="'. urlencode($r[1]) .'"');
	header('Content-Length: '. array_pop($r));
	header('Connection: close');

	// Increment counter and disconnect to prevent long opens while data is transferred.
	attach_inc_dl_count($id, $r[3]);
	db_close();

	// Spool file to browser.
	@ob_end_flush();	// Output buffering may cause memory issues with large files.
	@readfile($r[2]);
?>
