<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: adm.inc,v 1.17 2003/09/18 16:49:29 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if (!defined('shell_script')) {
	$r = $GLOBALS['USE_PATH_INFO'];
	$GLOBALS['USE_PATH_INFO'] = 'N';
	define('fud_theme', 'theme/default/');
	fud_use('db.inc');
	fud_use('cookies.inc');
	fud_use('users.inc');
	$GLOBALS['USE_PATH_INFO'] = $r;
}

define('__fud_index_name__', 'index.php');

if ($GLOBALS['usr']->is_mod != 'A') {
	header('Location: admloginuser.php?'._rsidl);
        exit;
}
if (isset($_POST['btn_cancel'])) {
	if (defined('back_to_main')) {
		header('Location: admglobal.php?'._rsidl);
		exit;
	}
	$_POST = $_GET = NULL;
}
?>