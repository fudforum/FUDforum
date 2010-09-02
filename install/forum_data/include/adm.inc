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

fud_use('db.inc');

if (!defined('shell_script')) {
	if (php_sapi_name() == 'cli') {	// adm_cli.inc wasn't called.
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

if (!($GLOBALS['usr']->users_opt & 1048576)) {	// is_admin
	if ($GLOBALS['usr']->users_opt & 268435456) {	// account moderator
		switch (basename($_SERVER['PHP_SELF'])) {
			case 'admaccapr.php':
			case 'admadduser.php':
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
}

/* Disable ACP caching, indexing, etc. */
header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past.
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

/* DEFINE COMMON FUNCTIONS USED IN ACP BELOW. */

/* Print string to web browser or command line. */
function pf($str, $webonly=false)
{
	if (php_sapi_name() == 'cli') {
		if ($webonly) return;
		echo strip_tags($str) ."\n";
	} else {
		echo $str .'<br />';
		@ob_flush(); flush();
	}
}

/* Format date. */
function fdate($format, $date)
{
	if (stristr($GLOBALS['charset'], 'utf-8') !== false && substr(PHP_OS, 0, 3) == 'WIN') {
		// Windows doesn't have UTF-8 locales.
		return utf8_encode(strftime($format, $date));
	} else {
		return strftime($format, $date);
	}
}

/* Format string as a success message. */
function successify($msg)
{
	return '<span style="color:green;">'. $msg .'</span><br />';
}

/* Format string as an error message. */
function errorify($err)
{
	return '<span style="color:red;">'. $err .'</span><br />';
}
?>
