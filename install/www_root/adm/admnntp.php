<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admnntp.php,v 1.2 2002/08/02 12:41:54 hackie Exp $
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
	fud_use('nntp_adm.inc', TRUE);

	list($ses, $usr) = initadm();
	
	if( $HTTP_POST_VARS['nntp_edit_cancel'] ) {
		header("Location: admnntp.php?"._rsid);
		exit;
	}
	
	$nntp_adm = new fud_nntp_adm;
	
	if( $edit ) 
		$nntp_adm->get($edit);
	else if ( $del )
		$nntp_adm->get($del);
		
	if( $HTTP_POST_VARS['nntp_forum_id'] ) {
		fetch_vars('nntp_', $nntp_adm, $HTTP_POST_VARS);
		
		if( $HTTP_POST_VARS['edit'] )
			$nntp_adm->sync();
		else
			$nntp_adm->add();
		
		header("Location: admnntp.php?"._rsid);	
		exit;			
	}
	else if( is_numeric($HTTP_GET_VARS['edit']) )
		export_vars('nntp_', $nntp_adm);
	else if( is_numeric($HTTP_GET_VARS['del']) ) {
		$nntp_adm->del();
		
		header("Location: admnntp.php?"._rsid);
		exit;	
	}
	else { /* Set the some default values */
		$nntp_nntp_post_apr = $nntp_complex_reply_match = 'N';
		$nntp_frm_post_apr = $nntp_allow_nntp_attch = 'Y';
		$nntp_timeout = 25;
		$nntp_port = 119;
	}
	
	cache_buster();
	include('admpanel.php'); 

	if( $GLOBALS['FILE_LOCK'] == 'Y' )
		echo '<font color="#ff0000" size="+3">You MUST UNLOCK the forum\'s files before you can run the newsgroup importing script(s).</font><p>';
