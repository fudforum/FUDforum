<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admbrowse.php,v 1.5 2002/09/18 20:52:08 hackie Exp $
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
	
	include_once "GLOBALS.php";
	
	fud_use('widgets.inc', true);
	fud_use('adm.inc', true);

function bit_test($val, $mask)
{
	return ( ($val&$mask)==$mask ) ? $mask : 0;
}

function mode_string($mode, $de)
{
	/* determine string mode
	01234567890 */
	$mode_str = 'drwxrwxrwxt';
		
	if ( !is_dir($de) ) /* directory */
		$mode_str[0] = '-';
		
	if ( !bit_test($mode, 00400) ) /* owner read */
		$mode_str[1] = '-';
			
	if ( !bit_test($mode, 00200) ) /* owner write */
		$mode_str[2] = '-';
		
	if ( !bit_test($mode, 00100) ) /* owner exec */
		$mode_str[3] = '-';
		
	if ( bit_test($mode, 0004000) ) /* setuid */
		$mode_str[3] = 's';
			
	if ( !bit_test($mode, 00040) ) /* group read */
		$mode_str[4] = '-';
			
	if ( !bit_test($mode, 00020) ) /* group write */
		$mode_str[5] = '-';
		
	if ( !bit_test($mode, 00010) ) /* group exec */
		$mode_str[6] = '-';
		
	if ( bit_test($mode, 0002000) ) /* setgid */
		$mode_str[6] = 's';
		
	if ( !bit_test($mode, 00004) ) /* world read */
		$mode_str[7] = '-';
			
	if ( !bit_test($mode, 00002) ) /* world write */
		$mode_str[8] = '-';
		
	if ( !bit_test($mode, 00001) ) /* world exec */
		$mode_str[9] = '-';
		
	if ( !bit_test($mode, 0001000) ) /* sticky (warning: NOT POSIX) */
		$mode_str[10] = '-';	
			
	return $mode_str;
}

	list($ses, $usr) = initadm();
	
	/* Figure out the ROOT paths based on the location of web browseable dir & data dir */
	$ROOT_PATH[0] = realpath($GLOBALS['WWW_ROOT_DISK']);
	$ROOT_PATH[1] = realpath($GLOBALS['INCLUDE'].'../');
	
	if ( $btn_cancel ) exit('<html><script>window.close();</script></html>');
	
	if( !($MYDIR = getcwd()) ) $MYDIR = dirname($HTTP_SERVER_VARS['PATH_TRANSLATED']);

	/* Remove slashes */
	if( isset($HTTP_POST_VARS['cur']) ) {
		$cur = $HTTP_POST_VARS['cur'] = stripslashes($HTTP_POST_VARS['cur']);
		$dest = $HTTP_POST_VARS['dest'] = stripslashes($HTTP_POST_VARS['dest']);
	}
	else if ( isset($HTTP_GET_VARS['cur']) ) {
		$cur = $HTTP_GET_VARS['cur'] = stripslashes($HTTP_GET_VARS['cur']);
		$dest = $HTTP_GET_VARS['dest'] = stripslashes($HTTP_GET_VARS['dest']);
	}
	else {
		$cur = $ROOT_PATH[0];
		$dest = '';
	}	
	
	$cur_path = $cur = realpath($cur);
	
	/* Security check to ensure the user does not attempt to control files outside of the allowed directories */
	if( !empty($dest) ) {
		$dest = basename($dest);
		if( $dest == '.' || $dest == '..' ) $dest = '';
	}	

	$ROOT_PATH[2] = preg_quote($ROOT_PATH[0]);
	$ROOT_PATH[3] = preg_quote($ROOT_PATH[1]);

	if( !preg_match('!^'.$ROOT_PATH[2].'!', $cur) && !preg_match('!^'.$ROOT_PATH[3].'!', $cur) ) {
		header('Location: admbrowse.php?'._rsidl.'&cur='.urlencode($ROOT_PATH[0]));
		exit;	
	}
	
	/* Directory creation code */
	if( isset($HTTP_GET_VARS['mkdir']) ) {
		$cur_dir = getcwd();
		chdir($cur);
		$oldmask = umask(0);
		$ret = mkdir(basename(stripslashes($HTTP_GET_VARS['mkdir'])), 0700);
		chdir($cur_dir);
		umask($oldmask);
		
		if( $ret == true ) {
			header('Location: admbrowse.php?'._rsidl.'&cur='.urlencode($cur));
			exit;
		}
		else {
			exit('FATAL ERROR: failed to create "'.stripslashes($HTTP_GET_VARS['mkdir']).'" directory inside "'.$cur."\"<br>\n");
		}
	}

	/* File upload code */
	if( isset($HTTP_POST_FILES['fname']) ) {
		$dest = $cur.'/'.(strlen($HTTP_POST_VARS['d_name']) ? stripslashes($HTTP_POST_VARS['d_name']) : $HTTP_POST_FILES['fname']['name']);
		$oldmask = umask(0177);
		move_uploaded_file($HTTP_POST_FILES['fname']['tmp_name'], $dest);
		umask($oldmask);
		header('Location: admbrowse.php?'._rsidl.'&cur='.urlencode($cur));
		exit;		
	}

	/* Download file code */
	if( isset($HTTP_GET_VARS['down']) && @file_exists($cur.'/'.$dest) ) {
		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename=".$dest);
		fpassthru(fopen($cur.'/'.$dest, 'rb'));
		exit;	
	}
	
	/* Delete file code */
	if( isset($HTTP_GET_VARS['del']) ) {
		if( !@file_exists($cur.'/'.$dest) ) {
			exit('FATAL ERROR: cannnot delete non-existant file: '.$cur.'/'.$dest);
		}
		
		if( isset($HTTP_GET_VARS['del_conf']) ) {
			if( @is_dir($cur.'/'.$dest) ) {
				if( rmdir($cur.'/'.$dest.'/') ) 
					exit('<html><script> window.opener.location = \'admbrowse.php?'._rsid.'&cur='.urlencode($cur).'\';  window.close();</script></html>');
				else
					exit('Failed to delete: '.$cur.'/'.$dest.'/');	
			}	
			else {
				if( unlink($cur.'/'.$dest) ) 
					exit('<html><script> window.opener.location = \'admbrowse.php?'._rsid.'&cur='.urlencode($cur).'\'; window.close();</script></html>');
				else
					exit('Failed to delete: '.$cur.'/'.$dest);
			}
			exit;			
		}
		else { 
			$file = $cur.'/'.$dest;
			$type = @is_dir($file) ? 'directory' : 'file';
		?>
			<html>
			<h2>File/Directory Deletion</h2>
			Are you sure you want to delete <?php echo $type.' <font color="#ff0000"><b>'.$file.'</b></font>'; ?><p>
			<form method="GET" action="admbrowse.php">
			<input type="hidden" name="cur" value="<?php echo $cur; ?>">
			<input type="hidden" name="dest" value="<?php echo $dest; ?>">
			<input type="hidden" name="del" value="1">
			<div align="center"><input type="submit" name="btn_cancel" value="No"> <input type="submit" name="del_conf" value="Yes"></div>
			</form>
			</html>
		<?php 
			exit;
		}
	}

