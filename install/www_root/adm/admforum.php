<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admforum.php,v 1.56 2009/04/03 14:55:59 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function get_max_upload_size()
{
	$us = strtolower(ini_get('upload_max_filesize'));
	$size = (int) $us;
	if (strpos($us, 'm') !== false) {
		$size *= 1024 * 1024;
	} else if (strpos($us, 'k') !== false) {
		$size *= 1024;
	}
	return $size;
}

/* main program */
	require('./GLOBALS.php');

	/* this is here so we get the cat_id when cancel button is clicked */
	$cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : (isset($_POST['cat_id']) ? (int)$_POST['cat_id'] : '');

	fud_use('adm.inc', true);
	fud_use('forum_adm.inc', true);
	fud_use('cat.inc', true);
	fud_use('widgets.inc', true);
	fud_use('logaction.inc');

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
	$max_upload_size = get_max_upload_size();

	if (!$cat_id || ($cat_name = q_singleval('SELECT name FROM '.$tbl.'cat WHERE id='.$cat_id)) === NULL) {
		exit('no such category');
	}

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_POST['frm_submit']) && !empty($_POST['frm_name'])) {
		if ($_POST['frm_max_attach_size'] > $max_upload_size) {
			$_POST['frm_max_attach_size'] = floor($max_upload_size / 1024);
		}
		/* (int) $_POST['frm_anon_forum']  is unused */
		$_POST['frm_forum_opt'] = (int) $_POST['frm_mod_attach'] | (int) $_POST['frm_moderated'] | (int) $_POST['frm_passwd_posting'] | (int) $_POST['frm_tag_style'];

		$frm = new fud_forum;

		if (!$edit) {
			fud_use('groups_adm.inc', true);
			fud_use('groups.inc');
			$frm->cat_id = $cat_id;
			$frm->add($_POST['frm_pos']);
			rebuild_forum_cat_order();
			logaction(_uid, 'ADDFORUM', $frm->id);
		} else {
			$frm->sync($edit, $cat_id);
			logaction(_uid, 'SYNCFORUM', $edit);
			$edit = '';
		}
	}
	if ($edit && ($c = db_arr_assoc('SELECT * FROM '.$tbl.'forum WHERE id='.$edit))) {
		foreach ($c as $k => $v) {
			${'frm_'.$k} = $v;
		}
	} else {
		$c = get_class_vars('fud_forum');
		foreach ($c as $k => $v) {
			${'frm_'.$k} = '';
		}

		/* some default values for new forums */
		$frm_pos = 'LAST';
		$frm_max_attach_size = floor($max_upload_size / 1024);
		$frm_message_threshold = '0';
		$frm_max_file_attachments = '1';
		$frm_forum_opt = 16;
	}

	if (isset($_GET['chpos'], $_GET['newpos'])) {
		frm_change_pos((int)$_GET['chpos'], (int)$_GET['newpos'], $cat_id);
		rebuild_forum_cat_order();
		unset($_GET['chpos'], $_GET['newpos']);
	} else if (isset($_GET['del'])) {
		if (frm_move_forum((int)$_GET['del'], 0, $cat_id)) {
			rebuild_forum_cat_order();
			logaction(_uid, 'FRMMARKDEL', q_singleval('SELECT name FROM '.$tbl.'forum WHERE id='.(int)$_GET['del']));
		}
	} else if (isset($_POST['btn_chcat'], $_POST['frm_id'], $_POST['cat_id'], $_POST['dest_cat'])) {
		if (frm_move_forum((int)$_POST['frm_id'], (int)$_POST['dest_cat'], $cat_id)) {
			rebuild_forum_cat_order();
			$r = db_saq('SELECT f.name, c1.name, c2.name FROM '.$tbl.'forum f INNER JOIN '.$tbl.'cat c1 ON c1.id='.$cat_id.' INNER JOIN '.$tbl.'cat c2 ON c2.id='.(int)$_POST['dest_cat'].' WHERE f.id='.(int)$_POST['frm_id']);
			logaction(_uid, 'CHCATFORUM', 'Moved forum "'.$r[0].'" from category: "'.$r[1].'" to category: "'.$r[2].'"');
		}
	}
	if (isset($_GET['o'], $_GET['ot'])) {
		if (in_array($_GET['ot'], array('name', 'descr', 'date_created'))) {
			$i = 0;
			$r = q("SELECT id FROM {$tbl}forum WHERE cat_id={$cat_id} ORDER BY {$_GET['ot']} ".((int)$_GET['o'] ? 'ASC' : 'DESC'));
			while ($o = db_rowarr($r)) {
				q("UPDATE {$tbl}forum SET view_order=".++$i." WHERE id={$o[0]}");
			}
			rebuild_forum_cat_order();
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Editing forums for <?php echo $cat_name; ?></h2>
<?php
if (!isset($_GET['chpos'])) {
?>
<a href="admcat.php?<?php echo __adm_rsid; ?>">&laquo; Back to categories</a><br />

<form method="post" id="frm_forum" action="admforum.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Forum Name:</td>
		<td><input type="text" name="frm_name" value="<?php echo $frm_name; ?>" maxlength="100" /></td>
	</tr>

	<tr class="field">
		<td valign="top">Description</td>
		<td><textarea nowrap="nowrap" name="frm_descr" cols="28" rows="5"><?php echo htmlspecialchars($frm_descr); ?></textarea>
	</tr>

	<tr class="field">
		<td>External redirect:<br /><font size="-2">Enter URL if this is a virtual forum that redirects to an external location.</font></td>
		<td><input type="text" name="frm_url_redirect" value="<?php echo $frm_url_redirect; ?>" maxlength="255" /></td>
	</tr>

	<tr class="field">
		<td>Tag Style<br /><font size="-2">What markup language may users use</font></td>
		<td><?php draw_select('frm_tag_style', "FUD ML\nHTML\nNone", "16\n0\n8", ($frm_forum_opt & 8 ? 8 : ($frm_forum_opt & 16 ? 16 : 0))); ?></td>
	</tr>

	<tr class="field">
		<td>Password Posting<br /><font size="-2">Posting is only allowed with a knowledge of a password</font></td>
		<td><?php draw_select('frm_passwd_posting', "No\nYes", "0\n4", $frm_forum_opt & 4); ?></td>
	</tr>

	<tr class="field">
		<td>Posting Password</td>
		<td><input type="passwd" maxlength="32" name="frm_post_passwd" value="<?php echo htmlspecialchars($frm_post_passwd); ?>" /></td>
	</tr>

	<tr class="field">
		<td>Moderated Forum</td>
		<td><?php draw_select('frm_moderated', "No\nYes", "0\n2", $frm_forum_opt & 2); ?></td>
	</tr>

	<tr class="field">
		<td>Max Attachment Size:<br /><font size="-2">Your php's maximum file upload size is <b><?php echo floor($max_upload_size / 1024); ?></b> KB.<br />You cannot set the forum's attachment size limit higher than that.</font></td>
		<td><input type="text" name="frm_max_attach_size" value="<?php echo $frm_max_attach_size; ?>" maxlength="100" size="5" />kb</td>
	</tr>

	<tr class="field">
		<td>Max Number of file Attachments:</td>
		<td><input type="text" name="frm_max_file_attachments" value="<?php echo $frm_max_file_attachments; ?>" maxlength="100" size="5" /></td>
	</tr>

	<tr class="field">
		<td>Can moderator(s) bypass attachment limits:</td>
		<td><?php draw_select('frm_mod_attach', "No\nYes", "0\n32", $frm_forum_opt & 32); ?></td>
	</tr>

	<tr class="field">
		<td>Notify moderator(s) of all new messages:</td>
		<td><?php draw_select('frm_mod_notify', "No\nYes", "0\n64", $frm_forum_opt & 64); ?></td>
	</tr>

	<tr class="field">
		<td>Message Threshold<br /><font size="-2">Maximum size of the message DISPLAYED<br />without the reveal link (0 == unlimited) </font></td>
		<td><input type="text" name="frm_message_threshold" value="<?php echo $frm_message_threshold; ?>" size="5" /> bytes</td>
	</tr>

	<tr class="field">
		<td><a name="frm_icon_pos">Forum Icon</a></td>
		<td><input type="text" name="frm_forum_icon" value="<?php echo $frm_forum_icon; ?>" /> <a href="javascript://" onclick="window.open('admiconsel.php?type=1&amp;<?php echo __adm_rsid; ?>', 'admiconsel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100')">[SELECT ICON]</a></td>
	</tr>

<?php if (!$edit) { ?>
	<tr class="field">
		<td>Insert Position</td>
		<td><?php draw_select('frm_pos', "Last\nFirst", "LAST\nFIRST", ''); ?></td>
	</tr>
<?php } ?>

	<tr class="fieldaction">
		<td colspan="2" align="right">
<?php
	if ($edit) {
		echo '<input type="submit" value="Cancel" name="btn_cancel" /> ';
	}
?>
			<input type="submit" value="<?php echo ($edit ? 'Update Forum' : 'Add Forum'); ?>" name="frm_submit" />
		</td>
	</tr>

</table>
<input type="hidden" name="cat_id" value="<?php echo $cat_id; ?>" />
<?php
	if ($edit) {
		echo '<input type="hidden" name="edit" value="'.$edit.'" />';
	}
	echo '</form>';
} else {
	echo '<a href="admforum.php?cat_id='.$cat_id.'&'.__adm_rsid.'">Cancel</a>';
}
?>
<table class="datatable">
<tr class="fieldtopic"><td valign="top" nowrap="nowrap">Reorder All Forums by:</td></tr>
<tr><td class="field"><font size="-2">
	<b>Forum Name</b> [ <a href="admforum.php?o=1&amp;ot=name&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>">Ascending</a> - <a href="admforum.php?o=0&amp;ot=name&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>">Descending</a> ]<br />
	<b>Forum Description</b> [ <a href="admforum.php?o=1&amp;ot=descr&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>">Ascending</a> - <a href="admforum.php?o=0&amp;ot=descr&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>">Descending</a> ]<br />
	<b>Forum Creation Date</b> [ <a href="admforum.php?o=1&amp;ot=date_created&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>">Ascending</a> - <a href="admforum.php?o=0&amp;ot=date_created&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>">Descending</a> ]<br />
</font></td></tr>
</table>
<br />
<table class="resulttable fulltable">
<tr class="resulttopic">
	<td nowrap="nowrap"><font size="-2">Forum name</font></td>
	<td><font size="-2">Description</font></td>
	<td nowrap="nowrap"><font size="-2">Password Posting</font></td>
	<td align="center"><font size="-2">Action</font></td>
	<td><font size="-2">Category</font></td>
	<td><font size="-2">Position</font></td>
</tr>
<?php
	$move_ct = create_cat_select('dest_cat', '', $cat_id);

	$i = 1;
	$c = uq('SELECT id, name, descr, forum_opt, view_order FROM '.$tbl.'forum WHERE cat_id='.$cat_id.' ORDER BY view_order');
	while ($r = db_rowobj($c)) {
		if ($edit == $r->id) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
		if (isset($_GET['chpos'])) {
			if ($_GET['chpos'] == $r->view_order) {
				$bgcolor = ' class="resultrow2"';
			} else if ($_GET['chpos'] != ($r->view_order - 1)) {
				echo '<tr class="field"><td align="center" colspan="9"><a href="admforum.php?chpos='.$_GET['chpos'].'&amp;newpos='.($r->view_order - ($_GET['chpos'] < $r->view_order ? 1 : 0)).'&amp;cat_id='.$cat_id.'&amp;'.__adm_rsid.'">Place Here</a></td></tr>';
			}
			$lp = $r->view_order;
		}
		$cat_name = !$move_ct ? $cat_name : '<form method="post" action="admforum.php">'._hs.'<input type="hidden" name="frm_id" value="'.$r->id.'" /><input type="hidden" name="cat_id" value="'.$cat_id.'" /><input type="submit" name="btn_chcat" value="Move To: " /> '.$move_ct.'</form>';
		echo '<tr '.$bgcolor.'><td>'.$r->name.'</td><td><font size="-2">'.htmlspecialchars(substr($r->descr, 0, 30)).'</font></td><td>'.($r->forum_opt & 4 ? 'Yes' : 'No').'</td><td nowrap="nowrap">[<a href="admforum.php?cat_id='.$cat_id.'&amp;edit='.$r->id.'&amp;'.__adm_rsid.'">Edit</a>] [<a href="admforum.php?cat_id='.$cat_id.'&del='.$r->id.'&'.__adm_rsid.'">Delete</a>]</td><td nowrap="nowrap">'.$cat_name.'</td><td nowrap="nowrap">[<a href="admforum.php?chpos='.$r->view_order.'&amp;cat_id='.$cat_id.'&amp;'.__adm_rsid.'">Change</a>]</td></tr>';
	}
	unset($c);
	if (isset($lp)) {
		echo '<tr class="field""><td align="center" colspan="9"><a href="admforum.php?chpos='.$_GET['chpos'].'&amp;newpos='.($lp + 1).'&amp;cat_id='.$cat_id.'&amp;'.__adm_rsid.'">Place Here</a></td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
