<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: fileio.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
define('_fileio_inc_', 1);


function register_fp($id)
{
	if( empty($GLOBALS['__MSG_FP__'][$id]) ) 
		$GLOBALS['__MSG_FP__'][$id] = fopen($GLOBALS["MSG_STORE_DIR"].'msg_'.$id, 'rb');
	
	return $GLOBALS['__MSG_FP__'][$id];
}

function un_register_fps()
{
	if( !@is_array($GLOBALS['__MSG_FP__']) ) return;
	
	reset($GLOBALS['__MSG_FP__']);
	while( list($k,$v) = each($GLOBALS['__MSG_FP__']) ) {
		if( @is_resource($v) ) fclose($v);
		$GLOBALS['__MSG_FP__'][$k] = NULL;
	}	
}

function read_msg_body($off, $len, $file_id)
{
	$fp = register_fp($file_id);
	fseek($fp, $off);
	return fread($fp, $len);
}
?>