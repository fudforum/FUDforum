<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: adm_cli.inc,v 1.4 2005/12/07 18:07:45 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function cli_execute($post_conf, $get_opts=null)
{
	$s = $_SERVER['REMOTE_ADDR'];
	unset($_SERVER['REMOTE_ADDR']);
	define('forum_debug', 1);
	$GLOBALS['usr']->users_opt = 1048576;

	fud_use('adm.inc');

	if ($post_conf) {
		$_POST['conf'] = 1;
	}
	if ($get_opts && is_array($get_opts)) {
		foreach ($get_opts as $k => $v) {
			$_GET[$k] = $v;
		}
	}

	$_SERVER['REMOTE_ADDR'] = $s;
}
?>