<?
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: root_index.php.t,v 1.2 2002/06/18 14:20:38 hackie Exp $
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
		$r = Q("SELECT name, locale FROM {SQL_TABLE_PREFIX}themes WHERE t_default='Y'");
	else
		$r = Q("SELECT name, locale FROM {SQL_TABLE_PREFIX}themes WHERE id=$usr->theme");

	$theme = DB_SINGLEOBJ($r);
	
	setlocale(LC_ALL, $theme->locale);
	if ( preg_match('/[^A-Za-z0-9_]/', $pg) ) exit("<html>This is an invalid request</html>\n");
	
	define('__index_page_start__', TRUE);
	require('theme/'.$theme->name.'/'.$pg.'.php');
?>