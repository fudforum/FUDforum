<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admmlist.php,v 1.29 2004/08/09 10:46:06 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function format_regex(&$regex)
{
	if (empty($regex)) {
		return;
	}

	$s = strpos($regex, '/') + 1;
	$e = strrpos($regex, '/');

	$ret = substr($regex, $e + 1);
	$regex = substr($regex, $s, ($e - $s));

	return $ret;
}
	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('mlist.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_POST['ml_forum_id'])) {
		$mlist = new fud_mlist;
		if ($edit) {
			$mlist->sync($edit);
			$edit = '';
		} else {
			$mlist->add();
		}
	} else if (isset($_GET['del'])) {
		fud_mlist::del((int)$_GET['del']);
	}

	if (isset($_GET['edit']) && $edit && ($o = db_sab('SELECT * FROM '.$tbl.'mlist WHERE id='.$edit))) {
		foreach ($o as $k => $v) {
			${'ml_' . $k} = $v;
		}
		$ml_subject_regex_haystack_opt = format_regex($ml_subject_regex_haystack);
		$ml_body_regex_haystack_opt = format_regex($ml_body_regex_haystack);
	} else {
		$c = get_class_vars('fud_mlist');
		foreach ($c as $k => $v) {
			${'ml_' . $k} = $v;
		}
		$ml_subject_regex_haystack_opt = $ml_body_regex_haystack_opt = '';
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');

	if ($FUD_OPT_2 & 8388608) {
		echo '<font color="#ff0000" size="+3">You MUST UNLOCK the forum\'s files before you can run the mailing list importing scripts.</font><p>';
	}
?>
<h2>Mailing List Manager</h2>
<form method="post" name="frm_forum" action="admmlist.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Mailing List Email:<br><font size="-1">The email address of the mailing list.</font></td>
		<td><input type="text" name="ml_name" value="<?php echo htmlspecialchars($ml_name); ?>" maxlength=255></td>
	</tr>

	<tr class="field">
		<td>
			Forum:<br>
			<font size="-1">Messages imported from the mailing list will be imported into this forum.
			It is <b>**highly recommeded**</b> that you setup a seperate forum for each mailing list.</font>
		</td>
		<td><select name="ml_forum_id">
		<?php
			$c = uq('SELECT f.id, f.name, c.name
				FROM '.$tbl.'forum f
				INNER JOIN '.$tbl.'cat c ON f.cat_id=c.id
				LEFT JOIN '.$tbl.'nntp n ON f.id=n.forum_id
				LEFT JOIN '.$tbl.'mlist ml ON f.id=ml.forum_id
				WHERE n.id IS NULL AND (ml.id IS NULL OR ml.id='.(int)$edit.')
				ORDER BY c.view_order, f.view_order');
			while ($r = db_rowarr($c)) {
				echo '<option value="'.$r[0].'"'.($r[0] != $ml_forum_id ? '' : ' selected').'>'.$r[2].' &raquo; '.$r[1].'</option>';
			}
		?>
		</select></td>
	</tr>

	<tr class="field">
		<td>
			Moderate Mailing List Posts:<br>
			<font size="-1">Any posts from the mailing list would 1st need to be approved by moderator(s) before
			they are made visible on the forum.</font>
		</td>
		<td><?php draw_select('ml_mlist_post_apr', "No\nYes", "0\n1", ($ml_mlist_opt & 1 ? 1 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Syncronize Forum Posts to Mailing List:<br>
			<font size="-1">If enabled, posts made by forum members inside the forum will be sent to the
			mailing list by the forum. On the mailing list the posts would appear on behalf of the user who
			has made the post.</font>
		</td>
		<td><?php draw_select('ml_allow_frm_post', "No\nYes", "0\n2", ($ml_mlist_opt & 2 ? 2 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Moderate Forum Posts:<br>
			<font size="-1">If enabled, any posts made by forum members in the forum would need to be 1st approved
			by the moderator(s) before they are syncronized to the mailing list or appear in the forum.</font>
		</td>
		<td><?php draw_select('ml_frm_post_apr', "No\nYes", "0\n4", ($ml_mlist_opt & 4 ? 4 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Allow Mailing List Attachments:<br>
			<font size="-1">If enabled, ANY file attachment attached to a message on the mailing list will be
			imported into the forum regardless of any limitations imposed on file attachments within the forum.</font>
		</td>
		<td><?php draw_select('ml_allow_mlist_attch', "No\nYes", "0\n8", ($ml_mlist_opt & 8 ? 8 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Allow HTML in Mailing List Messages:<br>
			<font size="-1">If enabled, HTML contained within mailing list messages that are imported will not be
			stripped. <b>**not recommended**</b></font>
		</td>
		<td><?php draw_select('ml_allow_mlist_html', "No\nYes", "0\n16", ($ml_mlist_opt & 16 ? 16 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Slow Reply Match:<br>
			<font size="-1">Certain mail client do sent send necessary headers needed to determine if a message is
			a reply to an existing message. If this option is enabled and normally avaliable reply headers are not there,
			the forum will try to determine if message is a reply by comparing the message's subject to subjects of existing
			messages in the forum.</font>
		</td>
		<td><?php draw_select('ml_complex_reply_match', "No\nYes", "0\n32", ($ml_mlist_opt & 32 ? 32 : 0)); ?></td>
	</tr>

	<tr class="field">
		<td>
			Create New Users:<br>
			<font size="-1">When importing messages from the mailing list, should a new user be created for every mailing
			list author, who cannot be matched against an existing forum user. If this option is set to 'No', then all
			imported mailing list messages who's authors cannot be matched against existing forum members will be attributed
			to the anonymous user.</font>
		</td>
		<td><?php draw_select('ml_create_users', "Yes\nNo", "64\n0", ($ml_mlist_opt & 64 ? 64 : 0)); ?></td>
	</tr>

	<tr>
		<td colspan=2><br></td>
	</tr>

	<tr class="field">
		<td colspan=2><font size="-1"><b>Optional</b> Subject Mangling<br><font size="-1">This field allows you to specify a regular expression, that
		will be applied to the subjects of messages imported from the mailing list. This is useful to remove
		automatically appended strings that are often used to identify mailing list messages. ex. [PHP]</font></td>
	</tr>

	<tr class="field">
		<td>Replace mask:</td>
		<td nowrap>/<input type="text" name="ml_subject_regex_haystack" value="<?php echo htmlspecialchars($ml_subject_regex_haystack); ?>">/<input type="text" name="ml_subject_regex_haystack_opt" size=3 value="<?php echo htmlspecialchars(stripslashes($ml_subject_regex_haystack_opt)); ?>"></td>
	</tr>

	<tr class="field">
		<td>Replace with:</td>
		<td><input type="text" name="ml_subject_regex_needle" value="<?php htmlspecialchars($ml_subject_regex_needle); ?>"></td>
	</tr>

	<tr>
		<td colspan=2><br></td>
	</tr>

	<tr class="field">
		<td colspan=2><font size="-1"><b>Optional</b> Body Mangling<br><font size="-1">This field allows you to specify a regular expression, that
		will be applied to the bodies of messages imported from the mailing list. It is recommended you use this option
		to remove the automatically prepended text added by the mailing list to the bottom of each message. This text often
		informs the user on how to unsubscribe from the list and is merely a waste of space in a forum enviroment.</font>
		</td>
	</tr>

	<tr class="field">
		<td>Replace mask:</td>
		<td nowrap>/<input type="text" name="ml_body_regex_haystack" value="<?php echo htmlspecialchars($ml_body_regex_haystack); ?>">/<input type="text" name="ml_body_regex_haystack_opt" size=3 value="<?php echo htmlspecialchars(stripslashes($ml_body_regex_haystack_opt)); ?>"></td>
	</tr>

	<tr class="field">
		<td>Replace with:</td>
		<td><input type="text" name="ml_body_regex_needle" value="<?php echo htmlspecialchars($ml_body_regex_needle); ?>"></td>
	</tr>

	<tr>
		<td colspan=2><br></td>
	</tr>

	<tr class="field">
		<td colspan=2><font size="-1"><b>Optional</b> Custom Headers<br><font size="-1">This field allows you to specify custom headers, that
		will be appended to any existing headers sent by the forum when posting a message to the mailing list. To avoid problem
		enter each header on a seperate line and do not place blank lines.</font></td>
	</tr>

	<tr class="field">
		<td valign="top">Custom Headers:</td>
		<td nowrap><textarea nowrap cols=50 rows=5 name="ml_additional_headers"><?php echo htmlspecialchars($ml_additional_headers); ?></textarea></td>
	</tr>

	<tr>
		<td colspan=2><br></td>
	</tr>

	<tr class="fieldaction">
		<td colspan=2 align=right>
			<?php if ($edit) { echo '<input type="submit" value="Cancel" name="btn_cancel">&nbsp;'; } ?>
			<input type="submit" value="<?php echo ($edit ? 'Update Mailing List Rule' : 'Add Mailing List Rule'); ?>" name="ml_submit">
		</td>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>
<br>
<table class="resulttable fulltable">
	<tr class="resulttopic">
		<td nowrap>Mailing List Rule</td>
		<td>Forum</td>
		<td>Exec Line</td>
		<td align="center">Action</td>
	</tr>
<?php
	$c = uq('SELECT ml.id, ml.name, f.name FROM '.$tbl.'mlist ml INNER JOIN '.$tbl.'forum f ON f.id=ml.forum_id');
	$i = 1;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
		echo '<tr'.$bgcolor.'><td>'.htmlspecialchars($r[1]).'</td><td>'.$r[2].'</td>
		<td nowrap><font size="-1">'.$GLOBALS['DATA_DIR'].'scripts/maillist.php '.$r[0].'</font></td>
		<td>[<a href="admmlist.php?edit='.$r[0].'&'.__adm_rsidl.'">Edit</a>] [<a href="admmlist.php?del='.$r[0].'&'.__adm_rsidl.'">Delete</a>]</td></tr>';
	}
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
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
