<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admavatar.php,v 1.3 2002/08/07 12:18:43 hackie Exp $
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
	
	fud_use('widgets.inc', TRUE);
	fud_use('util.inc');
	fud_use('avatar.inc');
	fud_use('adm.inc', TRUE);
	
	list($ses, $usr) = initadm();
	
	$avatar_dir = '../images/avatars/';
	
	cache_buster();
	
	if ( !empty($btn_cancel) ) {
		header("Location: admavatar.php?"._rsidl);
	}
	
	if ( !empty($del) ) {
		$avt_d = new fud_avatar;
		$avt_d->get($del);
		$avt_d->delete();
		header("Location: admavatar.php?"._rsidl);
		exit();
	}
	
	if ( !empty($btn_update) && !empty($edit) ) {
		$avt_u = new fud_avatar;
		$avt_u->get($edit);
		$avt_u->fetch_vars($HTTP_POST_VARS, 'avt_');
		$avt_u->sync();
		header("Location: admavatar.php?"._rsidl);
		exit();
	}
	
	if ( !empty($edit) && empty($prl) ) {
		$avt_r = new fud_avatar;
		$avt_r->get($edit);
		$avt_r->export_vars('avt_');
	}
	
	if ( !empty($btn_submit) ) {
		$avt = new fud_avatar;
		$avt->fetch_vars($HTTP_POST_VARS, 'avt_');
		$avt->add();
		header("Location: admavatar.php?"._rsidl);
		exit();
	}
	
	if ( !empty($icoul_size) ) {
		/* check extention */
		if ( preg_match('/.*\.(jpg|jpeg|gif|png)$/i', $icoul_name) ) {
			$err = 0;
			if ( !empty($ico_lfname) && !preg_match('/.*\.(jpg|jpeg|gif|png)$/i', $ico_lfname) ) {
				$err = 1;
				$err2 = 1;
			}
			
			if ( empty($err) ) {
				$dst_name = $avatar_dir.(( isset($ico_lfname) ) ? $ico_lfname : $icoul_name);
				umask(0177);
				move_uploaded_file($icoul, $dst_name);
				$avt_img = $icoul_name;
			}
		}
		else $err = 1;
	}

	include('admpanel.php'); 
?>
<h2>Avatar Management System</h2>

<form name="frm_avt" method="post" enctype="multipart/form-data">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<?php if ( is_writeable($avatar_dir) ) { ?>	
		<tr bgcolor="#bff8ff">
			<td colspan=2><b>Avatar Upload (upload avatars into the system)</td>
		</tr>
		<tr bgcolor="#bff8ff">
			<td>Avatar Upload:</td>
			<td>
				<input type="file" name="icoul"> <input type="submit" name="btn_upload" value="Upload">
				<?php if ( !empty($err) ) { ?>
					<br><font size=-1 color="#ff0000">Only (*.gif, *.jpg, *.png) files are supported</font>
				<?php } ?>
			</td>
		</tr>
	<?php } else { ?>
		<tr bgcolor="#bff8ff"> 
			<td colspan=2><font color="#ff0000">Web server doesn't have write permission to write to <b>'<?php echo realpath($avatar_dir); ?>'</b>, avatar upload disabled</font></td>
		</tr>
	<?php } ?>
	
	<tr><td colspan=2>&nbsp;</td></tr>

	<tr bgcolor="#bff8ff">
		<td colspan=2><a name="img"><b>Avatar Management</b></a></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>Avatar Description:</td>
		<td><input type="text" name="avt_descr" value="<?php echo (empty($avt_descr)?'':htmlspecialchars($avt_descr)); ?>"></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td valign=top><a name="avt_sel">Avatar Image:</a></td>
		<td>
			<input type="text" name="avt_img" value="<?php echo (empty($avt_img)?'':htmlspecialchars($avt_img)); ?>" 
				onChange="javascript: 
					if ( document.frm_avt.avt_img.value.length ) 
						document.prev_icon.src='<?php echo $avatar_dir; ?>' + document.frm_avt.avt_img.value; 
					else 
						document.prev_icon.src='../blank.gif';">
			[<a href="#avt_sel" onClick="javascript:window.open('admavatarsel.php?<?php echo _rsid; ?>', 'admavatarsel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100');">SELECT AVATAR</a>]
		</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Preview Image:</td>
		<td>
			<table border=1 cellspacing=1 cellpadding=2 bgcolor="#ffffff">
				<tr><td align=center valign=center>
					<img src="<?php echo ( strlen($avt_img) )?$avatar_dir.$avt_img:'../blank.gif'; ?>" name="prev_icon" border=0>
				</td></tr>
			</table>
		</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<?php
			if ( empty($edit) ) {
				echo '<td colspan=2 align=right><input type="submit" name="btn_submit" value="Add Avatar"></td>';
			}
			else {
				echo '<td colspan=2 align=right><input type="submit" name="btn_cancel" value="Cancel">
				      <input type="submit" name="btn_update" value="Update"></td>';
			}
		?>
	</tr>
	
</table>
<input type="hidden" name="edit" value="<?php echo (empty($edit)?'':$edit); ?>">
<input type="hidden" name="prl" value="1">
</form>
<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Avatar</td>
	<td>Description</td>
	<td align="center">Action</td>
</tr>
<?php
	$avt_draw = new fud_avatar;
	$avt_draw->getall();
	$avt_draw->resets();
	
	$i=0;
	while ( $obj = $avt_draw->eachs() ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		if ( !empty($edit) && $edit==$obj->id ) $bgcolor =' bgcolor="#ffb5b5"';
		
		$ctl = "<td>[<a href=\"admavatar.php?edit=".$obj->id."&"._rsid."#img\">Edit</a>] [<a href=\"admavatar.php?del=".$obj->id."&"._rsid."\">Delete</a>]</td>";
		echo "<tr".$bgcolor."><td><img src=\"".$avatar_dir.$obj->img."\" border=0></td><td>".$obj->descr."</td>".$ctl."</tr>\n";
	}  
?>
</table>
<?php readfile('admclose.html'); ?>