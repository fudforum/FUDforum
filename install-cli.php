<?php
/***************************************************************************
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: install-cli.php,v 1.20 2006/09/19 14:37:55 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; version 2 of the License. 
***************************************************************************/

	set_time_limit(-1);
	ini_set("magic_quotes_runtime", 0);
	ini_set("display_errors", 1);
	error_reporting(E_ALL);

function pf($str)
{
	echo $str;
	flush();
}

function fe($str)
{
	pf("\nFATAL ERROR!\n");
	pf($str);
	exit(-1);
}

function is_wr($path)
{
	while ($path && $path != "/") {
		if (@is_writeable($path)) {
			return 1;
		}
		$path = dirname($path);
	}
	return 0;
}

function mkdir_r($path)
{
	$dirs = array();
	while (!is_dir($path)) {
		$dirs[] = $path;
		$path = dirname($path);
		if (!$path || $path == "/") {
			break;
		}
	}
	foreach ($dirs as $dir) {
		if (!mkdir($dir, 0755)) {
			fe("Failed to create '{$dir}' directory\n");	
		}
	}
}

function validate_url($path)
{
	global $settings;

	if (!$path) {
		return 0;
	}

	if (($u = @parse_url($path)) && isset($u['host'])) {
		$settings['WWW_ROOT'] = $path;
		if (substr($settings['WWW_ROOT'], -1) != '/') {
			$settings['WWW_ROOT'] .= '/';
		}
		if ($u['host'] != 'localhost' && ip2long($u['host']) < 1) {
			$settings['COOKIE_DOMAIN'] = preg_replace('!^www\.!i', '.', $u['host']);
			$settings['COOKIE_PATH'] = $u['path'];
		} else {
			$settings['COOKIE_PATH'] = '/';
		}
		return 1;
	}
	return 0;
}

function module_check()
{
	$modules = array('zlib', 'mysql', 'pgsql', 'pcre', 'pspell', 'posix');
	foreach ($modules as $m) {
		$status[$m] = extension_loaded($m);
	}
	return $status;
}

function decompress_archive($data_root, $web_root)
{
	$clean = array('PHP_OPEN_TAG'=>'<?', 'PHP_OPEN_ASP_TAG'=>'<%');

	$data = file_get_contents(__FILE__);
	$p = strpos($data, "2105111608_\\ARCH_START_HERE") + strlen("2105111608_\\ARCH_START_HERE") + 1;
	$checksum = substr($data, $p, 32);
	$data = substr($data, $p + 32);
	if (strpos($data, 'RAW_PHP_OPEN_TAG') !== FALSE) { /* no compression */
		unset($clean['PHP_OPEN_TAG']); $clean['RAW_PHP_OPEN_TAG'] = '<?';
		$data = strtr($data, $clean);
	} else {
		$data_len = (int) substr($data, 0, 10);
		$data = strtr(substr($data, 10), $clean);

		if (!($data = gzuncompress($data, $data_len))) { /* compression */
			fe("Failed decompressing the archive\n");
		}
	}

	if (md5($data) != $checksum) {
		fe("Archive did not pass checksum test, CORRUPT ARCHIVE!\nIf you've encountered this error it means that you've:\n\tdownloaded a corrupt archive\n\tuploaded the archive in ASCII and not BINARY mode\n\tyour FTP Server/Decompression software/Operating System added un-needed cartrige return ('\\r') characters to the archive, resulting in archive corruption.\n\n");
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
		$path .= '/' . $meta_data[1];

		$path = str_replace('//', '/', $path);

		if (isset($meta_data[5])) {
			$file = substr($data, ($pos + 1), $meta_data[5]);
			if (md5($file) != $meta_data[4]) {
				fe("file '{$meta_data[1]}' was not read properly from archive.\n");
			}

			if ($path == $web_root . '.htaccess' && @file_exists($path)) {
				define('old_htaccess', 1);
				continue;
			}

			$fp = @fopen($path, 'wb');
			if (!$fp) {
				fe("Couldn't open '{$path}' for write\n");
			}
				fwrite($fp, $file);
			fclose($fp);

			chmod($path, 0644);
		} else {
			if (substr($path, -1) == '/') {
				$path = preg_replace('!/+$!', '', $path);
			}
			if (!is_dir($path)) {
				mkdir_r($path);
			}
			chmod($path, 0755);
		}
	} while (($pos = strpos($data, "\n//", $pos)) !== false);
}

