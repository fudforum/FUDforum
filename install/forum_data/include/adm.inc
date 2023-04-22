<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
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

/* Try to force unbuffered incremental output to see step by step progress. */
header('X-Accel-Buffering: no');
@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 'Off');
@ini_set('implicit_flush', 1);
@ob_implicit_flush(1);

/* Load DB driver. */
fud_use('db.inc');
// DB functions (generated) should now be included, but we need to double check.
if (!function_exists('q')) {
	include($GLOBALS['DATA_DIR'] .'sql/'. $GLOBALS['DBHOST_DBTYPE'] .'/db.inc');
}

if (!defined('shell_script')) {
	if (php_sapi_name() == 'cli') {	// adm_cli.inc wasn't called!
		die('Sorry, cannot run from command line.');
	}

	if ($GLOBALS['FUD_OPT_2'] & 32768) {	// Disable USE_PATH_INFO
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
	
	ses_update_status($GLOBALS['usr']->sid, 'Access ACP');
}

if (!($GLOBALS['usr']->users_opt & 1048576)) {	// not is_admin
	// Limited access for account moderators.
	if ($GLOBALS['usr']->users_opt & 268435456) {	// account moderator
		switch (basename($_SERVER['PHP_SELF'])) {
			case 'admuser.php':
			case 'admuseradd.php':
			case 'admuserapr.php':
			case 'admusermerge.php':
			case 'admprivlist.php':
			case 'admbanlist.php':
			case 'admsession.php':
			case 'admmodfrm.php':	// Modify Moderation Permissions
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
}

/* Load common functions used throughout the ACP and other scripts. */
define('__fud_index_name__', 'index.php');
fud_use('adm_common.inc', true);

?>
