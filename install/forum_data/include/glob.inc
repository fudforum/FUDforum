<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: glob.inc,v 1.1 2002/06/26 19:44:35 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
 if ( defined('_glob_inc_') ) return; else define('_glob_inc_', 1);

function read_global_config()
{
	return filetomem($GLOBALS['__GLOBALS.INC__']);
}

function write_global_config($data)
{
	$fp = fopen($GLOBALS['__GLOBALS.INC__'], 'wb');
		fwrite($fp, $data);
	fclose($fp);
}

function change_global_val($name, $val, &$data)
{
	if( ($s=strpos($data, '$'.$name." ")) === false ) $s=strpos($data, '$'.$name."\t");

	if( $s !== false ) {
		$s = strpos($data, '"', $s)+1;
		$e = strpos($data, '";', $s);
		
		$data = substr_replace($data, $val, $s, ($e-$s));
	}
	else { /* Adding new option */
		$s = strpos($data, '$ALLOW_REGISTRATION')-1;
		$data = substr_replace($data, "\t\$$name\t= \"$val\";\n", $s, 0);
	}
}

function global_config_ar($data)
{
	$ar = array();

	while( ($pos = strpos($data, '$')) ) {
		$line = substr($data, $pos, (($le=strpos($data, "\n", $pos))-$pos));
		
		$tp = strpos($line, "\t");
		$ts = strpos($line, " ");
		
		if( $tp === false )
			$key_end = $ts;
		else if( $ts === false )
			$key_end = $tp;	
		else if( $ts > $tp ) 
			$key_end = $tp;	
		else if( $tp > $ts )
			$key_end = $ts;	
		
		$key = rtrim(substr($line, 1, $key_end-1));	
		if( $key == strtoupper($key) && !strpos($key, ']') ) {
			if( ($vs = strpos($line, '"', $key_end)) ) {
				$vs++;
				$ve = strpos($line, '";', $vs);
				$val = substr($line, $vs, ($ve-$vs));
				
				$ar[$key]=$val;
			}
		}
		
		$data = substr($data, $le+1);
	}
	
	return $ar;
}

function read_help()
{
	$fp = fopen($GLOBALS['INCLUDE'].'GLOBALS_HELP', 'rb');
		$data = fread($fp, filesize($GLOBALS['INCLUDE'].'GLOBALS_HELP'));
	fclose($fp);
	
	$pos = 0;
	$len = filesize($GLOBALS['INCLUDE'].'GLOBALS_HELP');
	
	$help_ar = array();
	
	while( $pos<$len ) {
		if( ord($data[$pos]) >= ord('A') && ord($data[$pos]) <= ord('Z') ) {
			$end = strpos($data, "\n", $pos);
			$dt = substr($data, $pos, ($end-$pos));
			if( strtoupper($dt) == $dt ) {
				$end2 = strpos($data, ".\n\n", $end);
				$help_ar[$dt] = str_replace("\n\n", '<br>', htmlspecialchars(substr($data, $end+1, ($end2-$end))));
			}
			
			$pos = $end+1;
			continue;
		}
		else {
			$pos = strpos($data, "\n", $pos);
			$pos++;
		}	
			
		if( !$pos ) break;	
	}
	
	return $help_ar;
}

function maintenance_status($reason, $status)
{
	$global_config = read_global_config();
	change_global_val('FORUM_ENABLED', $status, $global_config);
	change_global_val('DISABLED_REASON', $reason, $global_config);
	write_global_config($global_config);
}
?>