/* win32 does not have symlink\s, so we use this crude emulation */
if (!function_exists('symlink')) {
	function symlink($src, $dest)
	{
		if (!($fp = fopen($dest, 'wb'))) {
			return FALSE;
		}
		fwrite($fp, '<?php include_once "'.$src.'"; ?>');
		fclose($fp);
	}
}

function htaccess_handler($web_root, $ht_pass)
{
	/* opening a connection to itself should not take more then 5 seconds */
	ini_set("default_socket_timeout", 5);
	if (@fopen($web_root . 'index.php', 'r') === FALSE) {
		unlink($ht_pass);
	}
}

function dbquery($qry)
{
	switch (__dbtype__) {
		case 'mysql':
			return mysql_query($qry, __FUD_SQL_LNK__);
			break;
		case 'pgsql':
			return pg_query(__FUD_SQL_LNK__, $qry);
			break;
	}
}

function dberror()
{
	switch (__dbtype__) {
		case 'mysql':
			return mysql_error(__FUD_SQL_LNK__);
			break;
		case 'pgsql':
			return pg_last_error(__FUD_SQL_LNK__);
			break;
	}
}

function dbperms_check()
{
	/* version check */
	if (($r = dbquery('SELECT VERSION()'))) {
		$fetch = __dbtype__ == 'pgsql' ? 'pg_fetch_row' : 'mysql_fetch_row';
		$val = $fetch($r);
		if ($val && preg_match('!([3-8]\.[0-9]+(?:\.[0-9]+)?)!', $val[0], $m)) {
			$version = $m[1];
		} else {
			$version = 0;
		}
		if (__dbtype__ == 'mysql' && !version_compare($version, '3.23.0', '>=')) {
			fe("The specified MySQL server is running version '{$version}', which is older then the minimum required version '3.23.0'\n");
		} else if (__dbtype__ == 'pgsql' && !version_compare($version, '7.2.0', '>=')) {
			fe("The specified PostgreSQL server is running version '{$version}', which is older then the minimum required version '7.2.0'\n");
		}
	}

	switch (__dbtype__) {
		case 'mysql':
			mysql_query('DROP TABLE IF EXISTS fud_forum_install_test_table');
			if (!mysql_query('CREATE TABLE fud_forum_install_test_table (test_val INT)')) {
				fe("Your MySQL account does not have permissions to create new MySQL tables.\nEnable this functionality and restart the script.\n");
			}
			if (!mysql_query('ALTER TABLE fud_forum_install_test_table ADD test_val2 INT')) {
				fe("Your MySQL account does not have permissions to run ALTER queries on existing MySQL tables\nEnable this functionality and restart the script.\n");
			}
			if (!mysql_query('LOCK TABLES fud_forum_install_test_table WRITE')) {
				fe("Your MySQL account does not have permissions to run LOCK queries on existing MySQL tables\nEnable this functionality and restart the script.\n");
			}
			mysql_query('UNLOCK TABLES');
			if (!mysql_query('DROP TABLE fud_forum_install_test_table')) {
				fe("Your MySQL account does not have permissions to run DROP TABLE queries on existing MySQL tables\nEnable this functionality and restart the script.\n");
			}
			break;
		case 'pgsql':
			@pg_query(__FUD_SQL_LNK__, 'DROP TABLE fud_forum_install_test_table');
			if (!pg_query(__FUD_SQL_LNK__, 'CREATE TABLE fud_forum_install_test_table (test_val INT)')) {
				fe("Your PostgreSQL account does not have permissions to create new PostgreSQL tables.\nEnable this functionality and restart the script.\n");
			}
			if (!pg_query(__FUD_SQL_LNK__, 'BEGIN WORK') || !pg_query(__FUD_SQL_LNK__, 'ALTER TABLE fud_forum_install_test_table ADD test_val2 INT')) {
				fe("Your PostgreSQL account does not have permissions to run ALTER queries on existing PostgreSQL tables\nEnable this functionality and restart the script.\n");
			}
			pg_query(__FUD_SQL_LNK__, 'COMMIT WORK');

			if (!pg_query(__FUD_SQL_LNK__, 'DROP TABLE fud_forum_install_test_table')) {
				fe("Your PostgreSQL account does not have permissions to run DROP TABLE queries on existing PostgreSQL tables\nEnable this functionality and restart the script.\n");
			}
			break;
	}
}