?>
<form method="post" name="frm_forum" action="admnntp.php">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>Newsgroup Server:<br><font size="-1">The ip or the hostname of your newsgroup server.</font></td>
		<td><input type="text" name="nntp_server" value="<?php echo htmlspecialchars($nntp_server); ?>" maxlength=255></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Newsgroup Server Port:</td>
		<td><input type="text" name="nntp_port" value="<?php echo $nntp_port; ?>" maxlength=10></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Newsgroup Server Timeout:<br><font size="-1">Number of seconds to wait for the nntp server to respond.</font></td>
		<td><input type="text" name="nntp_timeout" value="<?php echo $nntp_timeout; ?>" maxlength=10></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Newsgroup:<br><font size="-1">The name of the newsgroup to import.</font></td>
		<td><input type="text" name="nntp_newsgroup" value="<?php echo $nntp_newsgroup; ?>" maxlength=255></td>
	</tr>

	<tr>
		<td colspan=2><br></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Authentication Method:<br><font size="-1">The authentication method to use when connecting to nntp server.</font></td>
		<td><select name="nntp_auth">
			<option value="NONE">None</option>
			<?php
				if( $nntp_auth == 'ORIGINAL' )
					$opt1 = ' selected';
				else if ( $nntp_auth == 'SIMPLE' )
					$opt1 = ' selected';
				
				echo '<option value="ORIGINAL"'.$opt1.'>Original</option><option value="SIMPLE"'.$opt2.'>Simple</option>';
			?>
			</select>
		</td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>Login:<br><font size="-1">Not needed if authentication is not being used.</font></td>
		<td><input type="text" name="nntp_login" value="<?php echo htmlspecialchars($nntp_login); ?>" maxlength=255></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Password:<br><font size="-1">Not needed if authentication is not being used.</font></td>
		<td><input type="text" name="nntp_pass" value="<?php echo htmlspecialchars($nntp_pass); ?>" maxlength=255></td>
	</tr>

	<tr>
		<td colspan=2><br></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>
			Forum:<br>
			<font size="-1">Messages imported from the newsgroup will be imported into this forum.
			It is <b>**highly recommeded**</b> that you setup a seperate forum for each newsgroup.</font>
		</td>
		<td><select name="nntp_forum_id">
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
					".$GLOBALS['DBHOST_TBL_PREFIX']."mlist.id IS NULL AND 
					(".$GLOBALS['DBHOST_TBL_PREFIX']."nntp.id IS NULL OR ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp.id=".intzero($edit).")
				ORDER BY ".$GLOBALS['DBHOST_TBL_PREFIX']."cat.view_order, ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.view_order");
						
			while( list($fid,$fname) = db_rowarr($r) )
				echo '<option value="'.$fid.'"'.($fid!=$nntp_forum_id?'':' selected').'>'.$fname.'</option>';
			qf($r);
		?>
		</select></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>
			Moderate Newsgroup Posts:<br>
			<font size="-1">Any posts from the newsgroup would first need to be approved by moderator(s) before
			they are made visible on the forum.</font>
		</td>
		<td><?php draw_select('nntp_nntp_post_apr', "No\nYes", "N\nY", yn($nntp_nntp_post_apr)); ?></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>
			Syncronize Forum Posts to Newsgroup:<br>
			<font size="-1">If enabled, posts made by forum members inside the forum will be sent to the
			newsgroup by the forum. On the newsgroup the posts would appear on behalf of the user who
			has made the post.</font>
		</td>
		<td><?php draw_select('nntp_allow_frm_post', "No\nYes", "N\nY", yn($nntp_allow_frm_post)); ?></td>
	</tr>	
		
	<tr bgcolor="#bff8ff">
		<td>
			Moderate Forum Posts:<br>
			<font size="-1">If enabled, any posts made by forum members in the forum would need to be first approved
			by the moderator(s) before they are syncronized to the newsgroup or appear in the forum.</font>
		</td>
		<td><?php draw_select('nntp_frm_post_apr', "No\nYes", "N\nY", yn($nntp_frm_post_apr)); ?></td>
	</tr>	
	
	<tr bgcolor="#bff8ff">
		<td>
			Allow Newsgroup Attachments:<br>
			<font size="-1">If enabled, ANY file attachment attached to a message in the newsgroup will be
			imported into the forum regardless of any limitations imposed on file attachments within the forum.</font>
		</td>
		<td><?php draw_select('nntp_allow_nntp_attch', "No\nYes", "N\nY", yn($nntp_allow_nntp_attch)); ?></td>
	</tr>	
	
	<tr bgcolor="#bff8ff">
		<td>
			Slow Reply Match:<br>
			<font size="-1">Certain mail client do sent send necessary headers needed to determine if a message is
			a reply to an existing message. If this option is enabled and normally avaliable reply headers are not there,
			the forum will try to determine if message is a reply by comparing the message's subject to subjects of existing
			messages in the forum.</font>
		</td>
		<td><?php draw_select('nntp_complex_reply_match', "No\nYes", "N\nY", yn($nntp_complex_reply_match)); ?></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right>
			<?php if ( !empty($edit) ) echo '<input type="submit" value="Cancel" name="nntp_edit_cancel">&nbsp;'; ?>
			<input type="submit" value="<?php echo (( !empty($edit) ) ? 'Update Newsgroup Rule':'Add Newsgroup Rule'); ?>" name="nntp_submit">
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
		<td nowrap>Newsgroup Rule</td>
		<td>Forum</td>
		<td>Exec Line</td>
		<td align="center">Action</td>
	</tr>
	
	<?php
		$r = q("SELECT ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp.*, ".$GLOBALS['DBHOST_TBL_PREFIX']."forum.name AS frm_name FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp INNER JOIN ".$GLOBALS['DBHOST_TBL_PREFIX']."forum ON ".$GLOBALS['DBHOST_TBL_PREFIX']."nntp.forum_id=".$GLOBALS['DBHOST_TBL_PREFIX']."forum.id");
		$i=0;
		while( $obj = db_rowobj($r) ) {
			if( $obj->id != $edit ) 
				$bgcolor = ($i++%2)?' bgcolor="#fffee5"':'';
			else
				$bgcolor = ' bgcolor="#ffb5b5"';
				
			echo '<tr'.$bgcolor.'><td>'.htmlspecialchars($obj->newsgroup).'</td><td>'.$obj->frm_name.'</td>
			<td nowrap><font size="-1">'.$GLOBALS['DATA_DIR'].'scripts/nntp.php '.$obj->id.' </font></td>
			<td>[<a href="admnntp.php?edit='.$obj->id.'&'._rsid.'">Edit</a>] [<a href="admnntp.php?del='.$obj->id.'&'._rsid.'">Delete</a>]</td></tr>';
		}
		qf($r);
	?>
</table>
<p>
<b>***Notes***</b><br>
Exec Line parameter in the table above shows the execution line that you will need to place in your cron.
It is recommended you run the script on a small interval, we recommend a 2-3 minute interval.
<br>
Cron example:
<pre>
*/2 * * * * /home/forum/forum/scripts/nntp.php 1
</pre>
<?php require('admclose.html'); ?>