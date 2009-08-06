<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admdump.php,v 1.86 2009/08/06 18:00:55 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	error_reporting(E_ERROR | E_WARNING | E_PARSE | E_COMPILE_ERROR);
	@ini_set('display_errors', '1');
	@ini_set('memory_limit', '256M');
	@set_time_limit(0);

function backup_dir($dirp, $fp, $write_func, $keep_dir, $p=0)
{
	global $BUF_SIZE;

	$dirs = array(realpath($dirp));
	$repl = realpath($GLOBALS[$keep_dir]);
	$is_win = !strncasecmp('win', PHP_OS, 3);
	
	while (list(,$v) = each($dirs)) {
		if (!is_readable($v)) {
			echo 'Could not open "'.$v.'" for reading<br />';
			return;
		}
		echo 'Processing directory: '.$v.'<br />';
		@ob_flush(); flush();

		if (!($files = glob($v . '/{.h*,.p*,.n*,.m*,*}', GLOB_BRACE|GLOB_NOSORT))) {
			continue;
		}
		if ($is_win) {
			$v = str_replace("\\", '/', $v);
		}
		$dpath = trim(str_replace($repl, $keep_dir, $v), '/') . '/';

		if ($p) {
			$write_func($fp, '||WWW_ROOT_DISK/blank.gif||' . filesize($GLOBALS['WWW_ROOT_DISK'].'blank.gif') . "||\n" . file_get_contents($GLOBALS['WWW_ROOT_DISK'].'blank.gif') . "\n");
			$write_func($fp, '||WWW_ROOT_DISK/lib.js||' . filesize($GLOBALS['WWW_ROOT_DISK'].'lib.js') . "||\n" . file_get_contents($GLOBALS['WWW_ROOT_DISK'].'lib.js') . "\n");
			$p = 0;
		}

		foreach ($files as $f) {
			if (is_link($f)) {
				continue;
			}
			$name = basename($f);

			if (is_dir($f)) {
				if ($name == 'tmp' || $name == 'theme') {
					continue;
				} else if ($keep_dir == 'DATA_DIR' && ($name == 'adm' || $name == 'images')) {
					continue;
				}
				$dirs[] = $f;
				continue;
			}
			if ($name == 'GLOBALS.php' || ($keep_dir == 'DATA_DIR' && ($name == 'lib.js' || $name == 'blank.gif'))) {
				continue;
			}
			if (!is_readable($f)) {
				echo "WARNING: unable to open '".$f."' for reading<br />\n";
				continue;
			}
			$ln = filesize($f);
			if ($ln < $BUF_SIZE) {
				$write_func($fp, '||' . $dpath . $name . '||' . $ln . "||\n" . file_get_contents($f) . "\n");
			} else {
				$write_func($fp, '||' . $dpath . $name . '||' . $ln . "||\n");
				$fp2 = fopen($f, 'rb');
				while (($buf = fread($fp2, $BUF_SIZE))) {
					$write_func($fp, $buf);
				}
				fclose($fp2);
				$write_func($fp, "\n");
			}
		}
	}
}

	require('./GLOBALS.php');
	fud_use('db.inc');
	fud_use('mem_limit.inc', true);
	// uncomment the lines below if you wish to run this script via command line
	// fud_use('adm_cli.inc', 1); // this contains cli_execute() function.
	// cli_execute('');
	// when using this the script accepts 2 arguments
	// php admdump.php /path/to/dump_file [compress]
	// compress is optional and should only be specified if you want to datadump to be compressed

	/* check for cli arguments */
	if (php_sapi_name() == 'cli' && defined('forum_debug')) {
		if (empty($_SERVER['argv'][1])) {
			exit("Usage: php admdump.php /path/to/dump_file [compress]\n");
		}
		$_POST['submitted'] = 1;
		$_POST['path'] = $_SERVER['argv'][1];
		if (!empty($_SERVER['argv'][2])) {
			$_POST['compress'] = 1;
		}
	}

	/*
	 * Check for HTTP AUTH, before going for the usual cookie/session auth
	 * this is done to allow for easier running of this process via an
	 * automated cronjob.
	 */

	if (isset($_GET['do_http_auth']) && !isset($_SERVER['PHP_AUTH_USER'])) {
		header('WWW-Authenticate: Basic realm="Private"');
		header('HTTP/1.0 401 Unauthorized');
		exit('Authorization Required.');
	}
	if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
		if (!q_singleval('SELECT id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WHERE login='._esc($_SERVER['PHP_AUTH_USER']).' AND passwd=\''.md5($_SERVER['PHP_AUTH_PW']).'\' AND users_opt>=1048576 AND (users_opt & 1048576) > 0')) {
			header('WWW-Authenticate: Basic realm="Private"');
			header('HTTP/1.0 401 Unauthorized');
			exit('Authorization Required.');
		}
	} else {
		fud_use('adm.inc', true);
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	if (isset($_POST['submitted']) && !@fopen($_POST['path'], 'w')) {
		$path_error = '<font color="#ff0000">Couldn\'t open backup destination file, '.$_POST['path'].' for write.</font><br />';
		$_POST['submitted'] = null;
	}

	if (isset($_POST['submitted'])) {
		if (isset($_POST['compress'])) {
			if (!$fp = gzopen($_POST['path'], 'wb9')) {
				exit('cannot create file');
			}
			$write_func = 'gzwrite';
		} else {
			if (!$fp = fopen($_POST['path'], 'wb')) {
				exit('cannot create file');
			}
			$write_func = 'fwrite';
		}

		echo "Compressing forum datafiles<br />\n";
		@ob_flush(); flush();
		$write_func($fp, "\n----FILES_START----\n");
		backup_dir($DATA_DIR, $fp, $write_func, 'DATA_DIR');
		backup_dir($WWW_ROOT_DISK.'images/', $fp, $write_func, 'WWW_ROOT_DISK', 1);
		backup_dir($WWW_ROOT_DISK.'adm/', $fp, $write_func, 'WWW_ROOT_DISK');

		$write_func($fp, "\n----FILES_END----\n");

		$write_func($fp, "\n----SQL_START----\n");

		/* read sql table defenitions */
		
		if (!($files = glob($DATA_DIR . 'sql/*.tbl', GLOB_NOSORT))) {
			exit('Failed to open SQL directory "'.$DATA_DIR.'sql/"');
		}
		foreach ($files as $f) {
			$sql_data = file_get_contents($f);
			$sql_data = preg_replace(array("!\#.*?\n!s","!\s+!s"), array("\n"," "), $sql_data);
			$sql_data = str_replace(";", "\n", $sql_data);
			$write_func($fp, $sql_data . "\n");
		}
		unset($files);

		$sql_table_list = get_fud_table_list();
		db_lock(implode(' WRITE, ', $sql_table_list) . ' WRITE');

		foreach($sql_table_list as $tbl_name) {
			/* not needed, will be rebuilt by consistency checker */
			if (!strncmp($tbl_name, $DBHOST_TBL_PREFIX.'tv_', strlen($DBHOST_TBL_PREFIX.'tv_')) || 
				$tbl_name == $DBHOST_TBL_PREFIX . 'ses' ||
				!strncmp($tbl_name, $DBHOST_TBL_PREFIX.'fl_', strlen($DBHOST_TBL_PREFIX.'fl_'))
			) {
				continue;
			}
			if (isset($_POST['skipsearch']) && $_POST['skipsearch'] == 'y' && (
				$tbl_name == $DBHOST_TBL_PREFIX.'index' || 
				$tbl_name == $DBHOST_TBL_PREFIX.'title_index' || 
				$tbl_name == $DBHOST_TBL_PREFIX.'search' || 
				$tbl_name == $DBHOST_TBL_PREFIX.'search_cache')
			) {
				echo 'Skipping table: '.$tbl_name.'<br />';
				continue;
			}

			$num_entries = q_singleval('SELECT count(*) FROM '.$tbl_name);

			echo 'Processing table: '.$tbl_name.' ('.$num_entries.' rows) .... ';
			@ob_flush(); flush();
			if ($num_entries) {
				$db_name = preg_replace('!^'.preg_quote($DBHOST_TBL_PREFIX).'!', '', $tbl_name);
				$write_func($fp, "\0\0\0\0".$db_name."\n");
				
				$c = uq('SELECT * FROM '.$tbl_name);
				while ($r = db_rowarr($c)) {
					$tmp = '';
					foreach ($r as $v) {
						if ($v === null) {
							$tmp .= 'NULL,';
						} else {
							$tmp .= _esc($v).',';
						}
					}
					/* make sure new lines inside queries don't cause problems */
					if (strpos($tmp, "\n") !== false) {
						$tmp = str_replace("\n", '\n', $tmp);
					}
					$write_func($fp, "(".substr($tmp, 0, -1).")\n");
				}
				unset($c);
			}

			echo "DONE<br />\n";
		}

		$write_func($fp, "\n----SQL_END----\n");

		/* backup GLOBALS.php */
		fud_use('glob.inc', true);
		$skip = array_flip(array('WWW_ROOT','COOKIE_PATH','COOKIE_DOMAIN','COOKIE_NAME',
			'DBHOST','DBHOST_USER','DBHOST_PASSWORD','DBHOST_DBNAME','DBHOST_TBL_PREFIX',
			'ADMIN_EMAIL','DATA_DIR','WWW_ROOT_DISK','INCLUDE','ERROR_PATH',
			'MSG_STORE_DIR','TMP','FILE_STORE','FORUM_SETTINGS_PATH'));
		$vars = array();
		foreach (read_help() as $k => $v) {
			if ($v[1] != NULL || isset($skip[$k])) {
				continue;
			}
			$vars[$k] = $$k;
		}
		$write_func($fp, "\n\$global_vals = ".var_export($vars, 1).";\n");

		if (isset($_POST['compress'])) {
			gzclose($fp);
		} else {
			fclose($fp);
		}

		db_unlock();

		$datadump = $_POST['path'];
		echo '<div align="right">[ <a href="admbrowse.php?down=1&cur='. urlencode(dirname($datadump)) .'&dest='. urlencode(basename($datadump)) .'&'. __adm_rsid .'">Download</a> ] [ <a href="admbrowse.php?cur='. urlencode(dirname($datadump)) .'&'. __adm_rsid .'">Open Directory</a> ]</div>';
		echo '<div class="tutor">The backup process is complete! The dump file can be found at: <b>'.$datadump.'</b>. It is occupying '.filesize($_POST['path']).' bytes.</div><br />';
	} else {
		$gz = extension_loaded('zlib');
		if (!isset($path_error)) {
			$path = $TMP.'FUDforum_'.strftime('%d_%m_%Y_%I_%M', __request_timestamp__).'.fud';
			if ($gz) {
				$path .= '.gz';
				$compress = ' checked="checked"';
			} else {
				$compress = '';
			}
			$path_error = '';
		} else {
			$compress = isset($_POST['compress']) ? ' selected="selected"' : '';
			$path = $_POST['path'];
		}
?>
<h2>Forum Backup</h2>
<form method="post" action="admdump.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
	<td>Backup Save Path:<br /><font size="-1">Path on the disk, where you wish the forum data dump to be saved.</font></td>
	<td><?php echo $path_error; ?><input type="text" value="<?php echo $path; ?>" name="path" size="40" /></td>
</tr>
<?php if($gz) { ?>
<tr class="field">
	<td>Use Gzip Compression:<br /><font size="-1">Compress the backup file using Gzip compression. This will make the backup process a little slower, but will save a lot of harddrive space.</font></td>
	<td><label><input type="checkbox" name="compress" value="1" <?php echo $compress; ?> /> Yes</label></td>
</tr>
<?php } ?>
<tr class="field">
        <td>Skip Search Index:<br /><font size="-1">Do not backup search data. You will need to reindex your forum after doing an import.
        <td><label><input type="checkbox" value="y" name="skipsearch" /> Yes</label></td>
</tr>
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Take Backup" /><input type="hidden" name="submitted" value="1" /></td></tr>
</table>
</form>
<?php
	} /* isset($_POST['submitted']) */

	require($WWW_ROOT_DISK . 'adm/admclose.html');
?>
