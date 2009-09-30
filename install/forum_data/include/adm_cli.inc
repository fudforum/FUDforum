<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: adm_cli.inc,v 1.9 2009/09/30 16:47:32 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function cli_execute($post_conf, $get_opts=null)
{
	/* Prevent session initialization. */
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	$GLOBALS['usr'] = new stdClass();
	$GLOBALS['usr']->users_opt = 1048576;	// is_admin

	define('shell_script', 1);
	fud_use('adm.inc', 1);

	if ($post_conf) {
		$_POST['conf'] = 1;
	}
	if ($get_opts && is_array($get_opts)) {
		foreach ($get_opts as $k => $v) {
			$_GET[$k] = $v;
		}
	}
}
?>
