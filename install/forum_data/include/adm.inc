<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: adm.inc,v 1.45 2009/09/30 16:47:32 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

fud_use('db.inc');

if (!defined('shell_script')) {
	if (php_sapi_name() == 'cli') {	// adm_cli.inc wasn't called.
		echo "Cannot run from command line.\n";
		exit();
	}

	if ($GLOBALS['FUD_OPT_2'] & 32768) {	// USE_PATH_INFO
		$GLOBALS['FUD_OPT_2'] ^= 32768;
		$t = 1;
	}
	fud_use('cookies.inc');
	fud_use('users.inc');
	define('__adm_rsid', _rsidl.'&SQ='.$GLOBALS['sq']);
	define('__adm_rsidl', _rsidl.'&SQ='.$GLOBALS['sq']);
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
			case 'admuser.php':
				break;
			default:
				header('Location: '.$GLOBALS['WWW_ROOT'].'adm/admuser.php?'.__adm_rsidl);
				exit;
		}
	} else {
		header('Location: '.$GLOBALS['WWW_ROOT'].'adm/admloginuser.php?'.__adm_rsidl);
        	exit;
	}
}

function pf($str)
{
	if (php_sapi_name() == 'cli') {
		echo strip_tags($str) ."\n";
	} else {
		echo $str .'<br />';
		@ob_flush(); flush();
	}
}

function errorify($err)
{
	return '<span style="color:red;">'.$err.'</span><br />';
}
?>
