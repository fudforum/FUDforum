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

/* Disable ACP caching, indexing, etc. */
header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Way back in the past.
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

/* Load DB driver. */
fud_use('db.inc');

if (!defined('shell_script')) {
	if (php_sapi_name() == 'cli') {	// adm_cli.inc wasn't called!
		die('Sorry, cannot run from command line.');
	}

	if ($GLOBALS['FUD_OPT_2'] & 32768) {	// USE_PATH_INFO
		$GLOBALS['FUD_OPT_2'] ^= 32768;
		$t = 1;
	}
	fud_use('cookies.inc');
	fud_use('users.inc');
	define('__adm_rsid', _rsidl .'&amp;SQ='. $GLOBALS['sq']);
	define('__adm_rsidl', _rsidl .'&SQ='. $GLOBALS['sq']);
	if (isset($t)) {
		$GLOBALS['FUD_OPT_2'] |= 32768;	// Re-enable PATH_INFO.
	}

	if (!count($_POST)) {
		sq_check(0, $GLOBALS['usr']->sq);
	}
}

define('__fud_index_name__', 'index.php');

if (!($GLOBALS['usr']->users_opt & 1048576)) {	// not is_admin
	// Limit access for account moderators.
	if ($GLOBALS['usr']->users_opt & 268435456) {	// account moderator
		switch (basename($_SERVER['PHP_SELF'])) {
			case 'admuserapr.php':
			case 'admuseradd.php':
			case 'admuser.php':
			case 'admusermerge.php':
				break;
			default:
				header('Location: '. $GLOBALS['WWW_ROOT'] .'adm/admuser.php?'. __adm_rsidl);
				exit;
		}
	} else {
		header('Location: '. $GLOBALS['WWW_ROOT'] .'adm/admloginuser.php?'. __adm_rsidl);
        	exit;
	}
} else {
	// Admin's should see ALL errors.
	error_reporting(-1);
	@ini_set('display_errors', 1);
	// Disable buffering to see step by step prorgress.
	@ini_set('output_buffering', 'Off'); 
}

/* Load common functions used throughout the ACP and other scripts. */
fud_use('adm_common.inc', true);

?>
