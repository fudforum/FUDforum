<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admimport.php,v 1.3 2002/06/18 18:26:10 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('admin_form', 1);

	@set_time_limit(6000);
	@ini_set("memory_limit", "100M");
	
	include_once "GLOBALS.php";
	fud_use('db.inc');
	fud_use('static/adm.inc');
	fud_use('users_reg.inc');
	
	list($ses, $usr) = initadm();

function gzfiletomem($fn)
{
	$data = '';
        $fp = gzopen($fn, 'rb');
	while( !gzeof($fp) ) $data .= gzread($fp, 16384);
	gzclose($fp);
	
	return $data;
}	

function read_dump($file)
{
	if( !preg_match('!\.gz$!', $file) ) 
		return filetomem($file);
	else 
		return gzfiletomem($file);
}

function resolve_dest_path($path)
{
	$path = str_replace('IMG_ROOT_DISK', $GLOBALS['WWW_ROOT_DISK'].'images/', $path);
	$path = str_replace('DATA_DIR', $GLOBALS['DATA_DIR'], $path);
	
	return $path;
}

include('admpanel.php'); 

	if( $path && !@is_readable($path) ) {
		if( !@file_exists($path) ) 
			$path_error = '<font color="#ff0000"><b>'.$path.'</b> file does not exist.</font><br>';
		else
			$path_error = '<font color="#ff0000">the webserver has no permission to open <b>'.$path.'</b> for reading</font><br>';
	}
	else if( preg_match('!\.gz$!', $path) && !function_exists("gzopen") ) 
		$path_error = '<font color="#ff0000">The file <b>'.$path.'</b> is compressed using gzip & your PHP does not have gzip extension install. Please decompress the file yourself and try again.</font><br>';

	if( !$path_error && $path ) {
		$data = read_dump($path);	

		/* Process the MySQL Code */
		$start = strpos($data, "\n----SQL_START----\n")+strlen("\n----SQL_START----\n");
		$end = strpos($data, "\n----SQL_END----\n");
	
		$pos = $start;
		$line_end = $i = 0;
	
	/* deal with mySQL data */
		echo "Commencing restore of SQL data<br>\n";
		flush();
		while( $pos && $pos<$end ) {
			$lend = strpos($data, "\n", $pos);
			$qry = trim(substr($data, $pos, $lend-$pos));

			if( $qry ) {
				q($qry);
				$i++;
			}	
		
			$pos = $lend+1;
		
			if( !($i%10000) ) {
				echo "Processed ".$i." queries<br>\n";
				flush();
			}
		}

	/* restore user from db is login names match */
		if( get_id_by_login(addslashes($usr->login)) ) {
			q("DELETE FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."ses WHERE user_id=".$usr->id);
			q("INSERT INTO ".$GLOBALS['MYSQL_TBL_PREFIX']."ses (ses_id,user_id,sys_id,time_sec) VALUES('".$ses->ses_id."',".$usr->id.",'".$ses->sys_id."',".__request_timestamp__.")");
		}
		else {
			echo '<font color="#ff0000">Your current login ('.htmlspecialchars($usr->login).') is not found in the imported database.<br>There for you\'ll need to re-login once the import process is complete<br></font>';
			flush();
		}

	/* Handle backed up files */
		echo "Commencing restore of data files<br>\n";
		flush();
		
		$start = $end + strlen("\n----SQL_END----\n");
		$pos = $start-1;
	
		while( $pos ) {
			if( !($st = strpos($data, "\n//", $pos)) ) break;
		
			$st += 3;
			$en = strpos($data, "\n", $st);
			list($file,$path,$size,) = explode('//', substr($data, $st, $en-$st));
			$st = $en+1;
		
			$path = resolve_dest_path($path);
			if( !@is_dir($path) ) mkdir($path, 0755);
			$file_path = str_replace("//", "/", ($path.'/'.$file));
		
			$fp = fopen($file_path, "wb");
			fwrite($fp, substr($data, $st, $size));
			fclose($fp);
			@chmod($file_path, 0600);
		
			$pos = $st+$size;
		}
		
		echo "<b>Import process is now complete</b><br>\n";
	}
	else {
?>
<h2><font color="#ff0000">Please note that import process will REMOVE ALL current forum data and replace it with the one from the file you enter.</font></h2>
<table border=0 cellspacing=1 cellpadding=3>
<form method="post" action="admimport.php">
<?php echo _hs; ?>
<tr bgcolor="#bff8ff">
	<td>Import Data Path<br><font size="-1">location on the drive, where the file your wish to import FUDforum data from is located.</font></td>
	<td><?php echo $path_error; ?><input type="text" value="<?php echo $path; ?>" name="path" size=40></td>
</tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=right><input type="submit" name="btn_submit" value="Import Data"></td></tr>	
</form>
</table>
<?php 	
	}
	readfile('admclose.html');	
?>