<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: core.inc,v 1.115 2004/06/07 15:24:53 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

$FORUM_VERSION = "2.6.4RC2";

ignore_user_abort(true);
set_magic_quotes_runtime(0);

function rls_db_lck()
{
	if (connection_status() && defined('fud_sql_lnk') && db_locked()) {
		db_unlock();
	}
	return;
}

register_shutdown_function('rls_db_lck');

$GLOBALS['TITLE_EXTRA'] = '';
$GLOBALS['is_post'] = !empty($_POST);

if (get_magic_quotes_gpc()) {
	function strip_quotes(&$var)
	{
        	if (is_array($var)) {
			array_walk($var, 'strip_quotes');
		} else {
			$var = stripslashes($var);
		}
	}

	if (!empty($_GET)) {
		array_walk($_GET, 'strip_quotes');
	}
        if ($GLOBALS['is_post']) {
		array_walk($_POST, 'strip_quotes');
	}
        if (!empty($_COOKIES)) {
		array_walk($_COOKIES, 'strip_quotes');
	}
	if (!empty($_FILES)) {
		array_walk($_FILES, 'strip_quotes');
	}
}

$GLOBALS['PAGE_TIME'] = gettimeofday();
define('__request_timestamp__', $GLOBALS['PAGE_TIME']['sec']);
mt_srand($GLOBALS['PAGE_TIME']['usec']);

function fud_use($file, $static=false)
{
	if ($static) {
		include_once $GLOBALS['INCLUDE'] . $file;
		return;
	}
	if (!defined('fud_theme')) {
		define('fud_theme', 'theme/default/');
	}
	include_once $GLOBALS['INCLUDE'] . fud_theme . $file;
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

function get_ip()
{
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip = strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ',');
		do {
			if (($lip = ip2long($ip)) === -1) {
				continue;
			}
			/* some disallowed ip ranges, that are normally used for private networks
			 * 10.0.0.0 - 10.255.255.255
			 * 172.16.0.0 - 172.31.255.255
			 * 192.168.0.0 - 192.168.255.255
			 * 127.0.0.1, 255.255.255.255, 0.0.0.0
			*/
			if (!$lip || (($lip >= 167772160) && ($lip <= 184549375)) || (($lip >= -1408237568) && ($lip <= -1407188993)) || (($lip >= -1062731776) && ($lip <= -1062666241)) || ($lip == 2130706433)) {
				continue;
			}
			return long2ip(ip2long($ip));
		} while (($ip = strtok(',')));
	}
	return (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0');
}

function ssn($str)
{
	if (is_null($str) || $str === '') {
		return 'NULL';
	} else {
		return "'" . addslashes($str) . "'";
	}
}

/* Common DB functions */
function intzero($val)
{
	return (!empty($val) ? $val : '0');
}

function strnull($val)
{
	return (!is_null($val) ? "'".$val."'" : 'NULL');
}
/* END: Common DB Functions */

function __ffilesize($fp)
{
	$st = fstat($fp);
	return (isset($st['size']) ? $st['size'] : $st[7]);
}

if (!function_exists('file_get_contents')) {
	function file_get_contents($fname)
	{
		if (!($fp = @fopen($fname, 'rb'))) {
			return false;
		}
		/* this handles urls */
		if (!($size = @__ffilesize($fp))) {
			$size = 2 * 1024 * 1024;
		}
		$data = fread($fp, $size);
		fclose($fp);
		return $data;
	}
}
?>