<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admdump.php,v 1.15 2002/09/18 20:52:08 hackie Exp $
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
	@ini_set("memory_limit", "100M");

	define('admin_form', 1);
	
	include_once "GLOBALS.php";
	fud_use('db.inc');
	
	/* 
	 * Check for HTTP AUTH, before going for the usual cookie/session auth 
	 * this is done to allow for easier running of this process via an 
	 * automated cronjob.
	 */

	if( !empty($HTTP_GET_VARS['do_http_auth']) && empty($HTTP_SERVER_VARS['PHP_AUTH_USER']) ) {
		header('WWW-Authenticate: Basic realm="Private"');
		header('HTTP/1.0 401 Unauthorized');
		exit('Authorization Required.');
	}
	
	if( !empty($HTTP_SERVER_VARS['PHP_AUTH_USER']) && !empty($HTTP_SERVER_VARS['PHP_AUTH_PW']) ) {
		if( !bq("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE login='".$HTTP_SERVER_VARS['PHP_AUTH_USER']."' AND passwd='".md5($HTTP_SERVER_VARS['PHP_AUTH_PW'])."' AND is_mod='A'") ) {
			header('WWW-Authenticate: Basic realm="Private"');
			header('HTTP/1.0 401 Unauthorized');
			exit('Authorization Required.');
		}
		define('shell_script', 1);
		define('_hs', '');
		fud_use('adm.inc', true);
	}
	else {
		fud_use('adm.inc', true);
		list($ses, $usr) = initadm();
	}	

function make_insrt_qry($obj, $tbl, $field_data)
{
	$vl = $kv = '';
	
	foreach($obj as $k => $v) {
		if( !isset($field_data[$k]) ) continue;
	
		switch ( strtolower($field_data[$k]['type']) )
		{
			case 'string':
			case 'blob':
			case 'text':
			case 'date':
			case 'varchar':
				if( empty($v) && !$field_data[$k]['not_null'] ) 
					$vl .= 'NULL,';
				else
					$vl .= "'".str_replace("\n", '\n', str_replace("\t", '\t', str_replace("\r", '\r', addslashes($v))))."',";
				break;
			default:
				if( empty($v) && !$field_data[$k]['not_null'] ) 
					$vl .= 'NULL,';
				else
					$vl .= $v.',';
		}
		
		$kv .= $field_data[$k]['name'].',';
	}	
	$vl = substr($vl, 0, -1);
	$kv = substr($kv, 0, -1);
	
	return "INSERT INTO ".$tbl." (".$kv.") VALUES(".$vl.")";
}

function backup_dir($dirp, $key, $keep_dir='')
{
	global $fp;
	global $write_func;

	$cur_dir = getcwd();
	
	$opendir = $dirp;
	
	if( !@chdir($opendir) ) {
		echo "warning no perms to open '$opendir'<br>\n";
		return;
	}
	
	$dir = opendir('.');
	readdir($dir); readdir($dir);
	while( $file = readdir($dir) ) {
		if( @is_dir($file) ) {
			if( $file == 'tmp' ) continue;
			backup_dir($file, $key, $dirp);
		}	
		
		if( !@is_file($file) || $file == 'GLOBALS.php' ) continue;

		if( !@is_readable($file) ) {
			echo "WARNING: unable to open '".realpath($file)."' for reading<br>\n";
			continue;
		}
		$file_data = filetomem($file);
		$file_ln = strlen($file_data);
		
		$curdir = str_replace(substr($GLOBALS[$key],0, -1), '', getcwd());
		
		if( empty($keep_dir) )
			$write_func($fp, '//'.$file.'//'.$key.'//'.$file_ln."//\n".$file_data."\n");
		else
			$write_func($fp, '//'.$file.'//'.$key.$curdir.'//'.$file_ln."//\n".$file_data."\n");
	}
	closedir($dir);
	chdir($cur_dir);
}

function sql_num_fields($r)
{
	if( __dbtype__ == 'pgsql' ) 
		return pg_num_fields($r['res']);
	else
		return mysql_num_fields($r);
}

function sql_field_type($r,$n)
{
	if( __dbtype__ == 'pgsql' ) 
		return pg_field_type($r['res'], $n);
	else
		return mysql_field_type($r, $n);
}


function sql_field_name($r, $n)
{
	if( __dbtype__ == 'pgsql' ) 
		return pg_field_name($r['res'],$n);
	else
		return mysql_field_name($r, $n);
}

function sql_is_null($r, $n, $tbl='')
{
	if( __dbtype__ == 'pgsql' ) {
		$res = q_singleval("select a.attnotnull from pg_class c, pg_attribute a WHERE c.relname = '".$tbl."' AND a.attname = '".sql_field_name($r, $n)."' AND a.attnum > 0 AND a.attrelid = c.oid");
		return ( $res == 't' ? true : false );
	}
	else 
		return ( strstr(mysql_field_flags($r,$n), 'not_null') ? true : false );
}

