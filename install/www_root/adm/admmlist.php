<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admmlist.php,v 1.6 2002/07/29 12:43:37 hackie Exp $
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
	
	fud_use("widgets.inc", TRUE);
	fud_use("util.inc");
	fud_use('cookies.inc');
	fud_use('adm.inc', TRUE);
	fud_use('objutil.inc');	
	fud_use('logaction.inc');
	fud_use('mlist.inc', TRUE);

function format_regex(&$regex, &$opts)
{
	if( empty($regex) ) return;

	$s = strpos($regex, '/');
	$e = strrpos($regex, '/');
	
	$opts = substr($regex, $e+1);
	$regex = addslashes(substr($regex, $s, ($e-$s)));
}
	
	list($ses, $usr) = initadm();
	
	if( $HTTP_POST_VARS['ml_edit_cancel'] ) {
		header("Location: admmlist.php?"._rsid);
		exit;
	}
	
	$mlist = new fud_mlist;
	
	if( $edit) 
		$mlist->get($edit);
	else if ( $del )
		$mlist->get($del);
	
	if( $HTTP_POST_VARS['ml_forum_id'] ) {
		fetch_vars('ml_', $mlist, $HTTP_POST_VARS);
		
		if( $mlist->subject_regex_haystack ) 
			$mlist->subject_regex_haystack = '/'.$mlist->subject_regex_haystack.'/'.$HTTP_POST_VARS['ml_subject_regex_haystack_opt'];
	
		if( $mlist->body_regex_haystack ) 	
			$mlist->body_regex_haystack = '/'.$mlist->body_regex_haystack.'/'.$HTTP_POST_VARS['ml_body_regex_haystack_opt'];
		
		if( $HTTP_POST_VARS['edit'] )
			$mlist->sync();
		else
			$mlist->add();
		
		header("Location: admmlist.php?"._rsid);	
		exit;			
	}
	else if( is_numeric($HTTP_GET_VARS['edit']) ) {
		export_vars('ml_', $mlist);
		format_regex($ml_subject_regex_haystack, $ml_subject_regex_haystack_opt);
		format_regex($ml_body_regex_haystack, $ml_body_regex_haystack_opt);
		
		$ml_body_regex_needle = addslashes($ml_body_regex_needle);
		$ml_subject_regex_needle = addslashes($ml_subject_regex_needle);
	}
	else if( is_numeric($HTTP_GET_VARS['del']) ) {
		$mlist->del();
		
		header("Location: admmlist.php?"._rsid);
		exit;	
	}
	else { /* Set the some default values */
		$ml_mlist_post_apr = $ml_allow_mlist_html = $ml_complex_reply_match = 'N';
		$ml_frm_post_apr = $ml_allow_mlist_attch = 'Y';
	}
	
	cache_buster();
	include('admpanel.php'); 

	if( $GLOBALS['FILE_LOCK'] == 'Y' ) {
		echo '<font color="#ff0000" size="+3">You MUST UNLOCK the forum\'s files before you can run the mailing list importing scripts.</font><p>';
	}