function make_into_query($data)
{
	return trim(str_replace('{SQL_TABLE_PREFIX}', $GLOBALS['settings']['DBHOST_TBL_PREFIX'], preg_replace('!\s+!', ' ', preg_replace('!\#.*$!s', '', $data))));
}

function change_global_settings($list)
{
	$settings = file_get_contents($GLOBALS['INCLUDE'] . 'GLOBALS.php');
	foreach ($list as $k => $v) {
		if (($p = strpos($settings, '$' . $k)) === false) {
			$pos = strpos($settings, '$ADMIN_EMAIL');
			if (is_int($v)) {
				$settings = substr_replace($settings, "\${$k}\t= {$v};\n\t", $p, 0);
			} else {
				$v = addcslashes($v, '\\"$');
				$settings = substr_replace($settings, "\${$k}\t= \"{$v}\";\n\t", $p, 0);
			}
		} else {
			$p = strpos($settings, '=', $p) + 1;
			$e = $p + strrpos(substr($settings, $p, (strpos($settings, "\n", $p) - $p)), ';');

			if (is_int($v)) {
				$settings = substr_replace($settings, ' '.$v, $p, ($e - $p));
			} else {
				$v = addcslashes($v, '\\"$');
				$settings = substr_replace($settings, ' "'.$v.'"', $p, ($e - $p));
			}
		}
	}

	$fp = fopen($GLOBALS['INCLUDE'].'GLOBALS.php', 'w');
	fwrite($fp, $settings);
	fclose($fp);
}