include('admpanel.php'); 
	
	if( $HTTP_POST_VARS['submitted'] && !@fopen($HTTP_POST_VARS['path'], 'wb') ) {
		$path_error = '<font color="#ff0000">Couldn\'t open backup destination file, '.$HTTP_POST_VARS['path'].' for write.</font><br>';
		$HTTP_POST_VARS['submitted'] = '';
	}

	if( $HTTP_POST_VARS['submitted'] ) {
		if( $HTTP_POST_VARS['compress'] ) {
			if( !$fp = gzopen($HTTP_POST_VARS['path'], "wb9")) exit("cannot create file");
			$write_func = 'gzwrite';
		}
		else { 
			if( !$fp = fopen($HTTP_POST_VARS['path'], "wb")) exit("cannot create file");
			$write_func = 'fwrite';
		}	
	
		$write_func($fp, "\n----SQL_START----\n");
	
		$curdir = getcwd();
		chdir(realpath($INCLUDE.'../sql/'.__dbtype__.'/'));
		$dir = opendir('.');
		readdir($dir); readdir($dir);
		while( $file = readdir($dir) ) {
			if( !@is_file($file) ) continue;
		
			if( substr($file,-4)=='.tbl' ) {
				$sql_data = filetomem($file);
				$sql_data = preg_replace("!\#.*?\n!s", "\n", $sql_data);
				$sql_data = preg_replace("!\s+!s", " ", $sql_data);
				$sql_data = str_replace(";", "\n", $sql_data);
				$sql_data = str_replace("\r", "", $sql_data);
				$write_func($fp, $sql_data."\n");
			}
			else if ( substr($file,-5)=='.func' ) 
				$write_func($fp, preg_replace("!(\n|\t)!", " ", filetomem($file))."\n");
		}
		closedir($dir);
		chdir($curdir);
		
		$sql_table_list = get_fud_table_list();
		
		foreach($sql_table_list as $tbl_name) $locklist .= $tbl_name.'+,';
		$locklist = substr($locklist, 0, -1);
		db_lock($locklist);

		foreach($sql_table_list as $tbl_name) {
			echo "Processing table: $tbl_name .... ";
			flush();
			
			$num_entries = q_singleval("SELECT count(*) FROM ".$tbl_name);
			$start = 0;
			$limit = 100000;
			$db_name = preg_replace('!^'.preg_quote($DBHOST_TBL_PREFIX).'!', '{SQL_TABLE_PREFIX}', $tbl_name);
			$field_data = array();
			
			if( $num_entries ) {
				$r2 = q("SELECT * FROM ".$tbl_name." LIMIT 1");
				$nf = sql_num_fields($r2);
				for( $i=0; $i<$nf; $i++ ) {
					$field_data[sql_field_name($r2, $i)] = array(
						'name' => sql_field_name($r2, $i),
						'type' => sql_field_type($r2, $i),
						'not_null' => sql_is_null($r2, $i, $tbl_name)
					);	
				}
			}
			
			while ($start<$num_entries) {
				$r2 = q("SELECT * FROM ".$tbl_name." LIMIT ".qry_limit($limit, $start));
				while( $obj = db_rowobj($r2) ) $write_func($fp, make_insrt_qry($obj, $tbl_name, $field_data)."\n");
				qf($r2);
				$start += $limit;
			}			
			
			echo "DONE<br>\n";
			flush();
		}

		$write_func($fp, "\n----SQL_END----\n");
	
		echo "Compressing forum datafiles<br>\n";
		flush();
		backup_dir($DATA_DIR, 'DATA_DIR');
		if( $DATA_DIR != $WWW_ROOT_DISK ) backup_dir($WWW_ROOT_DISK.'images/', 'IMG_ROOT_DISK');
	
		if( $HTTP_POST_VARS['compress'] ) 
			gzclose($fp);
		else
			fclose($fp);
		
		db_unlock();
		
		@chmod($HTTP_POST_VARS['path'], 0600);
			
		echo "Backup Process is Complete<br>";
		echo "Backup file can be found at: <b>".$HTTP_POST_VARS['path']."</b>, it is occupying ".filesize($HTTP_POST_VARS['path'])." bytes<br>\n";
	}
	else {
		if( empty($path) ) {
			$path = $GLOBALS['TMP'].'FUDforum_'.strftime("%d_%m_%Y-%I:%M", __request_timestamp__).'.fud';
			if( function_exists("gzopen") ) $path .= '.gz';
		}
		
		if( !empty($checked) || (empty($checked) && empty($submitted) && function_exists("gzopen")) ) 
			$checked = ' checked';
?>
<h2>FUDforum Backup</h2>
<table border=0 cellspacing=1 cellpadding=3>
<form method="post" action="admdump.php">
<?php echo _hs; ?>
<tr bgcolor="#bff8ff">
	<td>Backup Save Path<br><font size="-1">path on the disk, where you wish the forum data dump to be saved.</font></td>
	<td><?php echo $path_error; ?><input type="text" value="<?php echo $path; ?>" name="path" size=40></td>
</tr>
<?php if( function_exists("gzopen") ) { ?>
<tr bgcolor="#bff8ff">
	<td>Use Gzip Compression<br><font size="-1">if you choose this option, the backup files will be compressed using Gzip compression. This may make the backup process a little slower, but will save a lot of harddrive space.</font></td>
	<td><input type="checkbox" name="compress" value="1"<?php echo $checked; ?>> Yes</td>
</tr>
<?php } ?>
<tr bgcolor="#bff8ff"><td colspan=2 align=right><input type="submit" name="btn_submit" value="Make Backup"></td></tr>	
<input type="hidden" name="submitted" value="1">
</form>
</table>
<?php	
	}
readfile('admclose.html');		
?>