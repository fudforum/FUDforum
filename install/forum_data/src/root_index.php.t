<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: root_index.php.t,v 1.18 2003/05/13 06:36:05 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require('GLOBALS.php');

	/* before we go on, we need to do some very basic activation checks */
	if ($FORUM_ENABLED != 'Y') {
		fud_use('cfg.inc', TRUE);
		exit(cfg_dec($DISABLED_REASON) . '{TEMPLATE: core_adm_login_msg}');
	}
	if (!$FORUM_TITLE && @file_exists($WWW_ROOT_DISK.'install.php')) {
	        exit('{TEMPLATE: install_script_present_error}');
	}

	fud_use('err.inc');

	if ($USE_PATH_INFO == 'Y' && !empty($_SERVER['PATH_INFO'])) {
		fud_use('path_info.inc');
	}
	
/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if (isset($_GET['t'])) {
		$t = $_GET['t'];
	} else if (isset($_POST['t'])) {
		$t = $_POST['t'];
	}
	if (!isset($t) || preg_match('/[^A-Za-z0-9_]/', $t) || !@file_exists($WWW_ROOT_DISK . fud_theme . $t . '.php')) {
		$t = 'index';
	}

	define('__index_page_start__', true);
	require($WWW_ROOT_DISK . fud_theme . $t . '.php');
?>