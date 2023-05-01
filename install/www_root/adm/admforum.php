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

/** Return PHP's maximum upload size in bytes. */
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

/* main */
	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('forum_adm.inc', true);
	fud_use('cat.inc', true);
	fud_use('widgets.inc', true);
	fud_use('logaction.inc');

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];
		
	// AJAX call to reorder forums.
	if (!empty($_POST['ajax']) && $_POST['ajax'] == 'reorder') {
		$new_order = 1;
		foreach ($_POST['order'] as $id) {
			q('UPDATE '. $tbl .'forum SET view_order = '. $new_order++ .' WHERE id = '. $id);
		}
		rebuild_forum_cat_order();
		exit('Forums successfully reordered.');	// End AJAX call.
	}
	
	require($WWW_ROOT_DISK .'adm/header.php');
	$max_upload_size = get_max_upload_size();

	/* This is here so we get the cat_id parameter when cancel button is clicked. */
	$cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : (isset($_POST['cat_id']) ? (int)$_POST['cat_id'] : '');
	if (empty($cat_id)) {	// Or get it from DB.
		$cat_id = q_singleval('SELECT MIN(id) FROM '. $tbl .'cat');
	}
	$cat_name = q_singleval('SELECT name FROM '. $tbl .'cat WHERE id='. (int)$cat_id);

	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_POST['frm_submit']) && !empty($_POST['frm_name'])) {
		if ($_POST['frm_max_attach_size'] > $max_upload_size) {
			$_POST['frm_max_attach_size'] = floor($max_upload_size / 1024);
		}
		$_POST['frm_forum_opt'] = (int) $_POST['frm_mod_notify'] | (int) $_POST['frm_mod_attach'] | (int) $_POST['frm_moderated'] | (int) $_POST['frm_passwd_posting'] | (int) $_POST['frm_tag_style'];

		$frm = new fud_forum;

		if (!$edit) {
			fud_use('groups_adm.inc', true);
			fud_use('groups.inc');
			$frm->cat_id = $cat_id;
			$frm->add($_POST['frm_pos']);
			rebuild_forum_cat_order();
			logaction(_uid, 'ADDFORUM', $frm->id);
			echo successify('Forum was successfully created.');
		} else {
			$frm->sync($edit, $cat_id);
			logaction(_uid, 'SYNCFORUM', $edit);
			$edit = '';
			echo successify('Forum was successfully updated.');
		}
	}
	if ($edit && ($c = db_arr_assoc('SELECT * FROM '. $tbl .'forum WHERE id='. $edit))) {
		foreach ($c as $k => $v) {
			${'frm_'.$k} = $v ?? '';
		}
	} else {
		$c = get_class_vars('fud_forum');
		foreach ($c as $k => $v) {
			${'frm_'.$k} = '';
		}

		/* Some default values for new forums. */
		$frm_pos = 'LAST';
		$frm_max_attach_size = floor($max_upload_size / 1024);
		$frm_message_threshold = '0';
		$frm_max_file_attachments = '5';
		$frm_forum_opt = 16;
	}

	if (isset($_GET['chpos'], $_GET['newpos'])) {
		frm_change_pos((int)$_GET['chpos'], (int)$_GET['newpos'], $cat_id);
		rebuild_forum_cat_order();
		unset($_GET['chpos'], $_GET['newpos']);
		echo successify('Forum position was successfully set.');
	} else if (isset($_GET['del'])) {
		if (frm_move_forum((int)$_GET['del'], 0, $cat_id)) {
			rebuild_forum_cat_order();
			$frm_name = q_singleval('SELECT name FROM '. $tbl .'forum WHERE id='. (int)$_GET['del']);
			logaction(_uid, 'FRMMARKDEL', (int)$_GET['del']);
			echo successify('The <b>'. $frm_name .'</b> forum was moved to the <b><a href="admforumdel.php?'. __adm_rsid .'">trash bin</a></b>.');
		}
	} else if (isset($_POST['btn_chcat'], $_POST['frm_id'], $_POST['cat_id'], $_POST['dest_cat'])) {
		if (frm_move_forum((int)$_POST['frm_id'], (int)$_POST['dest_cat'], $cat_id)) {
			rebuild_forum_cat_order();
			$r = db_saq('SELECT f.name, c1.name, c2.name FROM '. $tbl .'forum f INNER JOIN '. $tbl .'cat c1 ON c1.id='. $cat_id .' INNER JOIN '. $tbl .'cat c2 ON c2.id='. (int)$_POST['dest_cat'] .' WHERE f.id='. (int)$_POST['frm_id']);
			logaction(_uid, 'CHCATFORUM', (int)$_POST['frm_id']);
			echo successify('Forum was successfully moved.');
		}
	}

	// Reorder forum display order.
	if (isset($_GET['o'], $_GET['ot'])) {
		if (in_array($_GET['ot'], array('name', 'descr', 'date_created'))) {
			$i = 0;
			$r = q('SELECT id FROM '. $tbl .'forum WHERE cat_id='. $cat_id .' ORDER BY '. $_GET['ot'] . ((int)$_GET['o'] ? ' ASC' : ' DESC'));
			while ($o = db_rowarr($r)) {
				q('UPDATE '. $tbl .'forum SET view_order='. ++$i .' WHERE id='. $o[0]);
			}
			rebuild_forum_cat_order();
		}
	}
	
	// Get list of categories.
	$cat_sel = create_cat_select('cat_id', $cat_id, 0);
