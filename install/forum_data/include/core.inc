<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: core.inc,v 1.43 2002/12/06 16:24:14 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$FORUM_VERSION = "2.3.6RC3";

error_reporting(E_ALL & ~E_NOTICE);
ignore_user_abort(true);

function rls_db_lck()
{
	if( connection_status() && !empty($GLOBALS['__DB_INC__']['SQL_LINK']) && db_locked() ) db_unlock();
	return;
}

register_shutdown_function("rls_db_lck");

$GLOBALS['MOD']=$GLOBALS['TITLE_EXTRA']=NULL;

if( !isset($HTTP_SERVER_VARS['PATH_TRANSLATED']) && isset($HTTP_SERVER_VARS['SCRIPT_FILENAME']) ) 
	$HTTP_SERVER_VARS['PATH_TRANSLATED'] = $GLOBALS['HTTP_SERVER_VARS']['PATH_TRANSLATED'] = $HTTP_SERVER_VARS['SCRIPT_FILENAME'];

if( empty($GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']) ) 
	if( empty($GLOBALS['REQUEST_URI']) ) 
		$GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'] = $GLOBALS['REQUEST_URI'] = $GLOBALS['HTTP_SERVER_VARS']['SCRIPT_NAME'].'?'.$GLOBALS['HTTP_SERVER_VARS']['QUERY_STRING'];
	else 
		$GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'] = $GLOBALS['REQUEST_URI'];

if( !empty($GLOBALS['returnto']) ) $GLOBALS['returnto'] = stripslashes($GLOBALS['returnto']);



if (!($reg_globals=ini_get('register_globals')) || !($magic_quotes=get_magic_quotes_gpc())) {
	fud_use('reg_globals.inc', true);
	
	if (!$reg_globals && !$magic_quotes) {
		fud_variable_register('fud_request_add_gns');
	} else if (!$magic_quotes) {
		fud_variable_register('fud_request_add_slashes');
	} else if (!$reg_globals) {
		fud_variable_register('fud_request_add_globals');
	}
}

define('__request_timestamp__', time());

function fud_use($file, $static=false)
{
	if ( !$static && !defined('admin_form') && isset($GLOBALS['FUD_THEME']) ) 
		$thm = 'theme/'.$GLOBALS['FUD_THEME']->name.'/';
	else if ( (defined('admin_form') || !isset($GLOBALS['FUD_THEME'])) && !$static ) 
		$thm = 'theme/default/';
	else $thm = '';
	
	include_once $GLOBALS["INCLUDE"].$thm.$file;
}

function db_getmicrotime() 
{
	$tm_ar = gettimeofday();
	return ($tm_ar['sec']+$tm_ar['usec']/1000000);
}

function make_seed() 
{
	mt_srand((double)microtime()*1000000);
}

function get_random_value($bitlength=32)
{
	$n=round($bitlength/32);
	$v='';
	for( $i=0; $i<$n; $i++ ) $v .= mt_rand();
	return $v;
}

function start_bench($name, $pool='default')
{
	$GLOBALS['__BENCH_ARR__'][$pool][$name] = db_getmicrotime();
}

function end_bench($name, $pool='default')
{
	$end = db_getmicrotime();
	$GLOBALS['__BENCH_RESULT__'][$pool][$name]['runtime'] =  $end - $GLOBALS['__BENCH_ARR__'][$pool][$name];
	unset($GLOBALS['__BENCH_ARR__'][$pool][$name]);
	return $GLOBALS['__BENCH_RESULT__'][$pool][$name]['runtime'];
}

function get_bench($name, $pool='default')
{
	return $GLOBALS['__BENCH_RESULT__'][$pool][$name]['runtime'];
}

function del_pool_entry($name, $pool='default')
{
	unset($GLOBALS['__BENCH_RESULT__'][$pool][$name]);
}

function get_pool($pool='default')
{
	return $GLOBALS['__BENCH_RESULT__'][$pool];
}

function sum_pool($pool='default')
{
	$bp = &$GLOBALS['__BENCH_RESULT__'][$pool];
	
	$bt = 0;
	foreach($bp as $v) $bt += $v['runtime'];
	foreach($bp as $k => $v) $GLOBALS['__BENCH_RESULT__'][$pool][$k]['fract'] = round($v['runtime']/$bt*100).'%';
	
	return $bt;
}

function __ffilesize($fp)
{
	$st = fstat($fp);
	return (isset($st['size']) ? $st['size'] : $st[7]);
}

make_seed();

$GLOBALS['PAGE_TIME'] = db_getmicrotime();
unset($GLOBALS['BENCH_MARKS']);

?>