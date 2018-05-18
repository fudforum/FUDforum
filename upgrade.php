<?php
/***************************************************************************
* copyright            : (C) 2001-2018 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License.
***************************************************************************/

$__UPGRADE_SCRIPT_VERSION = 5309.2;
// define('fud_debug', 1);

/*
  * SQL Upgrade Functions - format is tablename_colname():
  * These functions will be called when a column is modified or a new column is added to a table.
  * Old columns can still be referenced (they will be dropped after all new columns were added).
  */

/** Move Usenet trackers into the database (3.0.0->3.0.1). */
function nntp_tracker($flds)
{
	$c = q('SELECT id, server, newsgroup FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'nntp');
	while ($r = db_rowarr($c)) {
		if (@file_exists($GLOBALS['ERROR_PATH'] .'.nntp/'. $r[1] .'-'. $r[2])) {
			$tracker = (int) trim(file_get_contents($GLOBALS['ERROR_PATH'] .'.nntp/'. $r[1] .'-'. $r[2]));
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'nntp SET tracker='. $tracker .' WHERE id='. $r[0]);
			pf('Tracker for NNTP server '. $r[1] .', group '. $r[2] .' was moved into the DB.');
			@unlink($GLOBALS['ERROR_PATH'] .'.nntp/'. $r[1] .'-'. $r[2]);
		} else {
			pf('Unable to move tracker for NNTP server '. $r[1] .', group '. $r[2] .' into the DB.');
		}
	}
}

/** Change birthday from NUMBER to CHAR(8) (3.0.1->3.0.2).
 * New format is MMDDYYYY.
 * Requred for index lookups on leading string. I.e: WHERE birthday LIKE 'mmdd%';
 */
function users_birthday($flds)
{
	pf('About to change birthday format. This may take a while...');
	$c = q('SELECT id, bday FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users');
	while ($r = db_rowarr($c)) {
		$bday = $yyyy = $mm = $dd = null;

		$bday = str_pad($r[1], 8, '0', STR_PAD_LEFT);

		$yyyy = substr($bday, 0, 4);
		$mm   = substr($bday, 4, 2);
		$dd   = substr($bday, 6, 2);

		$yyyy = ($yyyy == '0000') ? '    ' : $yyyy;
		$mm   = ($mm   == '00')   ? '  '   : $mm;
		$dd   = ($dd   == '00')   ? '  '   : $dd;

		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users SET birthday=\''. $mm . $dd . $yyyy .'\' WHERE id='. $r[0]);
	}
	pf('Birthday format change completed.');
}

/** Change reg_ip (encoded IPv4 address) to registration_ip (IPv6 address) (3.0.4RC2 -> 3.0.4RC3). */
function users_registration_ip($flds)
{
	pf('Convert reg_ip to registration_ip for IPv6 compatibility');
	try {
		$c = q('SELECT id, reg_ip FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users');
		while ($r = db_rowarr($c)) {
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users SET registration_ip=\''. long2ip($r[1]) .'\' WHERE id='. $r[0]);
		}
	} catch (Exception $e) {
		echo $e->getMessage(), "\n";
	}
}

/** Change last_known_ip (encoded IPv4 address) to last_used_ip (IPv6 address) (3.0.4RC2 -> 3.0.4RC3). */
function users_last_used_ip($flds)
{
	pf('Convert last_known_ip to last_used_ip IPv6 compatibility');
	try {
		$c = q('SELECT id, last_known_ip FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users');
		while ($r = db_rowarr($c)) {
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users SET last_used_ip=\''. long2ip($r[1]) .'\' WHERE id='. $r[0]);
		}
	} catch (Exception $e) {
		echo $e->getMessage(), "\n";
	}
}

/** Change reset/conf_key from '0' (VARCHAR) to NULL (no need to store & index a bunch of 0 values). */
function users_conf_key($flds)
{
	pf('Convert reset_key & conf_key from 0 to NULL');
	q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users SET conf_key =NULL WHERE conf_key =\'0\'');
	q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users SET reset_key=NULL WHERE reset_key=\'0\'');
}
function users_reset_key($flds)
{
	users_conf_key($flds);
}

/* For future implementation -
// Convert 'location' to a Custom Profile Field.
function users_location($flds)
{
	fud_use('custom_field_adm.inc', true);

	$cfield = new fud_custom_field;
	$cfield->name      = 'Location';
	$cfield->descr     = NULL;
	$cfield->type_opt  = 0; // Single line.
	$cfield->choice    = NULL;
	$cfield->vieworder = NULL;
	$cfield->field_opt = 2; // Optional, only user can edit, everyone can see value.
	$locid = $cfield->add();

	$c = q('SELECT id, custom_fields, location FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users WHERE location IS NOT NULL and location <> \'\'');
	while ($r = db_rowarr($c)) {
		$x = isset($r[1]) ? unserialize($r[1]) : array();
		$x[$locid] = $r[2];
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users SET custom_fields = '. _esc(serialize($x)) .' WHERE id = '. $r[0]);
	}
}
*/

/* END: SQL Upgrade Functions */

function fud_ini_get($opt)
{
	return (ini_get($opt) == '1' ? 1 : 0);
}

function fud_ini_set($opt, $val)
{
	if (function_exists('ini_set')) {
		ini_set($opt, $val);
	}
}

/** Print message to web browser or command line. */
function pf($msg='', $webonly=false)
{
	if (php_sapi_name() == 'cli') {
		if ($webonly) return;
		echo strip_tags($msg) ."\n";
	} else {
		echo $msg . (stripos($msg, '<h')!==FALSE ? '' : '<br />');
		@ob_flush(); flush();
	}
}

