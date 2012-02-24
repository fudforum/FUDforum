<?php
/***************************************************************************
* copyright            : (C) 2001-2012 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License. 
***************************************************************************/
//TODO: Do we still need extract_archive()???

$__UPGRADE_SCRIPT_VERSION = 5304.0;

/*
  * SQL Upgrade Functions - format is tablename_colname():
  * These functions will be called when a column is modified or a new column is added to a table.
  * Old columns can still be referenced (they will be dropped after all new columns were added).
  */

// Move Usenet trackers into the database (3.0.0->3.0.1).
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

// Change birthday from NUMBER to CHAR(8) (3.0.1->3.0.2).
// New format is MMDDYYYY.
// Requred for index lookups on leading string. I.e: WHERE birthday LIKE 'mmdd%';
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

		// echo('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users SET birthday=\''. $mm . $dd . $yyyy .'\' WHERE id='. $r[0] ."\n");
		q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'users SET birthday=\''. $mm . $dd . $yyyy .'\' WHERE id='. $r[0]);
	}
	pf('Birthday format change completed.');
}

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
		echo $msg . (stripos($msg, '<h2>')!==FALSE ? '' : '<br />');
		@ob_flush(); flush();
	}
}

/** Print error and exit. */
function seterr($msg)
{
	if (php_sapi_name() == 'cli') {
		exit($msg);
	} else {
		exit('<p class="alert">'. $msg .'</p></body></html>');
	}
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

/** Error handler for DB driver. */
function fud_sql_error_handler($query, $error_string, $error_number, $server_version)
{
	throw new Exception($error_number .': '. $error_string .' @ '. $query);
}

/** Find the CVS or SVN ID property. This is used to check if a file was changed.
  * Should eventually be moved to include/file_adm.inc.
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
  * Should eventually be moved to include/file_adm.inc.
  */
function backup_file($source, $theme='')
{
	$theme .= md5($source);
	copy($source, $GLOBALS['ERROR_PATH'] .'.backup/'. basename($source) .'_'. $theme .'_'. __time__);
}

/** Recursively create a given directory.
  * Should eventually be replaced with fud_mkdir in include/file_adm.inc.
  */
function __mkdir($dir)
{
	$perm = (($GLOBALS['FUD_OPT_2'] & 8388608) && !strncmp(PHP_SAPI, 'apache', 6)) ? 0711 : 0777;

	if (@is_dir($dir)) {
		@chmod($dir, $perm);
		return 1;
	}

	$ret = (mkdir($dir, $perm) || mkdir(dirname($dir), $perm));

	return $ret;
}

/** Recursively delete a given directory.
  * Copied from include/file_adm.inc. We cannot currently include it as file_adm.inc was introduced after 3.0.0 can only be included at the end of the upgrade script.
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
 * Upgrade GLOBALS.php to new format 
 */
function upgrade_globals_php()
{

	pf('Upgrading GLOBALS.php');
	$core = file_get_contents($GLOBALS['DATA_DIR'] .'include/core.inc');
	$FORUM_VERSION = preg_replace('/.*FORUM_VERSION = \'(.*?)\';.*/s', '\1', $core);
	if (version_compare($FORUM_VERSION, '3.0.4', '<')) {
		$new = '';
		$f = fopen($GLOBALS['INCLUDE'].'GLOBALS.php','r');
		while($s=fgets($f)) {
			$new .= preg_replace('/(\t)\$([A-Z_1-9]*)([\s\t]*)/i','$1$GLOBALS[\'$2\']$3',$s);
		}
		fclose( $f );
		file_put_contents($GLOBALS['INCLUDE'].'GLOBALS.php',$new);
	}
}

function upgrade_decompress_archive($data_root, $web_root)
{
	$clean = array('PHP_OPEN_TAG'=>'<?', 'PHP_OPEN_ASP_TAG'=>'<%');

	/* Install from './fudforum_archive' file. */
	// $GLOBALS['no_mem_limit'] may look strange in this context, but it is actually $no_mem_limit defined earlier.
	if ($GLOBALS['no_mem_limit']) {	
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

	/* Install from embedded file archive. */
	} else {
		if (DIRECTORY_SEPARATOR == '/' && defined('__COMPILER_HALT_OFFSET__')) {
			$data = stream_get_contents(fopen(__FILE__, 'r'), max_a_len, __COMPILER_HALT_OFFSET__ + 4); /* 4 = " ?>\n" */
			$p = 0;
		} else { 
			$data = file_get_contents(__FILE__);
			$p = strpos($data, '<?php __HALT_'.'COMPILER(); ?>') + strlen('<?php __HALT_'.'COMPILER(); ?>') + 1;
		}
		$checksum = substr($data, $p, 32);
		$data = substr($data, $p + 32);
		if (strpos($data, 'RAW_PHP_OPEN_TAG') !== FALSE) {	/* No compression. */
			unset($clean['PHP_OPEN_TAG']); $clean['RAW_PHP_OPEN_TAG'] = '<?';
			$data = strtr($data, $clean);
		} else {
			$data_len = (int) substr($data, 0, 10);
			// Data should be @ least 100k.
			if ($data_len < 100000) {
				exit('Failed getting archive size from '. htmlentities(substr($data, 0, 10)));
			}
			$data = strtr(substr($data, 10), $clean);

			if (!($data = gzuncompress($data, $data_len))) {	/* Compression. */
				exit('Failed decompressing the archive.');
			}
		}
	}

	$pos = 0;
	$perm = ((($GLOBALS['FUD_OPT_2'] & 8388608) && !strncmp(PHP_SAPI, 'apache', 6)) ? 0177 : 0111);

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
		$path .= '/' . $meta_data[1];

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

			if (!($fp = @fopen($path, 'wb'))) {
				if (basename($path) != '.htaccess') {
					seterr('Couldn\'t open "'. $path .'" for write');
				}
			}	
			fwrite($fp, $file);
			fclose($fp);
			@chmod($file, $perm);
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
	$path = $GLOBALS['DATA_DIR'] .'thm/'. $theme .'/'. $dir;
	$spath = $GLOBALS['DATA_DIR'] .'thm/'. $src_thm .'/'. $dir;

	if (!__mkdir($path)) {
		seterr('Directory "'. $path .'" does not exist, and the upgrade script failed to create it.');	
	}
	if (!($d = opendir($spath))) {
		seterr('Failed to open "'. $spath .'"');
	}
	readdir($d); readdir($d);
	$path .= '/';
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

function extract_archive($memory_limit)
{
	$fsize = filesize(__FILE__);

	if ($fsize < 200000 && !@file_exists('./fudforum_archive')) {
		seterr('The upgrade script is missing the data archive and cannot run. Please download it again and retry.');
	} else if ($fsize > 200000 || !$memory_limit) {
		$clean = array('PHP_OPEN_TAG'=>'<?', 'PHP_OPEN_ASP_TAG'=>'<%');
		if ($memory_limit) {
			if (!($fp = fopen('./fudforum_archive', 'wb'))) {
				$err = 'Please make sure that the intaller has permission to write to the current directory ('. getcwd() .')';
				if (!SAFE_MODE) {
					$err .= '<br />or create a "fudforum_archive" file inside the current directory and make it writable to the webserver.';
				}
				seterr($err);
			}

			$fp2 = fopen(__FILE__, 'rb');

			if (defined('__COMPILER_HALT_OFFSET__')) { /*  PHP 5.1 with halt support. */
				$main = stream_get_contents($fp2, __COMPILER_HALT_OFFSET__ + 4); /* 4 == " ?>\n" */
				fseek($fp2, __COMPILER_HALT_OFFSET__ + 4, SEEK_SET);
			} else {
				$main = '';

				$l = strlen('<?php __HALT_' . 'COMPILER(); ?>');

				while (($line = fgets($fp2))) {
					$main .= $line;
					if (!strncmp($line, '<?php __HALT_' . 'COMPILER(); ?>', $l)) {
						break;
					}
				}
			}
			$checksum = fread($fp2, 32);
			$pos = ftell($fp2);

			if (($zl = strpos(fread($fp2, 20000), 'RAW_PHP_OPEN_TAG')) === FALSE && !extension_loaded('zlib')) {
				seterr('The upgrade script uses zlib compression, however your PHP was not compiled with zlib support or the zlib extension is not loaded. In order to get the upgrade script to work you\'ll need to enable the zlib extension or download a non compressed upgrade script from <a href="http://fudforum.org/forum/">http://fudforum.org/forum/</a>');
			}
			fseek($fp2, $pos, SEEK_SET);
			if ($zl) {
				$rep = array('RAW_PHP_OPEN_TAG', 'PHP_OPEN_ASP_TAG');
				$rept = array('<?', '<%');

				while (($line = fgets($fp2))) {
					fwrite($fp, str_replace($rep, $rept, $line));
				}
			} else {
				$data_len = (int) fread($fp2, 10);
				fwrite($fp, gzuncompress(strtr(fread($fp2, $data_len), $clean), $data_len));
			}
			fclose($fp);
			fclose($fp2);

			if (md5_file('./fudforum_archive') != $checksum) {
				seterr('Archive did not pass checksum test, CORRUPT ARCHIVE!<br />If you\'ve encountered this error it means that you\'ve:<br />&nbsp;&nbsp;&nbsp;&nbsp;downloaded a corrupt archive<br />&nbsp;&nbsp;&nbsp;&nbsp;uploaded the archive in BINARY and not ASCII mode<br />&nbsp;&nbsp;&nbsp;&nbsp;your FTP Server/Decompression software/Operating System added un-needed cartrige return (\'\r\') characters to the archive, resulting in archive corruption.');	
			}

			/* Move the data archive from upgrade script. */
			$fp2 = fopen(__FILE__, 'wb');
			fwrite($fp2, $main);
			fclose($fp2);
			unset($main);
		} else {
			if (DIRECTORY_SEPARATOR == '/' && defined('__COMPILER_HALT_OFFSET__')) {
				$data = stream_get_contents(fopen(__FILE__, 'r'), max_a_len, __COMPILER_HALT_OFFSET__ + 4); /* 4 = " ?>\n" */
				$p = 0;
			} else { 
				$data = file_get_contents(__FILE__);
				$p = strpos($data, '<?php __HALT_'.'COMPILER(); ?>') + strlen('<?php __HALT_'.'COMPILER(); ?>') + 1;
			}
			if (($zl = strpos($data, 'RAW_PHP_OPEN_TAG', $p)) === FALSE && !extension_loaded('zlib')) {
				seterr('The upgrade script uses zlib compression, however your PHP was not compiled with zlib support or the zlib extension is not loaded. In order to get the upgrade script to work you\'ll need to enable the zlib extension.');
			}
			$checksum = substr($data, $p, 32);
			$p += 32;
			if (!$zl) {
				$data_len = (int) substr($data, $p, 10);
				$p += 10;
				$data = gzuncompress(strtr(substr($data, $p), $clean), $data_len);
			} else {
				unset($clean['PHP_OPEN_TAG']); $clean['RAW_PHP_OPEN_TAG'] = '<?';
				$data = strtr(substr($data, $p), $clean);
			}
			if (md5($data) != $checksum) {
				seterr('Archive did not pass checksum test, CORRUPT ARCHIVE!<br />If you\'ve encountered this error it means that you\'ve:<br />&nbsp;&nbsp;&nbsp;&nbsp;downloaded a corrupt archive<br />&nbsp;&nbsp;&nbsp;&nbsp;uploaded the archive in ASCII and not BINARY mode<br />&nbsp;&nbsp;&nbsp;&nbsp;your FTP Server/Decompression software/Operating System added un-needed cartrige return (\'\r\') characters to the archive, resulting in archive corruption.');
			}
			return $data;
		}
	}	
}

/* main program */
	error_reporting(E_ALL);
	ignore_user_abort(true);
	@set_magic_quotes_runtime(0);	// Depricated in PHP 5.3.
	@set_time_limit(0);

	error_reporting(E_ALL);
	fud_ini_set('memory_limit', '128M');	// PHP 5.3's default, old defaults too small.
	$no_mem_limit = ini_get('memory_limit');
	if ($no_mem_limit) {
		$no_mem_limit = (int) str_replace(array('k', 'm', 'g'), array('000', '000000', '000000000'), strtolower($no_mem_limit));
		if ($no_mem_limit < 1 || $no_mem_limit > 50000000) {
			$no_mem_limit = 0;
		}
	}

	/* Force install from external "fudforum_archive" file for FUDforum 3.0.2 and later releases. */
	$no_mem_limit = 1;

	define('max_a_len', filesize(__FILE__)); // Needed for offsets.

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
		if ($no_mem_limit) {
			extract_archive($no_mem_limit);
		}
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
		seterr('The upgrade script requires a "fudforum_archive" file to run. Please download it again and retry.');
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
	if (@file_exists($GLOBALS['ERROR_PATH'] .'UPGRADE_STATUS') && (int) trim(file_get_contents($ERROR_PATH .'UPGRADE_STATUS')) >= $__UPGRADE_SCRIPT_VERSION) {
		seterr('THIS UPGRADE SCRIPT HAS ALREADY BEEN RUN, IF YOU WISH TO RUN IT AGAIN USE THE FILE MANAGER TO REMOVE THE "'. $GLOBALS['ERROR_PATH'] .'UPGRADE_STATUS" FILE.');
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
	if (!function_exists('q_bitand')) {
		function q_bitand($fieldLeft, $fieldRight) {
			return $fieldLeft .' & '. $fieldRight;
		}
	}

	// Another temp hack. Manually check MySQL DB version 
	// Should be replaced by validate_db_version() introduced in 3.0.2 (as implemented below).
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
/* REMOVE
		if ($no_mem_limit && !@is_writeable(__FILE__)) {
			seterr('You need to chmod the '. __FILE__ .' file 666 (-rw-rw-rw-), so that the upgrade script can modify itself.');
		}
*/
		if ($no_mem_limit) {
			extract_archive($no_mem_limit);
		}
		if (php_sapi_name() == 'cli') {
			seterr('Usage: upgrade.php admin_user admin_password');
		}

pf('<h2>Step 1: Admin login</h2>', true);

?>
<form name="upgrade" action="<?php echo basename(__FILE__); ?>" method="post">
<p>Please enter the login and password of the administration account:</p>
<table class="datatable solidtable">
<tr class="field">
	<td><b>Login:</b><br /><small>Your forum's admin user.<small></td>
	<td><input type="text" name="login" value="" /></td>
</tr>
<tr class="field">
	<td><b>Password:</b><br /><small>Your forum's admin password.<small></td>
	<td><input type="password" name="passwd" value="" /></td>
</tr>
<tr class="field">
	<td><label for="custom_tmpl" title="If unsure, leave unchecked!"><b>Update custom template sets?</b><br /><small>Leave unchecked to preserve custom styling (prevent FUDforum from updating custom template sets, you will have to do it manually!)</small></label></td>
	<td><input type="checkbox" id="custom_tmpl" name="custom_tmpl" value="1" /></td>
</tr>
<tr class="field">
	<td><label for="custom_sql" title="If unsure, leave unchecked!"><b>Skip database changes?</b><br /><small>Check if you've modified FUDforum's SQL structure. You will have to apply the SQL changes yourself!</small></label></td>
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

	/* Load glob.inc for functions like change_global_settings(). */
	require($INCLUDE .'glob.inc');

	/* Disable the forum. */
	pf('Disabling the forum.');
	// We would normally do this with maintenance_status(). However, since we will not re-enable 
	// the forum (done in consist.php), we will not be able to restore the disable reason.
	change_global_settings(array('FUD_OPT_1' => ($GLOBALS['FUD_OPT_1'] &~ 1)));
	pf('Forum is now disabled.<br />');

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
		if (!is_dir($tdir)) {
			continue;
		}
		$lp = opendir($tdir);
		while ($le = readdir($lp)) {
			if (!array_key_exists($le, $langmap)) {	// Not in convertion map.
				continue;
			}

			// Remove old unused 'pspell_lang' files (3.0.0->3.0.1).
			if (file_exists($tdir.$le .'/pspell_lang')) {
				@unlink($tdir.$le .'/pspell_lang');
			}

			pf('Rename directory '. $te .'/i18n/'. $le .' to '. $langmap[$le]);
			@rename($tdir.$le, $tdir.$langmap[$le]);
			q('UPDATE '. $DBHOST_TBL_PREFIX .'themes SET lang=\''. addslashes($langmap[$le]) .'\' WHERE lang=\''. addslashes($le) .'\'');
		}
		closedir($lp);
	}
	closedir($tp);

	/* Upgrade globals variable to $_GLOBALS["xxx"] style */
	upgrade_globals_php();

	/* Upgrade files. */
	pf('Beginning the file upgrade process.');
	__mkdir($GLOBALS['ERROR_PATH'] .'.backup');
	define('__time__', time());
	pf('Beginning to decompress the archive.');
	upgrade_decompress_archive($GLOBALS['DATA_DIR'], $GLOBALS['WWW_ROOT_DISK']);

	/* Determine if this host can support .htaccess directives. */
	if (!defined('old_htaccess')) {
		htaccess_handler($GLOBALS['WWW_ROOT'], $GLOBALS['WWW_ROOT_DISK'] .'.htaccess');
	}
	pf('Finished decompressing the archive.');
	pf('File Upgrade Complete.');
	pf('<div class="tutor">All changed files were backed up to: "'. $GLOBALS['ERROR_PATH'] .'.backup/".</div>');

	/* Update database. */
	pf('Beginning SQL Upgrades.');

	// NOTE: dbadmin.inc becomes available in 3.0.2. We cannot use it until we've unpacked the new files.
	// Checking of SQL permisions should actuallty be done BEFORE we unpack - a catch 22.
	//TODO: Remember to move the code up in a later version again.
	include_once $GLOBALS['DATA_DIR'] .'include/dbadmin.inc';

	//TODO: Better late than never, move version checks up in future release (with loading of dbadmin.inc).
	/* Validate database version. */
	if (!isset($GLOBALS['errors'])) {
		$err = validate_db_version();
		if (!empty($err)) {
			seterr('DBHOST_DBNAME', $err);
		}
	}

	/* Check SQL permissions. */
	pf('Checking if SQL permissions to perform the upgrade are available.');
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

	/* Compare table definitions with what's on the DB and make corrections. */
	$db_tables = array_flip(get_fud_table_list());
	foreach (glob($GLOBALS['DATA_DIR'] .'/sql/*.tbl', GLOB_NOSORT) as $v) {
		$tbl = get_stbl_from_file($v);
		// echo 'Check table: '. $tbl['name'] ."\n";
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
				// echo ' - check column: '. $k ."\n";

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
					if (function_exists($f)) {
						$f($db_col);
					}
				} else if (array_diff_assoc($db_col[$k], $v2)) {
					/* Column definition has changed. */
					pf('Alter database column '. $k .' in table '. $tbl['name'] .'.');
					alter_column($tbl['name'], $k, $v2);
					$f = substr("{$tbl['name']}_{$k}", strlen($DBHOST_TBL_PREFIX));
					if (function_exists($f)) {
						$f($db_col);
					}
				}
				unset($db_col[$k]);	// Column still in use, no need to drop it.
			}

			/* Remove unused columns. */
			foreach (array_keys($db_col) as $v) {
				if (empty($_POST['custom_sql'])) {	// Standard or customized DB schema?
					pf('Drop unused database column '. $v .' from table '. $tbl['name'] .'.');
					drop_column($tbl['name'], $v);
				} else {
					pf('WARNING: Unused database column '. $v .' in table '. $tbl['name'] .'. Unless you\'ve added it, it should be dropped!');
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

	// Ensure all search terms are lowercase (bug in releases prior to 3.0.2).
	$c = q('SELECT id FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'search WHERE lower(word) <> word');
	while ($r = db_rowobj($c)) {
		try {
			q('UPDATE '. $DBHOST_TBL_PREFIX .'search SET word = lower(word) WHERE id = '. $r->id);
		} catch (Exception $e) {
			q('DELETE FROM '. $DBHOST_TBL_PREFIX .'search WHERE id = '. $r->id);
		}
	}

	pf('SQL Upgrades Complete.<br />');

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

	pf('Checking GLOBAL Variables.');
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
			 );
	foreach ($rm_adm as $f) {
 		if (file_exists($GLOBALS['WWW_ROOT_DISK'] .'adm/'. $f)) {
			unlink($GLOBALS['WWW_ROOT_DISK'] .'adm/'. $f);
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
	$rm_plugins = array('apc_cache.plugin');	// Renamed to apccache.plugin (3.0.2).
	foreach ($rm_plugins as $f) {
		if (file_exists($GLOBALS['DATA_DIR'] .'plugins/'. $f)) {
			unlink($GLOBALS['DATA_DIR'] .'plugins/'. $f);
		}
	}

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
	$rm_pathinfo_tmpl = array();
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

	// Loop through themes for maintenance.
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
			pf('Theme '. $r[2] .' was successfuly compiled.');
		} catch (Exception $e) {
			pf('Unable to compile theme '. $r[2] .'. Please fix it manually: '.  $e->getMessage());
			if ($r[2] == 'default') {
				pf('<b>IMPORTANT: The consistency checker requires components from the default theme. You will have to fix this theme or switch it to a valid template set before you can finalize the upgrade!</b>');
			}
		}
	}
	unset($c);

	/* Insert update script marker. */
	$fp = fopen($GLOBALS['ERROR_PATH'] .'UPGRADE_STATUS', 'wb');
	fwrite($fp, $__UPGRADE_SCRIPT_VERSION);
	fclose($fp);

	/* Log upgrade action. */
	q('INSERT INTO '. $DBHOST_TBL_PREFIX .'action_log (logtime, logaction, user_id, a_res) VALUES ('. __time__ .', \'Forum\', '. $auth .', \'Upgraded from '. $FORUM_VERSION .'\')');

	/* Remove UPGRADE script if the user won't be able to do it himself. */
	if (SAFE_MODE && basename(__FILE__) == 'upgrade_safe.php') {
		unlink(__FILE__);
	}
/* REMOVE?
	if ($no_mem_limit) {
		@unlink('./fudforum_archive');
	}
*/

	if (php_sapi_name() == 'cli') {
		pf('Done! Please run the consistency checker to complete the upgrade process.');
		exit;
	}

	/* Get session details to construct link to consistency checker. */
	$pfx = db_sab('SELECT u.sq, s.ses_id FROM '. $DBHOST_TBL_PREFIX .'users u INNER JOIN '. $DBHOST_TBL_PREFIX .'ses s ON u.id=s.user_id WHERE u.id='. $auth);
	if ($pfx && $pfx->sq) {
		$pfxs = '&S='. $pfx->ses_id .'&SQ='. $pfx->sq;
	} else {
		$pfxs = '';
	}
?>

<h2>Step 3: Consistency check</h2>

<p>Launching the <b>consistency checker...</b></p>

<p><b>IMPORTANT NOTE:</b> If the popup with the consistency checker doesn't appear, please <span style="white-space:nowrap">&gt;&gt; <a href="adm/consist.php?enable_forum=1<?php echo $pfxs; ?>"><b>click here</b></a> &lt;&lt;</span> or navigate to the <i>Admin Control Panel</i> -&gt; <i>Forum Consistency</i> to run it.</p>
<script>
	window.open('adm/consist.php?enable_forum=1<?php echo $pfxs; ?>');
</script>

<p>Done!</p>

<div class="tutor">Please remove the upgrade script to prevent hackers from running it. The script is located at <?php echo realpath('./upgrade.php'); ?></div>

</td></tr></table>
</body>
</html>
<?php exit; ?>
<?php __HALT_COMPILER(); ?>
