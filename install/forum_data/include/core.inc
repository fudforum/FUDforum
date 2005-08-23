<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: core.inc,v 1.162 2005/08/23 22:59:46 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

$FORUM_VERSION = "2.7.1";

ignore_user_abort('1');
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
$GLOBALS['is_aol'] = (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'AOL') !== false);

if (get_magic_quotes_gpc()) {
	$in = array(&$_GET, &$_POST, &$_COOKIE);
	while (list($k,$v) = each($in)) {
		foreach ($v as $key => $val) {
			if (!is_array($val)) {
				$in[$k][$key] = stripslashes($val);
				continue;
			}
			$in[] =& $in[$k][$key];
		}
	}
	unset($in);

	if (!empty($_FILES)) {
		foreach ($_FILES as $k => $v) {
			$_FILES[$k]['name'] = stripslashes((string)$v['name']);
		}
	}
}

$GLOBALS['PAGE_TIME'] = gettimeofday();
define('__request_timestamp__', $GLOBALS['PAGE_TIME']['sec']);
mt_srand($GLOBALS['PAGE_TIME']['usec']);

function fud_use($file, $static=0)
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
	if ($str === null || $str === '') {
		return 'NULL';
	} else {
		return "'" . addslashes($str) . "'";
	}
}

function char_fix($str)
{
        if (strpos($str, '&amp;#') === false) {
		return $str;
	}
        return preg_replace('!&amp;#([0-9]+);!', '&#\1;', $str);
}

function __ffilesize($fp)
{
	$st = fstat($fp);
	return (isset($st['size']) ? $st['size'] : $st[7]);
}

function __fud_cache($t)
{
	if ($GLOBALS['is_aol']) {
		return 0;
	}
	$mod = gmdate("D, d M Y H:i:s", $t) . " GMT";

	/* send caching headers */
	header("Last-Modified: " . $mod);
	if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && !isset($_SERVER['HTTP_RANGE']) && $_SERVER["HTTP_IF_MODIFIED_SINCE"] == $mod) {
		header('HTTP/1.1 304 Not Modified');
		header('Status: 304 Not Modified');
		return 1;
	}
	return 0;
}

function fud_sql_error_handler($query, $error_string, $error_number, $server_version)
{
	if (db_locked() && !in_array($query, array('COMMIT','COMMIT WORK','UNLOCK TABLES'))) {
		db_unlock();
	}

	$_SERVER['PATH_TRANSLATED'] = '';
	foreach (debug_backtrace() as $v) {
		$_SERVER['PATH_TRANSLATED'] .= "{$v['file']}:{$v['line']}<br />\n";
	}

	$error_msg = "(".$_SERVER['PATH_TRANSLATED'].") ".$error_number.": ".$error_string."<br />\n";
	$error_msg .= "Query: ".htmlspecialchars($query)."<br />\n";
	if (!empty($_GET)) {
		$error_msg .= "_GET: ";
		if (count($_GET, 1) < 100) {
			$error_msg .= htmlspecialchars(var_export($_GET, 1));
		} else {
			$error_msg .= "Too many vars: ".count($_GET, 1);
		}
		$error_msg .= "<br />\n";
	}
	if (!empty($_POST)) {
		$error_msg .= "_POST: ";
		if (count($_POST, 1) < 100) {
			$error_msg .= htmlspecialchars(var_export($_POST, 1));
		} else {
			$error_msg .= "Too many vars: ".count($_POST, 1);
		}
		$error_msg .= "<br />\n";
	}
	$error_msg .= "Server Version: ".$server_version."<br />\n";
	if (isset($_SERVER['HTTP_REFERER'])) {
		$error_msg .= "[Referring Page] ".htmlspecialchars($_SERVER['HTTP_REFERER'])."<br />\n";
	}

	if (!error_log('['.gmdate("D M j G:i:s T Y", __request_timestamp__).'] '.base64_encode($error_msg)."\n", 3, $GLOBALS['ERROR_PATH'].'sql_errors')) {
		echo "<b>UNABLE TO WRITE TO SQL LOG FILE</b><br>\n";
		echo $error_msg;
	} else {
		if (defined('forum_debug') || (isset($GLOBALS['usr']->users_opt) && $GLOBALS['usr']->users_opt & 1048576)) {
			echo $error_msg;
		} else {
			trigger_error('SQL Error has occurred, please contact the <a href="mailto:'.$GLOBALS['ADMIN_EMAIL'].'?subject=SQL%20Error">administrator</a> of the forum and have them review the forum&#39;s SQL query log', E_USER_ERROR);
			if (ini_get('display_errors') !== 1) {
				exit('SQL Error has occurred, please contact the <a href="mailto:'.$GLOBALS['ADMIN_EMAIL'].'?subject=SQL%20Error">administrator</a> of the forum and have them review the forum&#39;s SQL query log');
			}
		}
	}
	exit;
}
?>