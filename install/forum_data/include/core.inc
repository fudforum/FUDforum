<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

$FORUM_VERSION = '3.0.3RC1';

ignore_user_abort('1');
@set_magic_quotes_runtime(0);	// Deprecated as of PHP 5.3.0.

$GLOBALS['RSS'] = $GLOBALS['TITLE_EXTRA'] = '';
$GLOBALS['is_post'] = !empty($_POST);

define('__request_timestamp__', time());
define('__request_timestamp_exact__', microtime(true)); // For page stats.

register_shutdown_function('release_db_lock');
function release_db_lock()
{
	if (connection_status() && defined('fud_sql_lnk') && db_locked()) {
		db_unlock();
	}
}

// Sanity checks.
if (isset($_REQUEST['GLOBALS']) || isset($_FILES['GLOBALS'])) {
	exit;	// GLOBALS overwrite attempt.
}

// Deprecated as of PHP 5.3.0.
if (get_magic_quotes_gpc()) {
	$in = array(&$_GET, &$_POST, &$_COOKIE);
	while (list($k, $v) = each($in)) {
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

// Enable debug mode.
if ($GLOBALS['FUD_OPT_3'] & 1073741824) {
	error_reporting(-1);	// Everything.
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	ini_set('log_errors_max_len', 0);
	ini_set('error_log', $GLOBALS['ERROR_PATH'] .'/php_errors');

	define('fud_debug', 1);
	define('fud_query_stats', 1);
}

// Load and initialize the plugin system.
if ($GLOBALS['FUD_OPT_3'] & 4194304) {	// Plugins enabled.
	include_once $GLOBALS['INCLUDE'] .'plugins.inc';
}

/** Explisteley include a file. */
function fud_use($file, $static=0)
{
	if ($static) {
		include_once $GLOBALS['INCLUDE'] . $file;
		return;
	}
	defined('fud_theme') or define('fud_theme', 'theme/default/');
	include_once $GLOBALS['INCLUDE'] . fud_theme . $file;
}

/** Auto load a class. */
function __autoload( $className ) {
	require_once($GLOBALS['INCLUDE'] . $className .'.inc');
}

/** Generate a random value. */
function get_random_value($bitlength=32)
{
	$n = round($bitlength/32);
	$v = '';
	while (--$n > -1) {
		$v .= mt_rand();
	}
	return $v;
}

/** Get user's IP Address. */
function get_ip()
{
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		// If user is behind a proxy, this HTTP header might be added by its proxy to give you the real user IP, not the proxy one.
		$ip = strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ',');
		do {
			$ip = str_replace('::ffff:', '', $ip);
			if (($lip = ip2long($ip)) === -1) {
				continue;
			}
			/* Some disallowed ip ranges, that are normally used for private networks:
			 * 10.0.0.0 - 10.255.255.255
			 * 172.16.0.0 - 172.31.255.255
			 * 192.168.0.0 - 192.168.255.255
			 * 127.0.0.1, 255.255.255.255, 0.0.0.0
			*/

			// TO DO: USE: if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
			if (!$lip || (($lip >= 167772160) && ($lip <= 184549375)) || (($lip >= -1408237568) && ($lip <= -1407188993)) || (($lip >= -1062731776) && ($lip <= -1062666241)) || ($lip == 2130706433)) {
				continue;
			}
			return long2ip($lip);
		} while (($ip = strtok(',')));
	}

	$ip = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0');
	$ip = str_replace('::ffff:', '', $ip);
	return (!empty($ip) ? $ip : '0.0.0.0');
}

/** Replace empty string with NULL before DB insert. */
function ssn($str)
{
	if ($str === null || $str === '') {
		return 'NULL';
	}
	return _esc($str);
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
	if ($GLOBALS['FUD_OPT_3'] & 65536) {	// DISABLE_ANON_CACHE
		return 0;
	}
	$mod = gmdate('D, d M Y H:i:s', $t) .' GMT';

	/* Send caching headers. */
	header('Last-Modified: '. $mod);
	if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && !isset($_SERVER['HTTP_RANGE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $mod) {
		header('HTTP/1.1 304 Not Modified');
		header('Status: 304 Not Modified');
		return 1;
	}
	return 0;
}

/** Log an error. */
function fud_logerror($error_msg, $logfile) {
	if (defined('plugins')) {
		// Alter, log or block error message.
		$error_msg = plugin_call_hook('LOGERR', $error_msg);
		if (empty($error_msg)) {
			return;
		}
	}

	$pfx = sprintf('?%-10d?%-10d?', strlen($error_msg) + 1, __request_timestamp__);
	ini_set('log_errors_max_len', 0);
	if (!error_log($pfx . $error_msg ."\n", 3, $GLOBALS['ERROR_PATH'] . $logfile)) {
		echo '<hr><b>UNABLE TO WRITE TO LOG FILE:</b><ul>';
		echo $error_msg;
		echo '</ul><hr>';
	}
}

/** Database error handler. */
function fud_sql_error_handler($query, $error_string, $error_number, $db_version)
{
	if (db_locked() && !in_array($query, array('COMMIT','COMMIT WORK','UNLOCK TABLES'))) {
		db_unlock();
	}

	$_SERVER['PATH_TRANSLATED'] = '';
	foreach (debug_backtrace() as $v) {
		$_SERVER['PATH_TRANSLATED'] .= "{$v['file']}:{$v['line']}<br />\n";
	}

	$error_msg = '('. $_SERVER['PATH_TRANSLATED'] .') '. $error_number .': '. $error_string ."<br />\n";
	$error_msg .= 'Query: '. htmlspecialchars($query) ."<br />\n";
	if (!empty($_GET)) {
		$error_msg .= '_GET: ';
		if (count($_GET, 1) < 100) {
			$error_msg .= htmlspecialchars(var_export($_GET, 1));
		} else {
			$error_msg .= 'Too many vars to list: '. count($_GET, 1);
		}
		$error_msg .= "<br />\n";
	}
	if (!empty($_POST)) {
		$error_msg .= '_POST: ';
		if (count($_POST, 1) < 100) {
			$error_msg .= htmlspecialchars(var_export($_POST, 1));
		} else {
			$error_msg .= 'Too many vars: '. count($_POST, 1);
		}
		$error_msg .= "<br />\n";
	}
	$error_msg .= 'Database version: '. $db_version ."<br />\n";
	if (isset($_SERVER['HTTP_REFERER'])) {
		$error_msg .= '[Referring Page] '. htmlspecialchars($_SERVER['HTTP_REFERER']) ."<br />\n";
	}

	fud_logerror($error_msg, 'sql_errors');

	if (isset($GLOBALS['usr']->users_opt) && $GLOBALS['usr']->users_opt & 1048576) { // Admin user.
		// ACP may try to catch DB errors, e.g. temp table check in admglobal.php.
		throw new Exception($error_msg);
	} elseif (defined('fud_debug')) {	// Debug mode.
		echo '<hr><pre>'. $error_msg .'</pre><hr>';
	} else {
		trigger_error('SQL Error has occurred, please contact the <a href="mailto:'. $GLOBALS['ADMIN_EMAIL'] .'?subject=SQL%20Error">administrator</a> of the forum and have them review the forum&#39;s SQL query log', E_USER_ERROR);
		if (ini_get('display_errors') !== 1) {
			exit('SQL Error has occurred, please contact the <a href="mailto:'. $GLOBALS['ADMIN_EMAIL'] .'?subject=SQL%20Error">administrator</a> of the forum and have them review the forum&#39;s SQL query log');
		}
	}
	exit;
}

?>