if ( $chmod ) {
	$file = $cur.'/'.$dest;
	$st = stat($file);
	if( !isset($st[2]) ) $st[2] = $st['mode'];
	
	$mode_o = sprintf('%o', 0x0FFF&$st[2]);
	
	if ( $btn_cancel ) exit('<html><script>window.close();</script></html>');

	if ( $btn_submit ) {
		$new_mode = 
			($oread+0)
			|($owrite+0)
			|($oexec+0)
			|($gread+0)
			|($gwrite+0)
			|($gexec+0)
			|($wread+0)
			|($wwrite+0)
			|($wexec+0)
			|($setuid+0)
			|($setgid+0)
			|($sticky+0)
			;
					
		if ( !@chmod($file, $new_mode) )
			exit("<html>Unable to chmod <b>$file</b><br><a href=\"javscript: return false;\" onClick=\"javascript: window.close();\">close</a></html>");

		exit('<html><script> window.opener.location = \'admbrowse.php?'._rsid.'&cur='.urlencode($cur).'\'; window.close();</script></html>');
	}
?>
	<html>
		<h2>Change File Permissions</h2>
		<?php echo $file.' is currenly <b>'.mode_string($st[2], $file).' ('.$mode_o.')</b>'; ?><br>
		change it to:<br>
		<form method="post" action="admbrowse.php">
		<?php echo _hs; ?>
		<input type="hidden" name="chmod" value="1">
		<input type="hidden" name="cur" value="<?php echo $cur; ?>">
		<input type="hidden" name="dest" value="<?php echo $dest; ?>">
		<table border=0>
		<tr><td>Group:</td><td>Read</td><td>Write</td><td>Execute</td></tr>
		<tr><td>Owner:</td>
			<td><?php draw_checkbox('oread', 0400, bit_test($st[2], 0400)); ?></td>
			<td><?php draw_checkbox('owrite', 0200, bit_test($st[2], 0200)); ?></td>
			<td><?php draw_checkbox('oexec', 0100, bit_test($st[2], 0100)); ?></td></tr>
		<tr><td>Group:</td>
			<td><?php draw_checkbox('gread', 0040, bit_test($st[2], 0040)); ?></td>
			<td><?php draw_checkbox('gwrite', 0020, bit_test($st[2], 0020)); ?></td>
			<td><?php draw_checkbox('gexec', 0010, bit_test($st[2], 0010)); ?></td></tr>
		<tr><td>World:</td>
			<td><?php draw_checkbox('wread', 0004, bit_test($st[2], 0004)); ?></td>
			<td><?php draw_checkbox('wwrite', 0002, bit_test($st[2], 0002)); ?></td>
			<td><?php draw_checkbox('wexec', 0001, bit_test($st[2], 0001)); ?></td></tr>
		<tr><td colspan=4><?php draw_checkbox('setuid', 0004000, bit_test($st[2], 0004000)); ?> setuid</td></tr>
		<tr><td colspan=4><?php draw_checkbox('setgid', 0002000, bit_test($st[2], 0002000)); ?> setgid</td></tr>
		<tr><td colspan=4><?php draw_checkbox('sticky', 0001000, bit_test($st[2], 0001000)); ?> sticky</td></tr>
		<tr><td colspan=4 align=right><input type="submit" name="btn_submit" value="Apply"> <input type="submit" name="btn_cancel" value="Cancel"></td></tr>
		</table>
		</form>
	</html>
<?php
exit();
}

