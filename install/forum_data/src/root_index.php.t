<?
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: root_index.php.t,v 1.8 2002/08/14 12:02:46 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	include_once "GLOBALS.php";
	
	{PRE_HTML_PHP}
	{POST_HTML_PHP}

	$pg = ( !empty($HTTP_POST_VARS['t']) ) ? $HTTP_POST_VARS['t'] : $HTTP_GET_VARS['t'];
	if ( empty($pg) ) $pg = 'index';

	if ( !$usr->theme )
		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}themes WHERE t_default='Y'");
	else
		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}themes WHERE id=".$usr->theme);

	$GLOBALS['FUD_THEME'] = db_singleobj($r);
	define('__fud_theme_id__', $GLOBALS['FUD_THEME']->id);
	
	setlocale(LC_ALL, $GLOBALS['FUD_THEME']->locale);
	if ( preg_match('/[^A-Za-z0-9_]/', $pg) ) exit("<html>This is an invalid request</html>\n");
	
	define('__index_page_start__', true);
	fud_use('err.inc');
	require('theme/'.$GLOBALS['FUD_THEME']->name.'/'.$pg.'.php');
?>