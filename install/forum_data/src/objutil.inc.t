<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: objutil.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	
function fetch_vars($pref, &$obj, $arr)
{
	reset($obj);
	while ( list($k, $v) = each($obj) ) {
		if ( isset($arr[$pref.$k]) ) $obj->{$k} = $arr[$pref.$k];
	}
}
	
function export_vars($pref, &$obj)
{
	reset($obj);
	while ( list($k, $v) = each($obj) ) {
		if ( isset($obj->{$k}) ) $GLOBALS[$pref.$k] = $v;
	}
}

function empty_object(&$obj)
{
	reset($obj);
	while ( list($k, $v) = each($obj) ) $obj->{$k} = NULL;
}
?>