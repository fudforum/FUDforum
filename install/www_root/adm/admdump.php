<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admdump.php,v 1.2 2002/06/18 14:20:24 hackie Exp $
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
	fud_use('static/adm.inc');
	
	list($ses, $usr) = initadm();

function make_insrt_qry($obj, $tbl, $field_data)
{
	$vl = $kv = '';

	while( list($k,$v) = each($obj) ) {
		switch ( strtolower($field_data[$k]->type) )
		{
			case 'string':
			case 'blob':
			case 'text':
			case 'date':
				if( empty($v) && !$field_data[$k]->not_null ) 
					$vl .= 'NULL,';
				else
					$vl .= '"'.str_replace("\n", '\n', str_replace("\t", '\t', str_replace("\r", '\r', addslashes($v)))).'",';
				break;
			default:
				if( empty($v) && !$field_data[$k]->not_null ) 
					$vl .= 'NULL,';
				else
					$vl .= $v.',';
		}
		
		$kv .= $k.',';
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
	
	$opendir = empty($keep_dir) ? $dirp : realpath($keep_dir.'/'.$dirp);
	if( !@chdir($opendir) ) {
		echo "warning no perms to open $opendir\n";
		return;
	}
	
	$dir = opendir('.');
	readdir($dir); readdir($dir);
	while( $file = readdir($dir) ) {
		if( @is_dir($file) ) backup_dir($file, $key, $dirp);
		if( !@is_file($file) ) continue;
		
		$file_data = filetomem($file);
		$file_ln = strlen($file_data);
		
		if( empty($keep_dir) )
			$write_func($fp, '//'.$file.'//'.$key.'//'.$file_ln."//\n".$file_data."\n");
		else
			$write_func($fp, '//'.$file.'//'.$key.$dirp.'///'.$file_ln."//\n".$file_data."\n");
	}
	closedir($dir);
	chdir($cur_dir);
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
		chdir(realpath($INCLUDE.'../sql/'));
		$dir = opendir('.');
		readdir($dir); readdir($dir);
		while( $file = readdir($dir) ) {
			if( !@is_dir($file) && substr($file,-4)=='.tbl' ) {
				$sql_data = filetomem($file);
				$sql_data = preg_replace("!\#.*?\n!s", "\n", $sql_data);
				$sql_data = preg_replace("!\s+!s", " ", $sql_data);
				$sql_data = str_replace("{SQL_TABLE_PREFIX}", $MYSQL_TBL_PREFIX, $sql_data);
				$sql_data = str_replace(";", "\n", $sql_data);
				$sql_data = str_replace("\r", "", $sql_data);
				$write_func($fp, $sql_data."\n");
			}
		}
		closedir($dir);
		chdir($curdir);
	
		$r = Q("show tables");
		$prefix_len = strlen($MYSQL_TBL_PREFIX);
		
		while ( list($tbl_name) = DB_ROWARR($r) ) {
			if( substr($tbl_name, 0, $prefix_len) != $MYSQL_TBL_PREFIX ) continue;
			$locklist .= $tbl_name.'+,';
		}
		DB_SEEK($r, 0);
		$locklist = substr($locklist, 0, -1);
		DB_LOCK($locklist);

		while( list($tbl_name) = DB_ROWARR($r) ) {
			if( substr($tbl_name, 0, $prefix_len) != $MYSQL_TBL_PREFIX ) continue;
		
			echo "Processing table: $tbl_name .... ";
			flush();
			
			$r2 = Q("SELECT * FROM ".$tbl_name);
			if( DB_COUNT($r2) ) {
				$field_data = array();
				for( $i=0; $i<mysql_num_fields($r2); $i++ ) {
					$field_info = mysql_fetch_field($r2);
					$field_data[$field_info->name] = $field_info;
				}
				while( $obj = mysql_fetch_object($r2, MYSQL_ASSOC) ) $write_func($fp, make_insrt_qry($obj, $tbl_name, $field_data)."\n");
			}	
			QF($r2);
			echo "DONE<br>\n";
			flush();
		}
		QF($r);

		$write_func($fp, "\n----SQL_END----\n");
	
		echo "Compressing forum datafiles<br>\n";
		flush();
		backup_dir($DATA_DIR, 'DATA_DIR');
		backup_dir($WWW_ROOT_DISK.'images/', 'IMG_ROOT_DISK');
	
		if( $HTTP_POST_VARS['compress'] ) 
			gzclose($fp);
		else
			fclose($fp);
		
		DB_UNLOCK();
		
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