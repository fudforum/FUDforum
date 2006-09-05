<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admnntp.php,v 1.32 2006/09/05 12:58:00 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('nntp_adm.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_POST['nntp_forum_id'])) {
		$nntp_adm = new fud_nntp_adm;
		if ($edit) {
			$nntp_adm->sync($edit);
			$edit = '';
		} else {
			$nntp_adm->add();
		}
	} else if (isset($_GET['del'])) {
		nntp_del((int)$_GET['del']);
	} else if (isset($_GET['trk']) && ($nn = db_sab('SELECT * FROM '.$tbl.'nntp WHERE id='.(int)$_GET['trk']))) {
		@unlink($ERROR_PATH.'.nntp/'.$nn->server.'-'.$nn->newsgroup.'.lock');
		@unlink($ERROR_PATH.'.nntp/'.$nn->server.'-'.$nn->newsgroup);
	}

	if (isset($_GET['edit']) && $edit && ($o = db_sab('SELECT * FROM '.$tbl.'nntp WHERE id='.$edit))) {
		foreach ($o as $k => $v) {
			${'nntp_' . $k} = $v;
		}
	} else { /* Set the some default values */
		foreach (get_class_vars('fud_nntp_adm') as $k => $v) {
			${'nntp_' . $k} = $v;
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	if ($FUD_OPT_2 & 8388608) {
		echo '<font color="#ff0000" size="+3">You MUST UNLOCK the forum\'s files before you can run the newsgroup importing script(s).</font><p>';
	}
?>
<h2>Newsgroup Manager</h2>
<form method="post" name="frm_forum" action="admnntp.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Newsgroup Server:<br><font size="-1">The ip or the hostname of your newsgroup server.</font></td>
		<td><input type="text" name="nntp_server" value="<?php echo htmlspecialchars($nntp_server); ?>" maxlength=255></td>
	</tr>

	<tr class="field">
		<td>Newsgroup Server Port:</td>
		<td><input type="text" name="nntp_port" value="<?php echo $nntp_port; ?>" maxlength=10></td>
	</tr>

	<tr class="field">
		<td>Newsgroup Server Timeout:<br><font size="-1">Number of seconds to wait for the nntp server to respond.</font></td>
		<td><input type="text" name="nntp_timeout" value="<?php echo $nntp_timeout; ?>" maxlength=10></td>
	</tr>

	<tr class="field">
		<td>Newsgroup:<br><font size="-1">The name of the newsgroup to import.</font></td>
		<td><input type="text" name="nntp_newsgroup" value="<?php echo $nntp_newsgroup; ?>" maxlength=255></td>
	</tr>

	<tr>
		<td colspan=2><br></td>
	</tr>

	<tr class="field">
		<td>Authentication Method:<br><font size="-1">The authentication method to use when connecting to nntp server.</font></td>
		<td><?php draw_select('nntp_auth', "None\nOriginal\nSimple", "64\n128\n0", ($nntp_nntp_opt & 128 ? 128 : ($nntp_nntp_opt & 64 ? 64 : 0))); ?></td>
	</tr>

	<tr class="field">
		<td>Login:<br><font size="-1">Not needed if authentication is not being used.</font></td>
		<td><input type="text" name="nntp_login" value="<?php echo htmlspecialchars($nntp_login); ?>" maxlength=255></td>
	</tr>

	<tr class="field">
		<td>Password:<br><font size="-1">Not needed if authentication is not being used.</font></td>
		<td><input type="text" name="nntp_pass" value="<?php echo htmlspecialchars($nntp_pass); ?>" maxlength=255></td>
	</tr>

	<tr>
		<td colspan=2><br></td>
	</tr>

	<tr class="field">
		<td>
			Forum:<br>
			<font size="-1">Messages imported from the newsgroup will be imported into this forum.
			It is <b>**highly recommeded**</b> that you setup a seperate forum for each newsgroup.</font>
		</td>
		<td><select name="nntp_forum_id">
		<?php
			$c = uq('SELECT f.id, f.name, c.name
				FROM '.$tbl.'forum f
				INNER JOIN '.$tbl.'cat c ON f.cat_id=c.id
				LEFT JOIN '.$tbl.'nntp n ON f.id=n.forum_id
				LEFT JOIN '.$tbl.'mlist ml ON f.id=ml.forum_id
				WHERE ml.id IS NULL AND (n.id IS NULL OR n.id='.(int)$edit.')
				ORDER BY c.view_order, f.view_order');
			while ($r = db_rowarr($c)) {
				echo '<option value="'.$r[0].'"'.($r[0] != $nntp_forum_id ? '' : ' selected').'>'.$r[2].' &raquo; '.$r[1].'</option>';
			}
			unset($c);
		?>
		</select></td>
	</tr>

	<tr class="field">
		<td>
			Moderate Newsgroup Posts:<br>
			<font size="-1">Any posts from the newsgroup would first need to be approved by moderator(s) before
			they are made visible on the forum.</font>
		</td>
		<td><?php draw_select('nntp_nntp_post_apr', "No\nYes", "0\n1", ($nntp_nntp_opt & 1 ? 1 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Syncronize Forum Posts to Newsgroup:<br>
			<font size="-1">If enabled, posts made by forum members inside the forum will be sent to the
			newsgroup by the forum. On the newsgroup the posts would appear on behalf of the user who
			has made the post.</font>
		</td>
		<td><?php draw_select('nntp_allow_frm_post', "No\nYes", "0\n2", ($nntp_nntp_opt & 2 ? 2 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Moderate Forum Posts:<br>
			<font size="-1">If enabled, any posts made by forum members in the forum would need to be first approved
			by the moderator(s) before they are syncronized to the newsgroup or appear in the forum.</font>
		</td>
		<td><?php draw_select('nntp_frm_post_apr', "No\nYes", "0\n4", ($nntp_nntp_opt & 4 ? 4 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Allow Newsgroup Attachments:<br>
			<font size="-1">If enabled, ANY file attachment attached to a message in the newsgroup will be
			imported into the forum regardless of any limitations imposed on file attachments within the forum.</font>
		</td>
		<td><?php draw_select('nntp_allow_nntp_attch', "No\nYes", "0\n8", ($nntp_nntp_opt & 8 ? 8 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Slow Reply Match:<br>
			<font size="-1">Certain mail client do sent send necessary headers needed to determine if a message is
			a reply to an existing message. If this option is enabled and normally avaliable reply headers are not there,
			the forum will try to determine if message is a reply by comparing the message's subject to subjects of existing
			messages in the forum.</font>
		</td>
		<td><?php draw_select('nntp_complex_reply_match', "No\nYes", "0\n16", ($nntp_nntp_opt & 16 ? 16 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Create New Users:<br>
			<font size="-1">When importing messages from a newsgroup, should a new user be created for every newsgroup
			author, who cannot be matched against an existing forum user. If this option is set to 'No', then all
			imported newsgroup messages who's authors cannot be matched against existing forum members will be attributed
			to the anonymous user.</font>
		</td>
		<td><?php draw_select('nntp_create_users', "No\nYes", "0\n32", ($nntp_nntp_opt & 32 ? 32 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Skip Non-Forum Users:<br>
			<font size="-1">When importing messages, should the messages posted from users who cannot be matched
			to existing forum members be ignored.</font>
		</td>
		<td><?php draw_select('nntp_skip_non_forum_users', "Yes\nNo", "256\n0", ($nntp_nntp_opt & 256 ? 256 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>Max Messages to Import:<br>
			<font size="-1">Maximum number of messages to import per run.
			Leaving the value at 0 or empty means unlimited. When doing a 1st
			import which may need to import a lot of messages, since there is no
			starting point, it is important to set this option to prevent the 
			import script from timing out.
			</font>
		</td>
		<td><input type="text" name="nntp_imp_limit" value="<?php echo htmlspecialchars($nntp_imp_limit); ?>" maxlength=10></td>
	</tr>

	<tr class="field">
		<td>Forum Signature:<br>
			<font size="-1">A string of text to append to the end of every message 
			sent from the forum back to the newsgroup.</font>
		</td>
		<td><textarea name="nntp_custom_sig" rows="7" cols="30"><?php echo htmlspecialchars($nntp_custom_sig); ?></textarea></td>
	</tr>

	<tr class="fieldaction">
		<td colspan=2 align=right>
			<?php if ($edit) echo '<input type="submit" value="Cancel" name="btn_cancel">&nbsp;'; ?>
			<input type="submit" value="<?php echo ($edit ? 'Update Newsgroup Rule' : 'Add Newsgroup Rule'); ?>" name="nntp_submit">
		</td>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>
<br><br>
<table class="resulttable fulltable">
	<tr class="resulttopic">
		<td nowrap>Newsgroup Rule</td>
		<td>Forum</td>
		<td>Exec Line</td>
		<td align="center">Action</td>
	</tr>
<?php
	$c = uq('SELECT n.id, n.newsgroup, f.name FROM '.$tbl.'nntp n INNER JOIN '.$tbl.'forum f ON n.forum_id=f.id');
	$i = 1;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
		echo '<tr'.$bgcolor.'><td>'.htmlspecialchars($r[1]).'</td><td>'.$r[2].'</td>
			<td nowrap><font size="-1">'.$GLOBALS['DATA_DIR'].'scripts/nntp.php '.$r[0].' </font></td>
			<td>[<a href="admnntp.php?edit='.$r[0].'&'.__adm_rsidl.'">Edit</a>] [<a href="admnntp.php?del='.$r[0].'&'.__adm_rsidl.'">Delete</a>]
			[<a href="admnntp.php?trk='.$r[0].'&'.__adm_rsidl.'">Clear Tracker</a>]</td></tr>';
	}
	unset($c);
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
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