function db_connect($settings)
{
	if ($settings['DBHOST_TYPE'] == 'mysql') {
		if (($conn = @mysql_connect($settings['DBHOST'], $settings['DBHOST_USER'], $settings['DBHOST_PASSWORD']))) {
			if (@mysql_select_db($settings['DBHOST_DBNAME'], $conn)) {
				return $conn;
			}
		}
		pf("Failed to connect using provided information '".mysql_error()."'\n");
	} else {
		$connect_str = "host={$settings['DBHOST']} user={$settings['DBHOST_USER']} password={$settings['DBHOST_PASSWORD']} dbname={$settings['DBHOST_DBNAME']}";
		if (($conn = pg_connect($connect_str))) {
			return $conn;
		}
		pf("Failed to connect using provided information '".pg_last_error()."'\n");
	}
}

	$settings = array(
			'WWW_ROOT' => '',
			'SERVER_ROOT' => '',
			'SERVER_DATA_ROOT' => '',
			'DBHOST' => '',
			'DBHOST_USER' => '',
			'DBHOST_PASSWORD' => '',
			'DBHOST_DBNAME' => '',
			'DBHOST_TBL_PREFIX' => 'fud26_',
			'DBHOST_TYPE' => 'mysql',
			'COOKIE_DOMAIN' => '',
	                'LANGUAGE' => '',
	                'ROOT_LOGIN' => get_current_user(),
	                'ROOT_PASS' => '',
	                'ADMIN_EMAIL' => ''
	);

	$module_status = module_check();

	if (!version_compare(PHP_VERSION, '4.3.0', '>=')) {
		fe("Your php version (<?php echo PHP_VERSION; ?>) is older then the minimum required version (4.3.0)\n\n");
	} else if (($fs = filesize(__FILE__)) < 200000) {
		fe("The installer is missing the data archive, append the archive to the installer and try again.\n\n");
	} else if ($fs < 3500000 && !$module_status['zlib']) {
		fe("zlib extension required to decompress the archive is not loaded.\nPlease recompile your PHP with zlib support or load the zlib extension, in the event this is not possible download\nthe non-zlib version of the install or upgrade script from FUDforum's website at:\nhttp://fud.prohost.org/forum/\n\n");
	} else if (!$module_status['mysql'] && !$module_status['pgsql']) {
		fe("FUDforum can utilize either MySQL or PosgreSQL database to store it's data, unfortunately, your PHP does not have\nsupport for either one. Please install or load the appropriate database extension and then re-run the install script.\n\n");
	} else if (!$module_status['pcre']) {
		fe("PCRE (Perl Compatible Regular Expression) extension required for proper forum operation is not avaliable,\nplease load or install this extension and then re-run the installer.\n\n");
	}

	$got_config = 0;
	if (isset($_SERVER['argv'][1]) && !is_numeric($_SERVER['argv'][1]) && @file_exists($_SERVER['argv'][1])) {
		$settings = array_merge($settings, parse_ini_file($_SERVER['argv'][1]));
		$got_config = 1;
	} else if (@file_exists("./fud_config.ini")) {
		$settings = array_merge($settings, parse_ini_file("./fud_config.ini"));
		$got_config = 1;
	}

	/* Fetch forum's URL */
	while (!validate_url($settings['WWW_ROOT'])) {
		pf("Your forum's URL: ");
		$path = trim(fgets(STDIN, 1024));
		if (validate_url($path)) {
			break;
		}
		pf("'{$path} is not a valid URL, please supply a url in the 'http://host/path/' format\n");
	}

	/* Fetch file system path of the forum's web files */
	while (!$settings['SERVER_ROOT'] || !is_wr($settings['SERVER_ROOT'])) {
		pf("Path to forum's web browseable files: ");
		$path = trim(fgets(STDIN, 1024));
		if ($path && is_wr($path)) {
			$path = preg_replace('!/+$!', '', $path);
			$settings['SERVER_ROOT'] = $path . "/";
			mkdir_r($path);
			break;
		}
		pf("'{$path}' iether does not exist or the installer has no permission to create it\n");
	}

	/* Fetch file path of the forum's web files */
	while (!$settings['SERVER_DATA_ROOT'] || !is_wr($settings['SERVER_DATA_ROOT'])) {
		pf("Path to forum's data files (non-browseable) [{$settings['SERVER_ROOT']}]: ");
		$path = trim(fgets(STDIN, 1024));
		if (!$path) {
			$settings['SERVER_DATA_ROOT'] = $settings['SERVER_ROOT'];
			break;
		} else if (is_wr($path)) {
			$path = preg_replace('!/+$!', '', $path);
			$settings['SERVER_DATA_ROOT'] = $path . "/";
			mkdir_r($path);
			break;
		}
		pf("'{$path}' iether does not exist or the installer has no permission to create it\n");
	}

	/* decompress the archive */
	decompress_archive($settings['SERVER_DATA_ROOT'], $settings['SERVER_ROOT']);
	/* verify that all the important directories exist (old php bug) */
	$dir_ar = array('include', 'errors', 'messages', 'files', 'template', 'tmp', 'cache', 'errors/.nntp', 'errors/.mlist');
	foreach ($dir_ar as $v) {
		if (is_dir($settings['SERVER_DATA_ROOT'] . $v)) {
			mkdir_r($settings['SERVER_DATA_ROOT'] . $v);
		}
		chmod($settings['SERVER_DATA_ROOT'], 0755);
	}
	/* determine if this host can support .htaccess directives */
	htaccess_handler($settings['WWW_ROOT'], $settings['SERVER_ROOT'] . '.htaccess');
	
	$INCLUDE = $settings['SERVER_DATA_ROOT'].'include/';
	$ERROR_PATH  = $settings['SERVER_DATA_ROOT'].'errors/';
	$MSG_STORE_DIR = $settings['SERVER_DATA_ROOT'].'messages/';
	$FILE_STORE = $settings['SERVER_DATA_ROOT'].'files/';
	$TMP = $settings['SERVER_DATA_ROOT'].'tmp/';
	$FORUM_SETTINGS_PATH = $settings['SERVER_DATA_ROOT'].'cache/';

	chmod($INCLUDE . 'GLOBALS.php', 0666);
	touch($ERROR_PATH . 'FILE_LOCK');

	/* ensure we don't have any bogus symlinks (re-installing over old forum) */
	@unlink($settings['SERVER_ROOT'] . 'GLOBALS.php');
	@unlink($settings['SERVER_ROOT'] . 'adm/GLOBALS.php');
	@unlink($settings['SERVER_DATA_ROOT'] . 'scripts/GLOBALS.php');

	/* make symlinks to GLOBALS.php */
	symlink($INCLUDE . 'GLOBALS.php', $settings['SERVER_ROOT'] . 'GLOBALS.php');
	symlink($INCLUDE . 'GLOBALS.php', $settings['SERVER_ROOT'] . 'adm/GLOBALS.php');
	symlink($INCLUDE . 'GLOBALS.php', $settings['SERVER_DATA_ROOT'] . 'scripts/GLOBALS.php');

	/* default bitmask values */
	$FUD_OPT_1 = 1744762047;
	if (!$module_status['pspell']) {
		$FUD_OPT_1 ^= 2097152;
	}
	$FUD_OPT_2 = 695676991 | 8388608;

	change_global_settings(array(
		'INCLUDE' => $INCLUDE,
		'ERROR_PATH' => $ERROR_PATH,
		'MSG_STORE_DIR' => $MSG_STORE_DIR,
		'FILE_STORE' => $FILE_STORE,
		'TMP' => $TMP,
		'WWW_ROOT' => $settings['WWW_ROOT'],
		'WWW_ROOT_DISK' => $settings['SERVER_ROOT'],
		'FORUM_SETTINGS_PATH' => $FORUM_SETTINGS_PATH,
		'COOKIE_NAME' => 'fud_session_'.time(),
		'FUD_OPT_2' => $FUD_OPT_2,
		'FUD_OPT_1' => $FUD_OPT_1,
		'COOKIE_PATH' => $settings['COOKIE_PATH'],
		'COOKIE_DOMAIN' => $settings['COOKIE_DOMAIN'],
		'DATA_DIR' => $settings['SERVER_DATA_ROOT']));

	/* Pick a database type */
	$dbs = array();
	if ($module_status['mysql']) {
		$dbs[] = 'mysql';
	}
	if ($module_status['pgsql']) {
		$dbs[] = 'pgsql';
	}
	if (count($dbs) == 1) {
		$settings['DBHOST_TYPE'] = $dbs[0];
	} else {
		while (!$settings['DBHOST_TYPE']) {
			pf("Please choose a database type [mysql pgsql]: ");
			$db = trim(fgets(STDIN, 1024));
			if (in_array($db, $dbs)) {
				$settings['DBHOST_TYPE'] = $db;
				break;
			}
			pf("'{$db}' is not a valid database type or is not supported.\n");
		}
	}
	define('__dbtype__', $settings['DBHOST_TYPE']);

	if ($got_config) {
		$conn = db_connect($settings);
	} else {
		$conn = null;
	}

	while (!$conn) {
		pf("Please specify database host: ");
		$settings['DBHOST'] = trim(fgets(STDIN, 1024));
	
		pf("Please specify database name: ");
		$settings['DBHOST_DBNAME'] = trim(fgets(STDIN, 1024));

		pf("Please specify database user: ");
		$settings['DBHOST_USER'] = trim(fgets(STDIN, 1024));

		pf("Please specify password for '{$settings['DBHOST_USER']}': ");
		$settings['DBHOST_PASSWORD'] = trim(fgets(STDIN, 1024));
		
		pf("Please specify SQL table prefix '{$settings['DBHOST_TBL_PREFIX']}' [fud26_]: ");
		$settings['DBHOST_TBL_PREFIX'] = preg_replace('![^[:alnum:]_]!', '', trim(fgets(STDIN, 1024)));
		if (!$settings['DBHOST_TBL_PREFIX']) {
			$settings['DBHOST_TBL_PREFIX'] = 'fud26_';
		}
		$conn = db_connect($settings);
	}
	define('__FUD_SQL_LNK__', $conn);

	/* check SQL permissions */
	dbperms_check();

	/* Import sql data */
	$tables = $def_data = array();
	if (__dbtype__ == 'pgsql') {
		$prefix =& $settings['DBHOST_TBL_PREFIX'];
		$preflen = strlen($prefix);

		/* remove possibly conflicting tables */
		$c = dbquery("select relname from pg_class WHERE relkind='r' AND relname LIKE '".str_replace('_', '\\\\_', $prefix)."%'");
		while ($r = pg_fetch_row($c)) {
			if (!strncmp($r[0], $prefix, $preflen)) {
				if (!dbquery('DROP TABLE '.$r[0])) {
					fe(dberror());
				}
			}
		}
		unset($c);
	
		/* remove possibly conflicting sequences */
		$c = dbquery("select relname from pg_class WHERE relkind='S' AND relname LIKE '".str_replace('_', '\\\\_', $prefix)."%'");
		while ($r = pg_fetch_row($c)) {
			if (!strncmp($r[0], $prefix, $preflen)) {
				if (!dbquery('DROP SEQUENCE '.$r[0])) {
					fe(dberror());
				}
			}
		}
		unset($c);
	}

	$tbl = glob($settings['SERVER_DATA_ROOT'] . 'sql/*.tbl', GLOB_NOSORT);
	$sql = glob($settings['SERVER_DATA_ROOT'] . 'sql/*.sql', GLOB_NOSORT);

	if (!$tbl || !$sql) {
		fe("Failed to get a list of table defenitions and/or base table data from: '{$settings['SERVER_DATA_ROOT']}sql/'\n");
	}

	/* import tables */
	foreach ($tbl as $t) {
		$data = explode(';', preg_replace('!#.*?\n!s', '', file_get_contents($t)));
		foreach ($data as $q) {
			$q = trim($q);

			if (__dbtype__ != 'mysql') {
				if (!strncmp($q, 'DROP TABLE IF EXISTS', strlen('DROP TABLE IF EXISTS'))) {
					continue;	
				}
				$q = str_replace(array('BINARY', 'INT NOT NULL AUTO_INCREMENT'), array('', 'SERIAL'), $q);
			} else if (version_compare($version, '4.1.2', '>=') && !strncmp($q, 'CREATE TABLE', strlen('CREATE TABLE'))) {
				/* for MySQL 4.1.2 we need to specify a default charset */
				$q .= " DEFAULT CHARACTER SET latin1";
			}
			if (($q = make_into_query(trim($q)))) {
				if (!dbquery($q)) {
					fe('Failed to create table "'.basename($t, '.tbl').'" ("'.$q.'"), SQL Reason: '.dberror()."\n");
					break 2;
				}
			}
		}
	}

	/* import table data */
	foreach ($sql as $t) {
		$data = explode(";\n", file_get_contents($t));
		foreach ($data as $q) {
			if (strpos($q, 'UNIX_TIMESTAMP') !== false) {
				$q = str_replace('UNIX_TIMESTAMP', time(), $q);
			}
			if (($q = make_into_query(trim($q)))) {
				if (!dbquery($q)) {
					fe('Failed to import default data ("'.$q.'") into table '.basename($t, '.sql').', SQL Reason: '.dberror()."\n");
					break 2;
				}
			}
		}
	}

	change_global_settings(array(
		'DBHOST' => $settings['DBHOST'],
		'DBHOST_USER' => $settings['DBHOST_USER'],
		'DBHOST_PASSWORD' => $settings['DBHOST_PASSWORD'],
		'DBHOST_DBNAME' => $settings['DBHOST_DBNAME'],
		'DBHOST_TBL_PREFIX' => $settings['DBHOST_TBL_PREFIX']
	));

	/* handle language selection */
	if (!defined('GLOB_NOSORT')) { /* pre PHP 4.3.3 hack for Windows & FreeBSD */
		define('GLOB_NOSORT', 0);
	}
	$ln_dir = glob($settings['SERVER_DATA_ROOT'].'thm/default/i18n/*', GLOB_ONLYDIR|GLOB_NOSORT);
	if (!$ln_dir) {
		fe("Could not open i18n directory at '{$settings['SERVER_DATA_ROOT']}thm/default/i18n'\n");
	}
	foreach ($ln_dir as $f) {
		if (file_exists($f . '/locale')) {
			$langs[strtolower($f)] = array(trim(file_get_contents($path . $f . '/locale')), @trim(file_get_contents($path . $f . '/pspell_lang')));
		} 
	}

	pf("Supported Languages: \n\t".wordwrap(ucwords(implode(' ', array_keys($langs))), 75, "\n\t")."\n");

	while (!$settings['LANGUAGE']) {
		pf("Please choose a language [english]: ");
		$lang = strtolower(trim(fgets(STDIN, 1024)));
		if (!$lang) {
			$lang = 'english';	
		}
		if (isset($langs[$lang])) {
			dbquery("DELETE FROM ".$settings['DBHOST_TBL_PREFIX']."themes");
			if (!dbquery("INSERT INTO ".$settings['DBHOST_TBL_PREFIX']."themes(id, name, theme, lang, locale, theme_opt, pspell_lang) VALUES(1, 'default', 'default', '{$lang}', '{$langs[$lang][0]}', 3, '{$langs[$lang][1]}')")) {
				fe(dberror());
			}
			$settings['LANGUAGE'] = $lang;
			break;		
		}
		fs("Unsupported language '{$lang}', please choose a language\n");
	}

	/* get admin account information */
	if (!$got_config) {
		pf("Forum's Administrator Login [{$settings['ROOT_LOGIN']}]: ");
		$tmp = trim(fgets(STDIN, 1024));
		if ($tmp) {
			$settings['ROOT_LOGIN'] = $tmp;
		}
	}
	
	while (!$settings['ADMIN_EMAIL']) {
		pf("Forum's Administrator E-mail: ");
		$settings['ADMIN_EMAIL'] = trim(fgets(STDIN, 1024));
	}
	
	while (!$settings['ROOT_PASS']) {
		pf("Forum's Administrator Password: ");
		$settings['ROOT_PASS'] = trim(fgets(STDIN, 1024));
		pf("Please confirm the password: ");
		if ($settings['ROOT_PASS'] != trim(fgets(STDIN, 1024))) {
			$settings['ROOT_PASS'] = '';
			pf("Passwords do not match\n");
		}
	}

	dbquery("DELETE FROM ".$settings['DBHOST_TBL_PREFIX']."users WHERE id > 1");
	if (!dbquery("INSERT INTO ".$settings['DBHOST_TBL_PREFIX']."users (login, alias, passwd, name, email, users_opt, join_date, theme) VALUES('".addslashes($settings['ROOT_LOGIN'])."', '".addslashes(htmlspecialchars($settings['ROOT_LOGIN']))."', '".md5($settings['ROOT_PASS'])."', 'Administrator', '".addslashes($settings['ADMIN_EMAIL'])."', 5405687, ".time().", 1)")) {
		fe(dberror());
	}
	change_global_settings(array('ADMIN_EMAIL' => $settings['ADMIN_EMAIL'], 'NOTIFY_FROM' => $settings['ADMIN_EMAIL']));

	/* build theme */
	$GLOBALS['WWW_ROOT'] = $settings['WWW_ROOT'];
	$GLOBALS['WWW_ROOT_DISK'] = $settings['SERVER_ROOT'];
	$GLOBALS['DATA_DIR'] = $settings['SERVER_DATA_ROOT'];
	$GLOBALS['DBHOST_TBL_PREFIX'] = $settings['DBHOST_TBL_PREFIX'];

	require($settings['SERVER_DATA_ROOT'] . 'include/compiler.inc');
	compile_all('default', $lang);

	pf("FUDforum installation is now complete.");
	pf("You can access your new FUDforum install at: {$settings['WWW_ROOT']}/index.php");
	exit;
?>
2105111608_\ARCH_START_HERE
