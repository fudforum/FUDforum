<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: root_index.php.t,v 1.13 2003/04/06 13:36:48 hackie Exp $
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
	fud_use('init_errors.inc');
	fud_use('err.inc');
	
/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if (!isset($_REQUEST['t']) || preg_match('/[^A-Za-z0-9_]/', $_REQUEST['t'])) {
		$_REQUEST['t'] = 'index';
	}

	define('__index_page_start__', true);
	require($GLOBALS['DATA_DIR'] . fud_theme . $_REQUEST['t'] . '.php');
?>