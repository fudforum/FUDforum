<?php
/***************************************************************************
* copyright            : (C) 2001-2021 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
***************************************************************************/

// define('fud_debug', 1);

function fud_ini_get($opt)
{
	return (ini_get($opt) == '1' ? 1 : 0);
}

function fud_ini_set($opt, $val)
{
	if (function_exists('ini_set')) {
		@ini_set($opt, $val);
	}
}

function modules_enabled()
{
	$status = array();
	foreach (array('cubrid', 'ibm_db2', 'interbase', 'mysql', 'oci8', 'pdo_mysql', 'pdo_pgsql', 'pdo_sqlite', 'pdo_sqlsrv', 'pgsql', 'sqlsrv',
		           'mbstring', 'pcre', 'enchant', 'posix', 'zlib') as $m) {
		$status[$m] = extension_loaded($m);
	}

	// MySQLi is an extension, not a module, but we add it anyway.
	if (function_exists('mysqli_connect')) {
		$status['mysqli'] = true;
		$status['mysql']  = false;	// mysql is depricated and we have an alternative.
	} else {
		$status['mysqli'] = false;
	}

	return $status;
}

function databases_enabled() 
{
	$module_status = modules_enabled();
	$supported_databases = array(/*'cubrid'=>'CUBRID',*/ 'ibm_db2'=>'IBM DB2', 'interbase'=>'Firebird', 'mysql'=>'MySQL', 'mysqli'=>'MySQL Improved', 'oci8'=>'Oracle', 'pgsql'=>'PostgreSQL', 'sqlsrv' => 'SQL Server (Microsoft)', 'pdo_mysql'=>'PDO: MySQL', 'pdo_pgsql'=>'PDO: PostgreSQL', 'pdo_sqlite'=>'PDO: SQLite', 'pdo_sqlsrv'=>'PDO: SQL Server (Microsoft)');

	foreach ($supported_databases as $driver => $name) {
		if (!$module_status[$driver]) {
			unset($supported_databases[$driver]);	// Remove DBs that's not available.
		}
	}

	return $supported_databases;
}

/** Create a directory. 
 *  Basically the same as fud_mkdir() in include/fs.inc, but we need it before it can be included.
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

function is_wr($path)
{
	while ($path && $path != '/') {
		if (@is_writeable($path)) {
			return 1;
		}
		$path = dirname($path);
	}
	return 0;
}

function validate_url($url)
{
	global $_POST;

	if (!$url) {
		return 0;
	}

	if (($u = @parse_url($url)) && isset($u['host'])) {
		$_POST['WWW_ROOT'] = $url;
		if (substr($_POST['WWW_ROOT'], -1) != '/') {
			$_POST['WWW_ROOT'] .= '/';
		}
		$u['host'] = preg_replace('/[\[\]]/', '', $u['host']);   // Remove IPv6 brackets. i.e. [::1]
		if ($u['host'] != 'localhost' && !filter_var($u['host'], FILTER_VALIDATE_IP)) {
			$_POST['COOKIE_DOMAIN'] = preg_replace('!^www\.!i', '.', $u['host']);
			$_POST['COOKIE_PATH']   = $u['path'];
		} else {
			$_POST['COOKIE_PATH']   = '/';
		}
		return 1;
	}
	return 0;
}

function fix_slashes(&$val)
{
	if (!empty($val)) {
		$val = str_replace('\\', '/', $val);

		if (substr($val, -1) != '/') {
			$val .= '/';
		}
	}
	return $val;
}

function decompress_archive($data_root, $web_root)
{
	$clean = array('PHP_OPEN_TAG'=>'<?', 'PHP_OPEN_ASP_TAG'=>'<%');

	// CLI doesn't automatically change the CWD to the one the started script resides in.
	chdir(dirname(__FILE__));

	/* Install from './fudforum_archive' file. */
	$fp = fopen('./fudforum_archive', 'rb') or die('Please upload file fudforum_archive and try again.');
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
		if ($data_len > 100000) {
			$data = gzuncompress(strtr(fread($fp, $data_len), $clean), $data_len);
		}
	}
	fclose($fp);

	if (empty($data) || md5($data) != $checksum) {
		exit("Archive did not pass the checksum test, it is corrupt!<br />\nIf you've encountered this error it means that you've:<ul><li>downloaded a corrupt 'fudforum_archive' file</li><li>uploaded the archive to your server in ASCII and not BINARY mode</li><li>your FTP Server/Decompression software/Operating System added un-needed cartrige return ('\\r') characters to the archive.</li></ul>\n");
	}

	$pos = 0;

	do {
		$end = strpos($data, "\n", $pos+1);
		$meta_data = explode('//',  substr($data, $pos, ($end-$pos)));
		$pos = $end;

		if (!isset($meta_data[3]) || $meta_data[3] == '/install') {
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
				exit('ERROR: file '. $meta_data[1] .' was not read properly from archive');
			}

			if ($path == $web_root .'.htaccess' && @file_exists($path)) {
				define('old_htaccess', 1);
				continue;
			}

			if (defined('fud_debug')) echo "Extracting $path\n";
			$fp = @fopen($path, 'wb');
			if (!$fp) {
				if (basename($path) != '.htaccess') {
					exit('Couldn\'t open '. $path .' for write.');
				}
			}
// TODO: Good place for substitutions before we write the file.
			fwrite($fp, $file);
			fclose($fp);
			@chmod($path, 0644);
		} else {
			if (substr($path, -1) == '/') {
				$path = preg_replace('!/+$!', '', $path);
			}
			if (!__mkdir($path)) {
				exit('ERROR: failed creating directory '. $path);
			}
		}
	} while (($pos = strpos($data, "\n//", $pos)) !== false);
}

function get_server_uid_gid()
{
	if ($GLOBALS['module_status']['posix']) {
		$u = posix_getpwuid(posix_getuid());
		$g = posix_getgrgid($u['gid']);
		return '('. $u['name'] .'/'. $g['name'] .')';
	}
	return;
}

function check_primary_dir($dir, $type)
{
	if (!__mkdir($dir)) {
		seterr($type, 'Failed to create "'. $dir .'". Please create it manually and grant read/write permissions to the web-server user '. get_server_uid_gid());
		return 1;
	}
	if (!@is_writable($dir)) {
		seterr($type, 'Directory "'. $dir .'" exists, but the installation script is unable to write to the directory. Please grant read/write permissions to the web-server user '. get_server_uid_gid());
		return 1;
	}
	if (SAFE_MODE) {
		if (($safe = $st = @stat($dir))) {
			if (!ini_get('safe_mode_gid')) {
				$safe = (getmyuid() == $st['uid']);
			} else {
				$safe = (getmygid() == $st['gid']);
			}
		}
		if (!$safe && basename(__FILE__) != 'install.php') {
			seterr($type, 'Safe mode limitations prevent the installation script from writing to "'. $dir .'". Please grant read/write permissions to the web-server user '. get_server_uid_gid());
			return 1;
		}
	}
	if (open_basedir) {
		$safe = 1;
		foreach (explode(PATH_SEPARATOR, open_basedir) as $d) {
			if (!strncasecmp($dir, $d, strlen($d))) {
				$safe = 0;
				break;
			}
		}
		if ($safe) {
			seterr($type, 'open_basedir limitations "'. open_basedir .'" prevent the installation script from writing to "'. $dir .'". Please modify the open_basedir directive to include the specified directory');
			return 1;
		}
	}
}

function htaccess_handler($web_root, $ht_pass)
{
	if (!fud_ini_get('allow_url_fopen') || strncmp(PHP_SAPI, 'apache', 6)) {
		unlink($ht_pass);
		return;
	}

	/* Opening a connection to itself should not take more then 5 seconds. */
	fud_ini_set('default_socket_timeout', 5);
	if (@fopen($web_root .'blank.gif', 'r') === FALSE) {
		unlink($ht_pass);
	}
}

