<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admimport.php,v 1.16 2003/05/26 11:15:04 hackie Exp $
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

	require('./GLOBALS.php');
	fud_use('adm.inc', true);

function resolve_dest_path($path)
{
	return str_replace('WWW_ROOT_DISK', $GLOBALS['WWW_ROOT_DISK'], str_replace('DATA_DIR', $GLOBALS['DATA_DIR'], $path));
}

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 

	if (isset($_POST['path'])) {
		if (!@is_readable($_POST['path'])) {
			if (!@file_exists($path)) {
				$path_error = '<font color="#ff0000"><b>'.$_POST['path'].'</b> file does not exist.</font><br>';
			} else {
				$path_error = '<font color="#ff0000">the webserver has no permission to open <b>'.$_POST['path'].'</b> for reading</font><br>';
			}
		} else if (($gz_file = preg_match('!\.gz$!', $_POST['path'])) && !function_exists('gzopen')) {
			$path_error = '<font color="#ff0000">The file <b>'.$_POST['path'].'</b> is compressed using gzip & your PHP does not have gzip extension install. Please decompress the file yourself and try again.</font><br>';
		} else {
			if (!$gz_file) {
				$fp = fopen($_POST['path'], 'rb');
				$getf = 'fgets';
				$readf = 'fread';
				$closef = 'fclose';
				$feoff = 'feof';
			} else {
				$fp = gzopen($_POST['path'], 'rb');
				$getf = 'gzgets';
				$readf = 'gzread';
				$closef = 'gzclose';
				$feoff = 'gzeof';
			}
			/* skip to the start of data files */
			while ($getf($fp, 1024) != "----FILES_START----\n" && !$feoff($fp));

			/* handle data files */
			while (($line = $getf($fp, 1000000)) && $line != "----FILES_END----\n") {
				/* each file is preceeded by a header ||path||size|| */
				if (strncmp($line, '||', 2)) {
					continue;
				}
				list(,$path,$size,) = explode("||", $line);
				$path = resolve_dest_path($path);
				if (!($fd = fopen($path, 'wb'))) {
					echo "WARNING: couldn't create '".$path."'<br>\n";
					flush();
					if ($readf == 'gzread') {
						gzseek($fp, (gztell($fp) + $size));
					} else {
						fseek($fp, $size, SEEK_CUR);
					}
				} else {
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

			/* skip to the start of the SQL code */
			while ($getf($fp, 1024) != "----SQL_START----\n" && !$feoff($fp));

			/* clear SQL data */
			$tbl_list = get_fud_table_list();
			foreach($tbl_list as $v) {
				q('DROP TABLE '.$v);
			}
			/* If we are dealing with pgSQL drop all sequences too */
			if (__dbtype__ == 'pgsql') {
				$c = q("SELECT relname from pg_class where relkind='S' AND relname ~ '^".$GLOBALS['DBHOST_TBL_PREFIX']."'");
				while($r = db_rowarr($c)) {
					q('drop sequence '.$r[0]);
				}
				qf($c);
			}
			/* It is possible that the database type in the dump != database type in the current forum.
			 * No worries, we can handle that ;), but we need to get table defenitions else where
			 */
			preg_match("!define\('__dbtype__', '(mysql|pgsql)'\);!", file_get_contents($DATA_DIR.'src/db.inc.t'), $tmp);
			if ($tmp[1] != __dbtype__) {
				/* read the table definitions from appropriate SQL directory */
				if (!($d = opendir($DATA_DIR.'sql/'.__dbtype__))) {
					exit("Couldn't open ".$DATA_DIR.'sql/'.$tmp[1]." directory<br>\n");
				}
				readdir($dp); readdir($dp); 
				while ($f = readdir($d)) {
					if (substr($f, -4) != '.tbl') {
						continue;
					}
					$tbl_data = file_get_contents($DATA_DIR.'sql/'.__dbtype__.'/'.$f);
					$tbl_data = preg_replace("!#.*?\n!", '', $tbl_data);
					$tbl_data = preg_replace('!\s+!', ' ', trim($tbl_data));
					$tmp = explode(';', str_replace('{SQL_TABLE_PREFIX}', $DBHOST_TBL_PREFIX, $tbl_data));
					foreach($tmp as $qry) {
						if( trim($qry) ) {
							q(trim($qry));
						}	
					}
				}
				closedir($d);

				/* skip table defenitions inside the archive */
				while (($line = $getf($fp, 1000000)) && !$feoff($fp) && (!strncmp($line, 'DROP TABLE', 10) || !strncmp($line, 'CREATE TABLE', 12)));
				if ($line) {
					q(str_replace('{SQL_TABLE_PREFIX}', $DBHOST_TBL_PREFIX, $line));
				}
			}

			$i = 0;
			while (($line = $getf($fp, 1000000)) && $line != "----SQL_END----\n") {
				if (($line = trim($line))) {
					q(str_replace('{SQL_TABLE_PREFIX}', $DBHOST_TBL_PREFIX, $line));
					if ($i && !(++$i % 10000)) {
						echo 'Processed '.$i.' queries<br>';
						flush();
					}
				}
			}
			q('DELETE FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'ses');

			/* Try to restore the current admin's account by seeing if he exists in the imported database */
			if (($uid = q_singleval('SELECT id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WHERE login=\''.$usr->login.'\' AND is_mod=\'A\''))) {
				q('INSERT INTO '.$GLOBALS['DBHOST_TBL_PREFIX'].'ses (ses_id, user_id, time_sec) VALUES(\''.$usr->ses_id.'\', '.$uid.', '.__request_timestamp__.')');
			} else {
				echo '<font color="#ff0000">Your current login ('.htmlspecialchars($usr->login).') is not found in the imported database.<br>Therefor you\'ll need to re-login once the import process is complete<br></font>';
				flush();
			}

			echo "Recompiling Templates<br>\n";
			flush();

			fud_use('compiler.inc', true);
			$c = uq('SELECT theme, lang, name FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes WHERE enabled=\'Y\'');
			while ($r = db_rowarr($c)) {
				compile_all($r[0], $r[1], $r[2]);
			}
			qf($c);

			echo '<b>Import process is now complete</b><br>';
			exit;
		}
	}
?>
<h2><font color="#ff0000">Please note that import process will REMOVE ALL current forum data and replace it with the one from the file you enter.</font></h2>
<table border=0 cellspacing=1 cellpadding=3>
<form method="post" action="admimport.php">
<?php echo _hs; ?>
<tr bgcolor="#bff8ff">
	<td>Import Data Path<br><font size="-1">location on the drive, where the file your wish to import FUDforum data from is located.</font></td>
	<td><?php if (isset($path_error)) { echo $path_error; $path = $_POST['path']; } else { $path = ''; } ?><input type="text" value="<?php echo $path; ?>" name="path" size=40></td>
</tr>
<tr bgcolor="#bff8ff"><td colspan=2 align=right><input type="submit" name="btn_submit" value="Import Data"></td></tr>	
</form>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>