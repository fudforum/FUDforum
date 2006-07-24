<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: adm.inc,v 1.36 2006/07/24 18:43:46 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

if (!defined('shell_script')) {
	if ($GLOBALS['FUD_OPT_2'] & 32768) {
		$GLOBALS['FUD_OPT_2'] ^= 32768;
		$t = 1;
	}
	fud_use('db.inc');
	fud_use('cookies.inc');
	fud_use('users.inc');
	define('__adm_rsidl', _rsidl.'&SQ='.$GLOBALS['sq']);
	if (isset($t)) {
		$GLOBALS['FUD_OPT_2'] |= 32768;
	}
}

define('__fud_index_name__', 'index.php');

if (!($GLOBALS['usr']->users_opt & 1048576)) {
	if ($GLOBALS['usr']->users_opt & 268435456) {
		if (basename($_SERVER['PHP_SELF']) != 'admuser.php') {
			header('Location: '.$GLOBALS['WWW_ROOT'].'adm/admuser.php?'.__adm_rsidl);
			exit;
		}
	} else {
		header('Location: '.$GLOBALS['WWW_ROOT'].'adm/admloginuser.php?'.__adm_rsidl);
        	exit;
	}
}
if (isset($_POST['btn_cancel'])) {
	if (defined('back_to_main')) {
		header('Location: '.$GLOBALS['WWW_ROOT'].'adm/admglobal.php?'.__adm_rsidl);
		exit;
	}
	$_POST = $_GET = null;
}
if (!count($_POST)) {
	sq_check(0, $GLOBALS['usr']->sq);
}
function errorify($err)
{
	return '<font color="red">'.$err.'</font><br />';
}
?>