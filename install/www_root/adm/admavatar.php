<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admavatar.php,v 1.7 2003/05/26 11:15:04 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (isset($_GET['del'])) {
		if (($im = q_singleval('SELECT img FROM '.$tbl.'avatar WHERE id='.(int)$_GET['del']))) {
			q('DELETE FROM '.$tbl.'avatar WHERE id='.(int)$_GET['del']);
			if (db_affected()) {
				q('UPDATE '.$tbl.'users SET avatar_loc=NULL, avatar=0, avatar_approved=\'NO\' WHERE avatar='.(int)$_GET['del']);
			}
			@unlink($GLOBALS['WWW_ROOT_DISK'] . 'images/avatars/'.$im);
		}
	}
	if (isset($_GET['edit'])) {
		list($avt_img, $avt_descr) = db_saq('SELECT img, descr FROM '.$tbl.'avatar WHERE id='.(int)$_GET['edit']);
		$edit = (int)$_GET['edit'];
	} else {
		$edit = $avt_img = $avt_descr = '';
	}

	if (isset($_FILES['icoul']) && $_FILES['icoul']['size'] && preg_match('!\.(jpg|jpeg|gif|png)$!i', $_FILES['icoul']['name'])) {
		move_uploaded_file($_FILES['icoul']['tmp_name'], $GLOBALS['WWW_ROOT_DISK'] . 'images/avatars/' . $_FILES['icoul']['name']);
		if (empty($_POST['avt_img'])) {
			$_POST['avt_img'] = $_FILES['icoul']['name'];
		}
	}
	
	if (isset($_POST['btn_update'], $_POST['edit']) && !empty($_POST['avt_img'])) {
		$old_img = q_singleval('SELECT img FROM '.$tbl.'avatar WHERE id='.(int)$_POST['edit']);
		q('UPDATE '.$tbl.'avatar SET img='.strnull(addslashes($_POST['avt_img'])).', descr='.strnull(addslashes($_POST['avt_descr'])).' WHERE id='.(int)$_POST['edit']);
		if (db_affected() && $old_img != $_POST['avt_img']) {
			$size = getimagesize($GLOBALS['WWW_ROOT_DISK'] . 'images/avatars/' . $_POST['avt_img']); 
			$new_loc = '<img src="'.$GLOBALS['WWW_ROOT'].'images/avatars/'.$_POST['avt_img'].'" '.$size[3].' />';
			q('UPDATE '.$tbl.'users SET avatar_loc=\''.$new_loc.'\' WHERE avatar='.(int)$_POST['edit']);
		}
	} else if (isset($_POST['btn_submit']) && !empty($_POST['avt_img'])) {
		q('INSERT INTO '.$tbl.'avatar (img, descr) VALUES ('.strnull(addslashes($_POST['avt_img'])).', '.strnull(addslashes($_POST['avt_descr'])).')');
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 
?>
<h2>Avatar Management System</h2>

<form name="frm_avt" method="post" action="admavatar.php" enctype="multipart/form-data">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<?php if (@is_writeable($GLOBALS['WWW_ROOT_DISK'] . 'images/avatars')) { ?>
		<tr bgcolor="#bff8ff">
			<td colspan=2><b>Avatar Upload (upload avatars into the system)</td>
		</tr>
		<tr bgcolor="#bff8ff">
			<td>Avatar Upload:<br><font size="-1">Only (*.gif, *.jpg, *.png) files are supported</font></td>
			<td><input type="file" name="icoul"> <input type="submit" name="btn_upload" value="Upload"></td>
		</tr>
	<?php } else { ?>
		<tr bgcolor="#bff8ff"> 
			<td colspan=2><font color="#ff0000">Web server doesn't have write permission to write to <b>'<?php echo $GLOBALS['WWW_ROOT_DISK'] . 'images/avatars'; ?>'</b>, avatar upload disabled</font></td>
		</tr>
	<?php } ?>
	
	<tr><td colspan=2>&nbsp;</td></tr>

	<tr bgcolor="#bff8ff">
		<td colspan=2><a name="img"><b>Avatar Management</b></a></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>Avatar Description:</td>
		<td><input type="text" name="avt_descr" value="<?php echo htmlspecialchars($avt_descr); ?>"></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td valign=top><a name="avt_sel">Avatar Image:</a></td>
		<td>
			<input type="text" name="avt_img" value="<?php echo htmlspecialchars($avt_img); ?>" 
				onChange="javascript: 
					if (document.frm_avt.avt_img.value.length) {
						document.prev_icon.src='<?php echo $GLOBALS['WWW_ROOT_DISK']; ?>images/avatars/' + document.frm_avt.avt_img.value; 
					} else {
						document.prev_icon.src='../blank.gif';
					}">
			[<a href="#avt_sel" onClick="javascript:window.open('admavatarsel.php?<?php echo _rsidl; ?>', 'admavatarsel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100');">SELECT AVATAR</a>]
		</td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>Preview Image:</td>
		<td>
			<table border=1 cellspacing=1 cellpadding=2 bgcolor="#ffffff">
				<tr><td align=center valign=center>
					<img src="<?php echo ($avt_img ? $GLOBALS['WWW_ROOT'] . 'images/avatars/' . $avt_img : '../blank.gif'); ?>" name="prev_icon" border=0>
				</td></tr>
			</table>
		</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<?php
			if (!$edit) {
				echo '<td colspan=2 align=right><input type="submit" name="btn_submit" value="Add Avatar"></td>';
			} else {
				echo '<td colspan=2 align=right><input type="submit" name="btn_cancel" value="Cancel"><input type="submit" name="btn_update" value="Update"></td>';
			}
		?>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>

<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Avatar</td>
	<td>Description</td>
	<td align="center">Action</td>
</tr>
<?php
	$c = uq('SELECT id, img, descr FROM '.$tbl.'avatar');
	$i = 0;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' bgcolor="#ffb5b5"';
		} else {
			$bgcolor = ($i++%2) ? ' bgcolor="#fffee5"' : '';
		}
		echo '<tr '.$bgcolor.'>
				<td><img src="'.$GLOBALS['WWW_ROOT'].'images/avatars/'.$r[1].'" alt="'.$r[2].'" border=0 /></td>
				<td>'.$r[2].'</td>
				<td>[<a href="admavatar.php?edit='.$r[0].'&'._rsidl.'#img">Edit</a>] [<a href="admavatar.php?del='.$r[0].'&'._rsidl.'">Delete</a>]</td>
			</tr>';
	}
	qf($c);
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>