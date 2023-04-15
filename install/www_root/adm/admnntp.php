<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('nntp_adm.inc', true);

	require($WWW_ROOT_DISK .'adm/header.php');
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (!empty($_POST['nntp_newsgroup']) && !empty($_POST['nntp_forum_id'])) {
		$nntp_adm = new fud_nntp_adm;
		if ($edit) {
			$nntp_adm->sync($edit);
			echo successify('Newsgroup rule successfully updated.');
			$edit = '';
		} else {
			$nntp_adm->add();
			echo successify('Newsgroup rule successfully added (see <a href="#list">list</a> at bottom of page).');
		}
	} else if (isset($_GET['del'])) {
		nntp_del((int)$_GET['del']);
		echo successify('Newsgroup rule successfully deleted.');
	} else if (isset($_GET['trk']) && ($nn = db_sab('SELECT * FROM '. $tbl .'nntp WHERE id='. (int)$_GET['trk']))) {
		@unlink($ERROR_PATH .'.nntp/'. $nn->server.'-'. $nn->newsgroup .'.lock');
		@unlink($ERROR_PATH .'.nntp/'. $nn->server.'-'. $nn->newsgroup);
		nntp_reset((int)$_GET['trk']);
		echo successify('Newsgroup tracker was successfully cleard. The next load will start with the first message in the group.');
	}

	if (isset($_GET['edit']) && $edit && ($o = db_sab('SELECT * FROM '. $tbl .'nntp WHERE id='. $edit))) {
		foreach ($o as $k => $v) {
			${'nntp_'. $k} = $v;
		}
	} else { /* Set the some default values. */
		foreach (get_class_vars('fud_nntp_adm') as $k => $v) {
			${'nntp_'. $k} = $v ?? '';
		}
	}

?>
<h2>Newsgroup Manager</h2>

