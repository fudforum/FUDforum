<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: replace.inc.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function apply_custom_replace($text)
{
	if ( !($arr = make_replace_array()) ) return $text;
	return preg_replace($arr['pattern'], $arr['replace'], $text);
}

function make_replace_array()
{
	$res = q("SELECT * FROM {SQL_TABLE_PREFIX}replace WHERE replace_str IS NOT NULL AND with_str IS NOT NULL");
	if ( !is_result($res) ) return;
	
	$arr['pattern'] = array();
	$arr['replace'] = array();
	
	while ( $obj = db_rowobj($res) ) {
		if ( strlen($obj->with_str) && strlen($obj->replace_str) ) { 
			$arr['pattern'][] = $obj->replace_str;
			$arr['replace'][] = $obj->with_str;
		}	
	}
	
	qf($res);
	
	return $arr;
}

function make_reverse_replace_array()
{
	$res = q("SELECT * FROM {SQL_TABLE_PREFIX}replace");
	if ( !is_result($res) ) return;
	
	$arr['pattern'] = array();
	$arr['replace'] = array();
	
	while ( $obj = db_rowobj($res) ) {
		if ( $obj->type == 'PERL' && strlen($obj->from_post) && strlen($obj->to_msg) ) {
			$arr['pattern'][] = $obj->from_post;
			$arr['replace'][] = $obj->to_msg;
		}
		else if ( $obj->type == 'REPLACE' && strlen($obj->with_str) && strlen($obj->replace_str) ) {
			$arr['pattern'][] = '/'.str_replace('/', '\\/', preg_quote(stripslashes($obj->with_str))).'/';
			preg_match('/\/(.+)\/(.*)/', $obj->replace_str, $regs);
			$obj->replace_str = str_replace('\\/', '/', $regs[1]);
			$arr['replace'][] = $obj->replace_str;
		}
	}
	qf($res);

	return $arr;
}

function apply_reverse_replace($text)
{
	if ( !($arr = make_reverse_replace_array()) ) return $text;
	return preg_replace($arr['pattern'], $arr['replace'], $text);
}
?>