<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admforumicons.php,v 1.4 2002/09/18 20:52:08 hackie Exp $
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
	fud_use('util.inc');

	list($ses, $usr) = initadm();

	$ICONS_DIR = '../images/'.(($which_dir)?'message_icons/':'forum_icons/');
	
	if ( $btn_upload ) {
		$dest = $ICONS_DIR.$iconfile_name;
		$source = $iconfile;
		$upl_unable = 0;
		
		if ( file_exists($dest) ) {
			$upl_unable=1;
		}
		else if ( $iconfile_size && preg_match('/\.(gif|png|jpg|jpeg)$/i', $iconfile_name, $regs) ) {
			move_uploaded_file($source, $dest);
		}
		else {
			$upl_unable=1;
		}
		
		header("Location: admforumicons.php?"._rsidl."&upl_unable=$upl_unable&which_dir=$which_dir");
	}
	
	if ( $del ) {
		/* fix del */
		$unable = 0;
		if ( !strstr($del, '/') ) {
			if ( !@unlink($ICONS_DIR.$del) ) $unable = 1;
		}
		else
			$unable = 1;
		
		header("Location: admforumicons.php?"._rsidl."&unable=$unable&which_dir=$which_dir");
		exit();
	}
	
	include('admpanel.php'); 
	
	$dp = opendir($ICONS_DIR);
?>
<h2>Icon Administration System</h2>
<?php 
	if ( $unable ) 
		echo '<br><font color="red">Unable to delete icon from '.realpath($ICONS_DIR).'</font><br>';
?>

<?php 
	if ( $upl_unable ) 
		echo '<br><font color="red">Unable to upload file. Only .gif, .jpg, .jpeg, .png are allowed</font><br>';


if ( is_writeable($ICONS_DIR) ) {
?>
<form method="post" enctype="multipart/form-data" action="admforumicons.php">
<input type="hidden" name="which_dir" value="<?php echo $which_dir; ?>">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>Upload Icon:</td>
		<td><input type="file" name="iconfile"></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td align=right colspan=2><input type="submit" name="btn_upload" value="Upload & Add"></td>
	</tr>
</table>
</form>
<?php
}
else 
{
?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td align=center><font color="red"><?php echo realpath($ICONS_DIR).' is not writeable by the web server, file upload disabled'; ?></td>
	</tr>
</table>
<?php
}
?>
<table border=0 cellspacing=3 cellpadding=2>
<tr><td>Icon</td><td>Action</td></tr>
<?php
	while ( $de = readdir($dp) ) {
		if ( $de == '.' || $de == '..' ) continue;
		if ( !preg_match('/\.(gif|png|jpg|jpeg)$/i', $de, $regs) ) continue;
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		echo "<tr$bgcolor><td><img src=\"".$ICONS_DIR.$de."\"></td><td><a href=\"admforumicons.php?del=$de&"._rsid."&which_dir=$which_dir\">Delete</a></td></tr>\n";
	}
	closedir($dp);
?>
</table>
<?php readfile('admclose.html'); ?>