<h3><?php echo $edit ? '<a name="edit">Edit Rule:</a>' : 'Add New Rule:'; ?></h3>
<form method="post" id="frm_forum" action="admnntp.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Newsgroup Name:<br /><font size="-1">The name of the newsgroup to import.</font></td>
		<td><input type="text" name="nntp_newsgroup" value="<?php echo $nntp_newsgroup; ?>" size="30" maxlength="255" /></td>
	</tr>

	<tr class="field">
		<td>Newsgroup Server:<br /><font size="-1">The ip or the hostname of your newsgroup server.</font></td>
		<td><input type="text" name="nntp_server" value="<?php echo htmlspecialchars($nntp_server); ?>" size="30" maxlength="255" /></td>
	</tr>

	<tr class="field">
		<td>Newsgroup Server Port:<br /><font size="-1">Port number the server is listeneing on. Default is 119.</font></td>
		<td><input type="number" name="nntp_port" value="<?php echo $nntp_port; ?>" size="10" maxlength="10" type="number" /></td>
	</tr>

	<tr class="field">
		<td>Newsgroup Server Timeout:<br /><font size="-1">Number of seconds to wait for the nntp server to respond.</font></td>
		<td><input type="number" name="nntp_timeout" value="<?php echo $nntp_timeout; ?>" size="10" maxlength="10" type="number" /></td>
	</tr>

	<tr>
		<td colspan="2"><br /></td>
	</tr>

	<tr class="field">
		<td>Authentication Method:<br /><font size="-1">The authentication method to use when connecting to nntp server.</font></td>
		<td><?php draw_select('nntp_auth', "None\nOriginal\nSimple", "64\n128\n0", ($nntp_nntp_opt & 128 ? 128 : ($nntp_nntp_opt & 64 ? 64 : 0))); ?></td>
	</tr>

	<tr class="field">
		<td>Login:<br /><font size="-1">Not needed if authentication is not being used.</font></td>
		<td><input type="text" id="nntp_login" name="nntp_login" value="<?php echo htmlspecialchars($nntp_login); ?>" size="30" maxlength="255" /></td>
	</tr>

	<tr class="field">
		<td>Password:<br /><font size="-1">Not needed if authentication is not being used.</font></td>
		<td><input type="text" id="nntp_pass" name="nntp_pass" value="<?php echo htmlspecialchars($nntp_pass); ?>" size="30" maxlength="255" /></td>
	</tr>

	<script>
	jQuery(document).ready(function() {
		/* Hide 'Login' & 'Password' fields if 'Authentication Method' is NONE. */
		jQuery('#nntp_auth').change(function() {
			if ( jQuery('#nntp_auth option:selected').val() == 64 ) {
				jQuery('#nntp_login, #nntp_pass').parent().parent().hide('slow');
			} else {
				jQuery('#nntp_login, #nntp_pass').parent().parent().show('slow');
			}
		});
		jQuery('#nntp_auth').change();
	});
	</script>

	<tr>
		<td colspan="2"><br /></td>
	</tr>

	<tr class="field">
		<td>
			Forum:<br />
			<font size="-1">Messages imported from the newsgroup will be imported into this forum.
			It is <b>**highly recommended**</b> that you setup a separate forum for each newsgroup.</font>
		</td>
		<td><select name="nntp_forum_id"><option></option>
		<?php
			$c = uq('SELECT f.id, f.name, c.name
				FROM '. $tbl .'forum f
				INNER JOIN '. $tbl .'cat c ON f.cat_id=c.id
				LEFT JOIN '. $tbl .'nntp n ON f.id=n.forum_id
				LEFT JOIN '. $tbl .'mlist ml ON f.id=ml.forum_id
				WHERE ml.id IS NULL AND (n.id IS NULL OR n.id='. (int)$edit.')
				ORDER BY c.parent, c.view_order, f.view_order');
			while ($r = db_rowarr($c)) {
				echo '<option value="'. $r[0] .'"'.($r[0] != $nntp_forum_id ? '' : ' selected="selected"') .'>'. $r[2] .' &raquo; '. $r[1] .'</option>';
			}
			unset($c);
		?>
		</select></td>
	</tr>

	<tr class="field">
		<td>
			Moderate Newsgroup Posts:<br />
			<font size="-1">Any posts from the newsgroup would first need to be approved by moderator(s) before
			they are made visible on the forum.</font>
		</td>
		<td><?php draw_select('nntp_nntp_post_apr', "No\nYes", "0\n1", ($nntp_nntp_opt & 1 ? 1 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Synchronize Forum Posts to Newsgroup:<br />
			<font size="-1">If enabled, posts made by forum members inside the forum will be sent to the
			newsgroup by the forum. On the newsgroup the posts would appear on behalf of the user who
			has made the post.</font>
		</td>
		<td><?php draw_select('nntp_allow_frm_post', "No\nYes", "0\n2", ($nntp_nntp_opt & 2 ? 2 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Moderate Forum Posts:<br />
			<font size="-1">If enabled, any posts made by forum members in the forum would need to be first approved
			by the moderator(s) before they are synchronized to the newsgroup or appear in the forum.</font>
		</td>
		<td><?php draw_select('nntp_frm_post_apr', "No\nYes", "0\n4", ($nntp_nntp_opt & 4 ? 4 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Allow Newsgroup Attachments:<br />
			<font size="-1">If enabled, ANY file attachment attached to a message in the newsgroup will be
			imported into the forum regardless of any limitations imposed on file attachments within the forum.</font>
		</td>
		<td><?php draw_select('nntp_allow_nntp_attch', "No\nYes", "0\n8", ($nntp_nntp_opt & 8 ? 8 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Complex Reply Matching:<br />
			<font size="-1">Certain mail client do sent send necessary headers needed to determine if a message is
			a reply to an existing message. If this option is enabled and normally available reply headers are not there,
			the forum will try to determine if message is a reply by comparing the message's subject to subjects of existing
			messages in the forum.</font>
		</td>
		<td><?php draw_select('nntp_complex_reply_match', "No\nYes", "0\n16", ($nntp_nntp_opt & 16 ? 16 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Create New Users:<br />
			<font size="-1">When importing messages from a newsgroup, should a new user be created for every newsgroup
			author, who cannot be matched against an existing forum user. If this option is set to 'No', then all
			imported newsgroup messages who's authors cannot be matched against existing forum members will be attributed
			to the anonymous user.</font>
		</td>
		<td><?php draw_select('nntp_create_users', "No\nYes", "0\n32", ($nntp_nntp_opt & 32 ? 32 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Skip Non-Forum Users:<br />
			<font size="-1">When importing messages, should the messages posted from users who cannot be matched
			to existing forum members be ignored.</font>
		</td>
		<td><?php draw_select('nntp_skip_non_forum_users', "Yes\nNo", "256\n0", ($nntp_nntp_opt & 256 ? 256 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>Max Messages to Import:<br />
			<font size="-1">Maximum number of messages to import per run.
			Leaving the value at 0 or empty means unlimited. When doing a 1st
			import which may need to import a lot of messages, since there is no
			starting point, it is important to set this option to prevent the 
			import script from timing out.
			</font>
		</td>
		<td><input type="number" name="nntp_imp_limit" value="<?php echo htmlspecialchars($nntp_imp_limit); ?>" size="10" maxlength="10" type="number" /></td>
	</tr>

	<tr class="field">
		<td>Forum Signature:<br />
			<font size="-1">A string of text to append to the end of every message 
			sent from the forum back to the newsgroup.</font>
		</td>
		<td><textarea name="nntp_custom_sig" rows="5" cols="40"><?php echo htmlspecialchars($nntp_custom_sig); ?></textarea></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right">
			<?php if ($edit) echo '<input type="submit" value="Cancel" name="btn_cancel" />&nbsp;'; ?>
			<input type="submit" value="<?php echo ($edit ? 'Update Newsgroup Rule' : 'Add Newsgroup Rule'); ?>" name="nntp_submit" />
		</td>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>" />
</form>

<h3><a name="list">Available rules</a></h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th nowrap="nowrap">Newsgroup Rule</th>
	<th>Forum</th>
	<th>Exec Line</th>
	<th><abbr title="Last imported message. Used to track posts and prevent importing of duplicate content.">Tracker</abbr></th>
	<th align="center">Action</th>
</tr></thead>
<?php
	$c = uq('SELECT n.id, n.newsgroup, n.tracker, f.name FROM '. $tbl .'nntp n INNER JOIN '. $tbl .'forum f ON n.forum_id=f.id');
	$i = 0;
	while ($r = db_rowarr($c)) {
		$i++;
		$bgcolor = ($edit == $r[0]) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		echo '<tr'. $bgcolor .'><td>'. htmlspecialchars($r[1]) .'</td><td>'. $r[3] .'</td>
			<td nowrap="nowrap">nntp.php '. $r[0] .'</td>
			<td nowrap="nowrap">'. $r[2] .'</td>
			<td>[<a href="admnntp.php?edit='. $r[0] .'&amp;'. __adm_rsid .'#edit">Edit</a>] [<a href="admnntp.php?del='. $r[0] .'&amp;'. __adm_rsid .'">Delete</a>]
			[<a href="admnntp.php?trk='. $r[0] .'&amp;'. __adm_rsid .'">Clear Tracker</a>]</td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="5" align="center">No rules. Define some above.</td></tr>';
	}
?>
</table>
<br /><br />
<b>***Notes***</b><br />
<p>After setting up rules you can schedule or run ad hoc imports from the forum's <a href="admjobs.php?<?php echo __adm_rsid; ?>">Job Administration System</a>.</p>

<p><p>Alternatively, add the <i>Exec Line</i> in the table above to your system's job scheduler. Windows users can use <a href="http://en.wikipedia.org/wiki/Schtasks">schtasks.exe</a> to schedule tasks.
Here is a Linux <a href="http://en.wikipedia.org/wiki/Cron">cron</a> example:
<pre>
*/2 * * * * <?php echo realpath($GLOBALS['DATA_DIR'] .'scripts/nntp.php'); ?> 1
</pre></p>

<p>If you synchronize Forum Posts to newsgroups, it is recommended to run the script on a small interval. For example, every 2-3 minutes.</p>

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