/** Print error and exit. */
function seterr($msg)
{
	if (php_sapi_name() == 'cli') {
		exit($msg);
	} else {
		exit('<div class="alert">'. $msg .'</div></body></html>');
	}
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

/** Error handler for DB driver. */
function fud_sql_error_handler($query, $error_string, $error_number, $server_version)
{
	if (defined('fud_debug')) pf($query);
	throw new Exception($error_number .': '. $error_string .' @ '. $query);
}

/** Find the CVS or SVN ID property. This is used to check if a file was changed.
  * TODO: Should eventually be moved to include/fs.inc.
  */
function fetch_cvs_id($data)
{
	// SVN format: $Id$
	if (($s = strpos($data, '$Id: ')) === false) {
		return;
	}
	$s = $s + 5;
	if (($e = strpos($data, ' $', $s)) === false) {
		return;
	}
	return substr($data, $s, ($e - $s));
}

/** Backup a forum file before replacing it.
  * TODO: Should eventually be moved to include/fs.inc.
  */
function backup_file($source, $theme='')
{
	$theme .= md5($source);
	copy($source, $GLOBALS['ERROR_PATH'] .'.backup/'. basename($source) .'_'. $theme .'_'. __time__);
}

/** Create a directory
 * TODO: Use fud_mkdir() in include/fs.inc. We cannot currently include it as fs.inc was introduced after 3.0.0 (we may not have it yet).
 */
function __mkdir($dir)
{
	$u = umask(0);

	if (@is_dir($dir)) {
		return 1;
	} else if (file_exists($dir)) {
		unlink($dir);
	}

	$ret = (mkdir($dir, 0755) || mkdir(dirname($dir, 0755)));

	umask($u);
	return $ret;
}

/** Recursively delete a given directory.
  * Copied from include/fs.inc. We cannot currently include it as fs.inc was introduced after 3.0.0 (we may not have it yet).
  */
function __rmdir($dir, $deleteRootToo=false)
{
	if(!$dh = @opendir($dir)) {
		return;
	}
	while (false !== ($obj = readdir($dh))) {
		if($obj == '.' || $obj == '..') {
			continue;
		}
		$file = $dir .'/'. $obj;
		if (is_dir($file) && !is_link($file)) {
			__rmdir($file, true);
		} else if (!unlink($file)) {
			return false;
		}
	}
	closedir($dh);

	if ($deleteRootToo) {
		@rmdir($dir);
	}

	return true;
}

function htaccess_handler($web_root, $ht_pass)
{
	if (!fud_ini_get('allow_url_fopen') || strncmp(PHP_SAPI, 'apache', 6)) {
		unlink($ht_pass);
		return;
	}

	/* Opening a connection to itself should not take more then 5 seconds. */
	fud_ini_set('default_socket_timeout', 5);
	if (@fopen($web_root . 'blank.gif', 'r') === FALSE) {
		unlink($ht_pass);
	}
}

/**
 * Upgrade GLOBALS.php to new format (3.0.3->3.0.4).
 */
function upgrade_globals_php()
{
	$new = '';
	$f = fopen($GLOBALS['INCLUDE'] .'GLOBALS.php', 'r');
	while($s=fgets($f)) {
		if (strpos($s, '$GLOBALS[') !== false) {
			return;		// Already converted, bail out!
		}
		$new .= preg_replace('/(\s*)\$([A-Z_1-9]*)(\s*)=/i', "\t\$GLOBALS['$2']$3=", $s);
	}
	fclose($f);

	pf('Convert GLOBALS.php to new format.');
	file_put_contents($GLOBALS['INCLUDE'] .'GLOBALS.php', $new);
}

function upgrade_decompress_archive($data_root, $web_root)
{
	$clean = array('PHP_OPEN_TAG'=>'<?', 'PHP_OPEN_ASP_TAG'=>'<%');

	// CLI doesn't automatically change the CWD to the one the started script resides in.
	chdir(dirname(__FILE__));

	/* Install from './fudforum_archive' file. */
	$fp = fopen('./fudforum_archive', 'rb');
	$checksum = fread($fp, 32);
	$tmp = fread($fp, 20000);
	fseek($fp, (ftell($fp) - 20000), SEEK_SET);
	if (strpos($tmp, 'RAW_PHP_OPEN_TAG') !== FALSE) {	/* No compression. */
		unset($clean['PHP_OPEN_TAG']); $clean['RAW_PHP_OPEN_TAG'] = '<?';
		$data = '';
		while (($tmp = fgets($fp))) {
			$data .= strtr($tmp, $clean);
		}
	} else {
		$data_len = (int) fread($fp, 10);
		// Data should be @ least 100k.
		if ($data_len < 100000) {
			exit('Failed getting archive size from '. htmlentities(fread($fp, 10)));
		}
		$data = gzuncompress(strtr(fread($fp, $data_len), $clean), $data_len);
	}
	fclose($fp);

	if (md5($data) != $checksum) {
		exit("Archive did not pass the checksum test, it is corrupt!<br />\nIf you've encountered this error it means that you've:<ul><li>downloaded a corrupt archive</li><li>uploaded the archive to your server in ASCII and not BINARY mode</li><li>your FTP Server/Decompression software/Operating System added un-needed cartrige return ('\r') characters to the archive, resulting in archive corruption.</li></ul>\n");
	}

	$pos = 0;
	do  {
		$end = strpos($data, "\n", $pos+1);
		$meta_data = explode('//',  substr($data, $pos, ($end-$pos)));
		$pos = $end;

		if ($meta_data[1] == 'GLOBALS.php' || !isset($meta_data[3])) {
			continue;
		}

		if (!strncmp($meta_data[3], 'install/forum_data', 18)) {
			$path = $data_root . substr($meta_data[3], 18);
		} else if (!strncmp($meta_data[3], 'install/www_root', 16)) {
			$path = $web_root . substr($meta_data[3], 16);
		} else {
			continue;
		}
		$path .= '/'. $meta_data[1];

		$path = str_replace('//', '/', $path);

		if (isset($meta_data[5])) {
			$file = substr($data, ($pos + 1), $meta_data[5]);
			if (md5($file) != $meta_data[4]) {
				seterr('ERROR: file '. $meta_data[1] .' was not read properly from archive');
			}
			if (@file_exists($path)) {
				if (md5_file($path) == $meta_data[4]) {
					// File did not change - skip it!
					continue;
				}
				// Compare CVS/SVN Id to ensure we do not pointlessly replace files modified by the user.
				if (($cvsid = fetch_cvs_id($file)) && $cvsid && $cvsid == fetch_cvs_id(file_get_contents($path))) {
					continue;
				}

				backup_file($path);
			}

			if ($path == $web_root .'.htaccess' && @file_exists($path)) {
				define('old_htaccess', 1);
				continue;
			}

			if (defined('fud_debug')) pf('Extracting '. $path);
			if (!($fp = @fopen($path, 'wb'))) {
				if (basename($path) != '.htaccess') {
					seterr('Couldn\'t open "'. $path .'" for write');
				}
			}
			fwrite($fp, $file);
			fclose($fp);
			@chmod($file, 0644);
		} else {
			if (!__mkdir(preg_replace('!/+$!', '', $path))) {
				seterr('failed creating "'. $path .'" directory');
			}
		}
	} while (($pos = strpos($data, "\n//", $pos)) !== false);
}

function cache_avatar_image($url, $user_id)
{
	$ext = array(1=>'gif', 2=>'jpg', 3=>'png', 4=>'swf');
	if (!isset($GLOBALS['AVATAR_ALLOW_SWF'])) {
		$GLOBALS['AVATAR_ALLOW_SWF'] = 'N';
	}
	if (!isset($GLOBALS['CUSTOM_AVATAR_MAX_DIM'])) {
		$max_w = $max_y = 64;
	} else {
		list($max_w, $max_y) = explode('x', $GLOBALS['CUSTOM_AVATAR_MAX_DIM']);
	}

	if (!($img_info = @getimagesize($url)) || $img_info[0] > $max_w || $img_info[1] > $max_y || $img_info[2] > ($GLOBALS['AVATAR_ALLOW_SWF']!='Y'?3:4)) {
		return;
	}
	if (!($img_data = file_get_contents($url)) || strlen($img_data) > $GLOBALS['CUSTOM_AVATAR_MAX_SIZE']) {
		return;
	}
	if (!($fp = fopen($GLOBALS['WWW_ROOT_DISK'] .'images/custom_avatars/'. $user_id .'.'. $ext[$img_info[2]], 'wb'))) {
		return;
	}
	fwrite($fp, $img_data);
	fclose($fp);

	return '<img src="'. $GLOBALS['WWW_ROOT'] .'images/custom_avatars/'. $user_id .'.'. $ext[$img_info[2]] .'" '. $img_info[3] .' />';
}

function syncronize_theme_dir($theme, $dir, $src_thm)
{
	$path  = $GLOBALS['DATA_DIR'] .'thm/'. $theme   .'/'. $dir;
	$spath = $GLOBALS['DATA_DIR'] .'thm/'. $src_thm .'/'. $dir;

	if (!__mkdir($path)) {
		seterr('Directory "'. $path .'" does not exist, and the upgrade script failed to create it.');
	}
	if (!($d = opendir($spath))) {
		seterr('Failed to open "'. $spath .'"');
	}
	readdir($d); readdir($d);
	$path  .= '/';
	$spath .= '/';
	while ($f = readdir($d)) {
		if ($f == '.' || $f == '..') {
			continue;
		}
		if (@is_dir($spath . $f) && !is_link($spath . $f)) {
			syncronize_theme_dir($theme, $dir .'/'. $f, $src_thm);
			continue;
		}
		if (!@file_exists($path . $f) && !copy($spath . $f, $path . $f)) {
			seterr('Failed to copy "'. $spath . $f .'" to "'. $path . $f .'", check permissions then run this scripts again.');
		} else {
			if (md5_file($path . $f) == md5_file($spath . $f) || (($cid = fetch_cvs_id(file_get_contents($path . $f))) == fetch_cvs_id(file_get_contents($spath . $f)) && $cid)) {
				continue;
			}

			backup_file($path . $f, $theme);
			if (!copy($spath . $f, $path . $f) && file_exists($path . $f)) {
				unlink($path . $f);
				if (!copy($spath . $f, $path . $f)) {
					seterr('Failed to copy "'. $spath . $f .'" to "'. $path . $f .'", check permissions then run this scripts again.');
				}
			}
		}

	}
	closedir($d);
}

function syncronize_theme($theme)
{
	$t = array('default');

	if ($theme == 'path_info' || @file_exists($GLOBALS['DATA_DIR'] .'thm/'. $theme .'/.path_info')) {
		$t[] = 'path_info';
	}

	foreach ($t as $src_thm) {
		syncronize_theme_dir($theme, 'tmpl',   $src_thm);
		syncronize_theme_dir($theme, 'i18n',   $src_thm);
		syncronize_theme_dir($theme, 'images', $src_thm);
	}
}

/* main program */
	error_reporting(E_ALL);
	ignore_user_abort(true);
	@set_time_limit(0);

	error_reporting(E_ALL);
	fud_ini_set('memory_limit', '128M');	// PHP 5.3's default, old defaults too small.

	if (ini_get('error_log')) {
		@fud_ini_set('error_log', '');
	}
	if (!fud_ini_get('display_errors')) {
		fud_ini_set('display_errors', 1);
	}
	if (!fud_ini_get('track_errors')) {
		fud_ini_set('track_errors', 1);
	}

	// Determine SafeMode limitations.
	define('SAFE_MODE', fud_ini_get('safe_mode'));
	if (SAFE_MODE && basename(__FILE__) != 'upgrade_safe.php') {
		$c = getcwd();
		if (copy($c .'/upgrade.php', $c .'/upgrade_safe.php')) {
			header('Location: '. dirname($_SERVER['SCRIPT_NAME']) .'/upgrade_safe.php');
		}
		exit;
	}

	if (php_sapi_name() != 'cli') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>FUDforum Upgrade Wizard</title>
	<link rel="styleSheet" href="adm/adm.css" />
	<link rel="styleSheet" href="adm/style/adm.css" />
	<style>html, body { height: 95%; }</style>
	<script src="js/jquery.js"></script>
	<script>
	jQuery(document).ready(function() {
		jQuery(':text:visible:enabled:first').focus();
	});
	</script>
</head>
<body>
<table class="headtable"><tr>
  <td><img src="images/fudlogo.gif" alt="" style="float:left;" border="0" /></td>
  <td><span class="linkhead">FUDforum Upgrade Wizard</span></td>
  <td> &nbsp; </td>
</tr></table>
<table class="maintable" style="height:100%;">
<tr>
<td class="linktable linkdata" nowrap="nowrap">
<p><b>Preperation:</b></p>
<p>Please <b><a href="http://cvs.prohost.org/index.php/Backup">backup</a></b> your forum<br />
   and <b><a href="http://cvs.prohost.org/index.php/Upgrading">review the documentation</a></b><br />
   before proceeding!</p>

<p><b>Upgrade steps:</b></p>
<p>This wizard will guide you<br />
   through the steps required to<br />
   upgrade your forum:</p>
<p><span class="linkgroup">Step 1:</span> Admin login</p>
<p><span class="linkgroup">Step 2:</span> Perform upgrade</p>
<p><span class="linkgroup">Step 3:</span> Consistency check</p>

<p>Thank you for keeping<br />
   your forum up-to-date!</p>
</td>
<td class="maindata">

<?php
	}

	// Check if we have a forum_archive.
	if (!file_exists('./fudforum_archive')) {
		seterr('The upgrade script requires a "fudforum_archive" file to run. Please download it and retry again.');
	}

	// PHP version check.
	if (!version_compare(PHP_VERSION, '5.2.3', '>=')) {
		seterr('The upgrade script requires that you have PHP version 5.2.3 or higher.');
	}

	/* Mbstring hackery, necessary if function overload is enabled. */
	if (extension_loaded('mbstring') && ini_get('mbstring.func_overload') > 0) {
		mb_internal_encoding('UTF-8');
	}

	// We need to verify that GLOBALS.php exists in current directory & that we can open it.
	$gpath = getcwd() .'/GLOBALS.php';
	if (!@file_exists($gpath)) {
		seterr('Unable to find GLOBALS.php inside the current ('. getcwd() .') directory. Please place the upgrade script ('. basename(__FILE__) .') inside the main web directory of your forum.');
	} else if (!@is_writable($gpath)) {
		seterr('No permission to read/write to '. getcwd() .' /GLOBALS.php. Please make sure this script had write access to all of the forum files.');
	}

	/* GLOBALS.php may not be a symlink, but contains an include to a different location. */
	if (preg_match('/<\?php include_once (.+)\; \?>/i', file_get_contents($gpath), $m)) {
		eval('$gpath = ' .$m[1] .';');	// May include variables (i.e. with FUD2Go).
	}

	$input = preg_replace('!(require|include)\(([^;]+)\);!m', '', file_get_contents($gpath));
	$input = trim(str_replace(array('<?php', '<?', '?>'), array('','',''), $input));
	eval($input);

	/* This check is here to ensure the data from GLOBALS.php was parsed correctly. */
	if (!isset($GLOBALS['COOKIE_NAME'])) {
		seterr('Failed to parse GLOBALS.php at "'. $gpath .'" correctly');
	}

	/* Check FUDforum version. */
	$core = file_get_contents($GLOBALS['DATA_DIR'] .'include/core.inc');
	$FORUM_VERSION = preg_replace('/.*FORUM_VERSION = \'(.*?)\';.*/s', '\1', $core);
	if (version_compare($FORUM_VERSION, '3.0.0', '<')) {
			seterr('FUDforum '. $FORUM_VERSION .' must be upgraded to FUDforum version 3.0.0 before it can be upgraded to later release.');
	}

	/* Determine if this upgrade script was previously ran. */
	if (@file_exists($GLOBALS['ERROR_PATH'] .'UPGRADE_STATUS') && (float) trim(file_get_contents($ERROR_PATH .'UPGRADE_STATUS')) >= $__UPGRADE_SCRIPT_VERSION) {
		seterr('<p>THIS UPGRADE SCRIPT HAS ALREADY BEEN RUN.</p><p>If you wish to run it again, use the File Manager to remove file: '. $GLOBALS['ERROR_PATH'] .'UPGRADE_STATUS.</p><p>Alternatively, delete "upgrade.php" and "fudforum_archive"!');
	}

	/* Load glob.inc for functions like change_global_settings(). */
	require($INCLUDE .'glob.inc');

	/* The mysql driver was removed from PHP 7. */
	if ($GLOBALS['DBHOST_DBTYPE'] == 'mysql' && extension_loaded('mysqli') ) {
		pf('<span style="color:green;">Switching from depricated MySQL to new MySQLi driver.</span>');
		change_global_settings(array('DBHOST_DBTYPE' => 'mysqli'));
		$GLOBALS['DBHOST_DBTYPE'] = 'mysqli';
	}

	/* Include appropriate database functions. */
	$dbinc = $GLOBALS['DATA_DIR'] .'sql/'. $GLOBALS['DBHOST_DBTYPE'] .'/db.inc';
	if (!file_exists($dbinc)) {
		seterr('Unable to load database driver: '. $dbinc);
	}

	// Here's a good hack for ya!
	// Function get_fud_table_list() was moved from 'db.inc' to 'dbadmin.inc' in 3.0.2.
	// We need to remove it from the db.inc to prevent previously declared errors.
	file_put_contents($dbinc, preg_replace('/function get_fud_table_list\(.*?function/s', 'function', file_get_contents($dbinc)));
	include_once $dbinc;

	// Another hack: q_bitand() was introduced in 3.0.2 and is used in this script.
	// but the possibly older driver we've loaded may not have it yet.
	if (!function_exists('q_bitand'))
	{
		$driver = $GLOBALS['DBHOST_DBTYPE'];
		if( !strcasecmp( $driver, 'mysql' ) OR
			!strcasecmp( $driver, 'mysqli' ) OR
			!strcasecmp( $driver, 'mssql' ) OR
			!strcasecmp( $driver, 'pgsql' ) OR
			!strcasecmp( $driver, 'sqlsrv' ) OR
			!strcasecmp( $driver, 'pdo_mysql' ) OR
			!strcasecmp( $driver, 'pdo_pgsql' ) OR
			!strcasecmp( $driver, 'pdo_sqlite' ) OR
			!strcasecmp( $driver, 'pdo_sqlsrv' ) )
		{
			// mysql, mysqli, mssql, pgsql, sqlsrv
			// pdo_mysql, pdo_pgsql, pdo_sqlite, pdo_sqlsrv
			function q_bitand($fieldLeft, $fieldRight) {
				return $fieldLeft .' & '. $fieldRight;
			}
		}
		else if( !strcasecmp( $driver, 'db2' ) OR
			!strcasecmp( $driver, 'interbase' ) OR
			!strcasecmp( $driver, 'oci8' ) OR
			!strcasecmp( $driver, 'pdo_oci' ) )
		{
			// db2, interbase, oci8, pdo_oci
			function q_bitand($fieldLeft, $fieldRight) {
				return 'BITAND('. $fieldLeft .', '. $fieldRight .')';
			}
		}
		else
		{
			// Unknown driver!
			// TODO: manage error properly
			die('Unknown database driver '. $driver .': cannot define q_bitand() properly.');
		}
	}

	// Another temp hack. Manually check MySQL DB version .
	// Should be replaced by validate_db_version() in dbadmin.inc.
	// This file may not be present yet (introduced in 3.0.2), and cannot be included yet.
	if (!function_exists('db_version')) {
		// get_version() was renamed to db_version() in FUDforum v3.0.2.
		function db_version() { return get_version(); }
	}
	$dbver = db_version();
	if (__dbtype__ == 'mysql' && version_compare($dbver, '5.0.0', '<')) {
		seterr('DBHOST_DBNAME', 'MySQL version '. $dbver .' is not supported. Please upgrade to MySQL Version 5.0.0 or higher.');
	}

	/* Only allow the admin user to upgrade the forum. */
	$auth = 0;
	if (php_sapi_name() == 'cli' && (!empty($_SERVER['argv'][1]) || !empty($_SERVER['argv'][2]))) {
		$_POST['login']  = $_SERVER['argv'][1];
		$_POST['passwd'] = $_SERVER['argv'][2];
	}
	if (count($_POST)) {
		if (get_magic_quotes_gpc()) {
			$_POST['login']  = stripslashes($_POST['login']);
			$_POST['passwd'] = stripslashes($_POST['passwd']);
		}

		// Authenticate user & password against the database.
		try {
			// Try with password 'salt' - introduced in 3.0.1.
			$r = @db_sab('SELECT id, users_opt, passwd, salt FROM '. $DBHOST_TBL_PREFIX .'users WHERE login=\''. addslashes($_POST['login']) .'\'');
		} catch (Exception $e) {
			// It didn't work, try without salt.
			$r = db_sab('SELECT id, users_opt, passwd, NULL FROM '. $DBHOST_TBL_PREFIX .'users WHERE login=\''. addslashes($_POST['login']) .'\'');
		}
		if ($r && ($r->users_opt & 1048576) && (empty($r->salt) && $r->passwd == md5($_POST['passwd']) || $r->passwd == sha1($r->salt . sha1($_POST['passwd'])))) {
			$auth = $r->id;
		} else {
			$auth = 0;
			pf('<span style="color:red;">Authentification failed. Please try again.</span>');
		}
	}

	if (!$auth) {

		if (php_sapi_name() == 'cli') {
			seterr('Usage: upgrade.php admin_user admin_password');
		}

pf('<h2>Step 1: Admin login</h2>', true);

?>
<form name="upgrade" action="<?php echo basename(__FILE__); ?>" method="post">
<p>Please enter the login and password of the administration account:</p>
<table class="datatable solidtable">
<tr class="field">
	<td><label for="login"><b>Login:</b><br /><small>Your forum's admin user.<small></label></td>
	<td><input type="text" id="login" name="login" value="" /></td>
</tr>
<tr class="field">
	<td><label for="passwd"><b>Password:</b><br /><small>Your forum's admin password.<small></label></td>
	<td><input type="password" id="passwd" name="passwd" value="" /></td>
</tr>
<tr class="field">
	<td><label for="custom_tmpl" title="If unsure, leave unchecked!"><b>Update custom template sets?</b><br /><small>Leave unchecked to preserve custom styling. FUDforum will not update custom template sets and you will have to do it manually! If checked, the upgrade may overwrite custom themes.</small></label></td>
	<td><input type="checkbox" id="custom_tmpl" name="custom_tmpl" value="1" /></td>
</tr>
<tr class="field">
	<td><label for="custom_sql" title="If unsure, leave unchecked!"><b>Skip database changes?</b><br /><small>Check if you've modified FUDforum's SQL structure. You will have to apply the SQL changes yourself! Unless you know what you're doing, you should leave this unchecked!</small></label></td>
	<td><input type="checkbox" id="custom_sql" name="custom_sql" value="1" /></td>
</tr>
<tr class="fieldaction">
	<td align="right" colspan="2"><input type="submit" class="button" name="submit" value="Login" /></td>
</tr>
</table>
</form>

</td></tr></table>
</body>
</html>
<?php
		exit;
	} else if (isset($_POST['step']) && $_POST['step'] == 3) {
		pf('<h2>Step 3: Consistency check</h2>', true);

		/* Insert update script marker. */
		$fp = fopen($GLOBALS['ERROR_PATH'] .'UPGRADE_STATUS', 'wb');
		fwrite($fp, $__UPGRADE_SCRIPT_VERSION);
		fclose($fp);
		
		/* Flag for consistency check. */
		touch($GLOBALS['TMP'] .'RUN_CONSISTENCY_CHECK');

		/* Get session details to construct link to consistency checker. */
		$pfx = db_sab('SELECT u.sq, s.ses_id FROM '. $DBHOST_TBL_PREFIX .'users u INNER JOIN '. $DBHOST_TBL_PREFIX .'ses s ON u.id=s.user_id WHERE u.id='. $auth);
		if ($pfx && $pfx->sq) {
			$pfxs = '&S='. $pfx->ses_id .'&SQ='. $pfx->sq;
		} else {
			$pfxs = '';
		}
?>

<p>The last step is to run the consistency checker and re-enable your forum. To continue, click on the link below or navigate to your forum's <i>Admin Control Panel</i> -&gt; <i>Forum Consistency</i> to run it.</p>

<ul><a href="adm/consist.php?enable_forum=1<?php echo $pfxs; ?>"  class="button"><b>Run consistency checker now!</b></a></ul><br />
<div class="tutor">When done, please remove the upgrade script to prevent hackers from running it. The script is located at <?php echo realpath('./upgrade.php'); ?></div>
</td></tr></table>
</body>
</html>
<?php
		exit;
	}


	pf('<h2>Step 2: Perform upgrade</h2>', true);

	// Determine open_basedir limitations.
	define('open_basedir', ini_get('open_basedir'));
	if (open_basedir) {
		if (strncasecmp(PHP_OS, 'win', 3)) {
			$dirs = explode(':', open_basedir);
		} else {
			$dirs = explode(';', open_basedir);
		}
		$safe = 1;
		foreach ($dirs as $d) {
			if (!strncasecmp($GLOBALS['DATA_DIR'], $d, strlen($d))) {
			        $safe = 0;
			        break;
			}
		}
		if ($safe) {
			seterr('Your php\'s open_basedir limitation ('. open_basedir .') will prevent the upgrade script from writing to ('. $GLOBALS['DATA_DIR'] .'). Please make sure that access to ('. $GLOBALS['DATA_DIR'] .') is permitted.');
		}
		if ($GLOBALS['DATA_DIR'] != $GLOBALS['WWW_ROOT_DISK']) {
			$safe = 1;
			foreach ($dirs as $d) {
				if (!strncasecmp($GLOBALS['WWW_ROOT_DISK'], $d, strlen($d))) {
				        $safe = 0;
					break;
				}
			}
			if ($safe) {
				seterr('Your php\'s open_basedir limitation ('. open_basedir .') will prevent the upgrade script from writing to ('. $GLOBALS['WWW_ROOT_DISK'] .'). Please make sure that access to ('. $GLOBALS['WWW_ROOT_DISK'] .') is permitted.');
			}
		}
	}

	/* Disable the forum. */
	if ($GLOBALS['FUD_OPT_1'] & 1) {
		pf('Disabling the forum.');
		// We normally diable the forum with maintenance_status(). However, since we will not re-enable
		// the forum (done in consist.php), we will not be able to restore the disable reason.
		change_global_settings(array('FUD_OPT_1' => ($GLOBALS['FUD_OPT_1'] &~ 1)));
		pf('Forum is now disabled.');
	}

	/* Rename old language name directories to language codes (3.0.0->3.0.1). */
	$langmap = array('afrikaans' => 'af', 'arabic' => 'ar', 'breton' => 'br',
					 'bulgarian' => 'bg', 'catalan' => 'ca', 'chinese' => 'zh-hans',
					 'czech' => 'cs', 'danish' => 'da', 'dutch' => 'nl',
					 'english' => 'en', 'esperanto' => 'eo',
					 'finnish' => 'fi', 'french' => 'fr', 'galician' => 'gl',
					 'german' => 'de', 'german_formal' => 'de-formal', 'greek' => 'el',
					 'hungarian' => 'hu', 'indonesian' => 'id', 'italian' => 'it',
					 'japanese' => 'ja', 'korean' => 'ko', 'latvian' => 'lv',
					 'lithuanian' => 'lt', 'norwegian' => 'no', 'occitan' => 'oc',
					 'polish' => 'pl', 'portuguese' => 'pt', 'portuguese_br' => 'pt-br',
					 'romanian' => 'ro', 'russian' => 'ru', 'slovak' => 'sk',
					 'spanish' => 'es', 'swedish' => 'sv', 'swiss_german' => 'gsw',
					 'turkish' => 'tr', 'upper_sorbian' => 'hsb', 'vietnamese' => 'vi');
	$tp = opendir($GLOBALS['DATA_DIR'] .'thm/');
	while ($te = readdir($tp)) {
		$tdir = $GLOBALS['DATA_DIR'] .'thm/'. $te .'/i18n/';
		if (!@is_dir($tdir)) {
			continue;
		}
		$lp = opendir($tdir);
		while ($le = readdir($lp)) {
			if (!array_key_exists($le, $langmap)) {	// Not in convertion map.
				continue;
			}

			// Remove old unused 'pspell_lang' files (3.0.0->3.0.1).
			if (file_exists($tdir . $le .'/pspell_lang')) {
				@unlink($tdir . $le .'/pspell_lang');
			}

			pf('Rename directory '. $te .'/i18n/'. $le .' to '. $langmap[$le]);
			@rename($tdir.$le, $tdir.$langmap[$le]);
			q('UPDATE '. $DBHOST_TBL_PREFIX .'themes SET lang=\''. addslashes($langmap[$le]) .'\' WHERE lang=\''. addslashes($le) .'\'');
		}
		closedir($lp);
	}
	closedir($tp);

	/* Upgrade globals variable to $_GLOBALS["xxx"] style (3.0.3->3.0.4). */
	upgrade_globals_php();

	/* Upgrade files. */
	__mkdir($GLOBALS['ERROR_PATH'] .'.backup');
	define('__time__', time());
	pf('Beginning to decompress the archive.');
	upgrade_decompress_archive($GLOBALS['DATA_DIR'], $GLOBALS['WWW_ROOT_DISK']);

	/* Determine if this host can support .htaccess directives. */
	if (!defined('old_htaccess')) {
		htaccess_handler($GLOBALS['WWW_ROOT'], $GLOBALS['WWW_ROOT_DISK'] .'.htaccess');
	}
	pf('Finished decompressing the archive.');
	pf('File upgrade completed.');

	/* Update database. */

	// NOTE: dbadmin.inc becomes available in 3.0.2. We cannot use it until we've unpacked the new files.
	// Checking of SQL permisions should actuallty be done BEFORE we unpack - a catch 22.
	//TODO: Remember to move the code up in a later version again.
	include_once $GLOBALS['DATA_DIR'] .'include/dbadmin.inc';

	//TODO: Better late than never, move version checks up in future release (with loading of dbadmin.inc).
	/* Validate database version. */
	if (!isset($GLOBALS['errors'])) {
		$err = validate_db_version();
		if (!empty($err)) {
			seterr($err);
		}
	}

	/* Check SQL permissions. */
	pf('Checking SQL permissions.');
	drop_table('fud_forum_install_test_table', true);
	try {
		create_table('CREATE TABLE fud_forum_install_test_table (test_val INT)');
	} catch (Exception $e) {
		seterr('Please grant your database user access to create tables and try again.');
	}
	try {
		create_index('fud_forum_install_test_table', 'fud_forum_install_test_index', false, 'test_val');
	} catch (Exception $e) {
		seterr('Please grant your database user access to create indexes and try again.');
	}
	try {
		drop_table('fud_forum_install_test_table', false);
	} catch (Exception $e) {
		seterr('Please grant your database user access to drop tables and try again.');
	}

	/* Compare table definitions with what's in the DB and make corrections. */
	$db_tables = array_flip(get_fud_table_list());
	foreach (glob($GLOBALS['DATA_DIR'] .'/sql/*.tbl', GLOB_NOSORT) as $v) {
		$tbl = get_stbl_from_file($v);
		if (defined('fud_debug')) pf('Check table: '. $tbl['name']);
		$out_of_line_pks = array();

		// Skip thread view tables.
		if ($tbl['name'] == $DBHOST_TBL_PREFIX .'tv_') {
			continue;
		}
		if (!isset($db_tables[$tbl['name']])) {
			/* Add new table. */
			pf('Create new database table '. $v .'.');
			create_table(file_get_contents($v));
		} else {
			/* Handle DB columns. */
			$db_col = get_fud_col_list($tbl['name']);
			foreach ($tbl['flds'] as $k => $v2) {
				if (defined('fud_debug')) pf(' - check column: '. $k);

				// Queue "out of line PK's" for later processing.
				if ($v2['primary'] && !$v2['auto'] ) {	// Primary, but not auto number.
					$out_of_line_pks[] = $k;
					$v2['primary'] = 0;	// Don't consider on col level.
					$db_col[$k]['primary'] = 0;
				}

				if (!isset($db_col[$k])) {
					/* New column. */
					pf('Add new database column '. $k .' to table '. $tbl['name'] .'.');
					add_column($tbl['name'], $k, $v2);

					$f = substr("{$tbl['name']}_{$k}", strlen($DBHOST_TBL_PREFIX));
					if (function_exists($f)) {	// Run SQL conversion after add.
						$f($db_col);
					}
				} else if (array_diff_assoc($db_col[$k], $v2)) {
					/* Column definition has changed. */
					pf('Alter database column '. $k .' in table '. $tbl['name'] .'.');
					$f = substr("{$tbl['name']}_{$k}", strlen($DBHOST_TBL_PREFIX));
					alter_column($tbl['name'], $k, $v2);
					if (function_exists($f)) {	// Run SQL conversion after alter.
						$f($db_col);
					}
				}
				unset($db_col[$k]);	// Column still in use, no need to drop it.
			}

			/* Remove unused columns. */
			foreach (array_keys($db_col) as $v) {
				$f = substr("{$tbl['name']}_{$v}", strlen($DBHOST_TBL_PREFIX));
				if (function_exists($f)) {	// Run SQL conversion before drop.
					$f($db_col);
				}
				if (empty($_POST['custom_sql'])) {	// Standard or customized DB schema?
					pf('Drop unused database column '. $v .' from table '. $tbl['name'] .'.');
					drop_column($tbl['name'], $v);
				} else {
					pf('WARNING: Unused database column '. $v .' in table '. $tbl['name'] .'. Unless you\'ve added it, it should now be dropped!');
				}
			}

			/* Handle indexes. */
			$idx_l = get_fud_index_list($tbl['name']);
			foreach ($tbl['index'] as $k => $v) {
				/* Add a new index. */
				if (!isset($idx_l[$k])) {
					pf('Add new database index '. $k .' to table '. $tbl['name'] .'('. $v['cols'] .').');
					create_index($tbl['name'], $k, $v['unique'], $v['cols']);
				} else {
					/* Index already exists, but is of wrong type. */
					if ($v['unique'] != $idx_l[$k]['unique']) {
						pf('Recreate database index '. $k .' on table '. $tbl['name'] .'('. $v['cols'] .').');
						drop_index($tbl['name'], $k);
						create_index($tbl['name'], $k, $v['unique'], $v['cols']);
					}

					/* Remove from list so it doesn't get dropped. */
					unset($idx_l[$k]);
				}
			}

			/* Remove old un-unsed indexes. */
			foreach ($idx_l as $k => $v) {
				/* Skip SQLite's auto indexes. */
				if (__dbtype__ == 'sqlite' && strpos($k, 'sqlite_autoindex') !== FALSE) {
					continue;
				}
				pf('Drop unused database index '. $k .' from table '. $tbl['name'] .'.');
				drop_index($tbl['name'], $k);
			}

			/* Apply out of line PK's. */
			// Check for PK's after dropping indexes as MySQL can report UNIQUE keys as primary!?!
			foreach ($out_of_line_pks as $pk) {
				// Add if it's not a PK on the DB.
				$db_col = get_fud_col_list($tbl['name']);
				if (! $db_col[$pk]['primary']) {
					// Several PK's were added in 3.0.2.
					// We will worry about removing before recreating them in a later release.
					// q('ALTER TABLE '. $tbl['name'] .' DROP PRIMARY KEY');

					$col_list = implode(',', $out_of_line_pks);
					pf('Create composite primary key on '. $tbl['name'] .'('. $col_list .').');
					create_primary_key($tbl['name'], $col_list);
					break;
				}
			}

			unset($db_tables[$tbl['name']]);
		}
	}

	/* Drop old tables that is not used anymore. */
	if (isset($db_tables[$DBHOST_TBL_PREFIX .'mod_que'])) {	// Table removed from 3.0.2.
		pf('Drop unused database table '. $DBHOST_TBL_PREFIX .'mod_que.');
		drop_table($DBHOST_TBL_PREFIX .'mod_que');
	}

	// Change catch-all mime extention to '*'. To use '' in a NOT NULL column is wrong (FUDforum 3.0.1 -> 3.0.2).
	q('UPDATE '. $DBHOST_TBL_PREFIX .'mime SET fl_ext=\'*\' WHERE fl_ext=\'\'');
	// Password is NOT NULL. We should not use '' is a NOT NULL column (FUDforum 3.0.4).
	q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET passwd=\'*\' WHERE passwd=\'\'');

	// Ensure all search terms are lowercase (bug in releases prior to 3.0.2).
	$c = q('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'search WHERE lower(word) <> word');
	while ($r = db_rowobj($c)) {
		try {
			q('UPDATE '. $DBHOST_TBL_PREFIX .'search SET word = lower(word) WHERE id = '. $r->id);
		} catch (Exception $e) {
			q('DELETE FROM '. $DBHOST_TBL_PREFIX .'search WHERE id = '. $r->id);
		}
	}

	// Fix gravatars (add missing quote; bug in gravatar.plugin prior to 3.0.9).
	q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET avatar_loc=REPLACE(avatar_loc, \'r=g alt=\', \'r=g" alt=\')  WHERE avatar_loc LIKE \'%gravatar.com%\'');

	pf('SQL upgrades completed.');

	// FUDforum 3.0.3 refedined FUD_OPT_3=536870912 as PAGES_ENABLED.
	require($GLOBALS['DATA_DIR'] .'include/page_adm.inc');
	fud_page::enable_disable_pages_icon();

	if (!q_singleval(q_limit('SELECT id FROM '. $DBHOST_TBL_PREFIX .'themes WHERE '. q_bitand('theme_opt', 3) .' > 0', 1))) {
		pf('Setting default theme');
		if (!q_singleval('SELECT id FROM '. $DBHOST_TBL_PREFIX .'themes WHERE id=1')) {
			q('INSERT INTO '. $DBHOST_TBL_PREFIX .'themes (id, name, theme, lang, locale, theme_opt, pspell_lang) VALUES(1, \'default\', \'default\', \'en\', \'C\', 3, \'en\')');
		} else {
			q('UPDATE '. $DBHOST_TBL_PREFIX .'themes SET name=\'default\', theme=\'default\', lang=\'en\', locale=\'C\', theme_opt=3, pspell_lang=\'en\' WHERE id=1');
		}
		q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET theme=1');
	}

	/* Theme fixer upper for the admin users lacking a proper theme.
	 * this is essential to ensure the admin user can login.
	 */
	$df_theme = q_singleval(q_limit('SELECT id FROM '. $DBHOST_TBL_PREFIX .'themes WHERE '. q_bitand('theme_opt', 3) .' > 0', 1));
	$c = q('SELECT u.id FROM '. $DBHOST_TBL_PREFIX .'users u LEFT JOIN '. $DBHOST_TBL_PREFIX .'themes t ON t.id=u.theme WHERE '. q_bitand('u.users_opt', 1048576) .' > 0 AND t.id IS NULL');
	while ($r = db_rowarr($c)) {
		$bt[] = $r[0];
	}
	unset($c);
	if (isset($bt)) {
		q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET theme='. $df_theme .' WHERE id IN('. implode(',', $bt) .')');
	}

	pf('Checking GLOBAL variables.');
	// New GLOBALS.php settings to add.
	$default = array(
		'FUD_OPT_4'	=> 3,	// New in 3.0.2.
	);
	foreach ($default as $k => $v) {
		if (!isset($GLOBALS[$k])) {
			pf('Add variable '. $k .' with default value '. $v);
			change_global_settings(array($k => $v));
		}
	}

	/* List of obsolete files in WWW_ROOT_DISK that should be removed. */
	// JavaScript files moved to '/js' directory in 3.0.2.
	$rm_wwwroot = array('jquery.js', 'lib.js');
 	foreach ($rm_wwwroot as $f) {
 		if (file_exists($GLOBALS['WWW_ROOT_DISK'] . $f)) {
			unlink($GLOBALS['WWW_ROOT_DISK'] . $f);
		}
 	}

 	/* Remove obsolete ACP scripts. */
	$rm_adm = array('adm.css',		// Moved to 'style' subdirectory (3.0.1).
			  'admadduser.php',	// Renamed to admuseradd.php (3.0.1).
			  'admpanel.php',	// Renamed to header.php (3.0.1).
			  'admclose.html',	// Renamed to footer.php (3.0.1).
			  'admaprune.php',	// Renamed to admpruneattch.php (3.0.2).
			  'admbatch.php',	// Renamed to admjobs.php (3.0.2).
			  'admdelfrm.php',	// Renamed to admforumdel.php (3.0.3).
			  'admaccapr.php',	// Renamed to admuserapr.php (3.0.3).
			  'admapprove_avatar.php',	// Renamed to admavatarapr.php (3.0.3).
			  'admslist.php'	// Renamed to admprivlist.php (3.0.5).
			 );
	foreach ($rm_adm as $f) {
 		if (file_exists($GLOBALS['WWW_ROOT_DISK'] .'adm/'. $f)) {
			unlink($GLOBALS['WWW_ROOT_DISK'] .'adm/'. $f);
		}
 	}

	/* Remove obsolete include files. */
	$rm_inc = array('file_adm.inc');	// Renamed to fs.inc (3.0.5).
	foreach ($rm_inc as $f) {
		if (file_exists($GLOBALS['DATA_DIR'] .'include/'. $f)) {
			unlink($GLOBALS['DATA_DIR'] .'include/'. $f);
		}
	}

	/* Remove obsolete SQL files. */
	$rm_sql = array('def_users.sql',	// Merge into install.php (3.0.2).
			'fud_thread_view.tbl',	// Renamed to fud_tv_1.tbl (3.0.2).
			'fud_style.tbl',	// Left over from an ancient release.
			'fud_settings.tbl');	// Another old file.
	foreach ($rm_sql as $f) {
		if (file_exists($GLOBALS['DATA_DIR'] .'sql/'. $f)) {
			unlink($GLOBALS['DATA_DIR'] .'sql/'. $f);
		}
	}

	/* Remove obsolete plugin files. */
	$rm_plugins = array('apc_cache.plugin',	// Renamed to apccache.plugin (3.0.2).
			    'irc.plugin',	// Renamed to ircbot/ircbot.plugin (3.0.4RC2).
			    'google_adsense.plugin',   // Moved to 'google/' subdir in 3.0.5
			    'google_analytics.plugin', // Moved to 'google/' subdir in 3.0.5
			    'google_cdn.plugin',       // Moved to subdir 'google/' in 3.0.5
			    'youtube_tag.plugin', // Moved to video_tags.plugin in 3.0.5
			    'recaptcha/recaptchalib.php'); // Not needed for reCAPTCHA v2 in 3.0.9
	foreach ($rm_plugins as $f) {
		if (file_exists($GLOBALS['DATA_DIR'] .'plugins/'. $f)) {
			unlink($GLOBALS['DATA_DIR'] .'plugins/'. $f);
		}
	}

	/* Move plugin config files. */
	$mv_plugins = array('google_adsense.ini', // Moved to google/ subdir in 3.0.5
			    'google_analytics.ini'); // Moved to google/ subdir in 3.0.5
	foreach ($mv_plugins as $f) {
		if (file_exists($GLOBALS['DATA_DIR'] .'plugins/'. $f)) {
			rename($GLOBALS['DATA_DIR'] .'plugins/'. $f, $GLOBALS['DATA_DIR'] .'plugins/google/'. $f);
		}
	}

	/* Updata DB with new plugin locations. */
	q('UPDATE '. $DBHOST_TBL_PREFIX .'plugins SET name = \'apccache.plugin\' WHERE name = \'apc_cache.plugin\'');
	q('UPDATE '. $DBHOST_TBL_PREFIX .'plugins SET name = \'ircbot/ircbot.plugin\' WHERE name = \'irc.plugin\'');
	q('UPDATE '. $DBHOST_TBL_PREFIX .'plugins SET name = \'google/google_analytics.plugin\' WHERE name = \'google_analytics.plugin\'');
	q('UPDATE '. $DBHOST_TBL_PREFIX .'plugins SET name = \'google/google_cdn.plugin\' WHERE name = \'google_cdn.plugin\'');
	q('UPDATE '. $DBHOST_TBL_PREFIX .'plugins SET name = \'google/google_adsense.plugin\' WHERE name = \'google_adsense.plugin\'');
	q('UPDATE '. $DBHOST_TBL_PREFIX .'plugins SET name = \'video_tags.plugin\' WHERE name = \'youtube_tag.plugin\'');

	/* Remove obsolete SRC files. */
	$rm_src = array('tz.inc.t');	// Remove from 3.0.2.
	foreach ($rm_src as $f) {
		if (file_exists($GLOBALS['DATA_DIR'] .'src/'. $f)) {
			unlink($GLOBALS['DATA_DIR'] .'src/'. $f);
		}
	}

	/* Remove obsolete DEFAULT template files. */
	$rm_default_tmpl = array('tz.tmpl');	// Remove from 3.0.2.
	foreach ($rm_default_tmpl as $f) {
		if (file_exists($GLOBALS['DATA_DIR'] .'thm/default/tmpl/'. $f)) {
			unlink($GLOBALS['DATA_DIR'] .'thm/default/tmpl/'. $f);
		}
	}

 	/* Remove obsolete PATH_INFO templates. */
	$rm_pathinfo_tmpl = array('header.tmpl');	// PATH_INFO doesn't have a header.tmpl any more.
 	foreach ($rm_pathinfo_tmpl as $f) {
 		if (file_exists($GLOBALS['DATA_DIR'] .'thm/path_info/tmpl/'. $f)) {
			unlink($GLOBALS['DATA_DIR'] .'thm/path_info/tmpl/'. $f);
		}
 	}

	/* Delete old NNTP and MLIST error logs (moved one directory up in 3.0.2). */
	$rm_err = array('.nntp/error_log', '.mlist/error_log');
 	foreach ($rm_err as $f) {
 		if (file_exists($GLOBALS['ERROR_PATH'] . $f)) {
			unlink($GLOBALS['ERROR_PATH'] . $f);
		}
	}

	/* Remove 'firebird' directory. Renamed to 'interbase' in 3.0.3. */
	if (file_exists($GLOBALS['DATA_DIR'] .'sql/firebird/db.inc')) {
		__rmdir($GLOBALS['DATA_DIR'] .'sql/firebird', true);
	}

	/* Correct language code for Norwegian from no to nb in 3.0.4. */
	if (file_exists($GLOBALS['DATA_DIR'] .'thm/default/i18n/no/msg')) {
		q('UPDATE '. $DBHOST_TBL_PREFIX .'themes SET lang = \'nb\' WHERE lang = \'no\'');
		__rmdir($GLOBALS['DATA_DIR'] .'thm/default/i18n/no', true);
	}

	/* Avatar validator. */
	$list = glob($WWW_ROOT_DISK ."images/custom_avatars/*.[pP][hH][pP]");
	if ($list) {
		foreach ($list as $v) {
			unlink($v);
			q('UPDATE '. $DBHOST_TBL_PREFIX. 'users SET users_opt = '. q_bitor( q_bitand('users_opt', ~(16777216|8388608)), 4194304) .' WHERE id='. (int)basename(strtolower($v), '.php'));
		}
	}

	/* Forum icon checker. */
	$list = array();
	$c = q('SELECT id, forum_icon FROM '. $DBHOST_TBL_PREFIX .'forum WHERE forum_icon IS NOT NULL AND forum_icon != \'\'');
	while ($r = db_rowarr($c)) {
		if (($n = basename($r[1])) != $r[1]) {
			$list[$r[0]] = $n;
		}
	}
	foreach ($list as $k => $v) {
		q('UPDATE '. $DBHOST_TBL_PREFIX .'forum SET forum_icon=\''. addslashes($v) .'\' WHERE id='. $k);
	}

	/* Loop through themes for maintenance. */
	require($GLOBALS['DATA_DIR'] .'include/compiler.inc');
	$c = q('SELECT theme, lang, name FROM '. $DBHOST_TBL_PREFIX .'themes WHERE '. q_bitand('theme_opt', 1) .' > 0 OR id=1');
	while ($r = db_rowarr($c)) {
		// See if custom themes need to have their files updated.
		if ($r[0] != 'default' && $r[0] != 'path_info' && $r[0] != 'user_info_left' && $r[0] != 'user_info_right' && $r[0] != 'forestgreen' && $r[0] != 'slateblue' && $r[0] != 'twilightgrey') {
			if (empty($_POST['custom_tmpl'])) {
				pf('Please manually update custom theme '. $r[2] .'.');
			} else {
				pf('Updating files of custom theme '. $r[2] .'.');
				syncronize_theme($r[0]);
			}
		}
		foreach ($rm_default_tmpl as $f) {
			if (file_exists($GLOBALS['DATA_DIR'] .'thm/'. $r[0] .'/tmpl/'. $f)) {
				if (empty($_POST['custom_tmpl'])) {
					pf('Please remove file '. $GLOBALS['DATA_DIR'] .'thm/'. $r[0] .'/tmpl/'. $f .' as it is not part of FUDforum anymore.');
				} else {
					unlink($GLOBALS['DATA_DIR'] .'thm/'. $r[0] .'/tmpl/'. $f);
				}
			}
		}
		if (@file_exists($GLOBALS['DATA_DIR'] .'thm/'. $r[0] .'/.path_info')) {
			foreach ($rm_pathinfo_tmpl as $f) {
				if (file_exists($GLOBALS['DATA_DIR'] .'thm/'. $r[0] .'/tmpl/'. $f)) {
					if (empty($_POST['custom_tmpl'])) {
						pf('Please remove file '. $GLOBALS['DATA_DIR'] .'thm/'. $r[0] .'/tmpl/'. $f .' as it is not part of FUDforum anymore.');
					} else {
						unlink($GLOBALS['DATA_DIR'] .'thm/'. $r[0] .'/tmpl/'. $f);
					}
				}
			}
		}

		try {
			compile_all($r[0], $r[1], $r[2]);
			pf('Theme '. $r[2] .' was successfully compiled.');
		} catch (Exception $e) {
			pf('Unable to compile theme '. $r[2] .'. Please fix it manually: <span style="color:red;">'.  $e->getMessage() .'</span>');
			if ($r[2] == 'default') {
				pf('<b>FATAL ERROR: The consistency checker requires components from the default theme. You will have to fix this theme or switch to a valid template set before you can finalize the upgrade!</b>');
				die();
			}
		}
	}
	unset($c);

	/* Log upgrade action. */
	q('INSERT INTO '. $DBHOST_TBL_PREFIX .'action_log (logtime, logaction, user_id, a_res) VALUES ('. __time__ .', \'Forum\', '. $auth .', \'Upgraded from '. $FORUM_VERSION .'\')');

	if (php_sapi_name() == 'cli') {
		pf('Almost done! Please run the consistency checker to complete the upgrade process.');
		exit;
	}
?>

<div class="tutor">All changed files were backed up to: <small><?php echo $GLOBALS['ERROR_PATH'] .'.backup/'; ?></small>.</div>
<div class="tutor">If everything went well, you may click on the button below to continiue to step 3.</div>

<br />
<form name="upgrade" action="<?php echo basename(__FILE__); ?>" method="post">
<table width="100%" class="datatable solidtable">
<input type="hidden" name="login" value="<?php echo $_POST['login'] ?>" />
<input type="hidden" name="passwd" value="<?php echo $_POST['passwd'] ?>" />
<input type="hidden" name="step" value="3" />
<tr class="fieldaction">
	<td align="right"><input type="submit" class="button" name="submit" value="Continue to Step 3 >>" /></td>
</tr>
</table>
</form>

</td></tr></table>
</body>
</html>

