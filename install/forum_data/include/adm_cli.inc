<?php
/**
* copyright            : (C) 2001-2013 Advanced Internet Designs Inc.
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
	define('no_session', 1);
	unset($_SERVER['REMOTE_ADDR']);

	/* Instantiate as an admin users. */
	$GLOBALS['usr'] = new stdClass();
	$GLOBALS['usr']->users_opt  = 1048576;	// is_admin
	$GLOBALS['usr']->theme_name = 'default';
	$GLOBALS['usr']->lang       = 'en';
	define('__adm_rsid', 0);

	/* Something to test against. */
	define('shell_script', 1);
	
	// Show ALL errors.
	error_reporting(-1);
	@ini_set('display_errors', 1);
?>