include('admpanel.php'); 
?>
<h2>File Adminstration System</h2>
<?php
	if( !@is_dir($cur) ) exit("'".$cur."' is not a valid directory<br>\n");
	if ( !@chdir($cur) ) {	
		echo "<b>PERMISSION DENINED ACCSESING $cur</b><br>\n";
		$cur = $MYDIR;
		chdir($cur);
	}

	echo 'WWW_SERVER_ROOT: <a href="admbrowse.php?'._rsid.'&cur='.urlencode($ROOT_PATH[0]).'">'.$ROOT_PATH[0].'</a><br>
		DATA_ROOT:  <a href="admbrowse.php?'._rsid.'&cur='.urlencode($ROOT_PATH[1]).'">'.$ROOT_PATH[1].'</a><br>';
	echo 'Currently Browsing: <b>'.htmlspecialchars($cur)."</b><br>\n";		
	
	clearstatcache();
	if ( !($dp = @opendir('.') )) {
		echo "<b>PERMISSION DENINED ACCSESING $cur</b><br>\n";
		$cur = $MYDIR;
		chdir($cur);
		$dp = @opendir('.');
	}
?>
<br>
<table cellspacing=2 cellpadding=2 border=0>
	<form method="get" action="admbrowse.php"><input type="hidden" name="cur" value="<?php echo $cur; ?>"><?php echo _hs; ?>
	<tr style="font-size: x-small;">
		<td>Directory Name:</td>
		<td><input type="text" name="mkdir" value=""></td>
		<td align="right" colspan=2><input  style="font-size: x-small;"  type="submit" name="btn_mkdir" value="Create Directory">
	</tr>
	</form>
</table>
<br>
<table cellspacing=2 cellpadding=2 border=0>
	<form method="post" action="admbrowse.php" enctype="multipart/form-data"><input type="hidden" name="cur" value="<?php echo $cur; ?>"><?php echo _hs; ?>
	<tr style="font-size: x-small;">
		<td colspan=2><b>File Upload</b></td>
	</tr>
	<tr style="font-size: x-small;">
		<td>File To Upload:</td>
		<td><input type="file" name="fname"></td>
	</tr>
	<tr style="font-size: x-small;">
		<td>File Name:<br>(leave blank if want the uploaded filename to remain unchanged)</td>
		<td><input type="text" name="d_name" value=""></td>
	</tr>
	<tr style="font-size: x-small;">
		<td colspan=2 align="right"><input type="submit" name="file_upload" value="Upload File"></td>
	</tr>
	</form>
