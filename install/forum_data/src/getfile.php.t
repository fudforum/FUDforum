<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: getfile.php.t,v 1.18 2003/10/05 22:18:41 hackie Exp $
****************************************************************************

****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if (!isset($_GET['id']) || !($id = (int)$_GET['id'])) {
		invl_inp_err();
	}
	if (!isset($_GET['private'])) { /* non-private upload */
		$r = db_saq('SELECT mm.mime_hdr, a.original_name, a.location, m.id, mod.id,
			('.(_uid ? '(CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END)' : 'g1.group_cache_opt').' & 2) > 0
			FROM {SQL_TABLE_PREFIX}attach a
			INNER JOIN {SQL_TABLE_PREFIX}msg m ON a.message_id=m.id AND a.attach_opt=0
			INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? 2147483647 : 0).' AND g1.resource_id=t.forum_id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mod ON mod.forum_id=t.forum_id AND mod.user_id='._uid.'
			LEFT JOIN {SQL_TABLE_PREFIX}mime mm ON mm.id=a.mime_type
			'.(_uid ? 'LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id' : '').'
			WHERE a.id='.$id);
		if (!$r) {
			invl_inp_err();
		}
		if (!($usr->users_opt & 1048576) && !$r[4] && !$r[5]) {
			std_error('access');
		}
	} else {
		$r = db_saq('SELECT mm.mime_hdr, a.original_name, a.location, pm.id, a.owner
			FROM {SQL_TABLE_PREFIX}attach a
			INNER JOIN {SQL_TABLE_PREFIX}pmsg pm ON a.message_id=pm.id AND a.attach_opt=1
			LEFT JOIN {SQL_TABLE_PREFIX}mime mm ON mm.id=a.mime_type
			WHERE a.attach_opt=1 AND a.id='.$id);
		if (!$r) {
			invl_inp_err();
		}
		if (!($usr->users_opt & 1048576) && $r[4] != _uid) {
			std_error('access');
		}
	}

	if ($FUD_OPT_2 & 4194304 && !empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $WWW_ROOT) === false) {
		header("HTTP/1.0 404 Not Found");
		exit;
	}

	reverse_fmt($r[1]);
	if (!$r[0]) {
		$r[0] = 'application/ocet-stream';
		$append = 'attachment; ';
	} else if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') && preg_match('!^(audio|video|image)/!i', $r[0])) {
		$append = 'inline; ';
	} else if (strncmp($r[0], "image/", 6)) {
		$append = 'attachment; ';
	} else {
		$append = '';
	}

	/* if we encounter a compressed file and PHP's output compression is enabled do not
	 * try to compress images & already compressed files */
	if ($FUD_OPT_2 & 16384 && $append) {
		$comp_ext = array('zip', 'gz', 'rar', 'tgz', 'bz2', 'tar');
		$ext = strtolower(substr(strrchr($r[1], '.'), 1));
		if (!in_array($ext, $comp_ext)) {
			ob_start(array('ob_gzhandler', (int)$PHP_COMPRESSION_LEVEL));
		}
	}

	header('Content-type: '.$r[0]);
	header('Content-Disposition: '.$append.'filename='.$r[1]);

	if (!$r[2]) {
		$r[2] = $GLOBALS['FILE_STORE'] . $id . '.atch';
	}

	attach_inc_dl_count($id, $r[3]);
	fpassthru(fopen($r[2], 'rb'));
?>