<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: reg_globals.inc,v 1.4 2002/11/24 23:22:19 hackie Exp $
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
		if( !isset($GLOBALS[$key]) ) {
			foreach($val as $k => $v) {
				$GLOBALS[$key][$k] = $fmt_func ? $fmt_func($v) : $v;
			}	
		}	
	}
}

	$fmt_func = get_magic_quotes_gpc() ? '' : 'gpc_fmt'; 

	reset($GLOBALS['HTTP_GET_VARS']);
	array_walk($GLOBALS['HTTP_GET_VARS'], 'mod_arr_val', $fmt_func);
	reset($GLOBALS['HTTP_GET_VARS']);

	reset($GLOBALS['HTTP_POST_VARS']);
	array_walk($GLOBALS['HTTP_POST_VARS'], 'mod_arr_val', $fmt_func);
	reset($GLOBALS['HTTP_POST_VARS']);
	
	foreach($GLOBALS['HTTP_POST_FILES'] as $k => $v) { 
		foreach($v as $k2 => $v2) $GLOBALS[$k.'_'.$k2] = !$fmt_func ? $v2 : $fmt_func($v2);
		$GLOBALS[$k] = $v['tmp_name'];
	}
	reset($GLOBALS['HTTP_POST_FILES']);
	unset($k); unset($v); unset($k2); unset($v2);

	reset($GLOBALS['HTTP_COOKIE_VARS']);
	array_walk($GLOBALS['HTTP_COOKIE_VARS'], 'mod_arr_val', $fmt_func);
	reset($GLOBALS['HTTP_COOKIE_VARS']);
?>