</table>
<br>
<table border=0 cellspacing=1 cellpadding=3>
<tr class="admin_fixed" bgcolor="#bff8ff"><td>Mode</td><td>Owner</td><td>Group</td><td>Size</td><td>Date</td><td>Time</td><td>Name</td><td align="center" colspan=3>Action</td></tr>
<?php	
	$file_list = array();
	$dir_list = array();

	while ( $de = readdir($dp) ) {
		if( @is_dir($de) ) 
			$dir_list[] = $de;
		else
			$file_list[] = $de;	
	}
	closedir($dp);
	
	sort($dir_list);
	sort($file_list);
	
	$dir_data = array_merge($dir_list, $file_list);	
		
	foreach($dir_data as $de) { 
		if( @is_file($de) ) {
			$name = $de;
			$st = stat($de);
		}	
		if( @is_dir($de) ) {
			if( $de == '.' ) continue;
		
			$path = realpath($cur.'/'.$de.'/');
			if( !preg_match('!^'.$ROOT_PATH[2].'!', $path) && !preg_match('!^'.$ROOT_PATH[3].'!', $path) ) continue;
					
			$name = '<a href="admbrowse.php?cur='.urlencode($path).'&rand='.get_random_value().'&'._rsid.'">'.$de.'</a>';
			$st = stat($de);
		}	
	
		$mode = isset($st[2]) ? $st[2] : $st['mode'];
		$mode_str = mode_string($mode, $de);
		
		$passwdent = posix_getpwuid((isset($st[4])?$st[4]:$st['uid']));
		$owner = $passwdent['name'];
		$groupsent = posix_getgrgid((isset($st[5])?$st[5]:$st['gid']));
		$group = $groupsent['name'];
		
		$date_str = strftime("%b %d", (isset($st[9])?$st[9]:$st['mtime']));
		$time_str = strftime("%T", (isset($st[9])?$st[9]:$st['mtime']));
		$mode_o = sprintf('%o', 0x0FFF&$mode);
		
		$size = round((isset($st[7])?$st[7]:$st['size'])/1024);
		
		echo '<tr class="admin_fixed"><td nowrap>'.$mode_str.' ('.$mode_o.')</td><td>'.$owner.'</td><td>'.$group.'</td><td>'.$size.' KB</td><td>'.$date_str.'</td><td>'.$time_str.'</td><td>'.$name.'</td>';
		if( @is_readable($de) ) {
			if( @is_writeable($de) ) 
				echo "<td style=\"border: #AEBDC4; border-style: solid; border-top-width: 1px; border-right-width: 1px; border-bottom-width: 1px; border-left-width: 1px;\"><a href=\"javascript: return false;\" onClick=\"javascript: window.open('admbrowse.php?chmod=1&cur=".urlencode($cur)."&dest=".urlencode($de)."&"._rsid."', 'chmod_window', 'width=500,height=350,menubar=no');\">chmod</a></td> ";
			else
				echo '<td style="border: #AEBDC4; border-style: solid; border-top-width: 1px; border-right-width: 1px; border-bottom-width: 1px; border-left-width: 1px;" align="center">n/a</td>';	
			
			if( @is_file($de) ) 
				echo "<td style=\"border: #AEBDC4; border-style: solid; border-top-width: 1px; border-right-width: 1px; border-bottom-width: 1px; border-left-width: 1px;\"><a href=\"admbrowse.php?down=1&cur=".urlencode($cur)."&dest=".urlencode($de)."&"._rsid."\">download</a></td>";
			else
				echo '<td style="border: #AEBDC4; border-style: solid; border-top-width: 1px; border-right-width: 1px; border-bottom-width: 1px; border-left-width: 1px;" align="center">n/a</td>';					
			
			if( @is_writeable($de) ) 
				echo "<td style=\"border: #AEBDC4; border-style: solid; border-top-width: 1px; border-right-width: 1px; border-bottom-width: 1px; border-left-width: 1px;\"><a href=\"javascript: return false;\" onClick=\"javascript: window.open('admbrowse.php?del=1&cur=".urlencode($cur)."&dest=".urlencode($de)."&"._rsid."', 'chmod_window', 'width=500,height=350,menubar=no');\">delete</a></td>";
			else
				echo '<td style="border: #AEBDC4; border-style: solid; border-top-width: 1px; border-right-width: 1px; border-bottom-width: 1px; border-left-width: 1px;" align="center">n/a</td>';	
		}
		else
			echo '<td style="border: #AEBDC4; border-style: solid; border-top-width: 1px; border-right-width: 1px; border-bottom-width: 1px; border-left-width: 1px;" colspan=3 align="center">n/a</td>';
		echo '</tr>';
	}

	chdir($MYDIR);

readfile('admclose.html'); ?>