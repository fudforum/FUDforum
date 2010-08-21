<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	/* Sanity check. */
	if (php_sapi_name() != 'cli') {
		die('Go away.');
	}

	/* Prevent session initialization. */
	define('forum_debug', 1);
	unset($_SERVER['REMOTE_ADDR']);

	/* Instantiate as an admin users. */
	$GLOBALS['usr'] = new stdClass();
	$GLOBALS['usr']->users_opt = 1048576;	// is_admin
	define('__adm_rsid', 0);

	define('shell_script', 1);
?>
