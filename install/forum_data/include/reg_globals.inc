<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: reg_globals.inc,v 1.6 2002/11/25 00:48:34 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function fud_request_add_gns(&$val, $key)
{
	if( !is_array($val) ) {
		if( isset($GLOBALS[$key]) ) return;
		$GLOBALS[$key] = $val = addslashes($val);
	} else {	
		if( !isset($GLOBALS[$key]) ) {
			foreach($val as $k => $v) {
				$GLOBALS[$key][$k] = addslashes($v);
			}	
		}	
	}
}

function fud_request_add_globals(&$val, $key)
{
	if( !is_array($val) ) {
		if (isset($GLOBALS[$key])) return;
		$GLOBALS[$key] = $val;
	} else {	
		if (!isset($GLOBALS[$key])) {
			foreach($val as $k => $v) {
				$GLOBALS[$key][$k] = $v;
			}	
		}	
	}
}

function fud_request_add_slashes(&$val, $key)
{
	if( !is_array($val) ) {
		if ($GLOBALS[$key] == $val) {
			$GLOBALS[$key] = $val = addslashes($val);
		}
	} else {	
		foreach($val as $k => $v) {
			if ($GLOBALS[$key][$k] == $v) {
				$GLOBALS[$key][$k] = addslashes($v);
			}
		}	
	}
}

function fud_variable_register($mode)
{
	reset($GLOBALS['HTTP_GET_VARS']);
	array_walk($GLOBALS['HTTP_GET_VARS'], $mode);
	reset($GLOBALS['HTTP_GET_VARS']);

	reset($GLOBALS['HTTP_POST_VARS']);
	array_walk($GLOBALS['HTTP_POST_VARS'], $mode);
	reset($GLOBALS['HTTP_POST_VARS']);
	
	foreach($GLOBALS['HTTP_POST_FILES'] as $k => $v) { 
		if ($mode != 'fud_request_add_globals') {
			foreach($v as $k2 => $v2) {
				$GLOBALS[$k.'_'.$k2] = addslashes($v2);
			}
		} else {
			foreach($v as $k2 => $v2) {
				$GLOBALS[$k.'_'.$k2] = $v2;
			}
		}
		$GLOBALS[$k] = $v['tmp_name'];
	}

	reset($GLOBALS['HTTP_COOKIE_VARS']);
	array_walk($GLOBALS['HTTP_COOKIE_VARS'], $mode);
	reset($GLOBALS['HTTP_COOKIE_VARS']);
}	
?>