?>
<h2>Forum Management System</h2>

<fieldset class="fieldtopic">
<legend><b>Change category:</b></legend>
<table width="100%">
<tr><td>
<?php
	if (empty($cat_sel)) {
		pf(errorify('Your forum doesn\'t have any categories.<br />Please use the Category Manager to create some before returning to this screen.'));
	} else {
?>
		<form method="post" action="admforum.php">
			Manage forums in catagory:
			<?php echo _hs; echo $cat_sel; ?>&nbsp;
			<input type="submit" name="frm_submit" value="Change" />
		</form>
<?php	} ?>
</td><td>
	<nobr>[ <a title="List forums in the recycle bin" href="admforumdel.php?<?php echo __adm_rsid; ?>">Deleted Forums</a> ]</nobr>
</td></tr>
</table>
</fieldset>

<?php
if (empty($cat_sel)) {
	require($WWW_ROOT_DISK .'adm/footer.php');
	exit;
}
if (!isset($_GET['chpos'])) {	// Hide this if we are changing forum order.
	echo '<h3>'. ($edit ? '<a name="edit">Edit forum:</a>' : 'Add forum to <i>'. $cat_name .'</i>:') .'</h3>';
?>

<form method="post" id="frm_forum" action="admforum.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Forum Name:<br /><font size="-2">The forum's name.</font></td>
		<td><input type="text" name="frm_name" value="<?php echo $frm_name; ?>" maxlength="100" /></td>
	</tr>

	<tr class="field">
		<td valign="top">Description:<br /><font size="-2">Description that will be shown on the forums main index page. Can contain HTML.</font></td>
		<td><textarea nowrap="nowrap" name="frm_descr" cols="28" rows="5"><?php echo htmlspecialchars($frm_descr); ?></textarea></td>
	</tr>

<?php
	$forum_names = "forum index\ndon't display it";
	$forum_ids   = "0\n-1";
	$c = uq('SELECT id, name FROM '. $tbl .'forum WHERE cat_id='. $cat_id .' AND id != '. (empty($edit) ? 0 : $edit) .' ORDER BY name');
	while ($r = db_rowobj($c)) {
		$forum_names .= "\n". $r->name;
		$forum_ids   .= "\n". $r->id;
	}
?>
	<tr class="field">
		<td valign="top">Display forum in:<br /><font size="-2">Indicate if this is a subforum (not shown on main index, but in the context of another forum).</font></td>
		<td><?php draw_select('frm_parent', $forum_names, $forum_ids, $frm_parent); ?></td>
	</tr>

	<tr class="field">
		<td>External redirect:<br /><font size="-2">Enter URL if this is a virtual forum that redirects to an external location.</font></td>
		<td><input type="text" name="frm_url_redirect" value="<?php echo $frm_url_redirect; ?>" maxlength="255" /></td>
	</tr>

	<tr class="field">
		<td>Tag Style:<br /><font size="-2">What markup language may users use. The tag style must match the content that will be stored in the form.</font></td>
		<td><?php draw_select('frm_tag_style', "BBCode\nHTML\nNone", "16\n0\n8", ($frm_forum_opt & 8 ? 8 : ($frm_forum_opt & 16 ? 16 : 0))); ?></td>
	</tr>

	<tr class="field">
		<td>Password Posting:<br /><font size="-2">Posting is only allowed with a knowledge of a password.</font></td>
		<td><?php draw_select('frm_passwd_posting', "No\nYes", "0\n4", $frm_forum_opt & 4); ?></td>
	</tr>

	<tr class="field">
		<td>Posting Password:<br /><font size="-2">Password when <i>Password Posting</i> is enabled.</font></td>
		<td><input maxlength="32" name="frm_post_passwd" value="<?php echo htmlspecialchars($frm_post_passwd); ?>" /></td>
	</tr>

	<tr class="field">
		<td>Moderated Forum:<br /><font size="-2">Messages must be approved before they will be visible on the forum.</font></td>
		<td><?php draw_select('frm_moderated', "No\nYes", "0\n2", $frm_forum_opt & 2); ?></td>
	</tr>

	<tr class="field">
		<td>Max Attachment Size:<br /><font size="-2">Your php's maximum file upload size is <b><?php echo floor($max_upload_size / 1024); ?></b> KB.<br />You cannot set the forum's attachment size limit higher than that.</font></td>
		<td><input type="number" name="frm_max_attach_size" value="<?php echo $frm_max_attach_size; ?>" maxlength="100" size="5" />KB</td>
	</tr>

	<tr class="field">
		<td>Max Number of file Attachments:</td>
		<td><input type="number" name="frm_max_file_attachments" value="<?php echo $frm_max_file_attachments; ?>" maxlength="100" size="5" /></td>
	</tr>

	<tr class="field">
		<td>Can moderators bypass attachment limits:</td>
		<td><?php draw_select('frm_mod_attach', "No\nYes", "0\n32", $frm_forum_opt & 32); ?></td>
	</tr>

	<tr class="field">
		<td>Notify moderators of all new messages:</td>
		<td><?php draw_select('frm_mod_notify', "No\nYes", "0\n64", $frm_forum_opt & 64); ?></td>
	</tr>

	<tr class="field">
		<td>Message Threshold:<br /><font size="-2">Maximum size of the message DISPLAYED<br />without the reveal link (0 == unlimited).</font></td>
		<td><input type="number" name="frm_message_threshold" value="<?php echo $frm_message_threshold; ?>" size="5" /> bytes</td>
	</tr>

	<tr class="field">
		<td><a name="frm_icon_pos">Forum Icon:</a><br /><font size="-2">Icon to display next to this forum.</font></td>
		<td><input type="text" name="frm_forum_icon" value="<?php echo $frm_forum_icon; ?>" /> <a href="javascript://" onclick="window.open('admiconsel.php?type=1&amp;<?php echo __adm_rsid; ?>', 'admiconsel', 'menubar=false,scrollbars=yes,resizable=yes,height=300,width=500,screenX=100,screenY=100')">[SELECT ICON]</a></td>
	</tr>

<?php if (!$edit) { ?>
	<tr class="field">
		<td>Insert Position:</td>
		<td><?php draw_select('frm_pos', "Last\nFirst", "LAST\nFIRST", ''); ?></td>
	</tr>
<?php } ?>

	<tr class="fieldaction">
		<td colspan="2" align="right">
<?php
	if ($edit) {
		echo '<input type="hidden" name="edit" value="'.$edit.'" />';
		echo '<input type="submit" value="Cancel" name="btn_cancel" /> ';
	}
?>
			<input type="submit" value="<?php echo ($edit ? 'Update Forum' : 'Add Forum'); ?>" name="frm_submit" />
		</td>
	</tr>

</table>
<input type="hidden" name="cat_id" value="<?php echo $cat_id; ?>" />
</form>

<h3>Forums in <i><a name="forumlist"><?php echo $cat_name; ?></a></i>:</h3>
<?php
} else {	// Busy changing position.
	echo '<a href="admforum.php?cat_id='.$cat_id.'&amp;'.__adm_rsid.'">Cancel reorder operation</a>';
}
?>

