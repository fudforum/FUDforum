<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: root_index.php.t,v 1.12 2003/03/30 18:03:11 hackie Exp $
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
	
	{PRE_HTML_PHP}
	{POST_HTML_PHP}

	if (!isset($_REQUEST['t']) || preg_match('/[^A-Za-z0-9_]/', $_REQUEST['t'])) {
		$_REQUEST['t'] = 'index';
	}

	if (!$usr->theme) {
		$r = uq('SELECT id,lang,name,locale,theme,pspell_lang FROM {SQL_TABLE_PREFIX}themes WHERE t_default=\'Y\'');
	} else {
		$r = uq('SELECT id,lang,name,locale,theme,pspell_lang FROM {SQL_TABLE_PREFIX}themes WHERE id='.$usr->theme);
	}

	$GLOBALS['FUD_THEME'] = db_singlearr($r);
	define('__fud_theme_id__', $GLOBALS['FUD_THEME'][0]);
	
	setlocale(LC_ALL, $GLOBALS['FUD_THEME'][3]);
	
	define('__index_page_start__', true);
	require($GLOBALS['DATA_DIR'] . 'theme/' . $GLOBALS['FUD_THEME'][2] . '/' . $_REQUEST['t'] . '.php');
?>