<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admdump.php,v 1.21 2003/04/30 00:37:37 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
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

	return 'INSERT INTO '.$GLOBALS['DBHOST_TBL_PREFIX'].' ('.substr($kv, 0, -1).') VALUES('.substr($vl, 0, -1).')';
}

function backup_dir($dirp, $fp, $write_func, $keep_dir)
{
	if (!($d = opendir($dirp))) {
		echo 'Could not open "'.$dirp.'" for reading<br>';
		return;
	}
	echo 'Processing directory: '.$dirp.'<br>';
	flush();

	readdir($d); readdir($d);
	$path = $dirp . '/';
	$dpath = str_replace($path, $GLOBALS[$keep_dir], $keep_dir . '/');
	while ($f = readdir($d)) {
		switch (filetype($path . $f)) {
			case 'file':
				if ($f != 'GLOBALS.php') {
					if (!@is_readable($path . $f)) {
						echo "WARNING: unable to open '".$path . $f."' for reading<br>\n";
						break;
					}
					$ln = filesize($path . $f);
					if ($ln < 2000000) {
						$write_func($fp, '||' . $dpath . $f . '||' . $ln . "||\n" . file_get_contents($path . $f) . "\n");
					} else {
						$write_func($fp, '||' . $dpath . $f . '||' . $ln . "||\n");
						$fp2 = fopen($path . $f, 'rb');
						while (($buf = fread($fp2, 2000000))) {
							$write_func($fp, $buf);
						}
						fclose($fp2);
						$write_func($fp, "\n");
					}
				}
				break;
			case 'dir':
				if ($f != 'tmp') {
					backup_dir($path . $f, $fp, $write_func, $keep_dir);
				}
				break;
		}
	}
	closedir($d);	
}

function sql_num_fields($r)
{
	return __dbtype__ == 'pgsql' ? pg_num_fields($r['res']) : mysql_num_fields($r);
}

function sql_field_type($r,$n)
{
	return __dbtype__ == 'pgsql' ? pg_field_type($r['res'], $n) : mysql_field_type($r, $n);
}

function sql_field_name($r, $n)
{
	return __dbtype__ == 'pgsql' ? pg_field_name($r['res'], $n) : mysql_field_name($r, $n);
}

function sql_is_null($r, $n, $tbl='')
{
	if (__dbtype__ == 'pgsql') {
		$res = q_singleval("select a.attnotnull from pg_class c, pg_attribute a WHERE c.relname = '".$tbl."' AND a.attname = '".sql_field_name($r, $n)."' AND a.attnum > 0 AND a.attrelid = c.oid");
		return ($res == 't' ? true : false);
	} else {
		return (strpos(mysql_field_flags($r, $n), 'not_null') !== FALSE) ? true : false;
	}
}

	define('admin_form', 1);
	
	require('GLOBALS.php');
	fud_use('db.inc');
	
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
		if (!q_singleval('SELECT id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WHERE login=\''.addslashes($_SERVER['PHP_AUTH_USER']).'\' AND passwd=\''.md5($_SERVER['PHP_AUTH_PW']).'\' AND is_mod=\'A\'')) {
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
		$_POST['submitted'] = NULL;
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
		flush();
		$write_func($fp, "\n----FILES_START----\n");
		backup_dir(realpath($DATA_DIR), $fp, $write_func, 'DATA_DIR');
		if ($DATA_DIR != $WWW_ROOT_DISK) {
			backup_dir(realpath($WWW_ROOT_DISK.'images/'), $fp, $write_func, 'WWW_ROOT_DISK');
		}
		$write_func($fp, "\n----FILES_END----\n");
	
		$write_func($fp, "\n----SQL_START----\n");
	
		/* read sql table defenitions */
		$path = $DATA_DIR . 'sql/' . __dbtype__;
		if (!($d = opendir($path))) {
			exit('Failed to open SQL directory "'.$path.'"');
		}
		readdir($d); readdir($d);
		while ($f = readdir($d)) {
			if (substr($f, -4) != '.tbl') {
				continue;
			}
			$sql_data = file_get_contents($path . '/'. $f);
			$sql_data = preg_replace("!\#.*?\n!s", "\n", $sql_data);
			$sql_data = preg_replace("!\s+!s", " ", $sql_data);
			$sql_data = str_replace(";", "\n", $sql_data);
			$sql_data = str_replace("\r", "", $sql_data);
			$write_func($fp, $sql_data . "\n");
		}
		closedir($d);
		
		$sql_table_list = get_fud_table_list();
		db_lock(implode(' WRITE, ', $sql_table_list) . ' WRITE');

		foreach($sql_table_list as $tbl_name) {
			$num_entries = q_singleval('SELECT count(*) FROM '.$tbl_name);
		
			echo 'Processing table: '.$tbl_name.' ('.$num_entries.') .... ';
			flush();
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
				qf($c);
			}

			echo "DONE<br>\n";
			flush();
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
		$gz = function_exists('gzopen');
		if (!isset($path_error)) {
			$path = $TMP.'FUDforum_'.strftime('%d_%m_%Y-%I:%M', __request_timestamp__).'.fud';
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
<table border=0 cellspacing=1 cellpadding=3>
<form method="post" action="admdump.php">
<?php echo _hs; ?>
<tr bgcolor="#bff8ff">
	<td>Backup Save Path<br><font size="-1">path on the disk, where you wish the forum data dump to be saved.</font></td>
	<td><?php echo $path_error; ?><input type="text" value="<?php echo $path; ?>" name="path" size=40></td>
</tr>
<?php if($gz) { ?>
<tr bgcolor="#bff8ff">
	<td>Use Gzip Compression<br><font size="-1">if you choose this option, the backup files will be compressed using Gzip compression. This may make the backup process a little slower, but will save a lot of harddrive space.</font></td>
	<td><input type="checkbox" name="compress" value="1" <?php echo $compress; ?>> Yes</td>
</tr>
<?php } ?>
<tr bgcolor="#bff8ff"><td colspan=2 align=right><input type="submit" name="btn_submit" value="Make Backup"></td></tr>	
<input type="hidden" name="submitted" value="1">
</form>
</table>
<?php	
	} /* isset($_POST['submitted']) */

	require($WWW_ROOT_DISK . 'adm/admclose.html');		
?>