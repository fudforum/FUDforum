<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: reg_globals.inc,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function gpc_fmt($str)
{
	return addslashes($str);
}

function mod_arr_val(&$val, $key, $fmt_func)
{
	if( !is_array($val) ) {
		if( isset($GLOBALS[$key]) ) return;
		
		if( $fmt_func ) $val = $fmt_func($val);
		$GLOBALS[$key] = $val;
	}
	else {	
		reset($val);
		while( list($k,$v) = each($val) ) {
			if( !isset($GLOBALS[$key]) ) $GLOBALS[$key][$k] = $fmt_func ? $fmt_func($v) : $v;
		}
		reset($val);
	}
}

	$fmt_func = ini_get('magic_quotes_gpc') ? '' : 'gpc_fmt'; 

	reset($GLOBALS['HTTP_GET_VARS']);
	array_walk($GLOBALS['HTTP_GET_VARS'], 'mod_arr_val', $fmt_func);
	reset($GLOBALS['HTTP_GET_VARS']);

	reset($GLOBALS['HTTP_POST_VARS']);
	array_walk($GLOBALS['HTTP_POST_VARS'], 'mod_arr_val', $fmt_func);
	reset($GLOBALS['HTTP_POST_VARS']);
	
	reset($GLOBALS['HTTP_POST_FILES']);
	while ( list($k, $v) = each($GLOBALS['HTTP_POST_FILES']) ) {
		while ( list($k2, $v2) = each($v) ) {
			$GLOBALS[$k.'_'.$k2] = !$fmt_func ? $v2 : $fmt_func($v2);
		}
		$GLOBALS[$k] = $v['tmp_name'];
	}
	reset($GLOBALS['HTTP_POST_FILES']);
	unset($k); unset($v); unset($k2); unset($v2);

	reset($GLOBALS['HTTP_COOKIE_VARS']);
	array_walk($GLOBALS['HTTP_COOKIE_VARS'], 'mod_arr_val', $fmt_func);
	reset($GLOBALS['HTTP_COOKIE_VARS']);
?>