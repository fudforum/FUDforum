<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	error_reporting(E_ALL);
	@ini_set('display_errors', 1);
	@ini_set('memory_limit', '256M');
	@set_time_limit(0);

	require('./GLOBALS.php');

	/* Uncomment the line below if you wish to import data without authentication.
	 * This is useful if the previous import had failed resulting in the loss of old SQL data.
	**/
	// define('recovery_mode', 1);

	// Run from command line.
	if (php_sapi_name() == 'cli') {
		if (empty($_SERVER['argv'][1])) {
			echo "Usage: php admimport.php /path/to/dump_file\n";
			die();
		}

		fud_use('adm_cli.inc', 1);
		define('recovery_mode', 1);
		$_POST['path'] = $_SERVER['argv'][1];
		$_POST['submitted'] = 1;
	}

	fud_use('glob.inc', true);
	fud_use('dbadmin.inc', true);	// For creating and dropping tables, indexes, etc.
	if (defined('recovery_mode')) {
		fud_use('db.inc');
	} else {
		fud_use('adm.inc', true);
	}

function resolve_dest_path($path)
{
	$path = str_replace(array('WWW_ROOT_DISK','DATA_DIR'), 
				array($GLOBALS['WWW_ROOT_DISK'], $GLOBALS['DATA_DIR']), $path);
	$dir = dirname($path);
	if (!@is_dir($dir)) {
		while ($dir && $dir != '/' && !@is_dir($dir)) {
			$dirs[] = $dir;
			$dir = dirname($dir);
		}
		$dirs = array_reverse($dirs);
		foreach ($dirs as $d) {
			if (!mkdir($d, 0755)) {
				exit("Failed to create {$d} directory, check file permissions<br />\n");
			}
		}
	}

	return $path;
}

	require($WWW_ROOT_DISK .'adm/header.php');

	if (isset($_POST['path'])) {
		if (!@is_readable($_POST['path'])) {
			if (!@file_exists($path)) {
				$path_error = errorify('<b>'. $_POST['path'] .'</b> file does not exist.');
			} else {
				$path_error = errorify('The webserver has no permission to open <b>'. $_POST['path'] .'</b> for reading');
			}
		} else if (($gz_file = preg_match('!\.gz$!', $_POST['path'])) && !extension_loaded('zlib')) {
			$path_error = errorify('The file <b>'. $_POST['path'] .'</b> is compressed using gzip & your PHP does not have gzip extension install. Please decompress the file yourself and try again.');
		} else {
			if (!$gz_file) {
				$fp     = fopen($_POST['path'], 'rb');
				$getf   = 'fgets';
				$readf  = 'fread';
				$closef = 'fclose';
				$feoff  = 'feof';
			} else {
				$fp     = gzopen($_POST['path'], 'rb');
				$getf   = 'gzgets';
				$readf  = 'gzread';
				$closef = 'gzclose';
				$feoff  = 'gzeof';
			}
			/* Skip to the start of data files. */
			while ($getf($fp, 1024) != "----FILES_START----\n" && !$feoff($fp));

			/* Handle data files. */
			pf('Restore forum files...');
			while (($line = $getf($fp, 1000000)) && $line != "----FILES_END----\n") {
				/* Each file is preceeded by a header ||path||size|| */
				if (strncmp($line, '||', 2)) {
					continue;
				}
				list(,$path,$size,) = explode('||', $line);

				if ($path == 'WWW_ROOT_DISK/adm/admimport.php' ) {
					// Skip admimport.php, don't overwrite the running script.
					continue;
				}

				$path = resolve_dest_path($path);
				if (!($fd = fopen($path, 'wb'))) {
					pf('WARNING: couldn\'t create '. $path);
					if ($readf == 'gzread') {
						gzseek($fp, (gztell($fp) + $size));
					} else {
						fseek($fp, $size, SEEK_CUR);
					}
				} else {
					if ($size < 1) { /* Empty file. */
						continue;
					}
					if ($size < 2000000) {
						fwrite($fd, $readf($fp, $size));
					} else {
						$n_r = floor($size / 2000000);
						$rem = $size - 2000000 * $n_r;
						for ($i = 0; $i < $n_r; $i++) {
							fwrite($fd, $readf($fp, 2000000));
						}
						if ($rem) {
							fwrite($fd, $readf($fp, $rem));
						}
					}
					fclose($fd);
				}
			}

			/* Skip to the start of the SQL code. */
			while ($getf($fp, 1024) != "----SQL_START----\n" && !$feoff($fp));

			/* Drop and recreate tables. */
			pf('Drop and recreate database tables...');
			$tbl = glob($DATA_DIR .'sql/*.tbl', GLOB_NOSORT);
			$idx = array();	// Queue index statements for after load.
			foreach ($tbl as $t) {
				foreach (explode(';', preg_replace('!#.*?\n!s', '', file_get_contents($t))) as $q) {
					$q = trim($q);
					if (preg_match('/^DROP TABLE IF EXISTS (.*)$/si', $q, $m)) {
						drop_table($m[1], true);
					}
					if (preg_match('/^\s*CREATE\s*TABLE\s*([\{\}\w]*)/si', $q, $m)) {
						create_table($q);
					}
					if (preg_match('/^CREATE\s+(UNIQUE)?\s*INDEX (.*) ON (.*) \((.*)\)/is', $q, $m)) {
						$idx[] = $q;	// Speed up inserts, create indexes later.
					}
				}
			}

			/* Start loading the data. */
			$i = $skip = 0;
			$tmp = $pfx = ''; 
			while (($line = $getf($fp, 1000000)) && $line != "----SQL_END----\n") {
				// Reverse formatting applied in admdump.php.
				$line = str_replace('\n', "\n", $line);

				// Change MySQL escapting of single quotes to SQL standard ''.
				$line = str_replace("\\'", "''", $line);

				if (($line = trim($line))) {
					if ($line{0} != '(') {
						if ($tmp && !$skip) {
							q($pfx . substr($tmp, 0, -1));
							$tmp = '';
						}
						if ($i > 0) {
							pf('&nbsp;...'. $i .' rows loaded.');
							$i = 0;
						}
						$tbl = $line;
						$pfx = 'INSERT INTO '. $DBHOST_TBL_PREFIX . $tbl .' VALUES ';
						if (isset($_POST['skipsearch']) && $_POST['skipsearch'] == 'y' && ($tbl == 'index' || $tbl == 'title_index' || $tbl == 'search' || $tbl == 'search_cache')) {
							$skip = 1;
							pf('Skip table '. $DBHOST_TBL_PREFIX . $tbl .'!');
						} else {
							$skip = 0;
							pf('Process table '. $DBHOST_TBL_PREFIX . $tbl .':');
						}
						continue;
					}
					if ($skip) continue;
					if (__dbtype__ != 'mysql') {
						$line = str_replace('\'\'', 'NULL', $line);
						q($pfx . $line);
					} else {
						$tmp .= $line;
						if ($i && !($i % 10000)) {
							q($pfx . $tmp);
							$tmp = '';
						} else {
							$tmp .= ',';
						}
					}

					if ($i && !($i % 10000)) {
						pf('&nbsp;...'. $i .' rows');
					}
					++$i;
				}
			}

			pf('&nbsp;...'. $i .' rows loaded.');
/* STILL NEEDED???
			if ($tmp && !$skip) {
				q($pfx . substr($tmp, 0, -1));
				pf('&nbsp;...'.$pfx . substr($tmp, 0, -1));
				pf($i .' rows loaded.');
				unset($tmp);
			}
*/

			pf('Creating indexes...');
			foreach ($idx as $v) {
				preg_match('/^CREATE\s+(UNIQUE)?\s*INDEX (.*) ON (.*) \((.*)\)/is', $v, $m);
				// Func spec: create_index($tbl, $name, $unique, $flds, $del_dups);
				create_index($m[3], $m[2], (strtoupper($m[1]) == 'UNIQUE') ? true : false, $m[4], false);
			}

			/* Manually reset database sequences for databases like Oracle & PgSQL. */
			reset_fud_sequences();

			/* Handle importing of GLOBAL options. */
			pf('Import GLOBAL settings...');
			eval(trim($readf($fp, 100000))); // Should be enough to read all options in one shot.
			change_global_settings($global_vals);

			/* Try to restore the current admin's account by seeing if he exists in the imported database. */
			if (!defined('recovery_mode')) {
				if (($uid = q_singleval('SELECT id FROM '. $DBHOST_TBL_PREFIX .'users WHERE login=\''. $usr->login .'\' AND users_opt>=1048576 AND '. q_bitand('users_opt', 1048576) .' > 0'))) {
					q('INSERT INTO '. $DBHOST_TBL_PREFIX .'ses (ses_id, sys_id, user_id, time_sec) VALUES(\''. $usr->ses_id .'\', \''. $usr->sys_id .'\', '. $uid .', '. __request_timestamp__ .')');
				} else if (!defined('recovery_mode')) {
					pf( errorify('Your current login ('. htmlspecialchars($usr->login) .') is not found in the imported database.<br />Therefor you\'ll need to re-login once the import process is complete.') );
				}
			}

			pf('Recompiling Templates...');
			fud_use('compiler.inc', true);
			$c = uq('SELECT theme, lang, name FROM '. $DBHOST_TBL_PREFIX .'themes WHERE theme_opt>=1 AND '. q_bitand('theme_opt', 1) .' > 0');
			while ($r = db_rowarr($c)) {
				compile_all($r[0], $r[1], $r[2]);
			}
			unset($c);

			pf('<b>Restore successfully completed.</b><br /><br />');
			if (defined('__adm_rsid')) {
				pf('<div class="tutor">To finalize the process you should now run the <span style="white-space:nowrap">&gt;&gt; <b><a href="consist.php?'. __adm_rsid .'">consistency checker</a></b> &lt;&lt;</span>.</div>');
			} else {
				pf('To finalize the process you should now run the consistency checker.');
			}
			require($WWW_ROOT_DISK .'adm/footer.php');
			exit;
		}
	}