function page_header()
{
	if (php_sapi_name() == 'cli') return;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>FUDforum Installation Wizard</title>
<?php
if (isset($_POST['WWW_ROOT'])) {
	echo '<script src="'. $_POST['WWW_ROOT'] .'/js/jquery.js"></script>';
	echo '<script src="'. $_POST['WWW_ROOT'] .'/js/ui/jquery-ui.js"></script>';
	echo '<script src="'. $_POST['WWW_ROOT'] .'/js/lib.js"></script>';
}
?>
<style>
body {color: #272323; background: #eaf6f0;}
img {-webkit-transform: rotate(-5deg); -moz-transform: rotate(-5deg); transform: rotate(-5deg); }
table.maintable {
	background: #bbb;
	border-spacing: 0;
	border: 1px solid #242;
	padding: 0;
	-moz-border-radius: 0.75em;
	-webkit-border-radius: 0.75em;
	border-radius: 0.75em;
	width: 95%;
	-webkit-box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.3);
	-moz-box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.3);
	box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.3);
}
.button {color:white; font-size: large; padding: 2px!important; -moz-border-radius:6px!important;}
.button:hover { opacity:.8!important; padding: 2px!important; cursor:pointer; }
.forward { background:#546f61; }
.back { background:#b77c50; }
.headtable {
	background: #343e37; color: white; width: 100%; height: 50px; padding: 5px;
	-moz-border-radius: 0.75em; -webkit-border-radius: 0.75em; border-radius: 0.75em;
	-webkit-box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.3);
	-moz-box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.3);
	box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.3);
}
span.linkhead {color: #fff; font-weight: bold; font-size: xx-large;}
.field {
	vertical-align: top; background: #eeeffe;
}
.field:hover {
	background: #dddeed; border-bottom: 1px solid red;
} 
.field td {
	border-bottom: 2px inset #fff; padding: 2px; margin: 3px;
}
.prereq { background: #fffaf0; }
.step { color: #242; float:right; font-size:small; font-weight:bold; }
.descr { color: #505050; font-size:small; }
a:visited, a:link { color: #242; }
a:active, a:hover { color: red; }
</style>
<script>
jQuery(document).ready(function() {
	jQuery(':text:visible:enabled:first').focus();
});
</script>
</head>
<body>
<table class="headtable"><tr>
  <td><?php
	if (isset($_POST['WWW_ROOT']) )
		echo '<img src="'. $_POST['WWW_ROOT'] .'/images/fudlogo.gif" alt="" style="float:left;" border="0" />';
  ?></td>
  <td><span class="linkhead">FUDforum Installation Wizard</span></td>
  <td> &nbsp; </td>
</tr></table>
<br />

<form name="install" action="<?php echo basename(__FILE__) .'?'. rand(); ?>" method="post">
<?php
}

function page_footer()
{
	if (php_sapi_name() == 'cli') return;
	echo '</form></body></html>';
}

function dialog_start($title, $help)
{
?>
<table class="maintable" align="center" border="0" cellspacing="5" cellpadding="5" width="100%">
	<tr><td colspan="2"><?php echo $title; ?></td></tr>
	<tr><td colspan="2" bgcolor="#ffffff"><?php echo $help; ?></td></tr>
<?php
}

function dialog_end($section)
{
	if ($section != 'prereq') {
		if ($section != 'done') {
			echo '<tr bgcolor="#ffffff">';
			if ($section != 'welcome') {
				echo '<td align="left"><input class="button back" type="button" title="Go back to previous step." onclick="history.go(-1)" name="buttn" value="&lt;&lt; Back" /></td>';
			} else {
				echo '<td>&nbsp;</td>';
			}
			if ($section == 'welcome') {
				echo '<td align="right"><input class="button forward" type="submit" title="Install FUDforum on your system." name="submit" value=" Start installer &gt;&gt; " />';
			} else {
				echo '<td align="right"><input class="button forward" type="submit" title="Go to the next step." name="submit" value="Next &gt;&gt;" />';		
			}
		} 
		echo '</td></tr></table><br />';
	} else {
		echo '</table><br />';
	}
}

function input_row($title, $var, $def, $descr=NULL, $type='text', $extra='')
{
	echo '<tr class="field"><td><b>'. $title .'</b>'. ($descr ? '<br /><span class="descr">'. $descr .'</span>' : '') .'</td><td valign="bottom">'. (isset($GLOBALS['errors'][$var]) ? $GLOBALS['errors'][$var] : '') .'<input type="'. $type .'" name="'. $var .'" id="'. $var .'" value="'. htmlspecialchars($def) .'" size="40" '. $extra .' /></td></tr>';
}

function prereq_row($title, $descr=NULL, $value=NULL, $status='green') 
{
	echo '<tr class="field"><td><b>'. $title .'</b><br /><span class="descr">'. $descr .'</span></td><td><span style="color:'. $status .'">'. $value .'</span></td></tr>';
}

function sel_row($title, $var, $opt_list, $val_list, $descr=NULL, $def=NULL)
{
	$val_list = explode("\n", $val_list);
	$opt_list = explode("\n", $opt_list);

	if (($c = count($val_list)) != count($opt_list)) {
		exit('Value list does not match option count.');
	}

	echo '<tr class="field"><td valign="top"><b>'. $title .'</b>'. ($descr ? '<br /><span class="descr">'. $descr .'</span>' : '') .'</td><td valign="bottom">'. (isset($GLOBALS['errors'][$var]) ? $GLOBALS['errors'][$var] : '') .'<select name="'. $var .'">';
	for ($i = 0; $i < $c; $i++) {
		echo '<option value="'. htmlspecialchars($val_list[$i]) .'"'. ($def == $val_list[$i] ? ' selected="selected"' : '') .'>'. htmlspecialchars($opt_list[$i]) .'</option>';
	}
	echo '</select></td></tr>';
}

function seterr($name, $text)
{
	if (php_sapi_name() == 'cli') {
		echo "\nFATAL ERROR!\n";
		echo $text ."\n\n";
		exit(-1);
	} else {
		$GLOBALS['errors'][$name] = '<span style="color:darkred">'. $text .'</span><br />';
	}
}

function fud_sql_error_handler($query, $error_string, $error_number, $server_version)
{
	if (defined('fud_debug')) echo $query ."\n";
	throw new Exception($error_number .': '. $error_string .' @ '. $query);
}

function make_into_query($data)
{
	// Remove trailing ';' in case last line in SQL is ';' and not ';\n'.
	$q = rtrim($data, ';');
	// Remove comments.
	$q = preg_replace('%/\*[\s\S]+?\*/|^(?://|#).*(?:\r\n|\n)%m', '', $q);
	// Expand table prefix.
	$q = str_replace('{SQL_TABLE_PREFIX}', $_POST['DBHOST_TBL_PREFIX'], $q);
	// Expand date.
	$q = str_replace('{UNIX_TIMESTAMP}', time(), $q);
	// OR bitmap values together (i.e. 1|2 -> 3) as different databases handle them differently.
	$q = preg_replace_callback('/\b(\d[\d\|]+\d\b)/', 
		function($matches) {
			$or=0; 
			foreach( explode('|', $matches[0]) as $val) {
				$or = $or|$val;
			}
			return $or;
		},
		$q);
	return trim($q);
}

/* main */
error_reporting(E_ALL);
ignore_user_abort(true);
@set_time_limit(600);

if (!fud_ini_get('track_errors')) {
	fud_ini_set('track_errors', 1);
}
if (!fud_ini_get('display_errors')) {
	fud_ini_set('display_errors', 1);
}

fud_ini_set('memory_limit', '128M');	// PHP 5.3's default, old defaults too small.

$url_test = fud_ini_get('allow_url_fopen');
/* Uncomment the line below if the installer stalls after the 1st page. */
//$url_test = 0;

/* Opening a connection to itself should not take more then 5 seconds. */
fud_ini_set('default_socket_timeout', 5);

/* Determine SafeMode limitations. */
define('SAFE_MODE', fud_ini_get('safe_mode'));

/* Determine open_basedir limitations. */
define('open_basedir', ini_get('open_basedir'));

/* Mbstring hackery, necessary if function overload is enabled. */
if (extension_loaded('mbstring') && ini_get('mbstring.func_overload') > 0) {
	mb_internal_encoding('UTF-8');
}

if (!isset($_SERVER['PATH_TRANSLATED'])) {
	$_SERVER['PATH_TRANSLATED'] = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : realpath(__FILE__);
}

$module_status = modules_enabled();

/* Perform various sanity checks, which check for required components. */
if (!count($_POST)) {
	/* PHP version check. */
	if (!version_compare(PHP_VERSION, '5.3.0', '>=')) {
		seterr('PHPVER', 'FUDforum requires PHP 5.3.0 or later while you have version <?php echo PHP_VERSION; ?> Installed. Please rectify this and try again.');
	}

	/* Database check. */
	if (!$module_status['ibm_db2'] &&
		!$module_status['interbase'] &&
		!$module_status['mysql'] && !$module_status['mysqli'] && !$module_status['pdo_mysql'] &&
		!$module_status['oci8'] &&
		!$module_status['pgsql'] && !$module_status['pdo_pgsql'] &&
		!$module_status['pdo_sqlite'] &&
		!$module_status['sqlsrv'] && !$module_status['pdo_sqlsrv'])
	{
		// FUTURE: seterr('NODB', 'FUDforum requires a database to function. Your PHP installation does not support any of following databases: CUBRID, IBM DB2, Firebird, MySQL, Oracle, PosgreSQL, SQLite or MS-SQL Server. Please rectify this and try again.');
		seterr('NODB', 'FUDforum requires a database to function. Your PHP installation does not support any of following databases: IBM DB2, Firebird, MySQL, Oracle, PosgreSQL, SQLite or MS-SQL Server. Please rectify this and try again.');
	}

	/* PCRE check. */
	if (!$module_status['pcre']) {
		seterr('PCRE', 'The required PCRE (Perl Compatible Regular Expression) extension for PHP is not available. Please rectify this and try again.');
	}

	/* Mbstring check. */
	if (!$module_status['mbstring']) {
		seterr('MBSTRING', 'The required MBSTRING (Multibyte String) extension for PHP is not available. Please rectify this and try again.');
	}

	if (isset($GLOBALS['errors'])) {
		page_header();
		echo '<h2>Minimum requirements</h2>';
		foreach($GLOBALS['errors'] as $err) {
			echo '<p>'. $err .'</p>';
			// dialog_start('Unable to install', '<p>'. $err .'</p>');
			// dialog_end('prereq');
		}
		page_footer();
		exit;
	}

	// Check if we have a forum_archive.
	if (!file_exists('./fudforum_archive')) {
		seterr('NOARCHIVE', 'The install script requires a "fudforum_archive" file to run. Please download it and retry again.');
	}
}

/* In comand line mode we need to read parameters from the config file (is supplied).
 * We will prompt the user for missing values.
 */
if (php_sapi_name() == 'cli') {
	/* Config file settings. */
	$_POST = array(
		'WWW_ROOT'          => '',
		'SERVER_ROOT'       => '',
		'SERVER_DATA_ROOT'  => '',
		'DBHOST'            => '',
		'DBHOST_USER'       => '',
		'DBHOST_PASSWORD'   => '',
		'DBHOST_DBNAME'     => '',
		'DBHOST_TBL_PREFIX' => '',
		'DBHOST_DBTYPE'     => '',
		'COOKIE_DOMAIN'     => '',
		'LANGUAGE'          => '',
		'TEMPLATE'          => '',
		'ROOT_LOGIN'        => get_current_user(),
		'ROOT_PASS'         => '',
		'ADMIN_EMAIL'       => '',
		'PHP_CLI'           => ''
	);

	/* Read config file. */
	if (isset($_SERVER['argv'][1]) && !is_numeric($_SERVER['argv'][1]) && @file_exists($_SERVER['argv'][1])) {
		$_POST = array_merge($_POST, parse_ini_file($_SERVER['argv'][1]));
		echo 'Reading config file '. $_SERVER['argv'][1] .".\n";
	} else if (@file_exists('./install.ini')) {
		echo "Reading config file install.ini.\n";
		$_POST = array_merge($_POST, parse_ini_file('./install.ini'));
	}
}

/* In GUI/Web mode we will execute one section at a time, progressing through all section. 
   However, in command line mode we will sequentially run through them all.
  */
$section = isset($_POST['section']) ? $_POST['section'] : (isset($_GET['section']) ? $_GET['section'] : '');

if ($section == 'welcome') {
		// Nothing to do, advance to the next section.
		$display_section = 'stor_path';
}

if ($section == 'stor_path' || php_sapi_name() == 'cli') {
	if (php_sapi_name() == 'cli') {
		/* Prompt for forum's URL. */
		while (!validate_url($_POST['WWW_ROOT'])) {
			echo 'Forum URL: ';
			$url = trim(fgets(STDIN, 1024));
			if (validate_url($url)) {
				break;
			}
			echo 'ERROR: ['. $url ."] is not a valid URL. Please supply a URL in the 'http://host/path/' format\n";
		}

		/* Prompt for file system path of the forum's web files. */
		while (!$_POST['SERVER_ROOT'] || !is_wr($_POST['SERVER_ROOT'])) {
			echo 'Path to FUDforum web browseable files: ';
			$_POST['SERVER_ROOT']  = trim(fgets(STDIN, 1024));
			if ($_POST['SERVER_ROOT'] && is_wr($_POST['SERVER_ROOT'])) {
				break;
			}
			echo 'ERROR: ['. $_POST['SERVER_ROOT'] ."] does not exist or the installer does not have the required permissions to create it\n";
		}

		/* Prompt for file path of the forum's web files. */
		while (!$_POST['SERVER_DATA_ROOT'] || !is_wr($_POST['SERVER_DATA_ROOT'])) {
			echo 'Path to FUDforum data files (non-browseable) ['. $_POST['SERVER_ROOT'] .']: ';
			$_POST['SERVER_DATA_ROOT'] = trim(fgets(STDIN, 1024));
			if (!$_POST['SERVER_DATA_ROOT']) {
				$_POST['SERVER_DATA_ROOT'] = $_POST['SERVER_ROOT'];
				break;
			} else if (is_wr($_POST['SERVER_DATA_ROOT'])) {
				break;
			}
			echo 'ERROR: ['. $_POST['SERVER_DATA_ROOT'] ."] does not exist or the installer does not have the required permissions to create it\n";
		}
		
		echo "Copy forum files.\n";
	}

	if (isset($_GET['sfh'])) {	// Safe mode.
		$_POST['SERVER_ROOT'] 	   = $_GET['SERVER_ROOT'];
		$_POST['SERVER_DATA_ROOT'] = $_GET['SERVER_DATA_ROOT'];
		$_POST['WWW_ROOT']         = $_GET['WWW_ROOT'];
	}
	$SERVER_ROOT      = $_POST['SERVER_ROOT'];
	$SERVER_DATA_ROOT = $_POST['SERVER_DATA_ROOT'];
	$WWW_ROOT         = $_POST['WWW_ROOT'];

	fix_slashes($WWW_ROOT);
	fix_slashes($SERVER_ROOT);
	fix_slashes($SERVER_DATA_ROOT);

	$_POST['SERVER_ROOT']      = $SERVER_ROOT;
	$_POST['SERVER_DATA_ROOT'] = $SERVER_DATA_ROOT;
	$_POST['WWW_ROOT']         = $WWW_ROOT;

	$err = check_primary_dir($SERVER_ROOT, 'SERVER_ROOT');
	if ($SERVER_ROOT != $SERVER_DATA_ROOT) {
		if (check_primary_dir($SERVER_DATA_ROOT, 'SERVER_DATA_ROOT') && !$err) {
			$err = 1;
		}
	}

	if (!$err) {
		if (SAFE_MODE && !isset($_GET['sfh'])) {
			$s = realpath(__FILE__);
			$d = dirname($s) .'/install_safe.php';
			if (!copy($s, $d)) {
				exit('Failed to copy "'. $s .'" to "'. $d .'"');
			}
			header('Location: install_safe.php?SERVER_ROOT='. urlencode($SERVER_ROOT) .'&SERVER_DATA_ROOT='. urlencode($SERVER_DATA_ROOT) .'&WWW_ROOT='. urlencode($WWW_ROOT) .'&section=stor_path&sfh=1');
			exit;
		}

		/* Try to ensure that SERVER_ROOT resolves to WWW_ROOT. */
		if (fud_ini_get('allow_url_fopen') && !empty($_POST['url_check'])) {
			$check_time = time();

			$fp = fopen($SERVER_ROOT .'fud_test_page.htm', 'wb');
			fwrite($fp, $check_time);
			fclose($fp);

			if (($d = @file_get_contents($WWW_ROOT .'fud_test_page.htm')) != $check_time) {
				seterr('WWW_ROOT', 'Your Forum URL and Web Directory do not appear to point to the same location on disk.<br /><small>Unable to load '. $SERVER_ROOT .'fud_test_page.htm as '. $WWW_ROOT .'fud_test_page.htm.<br />Error: '. $php_errormsg .'</small>');
			}
			unlink($SERVER_ROOT .'fud_test_page.htm');
		}
	}

	if (!isset($GLOBALS['errors'])) {
		decompress_archive($SERVER_DATA_ROOT, $SERVER_ROOT);
		/* Verify that all the important directories exist (old php bug). */
		foreach (array('include', 'errors', 'messages', 'files', 'thm', 'tmp', 'cache', 'errors/.nntp', 'errors/.mlist') as $v) {
			if (!__mkdir($SERVER_DATA_ROOT . $v)) {
				exit('FATAL ERROR: Could not create "'. $SERVER_DATA_ROOT . $v .'".<br />You can try creating it manually (remember to grant read/write permissions to the web-server user).');
			}
		}

		/* Determine if this host can support .htaccess directives. */
		if (!defined('old_htaccess') && $url_test) {
			htaccess_handler($WWW_ROOT, $SERVER_ROOT .'.htaccess');
		} else if (!defined('old_htaccess') && !$url_test) {
			@unlink($SERVER_ROOT .'.htaccess');
		}

		$INCLUDE             = $SERVER_DATA_ROOT .'include/';
		$ERROR_PATH          = $SERVER_DATA_ROOT .'errors/';
		$MSG_STORE_DIR       = $SERVER_DATA_ROOT .'messages/';
		$FILE_STORE          = $SERVER_DATA_ROOT .'files/';
		$TMP                 = $SERVER_DATA_ROOT .'tmp/';
		$FORUM_SETTINGS_PATH = $SERVER_DATA_ROOT .'cache/';
		$PLUGIN_PATH         = $SERVER_DATA_ROOT .'plugins/';

		/* Load glob.inc for functions like fud_symlink() and read/change_global_settings(). */
		require_once($INCLUDE .'glob.inc');

		/* Make symlinks to GLOBALS.php. */
		fud_symlink($INCLUDE .'GLOBALS.php', $SERVER_ROOT .'GLOBALS.php');
		fud_symlink($INCLUDE .'GLOBALS.php', $SERVER_ROOT .'adm/GLOBALS.php');
		fud_symlink($INCLUDE .'GLOBALS.php', $SERVER_DATA_ROOT .'scripts/GLOBALS.php');

		$url_parts = parse_url($WWW_ROOT);

		/* Default bitmask values. */
		$FUD_OPT_1 = 1743713343;	// From default GLOBALS.php.
		if (!$module_status['enchant']) {
			$FUD_OPT_1 ^= 2097152;	// Disable spell checker.
		}

		/* Update GLOBALS.php. */
		change_global_settings(array(
			'INCLUDE'		=> $INCLUDE,
			'ERROR_PATH'		=> $ERROR_PATH,
			'MSG_STORE_DIR'		=> $MSG_STORE_DIR,
			'FILE_STORE'		=> $FILE_STORE,
			'TMP'			=> $TMP,
			'WWW_ROOT'		=> $WWW_ROOT,
			'WWW_ROOT_DISK'		=> $SERVER_ROOT,
			'FORUM_SETTINGS_PATH'	=> $FORUM_SETTINGS_PATH,
			'PLUGIN_PATH'		=> $PLUGIN_PATH,
			'COOKIE_NAME'		=> 'fud_session_'. time(),
			'FUD_OPT_1'		=> $FUD_OPT_1,
			'COOKIE_PATH'		=> $url_parts['path'],
			'DATA_DIR'		=> $SERVER_DATA_ROOT,
			'SERVER_TZ'		=> @date_default_timezone_get()
		));

		$display_section = 'db';
	}
}

if ($section == 'db' || php_sapi_name() == 'cli') {
	if (php_sapi_name() == 'cli') {
		/* Prompt for database type. */
		$db_types = array_keys( databases_enabled() );
		if (count($db_types) == 1) {
			$_POST['DBHOST_DBTYPE'] = $db_types[0][0];
		}
		while (!in_array($_POST['DBHOST_DBTYPE'], $db_types)) {
			echo 'Please choose a database type: ';
			$db = trim(fgets(STDIN, 1024));
			if (in_array($db, $db_types)) {
				$_POST['DBHOST_DBTYPE'] = $db;
				break;
			}
			echo 'ERROR: ['. $db ."] is not available or not supported.\n";
			echo 'Choose from: '. wordwrap(implode(', ', $db_types), 60, "\n\t") .".\n";
		}

		/* Prompt for other database settings. */
		if ($_POST['DBHOST_DBTYPE'] != 'pdo_sqlite') {
			while (empty($_POST['DBHOST'])) {
				echo 'Please specify database host (127.0.0.1): ';
				$_POST['DBHOST'] = trim(fgets(STDIN, 1024));
				if (empty($_POST['DBHOST'])) {
					$_POST['DBHOST'] = '127.0.0.1';
				}
			}
			while (empty($_POST['DBHOST_DBNAME'])) {
				echo 'Please specify database name: ';
				$_POST['DBHOST_DBNAME'] = trim(fgets(STDIN, 1024));
			}
			while(empty($_POST['DBHOST_USER'])) {
				echo 'Please specify database user: ';
				$_POST['DBHOST_USER'] = trim(fgets(STDIN, 1024));
			}
			while (empty($_POST['DBHOST_PASSWORD'])) {
				echo 'Please specify database password: ';
				$_POST['DBHOST_PASSWORD'] = trim(fgets(STDIN, 1024));
				if (empty($_POST['DBHOST_PASSWORD'])) {
					$_POST['DBHOST_PASSWORD'] = '';			// Password can be NULL.
					break;
				}
			}
		}
		while (empty($_POST['DBHOST_TBL_PREFIX'])) {
			echo 'Please specify SQL table prefix [fud30_]: ';
			$_POST['DBHOST_TBL_PREFIX'] = trim(fgets(STDIN, 1024));
			if (!$_POST['DBHOST_TBL_PREFIX']) {
				$_POST['DBHOST_TBL_PREFIX'] = 'fud30_';
			}
		}

		echo "Creating database tables.\n";
	}

	// Validate the table prefix.
	if (empty($_POST['DBHOST_TBL_PREFIX']) || preg_match('![^A-Za-z0-9_]!', $_POST['DBHOST_TBL_PREFIX'])) {
		seterr('DBHOST_TBL_PREFIX', 'SQL prefix cannot be empty or contain non A-Za-z0-9_ characters.');
	}

	// Fix path for SQLite db.
	if ($_POST['DBHOST_DBTYPE'] == 'pdo_sqlite') {
		$_POST['DBHOST'] = $_POST['SERVER_DATA_ROOT'] .'/forum.db.php';
		define('SQLITE_FAST_BUT_WRECKLESS', 1);	// Optimize for speed.
	}

	if (!isset($GLOBALS['errors'])) {
		/* Write database settings to GLOBALS.php. */
		$INCLUDE = $_POST['SERVER_DATA_ROOT'] .'include/';
		require_once($INCLUDE .'glob.inc');	// Load glob.inc for read/change_global_settings().
		change_global_settings(array(
			'DBHOST'		=> $_POST['DBHOST'],
			'DBHOST_USER'		=> $_POST['DBHOST_USER'],
			'DBHOST_PASSWORD'	=> $_POST['DBHOST_PASSWORD'],
			'DBHOST_DBNAME'		=> $_POST['DBHOST_DBNAME'],
			'DBHOST_TBL_PREFIX'	=> $_POST['DBHOST_TBL_PREFIX'],
			'DBHOST_DBTYPE'		=> $_POST['DBHOST_DBTYPE'],
		));
	}

	if (!isset($GLOBALS['errors'])) {
		/* Read GLOBALS.php without invoking core.inc. */
		read_global_settings();

		/* Try to swap in the apropriate DB driver. It will try to connect! */
		$dbinc = $DATA_DIR .'sql/'. $DBHOST_DBTYPE .'/db.inc';
		if (!file_exists($dbinc)) {
			seterr('DBHOST_DBTYPE', 'Unsupported database type.');
		} else {
			try {
				// error_reporting(0); // Hide warnings during connect probing.
				require_once($dbinc);
				require_once($INCLUDE .'dbadmin.inc');
			} catch (Exception $e) {
				// Try to position error on-screen based on message.
				if ( preg_match('/database/i', $e->getMessage()) ) {
					seterr('DBHOST_DBNAME', $e->getMessage());
				} elseif ( preg_match('/user/i', $e->getMessage()) || preg_match('/account/', $e->getMessage()) ) {
					seterr('DBHOST_USER', $e->getMessage());
				} elseif ( preg_match('/password/i', $e->getMessage()) ) {
					seterr('DBHOST_PASSWORD', $e->getMessage());
				} else {
					seterr('DBHOST', $e->getMessage());
				}
			}
			error_reporting(E_ALL);
		}
	}

	/* Validate database version. */
	if (!isset($GLOBALS['errors'])) {
		$err = validate_db_version();
		if (!empty($err)) {
			seterr('DBHOST_DBNAME', $err);
		}
	}

	/* Check SQL permissions. */
	if (!isset($GLOBALS['errors'])) {
		try {
			drop_table('fud_forum_install_test_table', true);
		} catch (Exception $e) {
			// Report this errors, for example "No database selected".
			seterr('DBHOST_DBNAME', $e->getMessage());
		}
	}
	if (!isset($GLOBALS['errors'])) {
		try {
			create_table('CREATE TABLE fud_forum_install_test_table (test_val INT)');
		} catch (Exception $e) {
			seterr('DBHOST_DBNAME', 'Please grant your database user permissions to create tables and try again.');
		}
		try {
			create_index('fud_forum_install_test_table', 'fud_forum_install_test_index', false, 'test_val');
		} catch (Exception $e) {
			seterr('DBHOST_DBNAME', 'Please grant your database user permissions to create indexes and try again.');
		}
		try {
			drop_table('fud_forum_install_test_table', false);
		} catch (Exception $e) {
			seterr('DBHOST_DBNAME', 'Please grant your database user permissions to drop tables and try again.');
		}
	}

	/* Get list of files with tables and seed data. */
	if (!isset($GLOBALS['errors'])) {
		$tbl = glob($DATA_DIR .'sql/*.tbl', GLOB_NOSORT);
		$sql = glob($DATA_DIR .'sql/*.sql', GLOB_NOSORT);
		if (!$tbl || !$sql) {
			seterr('DBHOST_DBNAME', 'Failed to get a list of table definitions and/or seed data from: "'. $DATA_DIR ."sql/\"\n");
		}
	}

	/* Drop and create tables and indexes. */
	$spin_phases = array('|', '/', '-', '\\');
	$spin_phase  = 0;
	if (!isset($GLOBALS['errors'])) {
		foreach ($tbl as $t) {
			foreach (explode(';', preg_replace('!#.*?\n!s', '', file_get_contents($t))) as $q) {
				if (php_sapi_name() == 'cli') {
					if($spin_phase == 4) $spin_phase = 0;
					printf('%s%s', chr(8), $spin_phases[$spin_phase++]);
				}

				$q = trim($q);
				if (preg_match('/^DROP TABLE IF EXISTS (.*)$/si', $q, $m)) {
					drop_table($m[1], true);
				}
				if (preg_match('/^\s*CREATE\s*TABLE\s*([\{\}\w]*)/si', $q, $m)) {
					create_table($q);
				}
				if (preg_match('/^CREATE\s+(UNIQUE)?\s*INDEX (.*) ON (.*) \((.*)\)/is', $q, $m)) {
					create_index($m[3], $m[2], (strtoupper($m[1]) == 'UNIQUE') ? true : false, $m[4], false);
				}
			}
		}
	}
	if (php_sapi_name() == 'cli') {
		printf('%s%s', chr(8), ' ');	// Remove spinner.
	}

	/* Import seed data. */
	if (!isset($GLOBALS['errors'])) {					
		foreach ($sql as $t) {
			// Replace hard coded newlines in seed scripts.
			$file = str_replace(array('\r\n', '\r', '\n'), "\n", file_get_contents($t));
			foreach (explode(";\n", $file) as $q) { 
				$q = make_into_query($q);
				if (defined('fud_debug')) echo "$q\n";
				if ($q) {
					try {
						q($q);
					} catch (Exception $e) {
						if (strpos($q, 'DROP ') !== 0) {	// Skip errors on DROP statements.
							seterr('DBHOST_DBNAME', 'Failed to load seed data into table '. basename($t, '.sql') .
								':<br />Query: '. $q .
								'<br />SQL error: '.  $e->getMessage());
							break 2;
						}
					}
				}
			}
		}
	}

	if (!isset($GLOBALS['errors'])) {
		$display_section = 'cookies';
	}
}

if ($section == 'cookies' || php_sapi_name() == 'cli') {
	if (php_sapi_name() == 'cli') {
		while (empty($_POST['COOKIE_DOMAIN'])) {
			echo 'Forum Administrator E-mail: ';
			$_POST['COOKIE_DOMAIN'] = trim(fgets(STDIN, 1024));
		}
	}

	if (empty($_POST['COOKIE_DOMAIN'])) {
		seterr('COOKIE_DOMAIN', 'Cookie domain required for cookies to work properly.');
	} else {
		if ($_POST['COOKIE_DOMAIN'] == 'localhost' || filter_var($_POST['COOKIE_DOMAIN'], FILTER_VALIDATE_IP)) {
			$_POST['COOKIE_DOMAIN'] = '';
		}

		$INCLUDE = $_POST['SERVER_DATA_ROOT'] .'include/';
		require_once($INCLUDE .'glob.inc');	// Load glob.inc for change_global_settings().
		change_global_settings(array('COOKIE_DOMAIN' => $_POST['COOKIE_DOMAIN']));

		$display_section = 'theme';
	}
// TODO: set a test cookie & notify users of problems.
}

if ($section == 'theme' || php_sapi_name() == 'cli') {
	if (php_sapi_name() == 'cli') {
		/* Prompt for template set. */
		$tmpl_names = array();
		foreach (glob($_POST['SERVER_DATA_ROOT'] .'thm/*', GLOB_ONLYDIR) as $f) {
			$tmpl_names[] = basename($f);
		}
		while (!in_array($_POST['TEMPLATE'], $tmpl_names)) {
			echo 'Please choose a template set: ';
			$tmpl = trim(fgets(STDIN, 1024));
			if (in_array($tmpl, $tmpl_names)) {
				$_POST['TEMPLATE'] = $tmpl;
				break;
			}
			echo 'ERROR: ['. $tmpl ."] is not a valid template set.\n";
			echo 'Choose from: '. wordwrap(implode(', ', $tmpl_names), 60, "\n\t") .".\n";
		}

		/* Prompt for language name. */
		$lang_names = array();
		foreach (glob($_POST['SERVER_DATA_ROOT'] .'thm/default/i18n/*', GLOB_ONLYDIR) as $f) {
			$lang_names[] = basename($f);
		}
		while (!in_array($_POST['LANGUAGE'], $lang_names)) {
			echo 'Please choose a language: ';
			$lang = trim(fgets(STDIN, 1024));
			if (in_array($lang, $lang_names)) {
				$_POST['LANGUAGE'] = $lang;
				break;
			}
			echo 'ERROR: ['. $lang ."] is not a valid language name.\n";
			echo 'Choose from: '. wordwrap(implode(', ', $lang_names), 60, "\n\t") .".\n";
		}
	}

 	$lang = $_POST['LANGUAGE'];
	$tmpl = $_POST['TEMPLATE'];

	/* Read GLOBALS.php without invoking core.inc. */
	$INCLUDE = $_POST['SERVER_DATA_ROOT'] .'include/';
	require_once($INCLUDE .'glob.inc');
	read_global_settings();

	if (!is_dir($DATA_DIR .'thm/'. $tmpl)) {
		seterr('TEMPLATE', 'Invalid template set.');
	}
	if (!is_dir($DATA_DIR .'thm/default/i18n/'. $lang)) {
		seterr('LANGUAGE', 'Invalid language.');
	}

	if (!isset($GLOBALS['errors'])) {
		$tryloc = file($DATA_DIR .'thm/default/i18n/'. $lang .'/locale', FILE_IGNORE_NEW_LINES);
		$tryloc[] = '';	// Also consider the system's default locale.
		$loc = setlocale(LC_ALL, $tryloc);
		$loc = preg_match('/WIN/', PHP_OS) ? utf8_encode($loc) : $loc;	// Windows silliness.

		/* Swap in the DB driver. */
		require_once($DATA_DIR .'sql/'. $DBHOST_DBTYPE .'/db.inc');

		q('DELETE FROM '. $DBHOST_TBL_PREFIX .'themes');
		q('INSERT INTO '. $DBHOST_TBL_PREFIX .'themes(name, theme, lang, locale, theme_opt, pspell_lang) VALUES(\'default\', \''. addslashes($tmpl) .'\', \''. addslashes($lang) .'\', \''. addslashes($loc) .'\', 3, \''. addslashes($lang) .'\')');

		$display_section = 'admin';
	}
}

if ($section == 'admin' || php_sapi_name() == 'cli') {
	if (php_sapi_name() == 'cli') {
		/* Prompt for Admin's E-mail address. */
		while (empty($_POST['ADMIN_EMAIL'])) {
			echo 'Forum Administrator E-mail: ';
			$_POST['ADMIN_EMAIL'] = trim(fgets(STDIN, 1024));
		}

		/* Prompt Admin password. */
		while (empty($_POST['ROOT_PASS'])) {
			echo 'Forum Administrator Password: ';
			$_POST['ROOT_PASS'] = trim(fgets(STDIN, 1024));
			echo 'Please confirm the password: ';
			if ($_POST['ROOT_PASS'] != trim(fgets(STDIN, 1024))) {
				$_POST['ROOT_PASS'] = '';
				echo "Passwords do not match!\n";
			}
		}

		$_POST['ROOT_PASS_C'] = $_POST['ROOT_PASS'];
	}

	if (empty($_POST['ROOT_PASS'])) {
		seterr('ROOT_PASS', 'Please enter a password for the administrator account.');
	} else if ($_POST['ROOT_PASS'] != $_POST['ROOT_PASS_C']) {
		seterr('ROOT_PASS', 'Your passwords do not match.');
	}
	if (empty($_POST['ROOT_LOGIN'])) {
		seterr('ROOT_LOGIN', 'Please enter a user name for the administrator account.');
	}
	if (empty($_POST['ADMIN_EMAIL'])) {
		seterr('ADMIN_EMAIL', 'Please enter a valid email address for the administrator account.');
	}

	if(!isset($GLOBALS['errors'])) {
		/* Read GLOBALS.php without invoking core.inc. */
		$INCLUDE = $_POST['SERVER_DATA_ROOT'] .'include/';
		require_once($INCLUDE .'glob.inc');	// Load glob.inc for read/change_global_settings().
		read_global_settings();

		/* Swap in the DB driver. */
		require_once($DATA_DIR .'sql/'. $DBHOST_DBTYPE .'/db.inc');

		/* Add anonymous user (must be id=1). */
		q('DELETE FROM '. $DBHOST_TBL_PREFIX .'users');
		$anon_id = db_li('INSERT INTO '. $DBHOST_TBL_PREFIX .'users (login, alias, theme, email, passwd, name, users_opt, join_date, time_zone) VALUES(\'Anonymous\', \'Anonymous\', 1, \'dev@null\', \'1\', \'Anonymous\', '. (1|4|16|32|128|256|512|2048|4096|8192|16384|262144|4194304) .', '. time() .', \''. $SERVER_TZ .'\')', $ef, 1);
		if ($anon_id != 1) {
			echo 'WARNING: Anonymous user\'s ID is not 1! Trying to fix it...';
			q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET id = 1');
			echo 'Done, we\re OK again.';
		}

		/* Add admin user. */
		$salt   = substr(md5(uniqid(mt_rand(), true)), 0, 9);
		$passwd = sha1($salt . sha1($_POST['ROOT_PASS']));
		q('INSERT INTO '. $DBHOST_TBL_PREFIX .'users (login, alias, passwd, salt, name, email, avatar, avatar_loc, users_opt, join_date, theme, posted_msg_count, u_last_post_id, level_id, custom_status, time_zone) VALUES(\''. addslashes($_POST['ROOT_LOGIN']) .'\', \''. addslashes(htmlspecialchars($_POST['ROOT_LOGIN'])) .'\', \''. $passwd .'\', \''. $salt .'\', \'Administrator\', \''. addslashes($_POST['ADMIN_EMAIL']) .'\', 3, \'<img src="images/avatars/smiley03.jpg" alt="" width="64" height="64" />\', 13777910, '. time() .', 1, 1, 1, 3, \'Administrator\', \''. $SERVER_TZ .'\')');

		/* Add web crawler users. */
		$bot_opts = 1|4|16|128|256|512|4096|8192|16384|131072|262144|4194304|33554432|67108864|536870912|1073741824;
		$uid = db_li('INSERT INTO '. $DBHOST_TBL_PREFIX .'users (login, alias, passwd, salt, name, email, users_opt, join_date, theme, time_zone) VALUES(\'Google\', \'Google\', \''. $passwd .'\', \''. $salt .'\', \'Googlebot\', \'Google@fud_spiders\', '. $bot_opts .', '. time() .', 1, \''. $SERVER_TZ .'\')', $ef, 1);
		q('INSERT INTO '. $DBHOST_TBL_PREFIX .'spiders (botname, useragent, theme, user_id, bot_opts) VALUES (\'Google\', \'Googlebot\', 1, '. $uid .', 1)');
		$uid = db_li('INSERT INTO '. $DBHOST_TBL_PREFIX .'users (login, alias, passwd, salt, name, email, users_opt, join_date, theme, time_zone) VALUES(\'Yahoo\', \'Yahoo\', \''. $passwd .'\', \''. $salt .'\', \'Yahoo!\', \'Yahoo@fud_spiders\', '. $bot_opts .', '. time() .', 1, \''. $SERVER_TZ .'\')', $ef, 1);
		q('INSERT INTO '. $DBHOST_TBL_PREFIX .'spiders (botname, useragent, theme, user_id, bot_opts) VALUES (\'Yahoo!\', \'Slurp\', 1, '. $uid .', 1)');
		$uid = db_li('INSERT INTO '. $DBHOST_TBL_PREFIX .'users (login, alias, passwd, salt, name, email, users_opt, join_date, theme, time_zone) VALUES(\'Bing\', \'Bing\', \''. $passwd .'\', \''. $salt .'\', \'Bing\', \'Bing@fud_spiders\', '. $bot_opts .', '. time() .', 1, \''. $SERVER_TZ .'\')', $ef, 1);
		q('INSERT INTO '. $DBHOST_TBL_PREFIX .'spiders (botname, useragent, theme, user_id, bot_opts) VALUES (\'Bing\', \'msnbot\', 1, '. $uid .', 1)');

		change_global_settings(array(
			'ADMIN_EMAIL' => $_POST['ADMIN_EMAIL'],
			'NOTIFY_FROM' => $_POST['ADMIN_EMAIL']
		));

		/* Build theme. */
		require($INCLUDE .'compiler.inc');
		try {
			$lang  = strtok($_POST['LANGUAGE'], '::');
			$templ = $_POST['TEMPLATE'];
			compile_all($templ, $lang);
		} catch (Exception $e) {
			die('Unable to compile theme '. $templ .' ('. $lang .'): '.  $e->getMessage() ."\n");
		}

		/* Build smiley cache */
		require($INCLUDE .'sml_rcache.inc');
		smiley_rebuild_cache();

		$display_section = 'done';
	}
}

/* End of command line installation. */
if (php_sapi_name() == 'cli') {
	/* Additional command line settings that's not in GUI. */
	require_once($INCLUDE .'glob.inc');	// Load glob.inc for change_global_settings().
	change_global_settings(array('PHP_CLI' => $_POST['PHP_CLI']));

	echo "\nCongratulations! Your FUDforum installation is now complete.\n";
	echo 'You may access your new forum at: '. $WWW_ROOT ."index.php\n\n";
	exit;
}

/* The code below is rendering the installer's web pages. */
page_header();

if (isset($display_section)) {
	// Section successfully processed, move to next.
	$section = $display_section;
} else {
	$section = empty($section) ? 'welcome' : $section;
}

switch ($section) {
	case 'welcome':
		/* Display some system information on the 1st page of the installer. */
		dialog_start('WELCOME TO FUDFORUM!', '<p>Thanks for choosing FUDforum, the fastest, most secure, and most feature rich PHP based discussion forum available today.</p><p>This wizard will guide you through installing your forum. For more information, please read the <a href="http://cvs.prohost.org/index.php?title=Installation">installation guide</a>. Kindly review your system information as set out below and click on <b>Start installer</b> if your system meets the minimum requirements. If you encounter any problems, please report them on the <a href="http://fudforum.org/forum">support website</a>.</p><p><i>This program is free software; you can redistribute it and/or modify it under the terms of the <a href="http://www.gnu.org/licenses/gpl-2.0.html">GNU General Public License version 2</a> as published by the Free Software Foundation. This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.</i></p>');
		dialog_end($section);

		dialog_start('System information:', '');
		prereq_row('PHP Version:', 'The version of PHP on you webserver.', PHP_VERSION, 'green');
		if (open_basedir) {
			prereq_row('Open_basedir restriction:', 'You will not be able to use PHP to create files outside of the specified directories.', open_basedir, 'red');
		}
		if (fud_ini_get('register_globals')) {
			prereq_row('Register Globals:', 'For performance &amp; security reasons we recommend keeping this option OFF.', 'enabled', 'orange');
		}
		prereq_row('MBsting Exension:', 'The Multibyte String extention provides UTF-8 support (required).', 
			($module_status['mbstring'] ? 'enabled' : 'disabled'), ($module_status['mbstring'] ? 'green' : 'red'));
		prereq_row('PCRE Extension:', 'Perl Compatible Regular Expression (required).', 
			($module_status['pcre'] ? 'enabled' : 'disabled'), ($module_status['pcre'] ? 'green' : 'red'));
		prereq_row('Zlib Extension:', 'The zlib extension is optional, however we recommend enabling it. This extension allow you to compress your forum backups as well as use zlib compression for your pages.',
			($module_status['zlib'] ? 'enabled' : 'disabled'), ($module_status['zlib'] ? 'green' : 'orange'));
		prereq_row('Enchant Extension:', 'Enchant extension is optional, this extension is needed by the FUDforum spellchecker. If you want to allow users to spell check their messages, please enable this extension.',
			($module_status['enchant'] ? 'enabled' : 'disabled'), ($module_status['enchant'] ? 'green' : 'orange'));
	dialog_end('prereq');

	dialog_start('At least <u>one</u> of the following database extensions must be enabled:', '');
	prereq_row('MySQL Improved:', 'Improved interface to the MySQL server database (mysqli) - HIGHLY RECOMMENDED!',
		($module_status['mysqli'] ? 'enabled' : 'disabled'), ($module_status['mysqli'] ? 'green' : 'orange'));
	if (!$module_status['mysqli']) {
		// Depricated, don't show it if we have an alternative.
		prereq_row('MySQL:', 'Interface to the MySQL server, which is the recommended database for FUDforum.', 
			($module_status['mysql'] ? 'enabled' : 'disabled'), ($module_status['mysql'] ? 'green' : 'orange'));
	}
	// TODO: FOR FUTURE IMPLEMENTATION:
	// prereq_row('CUBRID Extension:', 'Interface to CUBRID database (cubrid).', 
	// 	($module_status['cubrid'] ? 'enabled' : 'disabled'), ($module_status['cubrid'] ? 'green' : 'orange'));
	prereq_row('IBM DB2:', 'Interface to IBM DB2 database server (ibm_db2).', 
		($module_status['ibm_db2'] ? 'enabled' : 'disabled'), ($module_status['ibm_db2'] ? 'green' : 'orange'));
	prereq_row('Firebird:', 'Interface to Firebird/Interbase database (ibase).', 
		($module_status['interbase'] ? 'enabled' : 'disabled'), ($module_status['interbase'] ? 'green' : 'orange'));
	prereq_row('MySQL PDO:', 'PDO interface to the MySQL server (pdo_mysql).', 
		($module_status['pdo_mysql'] ? 'enabled' : 'disabled'), ($module_status['pdo_mysql'] ? 'green' : 'orange'));
	prereq_row('Oracle OCI8:', 'Interface to Oracle database server (oci8).', 
		($module_status['oci8'] ? 'enabled' : 'disabled'), ($module_status['oci8'] ? 'green' : 'orange'));
	prereq_row('PostgreSQL:', 'Interface to the PostgreSQL server.', 
		($module_status['pgsql'] ? 'enabled' : 'disabled'), ($module_status['pgsql'] ? 'green' : 'orange'));
	prereq_row('PostgreSQL PDO:', 'PDO interface to the PostgreSQL server (pdo_pgsql).', 
		($module_status['pdo_pgsql'] ? 'enabled' : 'disabled'), ($module_status['pdo_pgsql'] ? 'green' : 'orange'));
	prereq_row('SQLite PDO:', 'PDO interface to the SQLite server (pdo_sqlite).', 
		($module_status['pdo_sqlite'] ? 'enabled' : 'disabled'), ($module_status['pdo_sqlite'] ? 'green' : 'orange'));
	prereq_row('SQL Server:', 'Interface to Microsoft SQL Server (sqlsrv).', 
		($module_status['sqlsrv'] ? 'enabled' : 'disabled'), ($module_status['sqlsrv'] ? 'green' : 'orange'));
	prereq_row('SQL Server PDO:', 'PDO interface to Microsoft SQL Server (sqlsrv).', 
		($module_status['pdo_sqlsrv'] ? 'enabled' : 'disabled'), ($module_status['pdo_sqlsrv'] ? 'green' : 'orange'));

	dialog_end('prereq');
	break;

	case 'stor_path':
		if (isset($_POST['WWW_ROOT'])) {
			$WWW_ROOT = $_POST['WWW_ROOT'];
			$SERVER_ROOT = $_POST['SERVER_ROOT'];
			$SERVER_DATA_ROOT = $_POST['SERVER_DATA_ROOT'];
		} else {
			$SERVER_ROOT = dirname(realpath(__FILE__)) .'/';
			$WWW_ROOT = 'http://' . $_SERVER['SERVER_NAME'];
			if( $_SERVER['SERVER_PORT'] != 80 ) $WWW_ROOT .= ':'. $_SERVER['SERVER_PORT'];
			if (($d = dirname($_SERVER['SCRIPT_NAME']))) {
				$WWW_ROOT .= dirname(((strpos($_SERVER['SCRIPT_NAME'], basename(__FILE__)) !== false) ? $_SERVER['SCRIPT_NAME'] : $_SERVER['REQUEST_URI']));
				$d = str_replace('\\', '/', $d);
				if ($d != '/') {
					$WWW_ROOT .= '/';
				}
			}

			$SERVER_DATA_ROOT = @realpath(str_replace(dirname($_SERVER['SCRIPT_NAME']) .'/', '', $SERVER_ROOT) .'/../') .'/FUDforum/';
			if (open_basedir && strpos(open_basedir, $SERVER_DATA_ROOT) === FALSE) {
				$SERVER_DATA_ROOT = $SERVER_ROOT;
			}

			$WWW_ROOT = str_replace('\\', '/', $WWW_ROOT); // Win32 oriented hackery.
			$SERVER_ROOT = str_replace('\\', '/', $SERVER_ROOT);
			$SERVER_DATA_ROOT = str_replace('\\', '/', $SERVER_DATA_ROOT);
		}

		if (!SAFE_MODE) {
			dialog_start('SYSTEM FILE AND DIRECTORY PATH<span class="step">Step 1 of 5</span>', '<p>Specify the directories where FUDforum\'s files will be stored. Please ensure that your webserver has permission to read from / write to these directories.</p>
			<p>If you click on <b>Next</b>, the forum\'s files will be unpacked to the specified directories.</p>');
		} else {
			dialog_start('<div style="color:red"><b>SAFEMODE is ENABLED!</b></div><br />PATH OF SYSTEM FILES AND DIRECTORIES<span class="step">Step 1 of 5</span>',
					'
					Your PHP has <b><span style="color:red">SAFE MODE</span></b> enabled. Pay careful attention to the intructions below:<br /><br />
					Due to the nature of safe mode, we <span style="color:red">can not</span> install the forum in a directory
					created by you. Therefore you must install the forum into a directory, which <span style="color:red">does not yet exist</span>, so that
					the install script can be the one to create it and thus bypass the safe_mode checks.<br />For example, if you wanted to install
					your forum to "/my/home/dir/www/forum", you will need to make sure that "/my/home/dir/www/forum" does not exist and that the
					file permissions of "/my/home/dir/www" allow install script to create "forum" directory inside "/my/home/dir/www".
			');

			if (!count($_POST)) {
				$WWW_ROOT .= 'forum/';
				$SERVER_ROOT .= 'forum/';
				$SERVER_DATA_ROOT = $SERVER_ROOT;
			}
		}

		input_row('Web Directory', 'SERVER_ROOT', $SERVER_ROOT, 'Directory on the server where web browseable files (*.php, images, etc.) will be stored.');
		input_row('Data Directory', 'SERVER_DATA_ROOT', $SERVER_DATA_ROOT, 'Directory on the server where <b>NON-</b>browseable (cache, backups, and other data) files will be stored. This directory should perferably be placed outside the webserver\'s document root.');
		input_row('Forum URL', 'WWW_ROOT', $WWW_ROOT, 'The URL of your forum. It should point to the forum front page. This is the address needed to visit your forum.');
		input_row('URL Check', 'url_check', '1', 'Turn off this check if you get errors about the <i>Forum URL</i> not matching the <i>Web Directory</i> and are certain that the paths indicated are correct. We recommend you leave this checked!', 'checkbox', 'checked="checked"');
		dialog_end($section);
		break;

	case 'db':
		dialog_start('Database Settings<span class="step">Step 2 of 5</span>', '<p>FUDforum requires a database to store data. Please enter your database details below (leave all but the table prefix empty if you are using SQLite). It is recommended you create a separate <b>UTF-8 encoded</b> database for the forum.</p>
		<p>If you click on <b>Next</b>, the installer will try to connect to the specified database to create its tables and load the seed data.</p>');

		$db_types = databases_enabled();
		reset($db_types);
		if (count($db_types) > 1) {
			sel_row('Database Type', 'DBHOST_DBTYPE', implode("\n", $db_types), implode("\n", array_keys($db_types)), 'Type of database to store FUDforum data in.', (isset($_POST['DBHOST_DBTYPE']) ? $_POST['DBHOST_DBTYPE'] : 'mysqli'));
		} else {
			echo '<tr class="field"><td valign="top"><b>Database Type</b></td><td><input type="hidden" name="DBHOST_DBTYPE" value="'. key($db_types) .'" />Using '. current($db_types) .'</td></tr>';
		}

		if (isset($_POST['DBHOST'])) {
			$DBHOST            = $_POST['DBHOST'];
			$DBHOST_USER       = $_POST['DBHOST_USER'];
			$DBHOST_PASSWORD   = $_POST['DBHOST_PASSWORD'];
			$DBHOST_DBNAME     = $_POST['DBHOST_DBNAME'];
			$DBHOST_TBL_PREFIX = $_POST['DBHOST_TBL_PREFIX'];
		} else {
			$DBHOST = $DBHOST_USER = $DBHOST_PASSWORD = $DBHOST_DBNAME = '';
			$DBHOST_TBL_PREFIX = 'fud30_';
			if (isset($db_types['mysql']) || isset($db_types['mysqli']) || isset($db_types['pdo_mysql'])) {
				$DBHOST      = '127.0.0.1';
				$DBHOST_USER = 'root';					
			}
		}

		if (count($db_types) > 1 || !isset($db_types['pdo_sqlite'])) { // If only DB is sqlite, don't show non-relavent settings.
			input_row('Host', 'DBHOST', $DBHOST, 'The IP address (or unix domain socket) of the database server.', null, 'required');
			input_row('User', 'DBHOST_USER', $DBHOST_USER, 'The database user name.', null, 'required');
			input_row('Password', 'DBHOST_PASSWORD', $DBHOST_PASSWORD, 'The password for the above user name.', 'password');
			input_row('Database', 'DBHOST_DBNAME', $DBHOST_DBNAME, 'The database name.', null, 'required');
		} else {
			echo '<input type="hidden" name="DBHOST" value="" />
			      <input type="hidden" name="DBHOST_USER" value="" />
			      <input type="hidden" name="DBHOST_PASSWORD" value="" />
			      <input type="hidden" name="DBHOST_DBNAME" value="" />';
		}
		input_row('SQL Table Prefix', 'DBHOST_TBL_PREFIX', $DBHOST_TBL_PREFIX, 'A string to append to each table name to identify FUDforum database tables.');

		// jQuery to set database defaults & disable non-relavent input fields.
		echo '<script type="text/javascript">
			jQuery(document).ready(function() {
				jQuery("select").change(function() {
					jQuery("#DBHOST,#DBHOST_USER,#DBHOST_PASSWORD,#DBHOST_DBNAME").show();
					var db = jQuery("option:selected", this).val();
					if (db == "ibm_db2") {
						jQuery("#DBHOST").val("127.0.0.1");
						jQuery("#DBHOST_USER").val("db2inst1");
						jQuery("#DBHOST_DBNAME").val("SAMPLE");
					} else if (db == "interbase") {
						jQuery("#DBHOST").val("127.0.0.1");
						jQuery("#DBHOST_USER").val("SYSDBA");
					} else if (db == "mysql" || db == "mysqli" || db == "pdo_mysql" || db == "cubrid") {
						jQuery("#DBHOST").val("127.0.0.1");
						jQuery("#DBHOST_USER").val("root");
					} else if (db == "pgsql" || db == "pdo_pgsql") {
						jQuery("#DBHOST").val("127.0.0.1");
						jQuery("#DBHOST_USER").val("postgres");
					} else if (db == "oci8") {
						jQuery("#DBHOST").val("127.0.0.1");
						jQuery("#DBHOST_USER").val("scott");
						jQuery("#DBHOST_DBNAME").val("XE");
					} else if (db == "pdo_sqlite") {
						jQuery("#DBHOST,#DBHOST_USER,#DBHOST_PASSWORD,#DBHOST_DBNAME").hide().val("");
					} else if (db == "sqlsrv" || db == "pdo_sqlsrv") {
						jQuery("#DBHOST_USER").val("se");
					}
				});
			});
		</script>';
		dialog_end($section);
		break;

	case 'cookies':
		if (isset($_POST['COOKIE_DOMAIN'])) {
			$COOKIE_DOMAIN = $_POST['COOKIE_DOMAIN'];
		} else {
			$url_parts = parse_url($_POST['WWW_ROOT']);
			$COOKIE_DOMAIN = preg_replace('!^www\.!i', '.', $url_parts['host']);
		}

		dialog_start('Cookie Domain<span class="step">Step 3 of 5</span>', '<p>Enter a Fully Qualified Domain Name (FQDN) of the host itself, or one of its subdomains, or domain it belongs to. Browsers will ignore all cookies that do not satisfy this requirement. For example, if your forum is at http://www.mysite.com/forum, your cookie domain should be <b><i>.mysite.com</i></b>. If you don\'t have a domain yet, enter the server\'s IP address.</p>');
		input_row('Cookie Domain', 'COOKIE_DOMAIN', $COOKIE_DOMAIN, 'The domain of the cookie that will be used by the forum.');
		dialog_end($section);
		break;

	case 'theme':
		dialog_start('Forum Theme<span class="step">Step 4 of 5</span>', '<p>Choose the primary template set and language for your forum. Additional templates and languages can be configured after installation from the <i>Theme Manager</i> admin control panel.</p><p>If the language you require is not available, or the translation is incomplete, please go to <a href="http://cvs.prohost.org/index.php?title=Translate">FUDforum Wiki</a> to and read about translating the forum to other languages.</p>');

		// List available template sets.
		$tmpl_names = '';
		foreach (glob($_POST['SERVER_DATA_ROOT'] .'thm/*', GLOB_ONLYDIR) as $f) {
			if (file_exists($f .'/.path_info')) {
				continue;		// Skip path_info themes.
			}
			$tmpl_names .= basename($f) ."\n";
		}
		sel_row('Template set', 'TEMPLATE', rtrim($tmpl_names), rtrim($tmpl_names), 'The template set (style and layout) for your forum.', 'default');

		// List available languages.
		$lang_codes = $lang_names = '';
		$deflang = 'en';
		$browser_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : 'en';
		foreach (glob($_POST['SERVER_DATA_ROOT'] .'thm/default/i18n/*', GLOB_ONLYDIR) as $f) {
			$langcode = $langname = basename($f);
			if (!file_exists($f .'/msg') || $langcode == 'qqq') {
				continue;	// No messages or tranlations tips.
			}
			if (file_exists($f .'/name')) {
				$langname = trim(file_get_contents($f .'/name'));
			}
			$lang_codes .= $langcode ."\n";
			$lang_names .= $langname ."\n";

			if ($langcode == $browser_lang) {
				$deflang = $langcode;
			}
		}
		sel_row('Language', 'LANGUAGE', rtrim($lang_names), rtrim($lang_codes), 'The primary language for your forum.', $deflang);
		dialog_end($section);
		break;

	case 'admin':
		dialog_start('Admin Account<span class="step">Step 5 of 5</span>', '<p>This step creates the master user account, which is an unrestricted account that can do anything on the forum. You must use this account to edit and customize the forum. For security reasons, avoid predictable login names like "admin" and do not enter the password you use all over the Internet.</p>');

		if (!isset($_POST['ROOT_LOGIN'])) {
			$ROOT_LOGIN  = 'admin';
			$ROOT_PASS   = $ROOT_PASS_C = '';
			$ADMIN_EMAIL = get_current_user() .'@'. strtok($_SERVER['SERVER_NAME'],':'); // Some servers include the port in here.
		} else {
			$ROOT_LOGIN  = $_POST['ROOT_LOGIN'];
			$ROOT_PASS   = $_POST['ROOT_PASS'];
			$ROOT_PASS_C = $_POST['ROOT_PASS_C'];
			$ADMIN_EMAIL = $_POST['ADMIN_EMAIL'];
		}

		input_row('Login Name', 'ROOT_LOGIN', $ROOT_LOGIN);
		input_row('Admin Password', 'ROOT_PASS', $ROOT_PASS, NULL, 'password');
		input_row('Confirm Password', 'ROOT_PASS_C', $ROOT_PASS_C, NULL, 'password', 'onkeyup="passwords_match(\'ROOT_PASS\', this); return false;"');
		input_row('Admin Email', 'ADMIN_EMAIL', $ADMIN_EMAIL);
		dialog_end($section);
		break;

	case 'done':
		dialog_start('Installation Complete', '<p style="color:darkgreen;">Congratulations, you have now completed the basic installation of your forum. To continue configuring your forum, you must login and use the administrator control panel.
			Clicking "Finished" will take you to the login screen. After you login, you will be taken to the administrator control panel.</p>
			<p style="text-align: center; color:red;">Before you continue, please delete this <b>install.php</b> script as it can be used to overwrite your forum.</p>
		');

		echo '<tr><td colspan="2" align="center"><input type="submit" name="submit" value="Finished" title="Start enjoying FUDforum!" onclick="window.location=\''. $_POST['WWW_ROOT'] .'/adm/index.php\'; return false;" />';
		dialog_end($section);
		break;
}

if ($section !== 'done') {
	echo '<input type="hidden" name="section" value="'. $section .'" />';

	if (isset($_POST['WWW_ROOT']) && $section != 'stor_path') {
		echo '<input type="hidden" name="WWW_ROOT" value="'. $_POST['WWW_ROOT'] .'" />
		      <input type="hidden" name="SERVER_DATA_ROOT" value="'. $_POST['SERVER_DATA_ROOT'] .'" />
		      <input type="hidden" name="SERVER_ROOT" value="'. $_POST['SERVER_ROOT'] .'" />';
	}
	if (isset($_POST['LANGUAGE'])) {
		echo '<input type="hidden" name="LANGUAGE" value="'. $_POST['LANGUAGE'] .'" />';
	}
	if (isset($_POST['TEMPLATE'])) {
		echo '<input type="hidden" name="TEMPLATE" value="'. $_POST['TEMPLATE'] .'" />';
	}
}

switch ($section) {
	case 'cookies':
	case 'theme':
	case 'admin':
		echo '<input type="hidden" name="DBHOST" value="'. htmlspecialchars($_POST['DBHOST']) .'" />
		      <input type="hidden" name="DBHOST_PASSWORD" value="'. htmlspecialchars($_POST['DBHOST_PASSWORD']) .'" />
		      <input type="hidden" name="DBHOST_USER" value="'. htmlspecialchars($_POST['DBHOST_USER']) .'" />
		      <input type="hidden" name="DBHOST_DBNAME" value="'. htmlspecialchars($_POST['DBHOST_DBNAME']) .'" />
		      <input type="hidden" name="DBHOST_DBTYPE" value="'. htmlspecialchars($_POST['DBHOST_DBTYPE']) .'" />
		      <input type="hidden" name="DBHOST_TBL_PREFIX" value="'. htmlspecialchars($_POST['DBHOST_TBL_PREFIX']) .'" />';
		break;
}

page_footer();
?>

