<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admmime.php,v 1.2 2002/06/18 18:26:10 hackie Exp $
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
	
	fud_use('static/widgets.inc');
	fud_use('static/adm.inc');
	fud_use('static/mime_adm.inc');
	fud_use('util.inc');
	fud_use('objutil.inc');
	
	list($ses, $usr) = initadm();
	
	$web_path = '../images/mime/';
	
	if( !empty($HTTP_POST_VARS['prl']) ) {
		if( empty($HTTP_POST_FILES['icoul']['size']) ) {
			$mime = new fud_mime;
			fetch_vars('mime_', $mime, $HTTP_POST_VARS);
		
			if( empty($edit) )
				$mime->add();
			else
				$mime->sync($edit);
		
			header("Location: admmime.php?edit=".$mime->id."&"._rsid);
			exit;
		}
		else {	
			if ( preg_match('!\..*(jpg|jpeg|gif|png)$!i', $HTTP_POST_FILES['icoul']['name']) ) {
				$err = 0;
				umask(0177);
				move_uploaded_file($HTTP_POST_FILES['userfile']['tmp_name'], '../images/mime/'.$HTTP_POST_FILES['icoul']['name']);
				$mime_icon = $HTTP_POST_FILES['icoul']['name'];
				$prev_icon = $web_path.$HTTP_POST_FILES['icoul']['name'];
			}
			else 
				$err = 1;
		}	
	}
	else if( !empty($edit) ) {
		$mime = new fud_mime;
		$mime->get($edit);
		export_vars('mime_', $mime);
	}
	else if( $del ) {
		$mime = new fud_mime;
		$mime->get($del);
		$mime->delete();
		
		header("Location: admmime.php?"._rsid);
		exit;		
	}

	include('admpanel.php'); 
?>
<h2>MIME Management System</h2>
<table border=0 cellspacing=1 cellpadding=3>
<form action="admmime.php" name="frm_mime" method="post" enctype="multipart/form-data">
<?php 
	echo _hs; 
	if ( @is_writeable(realpath('../images/mime/')) ) {
?>	
<tr bgcolor="#bff8ff">
	<td colspan=2><b>MIME Icon Upload (upload mime icons into the system)</td>
</tr>
<tr bgcolor="#bff8ff">
	<td>MIME Icon Upload:</td>
	<td>
		<input type="file" name="icoul"> <input type="submit" name="btn_upload" value="Upload">
		<?php if ( !empty($err) ) { ?>
			<br><font size=-1 color="#ff0000">Only (.gif, *.jpg, *.png) files are supported</font>
		<?php } ?>
	</td>
</tr>
<?php } else { ?>
<tr bgcolor="#bff8ff"> 
	<td colspan=2><font color="#ff0000">Web server does not have write permissions to <b>'<?php echo realpath('../images/mime/'); ?>'</b>, mime icon upload disabled</font></td>
</tr>
<?php } ?>
<tr><td colspan=2>&nbsp;</td></tr>

<tr bgcolor="#bff8ff">
	<td colspan=2><a name="img"><b>MIME Management</b></a></td>
</tr>

<tr bgcolor="#bff8ff">
	<td>MIME Description:</td>
	<td><input type="text" name="mime_descr" value="<?php echo htmlspecialchars($mime_descr); ?>"></td>
</tr>
	
<tr bgcolor="#bff8ff"> 
	<td>MIME Header:</td>
	<td><input type="text" name="mime_mime_hdr" value="<?php echo htmlspecialchars($mime_mime_hdr); ?>"></td>
</tr>

<tr bgcolor="#bff8ff"> 
	<td>File Extension:<br><font size="-1">Files with this extension (case-insensitive) will be attributed to this MIME.</font></td>
	<td><input type="text" name="mime_fl_ext" value="<?php echo htmlspecialchars($mime_fl_ext); ?>"></td>
</tr>
		
<tr bgcolor="#bff8ff">
	<td valign="top"><a name="mime_sel">MIME Icon:</a></td>
	<td><input type="text" name="mime_icon" value="<?php echo htmlspecialchars($mime_icon); ?>" onChange="javascript: if( document.frm_sml.mime_icon.value.length ) document.prev_icon.src='<?php echo $web_path; ?>' + document.frm_sml.mime_icon.value; else document.prev_icon.src='<?php echo $web_path; ?>blank.gif';"> [<a href="#mime_sel" onClick="javascript:window.open('admmimesel.php?<?php echo _rsid; ?>', 'admmimesel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100');">select MIME icon</a>]</td>
</tr>
	
<tr bgcolor="#bff8ff">
	<td valign="top">Preview Image:</td>
	<td>
		<table border=1 cellspacing=1 cellpadding=2 bgcolor="#ffffff">
		<tr><td align=center valign=center><img src="<?php echo $web_path.(!empty($mime_icon)?$mime_icon:'unknown.gif'); ?>" name="prev_icon" border=0></td></tr>
		</table>
	</td>
</tr>
	
<tr bgcolor="#bff8ff">
<?php
	if ( empty($edit) ) 
		echo '<td colspan=2 align=right><input type="reset" name="btn_cancel" value="Reset"> <input type="submit" name="btn_submit" value="Add MIME"></td>';
	else 
		echo '<td colspan=2 align=right><input type="reset" name="btn_cancel" value="Reset"> <input type="submit" name="btn_update" value="Update"></td>';
?>
</tr>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
<input type="hidden" name="prl" value="1">
</form>
</table>
<p>
<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Icon</td>
	<td>MIME Header</td>
	<td>Description</td>
	<td>Extension</td>
	<td align="center">Action</td>
</tr>
<?php
	$r = q("SELECT * FROM ".$GLOBALS['MYSQL_TBL_PREFIX']."mime");
	$i=1;
	while( $obj = db_rowobj($r) ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		echo '<tr'.$bgcolor.' valign="top"><td><img src="'.$web_path.$obj->icon.'" border=0></td><td>'.$obj->mime_hdr.'</td><td>'.$obj->descr.'</td><td>'.$obj->fl_ext.'</td><td nowrap>[<a href="admmime.php?edit='.$obj->id.'&'._rsid.'#img">Edit</a>] [<a href="admmime.php?del='.$obj->id.'&'._rsid.'">Delete</a>]</td></tr>';
	}
?>
</table>
<?php require('admclose.html'); ?>