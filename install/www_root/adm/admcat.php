<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admcat.php,v 1.27 2004/06/07 15:24:53 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

	require ('./GLOBALS.php');

	fud_use('adm.inc', true);
	fud_use('cat.inc', true);
	fud_use('widgets.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_POST['cat_submit'])) {
		if (!empty($_POST['cat_description'])) {
			$_POST['cat_description'] = ' - ' . $_POST['cat_description'];
		}
		$_POST['cat_cat_opt'] = ($_POST['cat_allow_collapse'] == 'Y' ? 1 : 0) | ($_POST['cat_default_view'] == 'COLLAPSED' ? 0 : 2);
		unset($_POST['cat_allow_collapse'], $_POST['cat_allow_collapse']);

		$cat = new fud_cat;
		if ($edit) {
			$cat->sync($edit);
			$edit = '';
		} else {
			$cat->add($_POST['cat_pos']);
			rebuild_forum_cat_order();
		}
	}
	if ($edit && ($c = db_arr_assoc('SELECT name, description, cat_opt FROM '.$tbl.'cat WHERE id='.$edit))) {
		foreach ($c as $k => $v) {
			${'cat_'.$k} = $v;
		}
		if ($cat_description && !strncmp($cat_description, ' - ', 3)) {
			$cat_description = substr($cat_description, 3);
		}
		$cat_opt = $c['cat_opt'];
	} else {
		$c = get_class_vars('fud_cat');
		foreach ($c as $k => $v) {
			${'cat_'.$k} = '';
		}
		$cat_pos = 'LAST';
		$cat_opt = 3;
	}

	if (isset($_GET['del'])) {
		$del = (int)$_GET['del'];
		db_lock($tbl.'cat WRITE, '.$tbl.'cat c WRITE, '.$tbl.'forum WRITE, '.$tbl.'forum f WRITE, '.$tbl.'fc_view WRITE');
		q_singleval('DELETE FROM '.$tbl.'cat WHERE id='.$del);
		if (db_affected()) {
			q('UPDATE '.$tbl.'forum SET cat_id=0 WHERE cat_id='.$del);
			cat_rebuild_order();
			rebuild_forum_cat_order();
		}
		db_unlock();
	}
	if (isset($_GET['chpos'], $_GET['newpos'])) {
		cat_change_pos((int)$_GET['chpos'], (int)$_GET['newpos']);
		rebuild_forum_cat_order();
		unset($_GET['chpos'], $_GET['newpos']);
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Category Management System</h2>
<?php
	if (!isset($_GET['chpos'])) {
?>
<form method="post" action="admcat.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Category Name:</td>
		<td><input type="text" name="cat_name" value="<?php echo htmlspecialchars($cat_name); ?>" maxLength=50></td>
	</tr>

	<tr class="field">
		<td>Description:</td>
		<td><input type="text" name="cat_description" value="<?php echo htmlspecialchars($cat_description); ?>" maxLength=255></td>
	</tr>

	<tr class="field">
		<td>Collapsible</td>
		<td><?php draw_select('cat_allow_collapse', "Yes\nNo", "Y\nN", $cat_opt & 1); ?></td>
	</tr>

	<tr class="field">
		<td>Default view: </td>
		<td><?php draw_select('cat_default_view', "Open\nCollapsed", "OPEN\nCOLLAPSED", !($cat_opt & 2)); ?></td>
	</tr>

	<?php if (!$edit) { ?>

	<tr class="field">
		<td>Insert position:</td>
		<td><?php draw_select('cat_pos', "Last\nFirst", "LAST\nFIRST", $cat_pos); ?></td>
	</tr>

	<?php } ?>

	<tr class="fieldaction">
		<td colspan=2 align=right>
<?php
	if ($edit) {
		echo '<input type="submit" name="btn_cancel" value="Cancel">&nbsp;';
	}
?>
			<input type="submit" value="<?php echo ($edit ? 'Update Category' : 'Add Category'); ?>" name="cat_submit">
		</td>
	</tr>
</table>
<?php
		if ($edit) {
			echo '<input type="hidden" value="'.$edit.'" name="edit">';
		}
		echo '</form>';
	} else {
		echo '<a href="admcat.php?'.__adm_rsidl.'">Cancel</a><br>';
	}
?>
<br>
<table class="resulttable fulltable">
<tr class="resulttopic">
	<td>Category Name</td>
	<td>Description</td>
	<td>Collapsible</td>
	<td>Default View</td>
	<td align="center">Action</td>
	<td>Position</td>
</tr>
<?php
	$c = uq('SELECT * FROM '.$tbl.'cat ORDER BY view_order');
	$i = 1;
	while ($r = db_rowobj($c)) {
		if ($edit == $r->id) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2""' : ' class="resultrow1"';
		}
		if (isset($_GET['chpos'])) {
			if ($_GET['chpos'] == $r->view_order) {
				$bgcolor = ' class="resultrow2"';
			} else if ($_GET['chpos'] != ($r->view_order - 1)) {
				echo '<tr class="field"><td align=center colspan=7><font size=-1><a href="admcat.php?chpos='.$_GET['chpos'].'&newpos='.($r->view_order - ($_GET['chpos'] < $r->view_order ? 1 : 0)).'&'.__adm_rsidl.'">Place Here</a></font></td></tr>';
			}
			$lp = $r->view_order;
		}
		if ($r->description && !strncmp($r->description, ' - ', 3)) {
			$r->description = substr($r->description, 3);
		}

		echo '<tr '.$bgcolor.'>
			<td>'.$r->name.'</td>
			<td>'.htmlspecialchars(substr($r->description, 0, 30)).'</td>
			<td>'.($r->cat_opt & 1 ? 'Yes' : 'No').'</td>
			<td>'.($r->cat_opt & 2 ? 'Open' : 'Collapsed').'</td>
			<td nowrap>[<a href="admforum.php?cat_id='.$r->id.'&'.__adm_rsidl.'">Edit Forums</a>] [<a href="admcat.php?edit='.$r->id.'&'.__adm_rsidl.'">Edit Category</a>] [<a href="admcat.php?del='.$r->id.'&'.__adm_rsidl.'">Delete</a>]</td>
			<td>[<a href="admcat.php?chpos='.$r->view_order.'&'.__adm_rsidl.'">Change</a>]</td></tr>';

		if (isset($_GET['chpos']) && $_GET['chpos'] == ($r->view_order -1)) {

		}
	}
	if (isset($lp)) {
		echo '<tr class="field"><td align=center colspan=7><font size=-1><a href="admcat.php?chpos='.$_GET['chpos'].'&newpos='.($lp + 1).'&'.__adm_rsidl.'">Place Here</a></font></td></tr>';
	}
?>
</table>
<?php readfile($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
