<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

$FORUM_VERSION = '3.1.3';

ignore_user_abort('1');

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

// Enable debug mode.
if ($GLOBALS['FUD_OPT_3'] & 536870912) {	// Detailed logging.
	define('fud_logging', 1);
} else if ($GLOBALS['FUD_OPT_3'] & 1073741824) {	// Full debug mode.
	define('fud_logging', 1);
	define('fud_debug', 1);
	define('fud_query_stats', 1);

	error_reporting(-1);	// Show all PHP messages.
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	ini_set('log_errors_max_len', 0);
	ini_set('error_log', $GLOBALS['ERROR_PATH'] .'/php_errors');
}

// Load and initialize the plugin system.
if ($GLOBALS['FUD_OPT_3'] & 4194304) {	// Plugins enabled.
	include_once $GLOBALS['INCLUDE'] .'plugins.inc';
}

/** Explicitly include a file. */
function fud_use($file, $static=0)
{
	if ($static) {
		include_once $GLOBALS['INCLUDE'] . $file;
		return;
	}
	defined('fud_theme') or define('fud_theme', 'theme/default/');
	include_once $GLOBALS['INCLUDE'] . fud_theme . $file;
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
		// IP address added by proxy server that gives the real user IP, not the proxy one.
		$ip = strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ',');
		do {
			$ip = str_replace('::ffff:', '', $ip);
			// Disallowed ip ranges that are normally used for private networks.
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
				return $ip;
			}
		} while (($ip = strtok(',')));
	}

	$ip = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0');
	$ip = str_replace('::ffff:', '', $ip);
	return (filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0');
}

/** Print an localized and time-zone adjusted date.
 *  Data formats are defined in date_formats.tmpl
 */
function print_date($format, $date) {

	if (extension_loaded('intl')) {
		// Convert old strftime format to Intl format.
		$search  = array('%Z', '%Y', '%a', '%B',   '%b',  '%d', '%H', '%M', '%S');
		$replace = array('z',  'y',  'E',  'LLLL', 'LLL', 'dd', 'HH', 'mm', 'ss');
		$format  = str_replace($search, $replace, $format);

		//$tz  = date_default_timezone_get();
		//$loc = locale_get_default();
		//echo "TZ=[". $tz ."] LOC=[". $loc ."] DATE=[". $date ."] FORMAT=[". $format ."]<hr>";

		$datefmt = datefmt_create(
		    null,   // locale_get_default(),
		    0,      // IntlDateFormatter::NONE,
		    0,      // IntlDateFormatter::NONE,
		    null,   // date_default_timezone_get(),
		    null,   // IntlDateFormatter::GREGORIAN,
		    $format // ex. 'E dd MMMM yyyy HH:mm'
		  );

		return datefmt_format($datefmt , $date);
	} else {
		// Fall back to deprecated strftime().
		return strftime($format, $date);
	}
}

/** Prepare string for use in a SEO URL - inspired by MediaWiki's wfUrlencode(). */
function seo_url_bit($str) {
        $str = str_replace(' ', '_', $str);
        $str = str_replace('/', '|', $str);

	// Encode and un-escape some "safe" characters. RFC 1738 says these are safe: ;:&=$-_.+!*'(),
        $str = urlencode($str);
        $str = str_ireplace(
           ['%3B', '%3A', '%40', '%24', '%21', '%26', '%2A', '%28', '%29', '%2C', '%7E'],
           [ ';',  ':',   '@',   '$',   '!',   '&',   '*',   '(',   ')',   ',',   '~'  ],
           $str
        );
 
	return $str;
}

/** Replace empty string with NULL before DB insert. */
function ssn($str)
{
	if ($str === null || $str === '') {
		return 'NULL';
	}
	return _esc($str);
}

/** htmlentity support inside user names. 
  * Added on 2005-03-05 by iliaa: http://sourceforge.net/p/fudforum/code/3417/
  */
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
function fud_logerror($error_msg, $logfile, $extra_data=null) {
	// Log extra data to a file.
	if(!empty($extra_data)) {
		if (defined('script')) {	// nntp or mlist.
			$extra_file = $GLOBALS['ERROR_PATH'] .'.'. script .'/'. time() .'_'. md5($extra_data);
		} else {
			$extra_file = $GLOBALS['ERROR_PATH'] . time() .'_'. md5($extra_data);
		}
		$u = umask(0111);
		if (!($fp = fopen($extra_file, 'wb'))) {
			exit('No perms to write '. $extra_file ."\n");
		}
		fwrite($fp, $extra_data);
		fclose($fp);
		umask($u);
		$error_msg .= ' Details @ '. $extra_file;
	 }

	// Call plugins to alter, log or block error messages.
	if (defined('plugins')) {
		$error_msg = plugin_call_hook('LOGERR', $error_msg);
		if (empty($error_msg)) {
			return;
		}
	}

	// Default logging.
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
	if (db_locked() && !in_array($query, array('COMMIT', 'COMMIT WORK', 'UNLOCK TABLES'))) {
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

	if (empty($GLOBALS['ADMIN_EMAIL'])) {
		$public_msg = 'SQL error has occurred! Please contact the administrator and have them look at the Error Log Viewer in the forum\'s Admin Control Panel.';
	} else {
		$public_msg = 'SQL error has occurred! Please contact the <a href="mailto:'. $GLOBALS['ADMIN_EMAIL'] .'?subject=SQL%20Error">administrator</a> and have them look at the Error Log Viewer in the forum\'s Admin Control Panel.';
	}

	if (isset($GLOBALS['usr']->users_opt) && $GLOBALS['usr']->users_opt & 1048576) { // Admin user.
		// ACP may try to catch DB errors, e.g. temp table check in admglobal.php.
		throw new Exception($error_msg);
	} elseif (defined('fud_debug')) {	// Debug mode.
		echo '<hr><pre>'. $error_msg .'</pre><hr>';
	} else {
		trigger_error($public_msg, E_USER_ERROR);
		if (ini_get('display_errors') !== '1') {
			echo $public_msg;
		}
	}
	exit;
}

?>