?>
<form method="post" name="frm_forum" action="admmlist.php">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>Mailing List Name:<br><font size="-1">Internal field, for your reference only.</font></td>
		<td><input type="text" name="ml_name" value="<?php echo htmlspecialchars($ml_name); ?>" maxlength=255></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>
			Forum:<br>
			<font size="-1">Messages imported from the mailing list will be imported into this forum.
			It is <b>**highly recommeded**</b> that you setup a seperate forum for each mailing list.</font>
		</td>
		<td><select name="ml_forum_id">
		<?php
			$r = q("SELECT 
					".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id,
					".$GLOBALS['DBHOST_TBL_PREFIX']."forum.name 
				FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."forum 
				INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."cat 
					ON ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.cat_id=".$GLOBALS['DBHOST_TBL_PREFIX']."cat.id 
				LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp
					ON ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id=".$GLOBALS['DBHOST_TBL_PREFIX']."nntp.forum_id 
				LEFT JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist
					ON ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id=".$GLOBALS['DBHOST_TBL_PREFIX']."mlist.forum_id 	
				WHERE
					".$GLOBALS['DBHOST_TBL_PREFIX']."nntp.id IS NULL AND 
					(".$GLOBALS['DBHOST_TBL_PREFIX']."mlist.id IS NULL OR ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist.id=".intzero($edit).")
				ORDER BY ".$GLOBALS['DBHOST_TBL_PREFIX']."cat.view_order, ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.view_order");
			
			while( list($fid,$fname) = db_rowarr($r) )
				echo '<option value="'.$fid.'"'.($fid!=$ml_forum_id?'':' selected').'>'.$fname.'</option>';
			qf($r);
		?>
		</select></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>
			Moderate Mailing List Posts:<br>
			<font size="-1">Any posts from the mailing list would 1st need to be approved by moderator(s) before
			they are made visible on the forum.</font>
		</td>
		<td><?php draw_select('ml_mlist_post_apr', "No\nYes", "N\nY", yn($ml_mlist_post_apr)); ?></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>
			Syncronize Forum Posts to Mailing List:<br>
			<font size="-1">If enabled, posts made by forum members inside the forum will be sent to the
			mailing list by the forum. On the mailing list the posts would appear on behalf of the user who
			has made the post.</font>
		</td>
		<td><?php draw_select('ml_allow_frm_post', "No\nYes", "N\nY", yn($ml_allow_frm_post)); ?></td>
	</tr>	
		
	<tr bgcolor="#bff8ff">
		<td>
			Moderate Forum Posts:<br>
			<font size="-1">If enabled, any posts made by forum members in the forum would need to be 1st approved
			by the moderator(s) before they are syncronized to the mailing list or appear in the forum.</font>
		</td>
		<td><?php draw_select('ml_frm_post_apr', "No\nYes", "N\nY", yn($ml_frm_post_apr)); ?></td>
	</tr>	
	
	<tr bgcolor="#bff8ff">
		<td>
			Allow Mailing List Attachments:<br>
			<font size="-1">If enabled, ANY file attachment attached to a message on the mailing list will be
			imported into the forum regardless of any limitations imposed on file attachments within the forum.</font>
		</td>
		<td><?php draw_select('ml_allow_mlist_attch', "No\nYes", "N\nY", yn($ml_allow_mlist_attch)); ?></td>
	</tr>	
	
	<tr bgcolor="#bff8ff">
		<td>
			Allow HTML in Mailing List Messages:<br>
			<font size="-1">If enabled, HTML contained within mailing list messages that are imported will not be
			stripped. <b>**not recommended**</b></font>
		</td>
		<td><?php draw_select('ml_allow_mlist_html', "No\nYes", "N\nY", yn($ml_allow_mlist_html)); ?></td>
	</tr>	
	
	<tr bgcolor="#bff8ff">
		<td>
			Slow Reply Match:<br>
			<font size="-1">Certain mail client do sent send necessary headers needed to determine if a message is
			a reply to an existing message. If this option is enabled and normally avaliable reply headers are not there,
			the forum will try to determine if message is a reply by comparing the message's subject to subjects of existing
			messages in the forum.</font>
		</td>
		<td><?php draw_select('ml_complex_reply_match', "No\nYes", "N\nY", yn($ml_complex_reply_match)); ?></td>
	</tr>
	
	<tr>
		<td colspan=2><br></td>
	</tr>
	
	<tr bgcolor="#bff8ff">	
		<td colspan=2><font size="-1"><b>Optional</b> Subject Mangling<br><font size="-1">This field allows you to specify a regular expression, that
		will be applied to the subjects of messages imported from the mailing list. This is useful to remove
		automatically appended strings that are often used to identify mailing list messages. ex. [PHP]</font></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Replace mask:</td>
		<td nowrap>/<input type="text" name="ml_subject_regex_haystack" value="<?php echo htmlspecialchars(stripslashes($ml_subject_regex_haystack)); ?>">/<input type="text" name="ml_subject_regex_haystack_opt" size=3 value="<? echo htmlspecialchars(stripslashes($ml_subject_regex_haystack_opt)); ?>"></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Replace with:</td>
		<td><input type="text" name="ml_subject_regex_needle" value="<?php echo (empty($ml_subject_regex_needle)?'':htmlspecialchars(stripslashes($ml_subject_regex_needle))); ?>"></td>
	</tr>
	
	<tr>
		<td colspan=2><br></td>
	</tr>
	
	<tr bgcolor="#bff8ff">	
		<td colspan=2><font size="-1"><b>Optional</b> Body Mangling<br><font size="-1">This field allows you to specify a regular expression, that
		will be applied to the bodies of messages imported from the mailing list. It is recommended you use this option
		to remove the automatically prepended text added by the mailing list to the bottom of each message. This text often
		informs the user on how to unsubscribe from the list and is merely a waste of space in a forum enviroment.</font>
		</td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Replace mask:</td>
		<td nowrap>/<input type="text" name="ml_body_regex_haystack" value="<?php echo htmlspecialchars(stripslashes($ml_body_regex_haystack)); ?>">/<input type="text" name="ml_body_regex_haystack_opt" size=3 value="<? echo htmlspecialchars(stripslashes($ml_body_regex_haystack_opt)); ?>"></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Replace with:</td>
		<td><input type="text" name="ml_body_regex_needle" value="<?php echo (empty($ml_body_regex_needle)?'':htmlspecialchars(stripslashes($ml_body_regex_needle))); ?>"></td>
	</tr>
	
	<tr>
		<td colspan=2><br></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right>
			<?php if ( !empty($edit) ) echo '<input type="submit" value="Cancel" name="ml_edit_cancel">&nbsp;'; ?>
			<input type="submit" value="<?php echo (( !empty($edit) ) ? 'Update Mailing List Rule':'Add Mailing List Rule'); ?>" name="ml_submit">
		</td>
	</tr>
</table>
<?php
	if ( !empty($edit) ) echo '<input type="hidden" name="edit" value="'.$edit.'">';
?>
</form>
<br><br>
<table border=0 cellspacing=3 cellpadding=2 width="100%">
	<tr bgcolor="#e5ffe7">
		<td nowrap>Mailing List Rule</td>
		<td>Forum</td>
		<td>Exec Line</td>
		<td align="center">Action</td>
	</tr>
	
	<?php
		$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist.*, ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.name AS frm_name FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."forum ON ".$GLOBALS['DBHOST_TBL_PREFIX']."mlist.forum_id=".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id");
		$i=0;
		while( $obj = db_rowobj($r) ) {
			if( $obj->id != $edit ) 
				$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
			else
				$bgcolor = ' bgcolor="#ffb5b5"';
				
			echo '<tr'.$bgcolor.'><td>'.htmlspecialchars($obj->name).'</td><td>'.$obj->frm_name.'</td>
			<td nowrap><font size="-1">'.$GLOBALS['DATA_DIR'].'scripts/maillist.php '.$obj->id.'</font></td>
			<td>[<a href="admmlist.php?edit='.$obj->id.'&'._rsid.'">Edit</a>] [<a href="admmlist.php?del='.$obj->id.'&'._rsid.'">Delete</a>]</td></tr>';
		}
		qf($r);
	?>
</table>
<p>
<b>***Notes***</b><br>
Exec Line parameter in the table above shows the execution line that you will need to pipe
the mailing list messages to.<br> Procmail example:
<pre>
:0:
* ^TO_.*php-general@lists.php.net 
| /home/forum/F/test/maillist.php 1
</pre>
<?php require('admclose.html'); ?>