<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th nowrap="nowrap">Forum name</th>
	<th>Description</th>
	<th>Category</th>
	<th align="center">Action</th>
</tr></thead>
<tbody id="sortable">
<?php
	$move_ct = create_cat_select('dest_cat', '', $cat_id);

	$i = 0;
	$c = uq('SELECT id, name, descr, forum_opt, view_order FROM '. $tbl .'forum WHERE cat_id='. $cat_id .' ORDER BY view_order');
	while ($r = db_rowobj($c)) {
		$i++;
		$bgcolor = ($edit == $r->id) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');
		if (isset($_GET['chpos'])) {
			if ($_GET['chpos'] == $r->view_order) {
				$bgcolor = ' class="resultrow3"';
			} else if ($_GET['chpos'] != ($r->view_order - 1)) {
				echo '<tr class="field"><td align="center" colspan="9"><a href="admforum.php?chpos='.$_GET['chpos'] .'&amp;newpos='. ($r->view_order - ($_GET['chpos'] < $r->view_order ? 1 : 0)) .'&amp;cat_id='. $cat_id .'&amp;'. __adm_rsid. '">Place Here</a></td></tr>';
			}
			$lp = $r->view_order;
		}
		$r->descr = $r->descr ?? '';
		$cat_name = !$move_ct ? $cat_name : '<form method="post" action="admforum.php">'. _hs .'<input type="hidden" name="frm_id" value="'. $r->id .'" /><input type="hidden" name="cat_id" value="'. $cat_id .'" /><input type="submit" name="btn_chcat" value="Move To: " /> '. $move_ct .'</form>';
		echo '<tr id="order_'. $r->id .'"'. $bgcolor .' title="'. htmlspecialchars($r->descr) .'">
			<td><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>'. $r->name .'</td>
			<td><font size="-1">'. htmlspecialchars(substr($r->descr, 0, 30)) .'...</font></td>
			<td nowrap="nowrap">'. $cat_name .'</td>
			<td nowrap="nowrap">
				[<a href="admforum.php?cat_id='. $cat_id .'&amp;edit='. $r->id .'&amp;'. __adm_rsid .'#edit">Edit</a>]
				[<a href="admforum.php?cat_id='. $cat_id .'&amp;del='. $r->id .'&amp;'. __adm_rsid .'">Delete</a>]
				[<a href="admforum.php?chpos='. $r->view_order .'&amp;cat_id='. $cat_id .'&amp;'. __adm_rsid .'">Change Position</a>]
			</td></tr>';
	}
	unset($c);
	if (isset($lp)) {
		echo '<tr class="field""><td align="center" colspan="6"><a href="admforum.php?chpos='. $_GET['chpos'] .'&amp;newpos='. ($lp + 1) .'&amp;cat_id='. $cat_id .'&amp;'. __adm_rsid .'">Place Here</a></td></tr>';
	}
	if (!$i) {
		echo '<tr class="field"><td colspan="6"><center>No forums found. Define some above.</center></td></tr>';
	}
?>
</tbody></table>

<br />
<table class="datatable" align="right">
<tr class="fieldtopic"><td valign="top" nowrap="nowrap">Reorder All Forums by:</td></tr>
<tr><td class="field"><font size="-2">
	<b>Forum Name:</b> [ <a href="admforum.php?o=1&amp;ot=name&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>#forumlist">Ascending</a> - <a href="admforum.php?o=0&amp;ot=name&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>#forumlist">Descending</a> ]<br />
	<b>Description:</b> [ <a href="admforum.php?o=1&amp;ot=descr&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>#forumlist">Ascending</a> - <a href="admforum.php?o=0&amp;ot=descr&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>#forumlist">Descending</a> ]<br />
	<b>Creation Date:</b> [ <a href="admforum.php?o=1&amp;ot=date_created&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>#forumlist">Ascending</a> - <a href="admforum.php?o=0&amp;ot=date_created&amp;cat_id=<?php echo $cat_id; ?>&amp;<?php echo __adm_rsid; ?>#forumlist">Descending</a> ]<br />
</font></td></tr>
</table>
<br clear="right" />

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
