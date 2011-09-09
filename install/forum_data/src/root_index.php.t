<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	if (function_exists('mb_internal_encoding')) {
		mb_internal_encoding('{TEMPLATE: forum_CHARSET}');
	}
	require('./GLOBALS.php');

	/* Activation check. */
	if (!($FUD_OPT_1 & 1)) {	// FORUM_ENABLED
		fud_use('errmsg.inc');
		exit_forum_disabled();
	}

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	fud_use('err.inc');

	/* BUST_A_PUNK enabled. */
	if ($FUD_OPT_2 & 524288 && isset($_COOKIE[$COOKIE_NAME .'1']) && $t != 'error') {
		fud_use('errmsg.inc');
		exit_user_banned();
	}

	/* Check PHP_COMPRESSION_ENABLE. */
	if ($FUD_OPT_2 & 16384 && $t != 'getfile') {
		ob_start(array('ob_gzhandler', (int)$PHP_COMPRESSION_LEVEL));
	}

	/* This is needed to determine what extension to use for alpha-transparency images. */
	// @TODO: Remove in future. IE 7 supports PNG alpha.
	if (!empty($_SERVER['HTTP_USER_AGENT']) && 
		strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false && 
		strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') === false) {
		define('img_ext', '.gif');
	} else {
		define('img_ext', '.png');
	}

	if ($t == 'rview') {
		if (isset($_GET['th']) || isset($_GET['goto'])) {
			$t = $_GET['t'] = d_thread_view;
		} else if (isset($_GET['frm_id'])) {
			$t = $_GET['t'] = t_thread_view;
		} else {
			$t = $_GET['t'] = 'index';
		}
	} else if (preg_match('/[^a-z_]/', $t) || !@file_exists($WWW_ROOT_DISK . fud_theme . $t .'.php')) {
		$t = 'index';
	}

	/* Call themed template. */
	if (defined('plugins')) {
		$t = plugin_call_hook('PRE_TEMPLATE', $t);
		if (isset($plugin_hooks['POST_TEMPLATE'])) {
			ob_start();	// Start capturing output for POST_TEMPLATE plugins.
		}
	}
	require($WWW_ROOT_DISK . fud_theme .'language.inc');	// Initialize theme's language helper functions.
	require($WWW_ROOT_DISK . fud_theme . $t .'.php');
	if (defined('plugins') && isset($plugin_hooks['POST_TEMPLATE'])) {
		$template_data = ob_get_contents();
		ob_end_clean();
		echo plugin_call_hook('POST_TEMPLATE', $template_data);
	}

	/* Housekeeping. */
	while (ob_get_level() > 0) ob_end_flush();	// Flush all output to browser.
	switch ($t) {
		case 'msg':
			if (!isset($_GET['prevloaded'])) {
				th_inc_view_count($frm->id);
			}
			if (_uid && $obj2) {
				if ($frm->last_forum_view < $obj2->post_stamp) {
					user_register_forum_view($frm->forum_id);
				}
				if ($frm->last_view < $obj2->post_stamp) {
					user_register_thread_view($frm->id, $obj2->post_stamp, $obj2->id);
				}
			}
			break;
		case 'tree':
			if (_uid && $msg_obj) {
				th_inc_view_count($msg_obj->thread_id);
				if ($frm->last_forum_view < $msg_obj->post_stamp) {
					user_register_forum_view($msg_obj->forum_id);
				}
				if ($frm->last_view < $msg_obj->post_stamp) {
					user_register_thread_view($msg_obj->thread_id, $msg_obj->post_stamp, $msg_obj->id);
				}
			}
			break;
		case 'login':
			/* Clear expired sessions AND anonymous sessions older than 1 day. */
			q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE time_sec<'. (__request_timestamp__- ($FUD_OPT_3 & 1 ? $SESSION_TIMEOUT : $COOKIE_TIMEOUT)) .' OR (user_id>2000000000 AND time_sec<'. (__request_timestamp__- 86400) .')');
			break;
		case 'thread':
		case 'threadt':
			if (_uid) {
				user_register_forum_view($frm_id);
			}
			break;
	}

?>
