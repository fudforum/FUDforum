<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admforum.php,v 1.6 2002/08/07 12:18:43 hackie Exp $
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
	
	fud_use("forum.inc");
	fud_use("cat.inc");
	fud_use("widgets.inc", TRUE);
	fud_use("util.inc");
	fud_use('cookies.inc');
	fud_use('adm.inc', TRUE);
	fud_use('objutil.inc');	
	fud_use('groups.inc');
	fud_use('logaction.inc');
	
	list($ses, $usr) = initadm();	
	
	$cat = new fud_cat;
	$frm = new fud_forum_adm;
	
	if ( empty($cat_id) ) {
		exit("no such category\n");
	}

	if ( !empty($frm_edit_cancel) ) {
		header("Location: admforum.php?"._rsidl."&cat_id=".$cat_id);
		exit();
	}

	if ( !$cat->get_cat($cat_id) ) {
		exit("no such category\n");
	}

	if ( !empty($frm_submit) ) {
		if ( $edit ) $frm->get($edit);
		fetch_vars('frm_', $frm, $HTTP_POST_VARS);
		
		if ( empty($edit) ) {
			$frm->cat_id = $cat_id;
			$frm->add($frm_pos);
			logaction($usr->id, "ADDFORUM", $frm->id);
			header("Location: admforum.php?"._rsidl."&cat_id=".$cat_id);
			exit();
		}
		else if ( !empty($edit) ) {
			$frm->sync();
			logaction($usr->id, "SYNCFORUM", $frm->id);
			header("Location: admforum.php?"._rsidl."&cat_id=".$cat_id);
			exit();
		}
		else {
			exit("an error occured during form reload\n");
		}
	}
	else $frm_max_file_attachments=1;
	
	if ( !empty($chpos) && !empty($newpos) && !empty($cat_id) ) {
		$frm->change_pos($chpos, $newpos, $cat_id);
		header("Location: admforum.php?"._rsidl."&cat_id=".$cat_id);
		exit();
	}
	
	if ( !empty($act) && $act=='del' && !empty($cat_id) && !empty($del) ) {
		$frm->get($del);
		$frm->chcat($del, 0);
		logaction($usr->id, "CHCATFORUM", $frm->id);
		
	}
	
	if ( !empty($edit) ) {
		$frm->get($edit);
		export_vars('frm_', $frm);
	}
	
	if ( !empty($btn_chcat) ) {
		$frm->get($chcat_src);
		$frm->get($dest_cat);
		$frm->chcat($chcat_src, $dest_cat);
		header("Location: admforum.php?"._rsidl."&cat_id=$cat_id");
		exit();
		
	}
	
	if( !isset($frm_max_attach_size) ) $frm_max_attach_size = 1024;
	
	cache_buster();

	$frm_name = ( isset($frm_name) ) ? htmlspecialchars($frm_name) : '';
	$frm_descr = ( isset($frm_descr) ) ? htmlspecialchars($frm_descr) : '';
	$frm_post_passwd = ( isset($frm_post_passwd) ) ? htmlspecialchars($frm_post_passwd) : '';

	include('admpanel.php'); 
