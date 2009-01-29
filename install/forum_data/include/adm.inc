<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: adm.inc,v 1.43 2009/01/29 18:37:17 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

if (!defined('shell_script')) {
	if ($GLOBALS['FUD_OPT_2'] & 32768) {
		$GLOBALS['FUD_OPT_2'] ^= 32768;
		$t = 1;
	}
	fud_use('db.inc');
	fud_use('cookies.inc');
	fud_use('users.inc');
	define('__adm_rsid', _rsidl.'&amp;SQ='.$GLOBALS['sq']);
	define('__adm_rsidl', _rsidl.'&SQ='.$GLOBALS['sq']);
	if (isset($t)) {
		$GLOBALS['FUD_OPT_2'] |= 32768;
	}
}

define('__fud_index_name__', 'index.php');

if (!($GLOBALS['usr']->users_opt & 1048576)) {
	if ($GLOBALS['usr']->users_opt & 268435456) {
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
if (!count($_POST)) {
	sq_check(0, $GLOBALS['usr']->sq);
}
if (isset($_POST['btn_cancel'])) {
	if (defined('back_to_main')) {
		header('Location: '.$GLOBALS['WWW_ROOT'].'adm/admglobal.php?'.__adm_rsidl);
		exit;
	}
	$_POST = $_GET = null;
}
function errorify($err)
{
	return '<font color="red">'.$err.'</font><br />';
}
?>