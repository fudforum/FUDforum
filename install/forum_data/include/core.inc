<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: core.inc,v 1.55 2003/04/24 21:35:23 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$FORUM_VERSION = "2.3.9RC1";

error_reporting(E_ALL);
ignore_user_abort(true);

function rls_db_lck()
{
	if (connection_status() && defined('FUD_SQL_LNK') && db_locked()) {
		db_unlock();
	}
	return;
}

register_shutdown_function('rls_db_lck');

$GLOBALS['TITLE_EXTRA']=NULL;

if (!isset($_SERVER['PATH_TRANSLATED']) && isset($_SERVER['SCRIPT_FILENAME'])) {
	$_SERVER['PATH_TRANSLATED'] = $_SERVER['SCRIPT_FILENAME'];
}

if (!isset($_SERVER['REQUEST_URI']) && isset($_SERVER['SCRIPT_NAME'], $_SERVER['QUERY_STRING'])) {
	$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'];
}

if (get_magic_quotes_gpc()) {
	function strip_quotes(&$var) 
	{
        	if (is_array($var)) {
			array_walk($var, 'strip_quotes');
		} else {
			$var = stripslashes($var);
		}
	}

	if (@count($_GET)) {
		array_walk($_GET, 'strip_quotes');
	}
        if (@count($_POST)) {
		array_walk($_POST, 'strip_quotes');
	}
        if (@count($_COOKIES)) {
		array_walk($_COOKIES, 'strip_quotes');
	}
	if (@count($_FILES)) {
		array_walk($_FILES, 'strip_quotes');
	}
}
                                                                                                                                                                                        
define('__request_timestamp__', time());

function fud_use($file, $static=false)
{
	if ($static) {
		include_once $GLOBALS['INCLUDE'] . $file;
	} else if (defined('admin_form') || !defined('fud_theme')) {
		include_once $GLOBALS['INCLUDE'] . 'theme/default/' . $file;
	} else {
		include_once $GLOBALS['INCLUDE'] . fud_theme . $file;
	}
}

function make_seed() 
{
	mt_srand((double)microtime()*1000000);
}

function get_random_value($bitlength=32)
{
	$n = round($bitlength/32);
	$v='';
	while (--$n > -1) {
		$v .= mt_rand();
	}
	return $v;
}

if (!function_exists('file_get_contents')) {
	function file_get_contents($fname)
	{
		if (!($fp = @fopen($fname, 'rb'))) {
			return FALSE;
		}
		$data = fread($fp, filesize($fname));
		fclose($fp);
		return $data;
	}
}

function __ffilesize($fp)
{
	$st = fstat($fp);
	return (isset($st['size']) ? $st['size'] : $st[7]);
}

make_seed();

$GLOBALS['PAGE_TIME'] = gettimeofday();
?>