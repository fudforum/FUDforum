<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admimport.php,v 1.12 2002/08/19 09:17:08 hackie Exp $
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
	fud_use('adm.inc', TRUE);
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

		/* Process the SQL Code */
		$end = strpos($data, "\n----SQL_END----\n");
	
	/* Handle backed up files */
		echo "Commencing restore of data files<br>\n";
		flush();
		
		$start = $end + strlen("\n----SQL_END----\n");
		$pos = $start-1;
	
		while( $pos ) {
			if( !($st = strpos($data, "\n//", $pos)) ) break;
		
			$st += 3;
			$en = strpos($data, "\n", $st);
			
			list($file,$path,$size,) = explode('//', ($tmp=substr($data, $st, $en-$st)));
			$st = $en+1;
		
			$path = resolve_dest_path($path);
			if( !@is_dir($path) ) {
				if( !@mkdir($path, 0700) ) {
					echo "ERROR: Directory '$path' does not exist and the import script was unable to create it<br>\n";
					flush();
				}	
			}
			
			$file_path = $path."/".$file;
			
			if( ($fp = @fopen($file_path, "wb")) ) { 
				$write_data = substr($data, $st, $size);
			
				if( strlen($write_data) != $size ) {
					echo "ERROR: Size mismatch on $file_path<br>\n";
					flush();
				}
				
				if( fwrite($fp, $write_data) != $size ) {
					echo "ERROR: Could not write $size bytes to $file_path<br>\n";
					flush();	
				}
				fclose($fp);
				@chmod($file_path, 0600);
			}
			else {
				echo "Unable to open $file_path for write<br>\n";
				flush();
			}
			$pos = $st+$size;
		}
	
		$start = strpos($data, "\n----SQL_START----\n")+strlen("\n----SQL_START----\n");
		$pos = $start;
		$line_end = $i = 0;
	
	// deal with SQL data
		// In the event of drop all exting tables
		$tbl_list = get_fud_table_list();
		foreach($tbl_list as $v) q("DROP TABLE $v");
		
		/* If we are dealing with pgSQL drop all sequences too */
		if( __dbtype__ == 'pgsql' ) {
			$r = q("SELECT relname from pg_class where relkind='S' AND relname ~ '^".$GLOBALS['DBHOST_TBL_PREFIX']."'");
			while( list($v) = db_rowarr($r) ) q("drop sequence $v");
			qf($r);
		}
		
		/* check if we are changing db structure */
		preg_match("!define\('__dbtype__', '([a-z]*?)'\);!", filetomem($GLOBALS['DATA_DIR'].'src/db.inc.t'), $tmp);
		$db_s_c = ( $tmp[1] == __dbtype__ ) ? 0 : 1;
		
		/* if we are chaning db structure,import it from files */
		if( $db_s_c ) {
			if( !($dp = opendir($GLOBALS['DATA_DIR'].'sql/'.__dbtype__)) ) {
				exit("Couldn't open ".$GLOBALS['DATA_DIR'].'sql/'.$tmp[1]." directory<br>\n");
			}
			readdir($dp); readdir($dp); 
			while( $file = readdir($dp) ) {
				if( substr($file, -4) != '.tbl' ) continue;
				
				$tbl_data = filetomem($GLOBALS['DATA_DIR'].'sql/'.__dbtype__.'/'.$file);
				$tbl_data = preg_replace("!#.*?\n!", "", $tbl_data);
				$tbl_data = preg_replace("!\s+!", " ", trim($tbl_data));
				$tmp = explode(";", str_replace('{SQL_TABLE_PREFIX}', $DBHOST_TBL_PREFIX, $tbl_data));
				foreach($tmp as $qry) {
					if( trim($qry) ) {
						q(trim($qry));
					}	
				}
			}
			closedir($dp);
		}
		
		echo "Commencing restore of SQL data<br>\n";
		flush();
		while( $pos && $pos<$end ) {
			$lend = strpos($data, "\n", $pos);
			$qry = trim(substr($data, $pos, $lend-$pos));

			if( $qry ) {
				if( !$db_s_c || !strncasecmp("INSERT INTO ", $qry, 12) ) {
					q(str_replace('{SQL_TABLE_PREFIX}', $DBHOST_TBL_PREFIX, $qry));
					$i++;
				}	
			}	
		
			$pos = $lend+1;
		
			if( $i && !($i%10000) ) {
				echo "Processed ".$i." queries<br>\n";
				flush();
			}
		}

	// restore user from db is login names match
		if( get_id_by_login(addslashes($usr->login)) ) {
			q("DELETE FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."ses WHERE user_id=".$usr->id);
			q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."ses (ses_id,user_id,sys_id,time_sec) VALUES('".$ses->ses_id."',".$usr->id.",'".$ses->sys_id."',".__request_timestamp__.")");
		}
		else {
			echo '<font color="#ff0000">Your current login ('.htmlspecialchars($usr->login).') is not found in the imported database.<br>There for you\'ll need to re-login once the import process is complete<br></font>';
			flush();
		}

		
		echo "Recompiling Templates<br>\n";
		flush();
		
		fud_use('compiler.inc', TRUE);
		$r = q("SELECT * FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE enabled='Y'");
		while ( $obj = db_rowobj($r) )
			compile_all($obj->theme, $obj->lang, $obj->name);
		qf($r);
		
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