?>
<h2>Editing forums for <?php echo $cat->name; ?></h2>
<?php if ( empty($chpos) ) { ?> 
<a href="admcat.php?<?php echo _rsid; ?>">Back to categories</a><br>

<form method="post" name="frm_forum" action="admforum.php">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>Forum Name:</td>
		<td><input type="text" name="frm_name" value="<?php echo $frm_name; ?>" maxlength=100></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td valign=top>Description</td>
		<td><textarea nowrap name="frm_descr" cols=25 rows=5><?php echo $frm_descr; ?></textarea>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Tag Style</td>
		<td><?php draw_select('frm_tag_style', "FUD ML\nHTML\nNone", "ML\nHTML\nNONE", empty($frm_tag_style)?'':$frm_tag_style); ?></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Password Posting<br><font size=-2>Posting is only allowed with a knowledge of a password</font></td>
		<td><?php draw_select('frm_passwd_posting', "No\nYes", "N\nY", yn($frm_passwd_posting)); ?></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Posting Password</td>
		<td><input type="passwd" maxLength=32 name="frm_post_passwd" value="<?php echo $frm_post_passwd; ?>"></td>
	</tr>
	
<!-- Not implemented at the time of release
	
	<tr bgcolor="#bff8ff">
		<td valign="top">Anonymous Forum<br><br><font size=2>All poster names are hidden</font></td>
		<td><?php draw_select('frm_anon_forum', "No\nYes", "N\nY", yn($frm_anon_forum)); ?></td>
	</tr>
-->	
	<tr bgcolor="#bff8ff">
		<td>Moderated Forum</td>
		<td><?php draw_select('frm_moderated', "No\nYes", "N\nY", yn($frm_moderated)); ?></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Max Attachment Size:</td>
		<td><input type="text" name="frm_max_attach_size" value="<?php echo $frm_max_attach_size; ?>" maxlength=100 size=5>kb</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Max Number of file Attachments:</td>
		<td><input type="text" name="frm_max_file_attachments" value="<?php echo $frm_max_file_attachments; ?>" maxlength=100 size=5></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Message Threshold<br><font size=-1>Maximum size of the message DISPLAYED<br>without the reveal link (0 means unlimited) </font></td>
		<td><input type="text" name="frm_message_threshold" value="<?php echo (empty($frm_message_threshold))?'0':$frm_message_threshold; ?>" size=5> bytes</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td><a name="frm_icon_pos">Forum Icon</a></td>
		<td><input type="text" name="frm_forum_icon" value="<?php echo empty($frm_forum_icon)?'':$frm_forum_icon; ?>"> <a href="javascript://" onClick="javascript:window.open('admiconsel.php', 'admiconsel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100')">[SELECT ICON]</a></td>
	</tr>
	
	<?php if ( empty($edit) ) { ?>
	<tr bgcolor="#bff8ff">
		<td>Insert Position</td>
		<td><?php draw_select('frm_pos', "Last\nFirst", "LAST\nFIRST", empty($frm_pos)?'':$frm_pos); ?></td>
	</tr>
	<?php } ?>
	
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right>
			<?php if ( !empty($edit) ) echo '<input type="submit" value="Cancel" name="frm_edit_cancel">&nbsp;'; ?>
			<input type="submit" value="<?php echo (( !empty($edit) ) ? 'Update Forum':'Add Forum'); ?>" name="frm_submit">
		</td>
	</tr>
		
</table>
<input type="hidden" name="cat_id" value="<?php echo $cat_id; ?>">
<?php
	if ( !empty($edit) ) echo '<input type="hidden" name="edit" value="'.$edit.'">';
?>
</form>
<?php } else { ?>
<a href="admforum.php?cat_id=<?php echo $cat->id; ?>">Cancel</a>
<?php } ?>

<br><br>
<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td nowrap><font size=-2>Forum name</font></td>
	<td><font size=-2>Description</font></td>
	<td nowrap><font size=-2>Password Posting</font></td>
	<td align="center"><font size=-2>Action</font></td>
	<td><font size=-2>Category</font></td>
	<td><font size=-2>Position</font></td>
</tr>
<?php
	$frm->get_cat_forums($cat_id);
	$frm->resetfrm();
	
	$i=1;

	$move_ct = create_cat_select('dest_cat', empty($dest_cat)?'':$dest_cat, $cat_id);
	
	while ( $frm->nextfrm() ) {
		$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
		
		if ( !empty($edit) && $edit==$frm->id ) $bgcolor =' bgcolor="#ffb5b5"';
		if ( !empty($chpos) ) {
			if ( $chpos == $frm->view_order ) $bgcolor =' bgcolor="#ffb5b5"';
			
			if ( $chpos != $frm->view_order && $chpos != $frm->view_order-1 ) {
				echo '<tr bgcolor="#efefef"><td align=center colspan=9><a href="admforum.php?chpos='.$chpos.'&newpos='.$frm->view_order.'&cat_id='.$frm->cat_id.'&'._rsid.'">Place Here</a></td></tr>';
			}
		}
			
		if( !empty($move_ct) )	
			$cat_name = '<form method="post">'._hs.'<input type="hidden" name="chcat_src" value="'.$frm->id.'"><input type="submit" name="btn_chcat" value="Move To:"> '.$move_ct.'</form>'; 
		else
			$cat_name = $cat->name;
		
		echo "<tr$bgcolor><td>".$frm->name."</td><td>".((strlen($frm->descr )>30)?substr($frm->descr, 0, 30).'...':$frm->descr)."&nbsp;</td><td>".(($frm->passwd_posting=='Y')?'Yes':'No')."</td><td nowrap>[<a href=\"admforum.php?cat_id=$cat_id&edit=".$frm->id."&"._rsid."\">Edit</a>] [<a href=\"admforum.php?cat_id=$cat_id&act=del&del=".$frm->id."&"._rsid."\">Delete</a>]</td><td nowrap>$cat_name</td><td>[<a href=\"admforum.php?chpos=".$frm->view_order."&cat_id=".$frm->cat_id."&"._rsid."\">Change</a>]</td></tr>";
	}
	
	if ( !empty($chpos) && $chpos != $frm->view_order ) {
		echo '<tr bgcolor="#efefef"><td align=center colspan=9><a href="admforum.php?chpos='.$chpos.'&newpos='.$frm->view_order.'&cat_id='.$frm->cat_id.'&'._rsid.'">Place Here</a></td></tr>';
	}
?>
</table>
<?php require('admclose.html'); ?>