?>
<h2>Import forum data</h2>
<div class="alert">
	The import process will REMOVE ALL forum data (all files and tables with '<?php echo $DBHOST_TBL_PREFIX; ?>' prefix) and replace it with the data in the backup file you enter.
	Remember to <a href="admdump.php?<?php echo __adm_rsid; ?>">BACKUP</a> your data before importing and do not try to import backups taken with old forum versions!
	You can use the <a href="admbrowse.php?cur=<?php echo urlencode($TMP) .'&amp;'. __adm_rsid ?>">File Manager</a> to upload off-site backup files.
</div>

<?php
$datadumps = (glob($TMP .'*.fud*'));
if ($datadumps) {
?>
	<h3>Available datadumps:</h3>
	<table class="resulttable fulltable">
	<thead><tr class="resulttopic">
		<th>File name</th><th>Backup Date</th><th>Action</th>
	</tr></thead>
	<?php foreach ($datadumps as $datadump) { ?>
		<tr class="field admin_fixed">
			<td><?php echo basename($datadump); ?></td>
			<td><?php echo fdate('%d %b %Y %H:%M:%S', filectime($datadump)); ?></td>
			<td> [ <a href="javascript://" onclick="document.admimport.path.value='<?php echo $datadump; ?>';">use</a> ]</td>
		</tr>
	<?php } ?>
	<tr class="resultrow2 tiny"><td colspan="2">&nbsp;</td><td>[ <a href="admbrowse.php?down=1&amp;cur=<?php echo urlencode(dirname($datadump)); ?>&amp;<?php echo __adm_rsid; ?>">Manage backup files</a> ]</td></tr>
	</table>
<?php } /* Datadumps available. */ ?>

<h3>Dump to restore:</h3>
<form method="post" action="admimport.php" id="admimport" name="admimport">
<?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
	<td>Import Data File:<br /><font size="-1">Full path to the backup file (*.fud or *.fud.gz) on disk that you want to import from.</font></td>
	<td><?php if (isset($path_error)) { echo $path_error.'<br />'; $path = $_POST['path']; } else { $path = ''; } ?><input type="text" value="<?php echo $path; ?>" name="path" size="40" /></td>
</tr>
<tr class="field">
	<td>Skip Search Index:<br /><font size="-1">Do not load search data. You will need to reindex your forum after the import.</font></td>
	<td><label><input type="checkbox" value="y" name="skipsearch" /> Yes</label></td>
</tr>
<tr class="fieldaction"><td colspan="2" align="right"><input type="submit" name="btn_submit" value="Import Data" /></td></tr>
</table>
</form>

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
