<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admdump.php,v 1.47 2004/10/06 17:43:09 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

	@set_time_limit(6000);

function make_insrt_qry($obj, $tbl, $field_data)
{
	$vl = $kv = '';

	foreach($obj as $k => $v) {
		if (!isset($field_data[$k])) {
			continue;
		}

		switch (strtolower($field_data[$k]['type'])) {
			case 'string':
			case 'blob':
			case 'text':
			case 'date':
			case 'varchar':
				if (empty($v) && !$field_data[$k]['not_null']) {
					$vl .= 'NULL,';
				} else {
					$vl .= "'".str_replace("\n", '\n', str_replace("\t", '\t', str_replace("\r", '\r', addslashes($v))))."',";
				}
				break;
			default:
				if (empty($v) && !$field_data[$k]['not_null']) {
					$vl .= 'NULL,';
				} else {
					$vl .= $v.',';
				}
		}

		$kv .= $field_data[$k]['name'].',';
	}

	return 'INSERT INTO '.$tbl.' ('.substr($kv, 0, -1).') VALUES('.substr($vl, 0, -1).')';
}

function backup_dir($dirp, $fp, $write_func, $keep_dir)
{
	global $BUF_SIZE;

	$dirs = array(realpath($dirp));
	$repl = realpath($keep_dir) . '/';
	
	while (list(,$v) = each($dirs)) {
		if (!is_readable($v)) {
			echo 'Could not open "'.$v.'" for reading<br>';
			return;
		}
		echo 'Processing directory: '.$v.'<br>';

		if (!($files = glob($v . '/{.htaccess,*}', GLOB_BRACE))) {
			continue;
		}
		
		$dpath = str_replace($repl, '', $v) . '/';
		
		foreach ($files as $f) {
			if (is_link($f)) {
				continue;
			}
			$name = basename($f);

			if (is_dir($f)) {
				if ($name != 'tmp' && $name != 'theme') {
					$dirs[] = $f;
				}
				continue;
			}
			if ($name == 'GLOBALS.php') {
				continue;
			}
			if (!@is_readable($f)) {
				echo "WARNING: unable to open '".$f."' for reading<br>\n";
				break;
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

function sql_num_fields($r)
{
	 return __dbtype__ == 'pgsql' ? pg_num_fields($r) : mysql_num_fields($r);
}

function sql_field_type($r,$n)
{
	return __dbtype__ == 'pgsql' ? pg_field_type($r, $n) : mysql_field_type($r, $n);
}

function sql_field_name($r, $n)
{
	return __dbtype__ == 'pgsql' ? pg_field_name($r, $n) : mysql_field_name($r, $n);
}

function sql_is_null($r, $n, $tbl='')
{
	if (__dbtype__ == 'pgsql') {
		$res = q_singleval("select a.attnotnull from pg_class c, pg_attribute a WHERE c.relname = '".$tbl."' AND a.attname = '".sql_field_name($r, $n)."' AND a.attnum > 0 AND a.attrelid = c.oid");
		return ($res == 't' ? true : false);
	} else {
		return (strpos(mysql_field_flags($r, $n), 'not_null') !== false) ? true : false;
	}
}

	require('./GLOBALS.php');
	fud_use('db.inc');
	fud_use('mem_limit.inc', true);
	// uncomment the lines below if you wish to run this script via command line
	// fud_use('adm_cli.inc', 1); // this contains cli_execute() function.
	// when using this the script accepts 2 arguments
	// php admdump.php /path/to/dump_file [compress]
	// compress is optional and should only be specified if you want to datadump to be compressed

	/* check for cli arguments */
	if (defined('forum_debug')) {
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
		if (!q_singleval('SELECT id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WHERE login=\''.addslashes($_SERVER['PHP_AUTH_USER']).'\' AND passwd=\''.md5($_SERVER['PHP_AUTH_PW']).'\' AND users_opt>=1048576 AND (users_opt & 1048576) > 0')) {
			header('WWW-Authenticate: Basic realm="Private"');
			header('HTTP/1.0 401 Unauthorized');
			exit('Authorization Required.');
		}
	} else {
		fud_use('adm.inc', true);
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	if (isset($_POST['submitted']) && !@fopen($_POST['path'], 'w')) {
		$path_error = '<font color="#ff0000">Couldn\'t open backup destination file, '.$_POST['path'].' for write.</font><br>';
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

		echo "Compressing forum datafiles<br>\n";
		$write_func($fp, "\n----FILES_START----\n");
		backup_dir(realpath($DATA_DIR), $fp, $write_func, 'DATA_DIR');
		if ($DATA_DIR != $WWW_ROOT_DISK) {
			backup_dir(realpath($WWW_ROOT_DISK.'images/'), $fp, $write_func, 'WWW_ROOT_DISK');
		}
		$write_func($fp, "\n----FILES_END----\n");

		$write_func($fp, "\n----SQL_START----\n");

		/* read sql table defenitions */
		
		if (!($files = glob($DATA_DIR . 'sql/*.tbl'))) {
			exit('Failed to open SQL directory "'.$DATA_DIR.'sql/"');
		}
		foreach ($files as $f) {
			$sql_data = file_get_contents($f);
			$sql_data = preg_replace("!\#.*?\n!s", "\n", $sql_data);
			$sql_data = preg_replace("!\s+!s", " ", $sql_data);
			$sql_data = str_replace(";", "\n", $sql_data);
			$sql_data = str_replace("\r", "", $sql_data);
			$write_func($fp, $sql_data . "\n");
		}

		$sql_table_list = get_fud_table_list();
		db_lock(implode(' WRITE, ', $sql_table_list) . ' WRITE');

		foreach($sql_table_list as $tbl_name) {
			/* not needed, will be rebuilt by consistency checker */
			if ($tbl_name == $DBHOST_TBL_PREFIX . 'thread_view' || $tbl_name == $DBHOST_TBL_PREFIX . 'ses') {
				continue;
			}
			$num_entries = q_singleval('SELECT count(*) FROM '.$tbl_name);

			echo 'Processing table: '.$tbl_name.' ('.$num_entries.') .... ';
			if ($num_entries) {
				$db_name = preg_replace('!^'.preg_quote($DBHOST_TBL_PREFIX).'!', '{SQL_TABLE_PREFIX}', $tbl_name);
				// get field defenitions
				$r = q('SELECT * FROM '.$tbl_name.' LIMIT 1');
				$nf = sql_num_fields($r);
				for ($i = 0; $i < $nf; $i++) {
					$field_data[sql_field_name($r, $i)] = array('name' => sql_field_name($r, $i), 'type' => sql_field_type($r, $i), 'not_null' => sql_is_null($r, $i, $tbl_name));
				}
				$c = uq('SELECT * FROM '.$tbl_name);
				while ($r = db_rowobj($c)) {
					$write_func($fp, make_insrt_qry($r, $db_name, $field_data)."\n");
				}
			}

			echo "DONE<br>\n";
		}

		$write_func($fp, "\n----SQL_END----\n");

		if (isset($_POST['compress'])) {
			gzclose($fp);
		} else {
			fclose($fp);
		}

		db_unlock();

		echo "Backup Process is Complete<br>";
		echo "Backup file can be found at: <b>".$_POST['path']."</b>, it is occupying ".filesize($_POST['path'])." bytes<br>\n";
	} else {
		$gz = extension_loaded('zlib');
		if (!isset($path_error)) {
			$path = $TMP.'FUDforum_'.strftime('%d_%m_%Y_%I_%M', __request_timestamp__).'.fud';
			if ($gz) {
				$path .= '.gz';
				$compress = ' checked';
			} else {
				$compress = '';
			}
			$path_error = '';
		} else {
			$compress = isset($_POST['compress']) ? ' checked' : '';
			$path = $_POST['path'];
		}
?>
<h2>FUDforum Backup</h2>
<form method="post" action="admdump.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
<tr class="field">
	<td>Backup Save Path<br><font size="-1">path on the disk, where you wish the forum data dump to be saved.</font></td>
	<td><?php echo $path_error; ?><input type="text" value="<?php echo $path; ?>" name="path" size=40></td>
</tr>
<?php if($gz) { ?>
<tr class="field">
	<td>Use Gzip Compression<br><font size="-1">if you choose this option, the backup files will be compressed using Gzip compression. This may make the backup process a little slower, but will save a lot of harddrive space.</font></td>
	<td><input type="checkbox" name="compress" value="1" <?php echo $compress; ?>> Yes</td>
</tr>
<?php } ?>
<tr class="fieldaction"><td colspan=2 align=right><input type="submit" name="btn_submit" value="Make Backup"></td></tr>
<input type="hidden" name="submitted" value="1">
</form>
</table>
<?php
	} /* isset($_POST['submitted']) */

	require($WWW_ROOT_DISK . 'adm/admclose.html');
?>
