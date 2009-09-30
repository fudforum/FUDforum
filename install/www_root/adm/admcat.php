<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admcat.php,v 1.51 2009/09/30 16:47:32 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require ('./GLOBALS.php');

	fud_use('adm.inc', true);
	fud_use('cat.inc', true);
	fud_use('widgets.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_POST['cat_submit']) && !empty($_POST['cat_name'])) {
		if (!empty($_POST['cat_description'])) {
			$_POST['cat_description'] = ' - ' . $_POST['cat_description'];
		}
		$_POST['cat_cat_opt'] = (int) $_POST['cat_allow_collapse'] | (int) $_POST['cat_default_view'];
		unset($_POST['cat_allow_collapse'], $_POST['cat_allow_collapse']);

		$cat = new fud_cat;
		if ($edit) {
			if (isset($_POST['cat_parent']) && $edit != $_POST['cat_parent']) {
				$cat->sync($edit);
			}
			$edit = '';
		} else {
			$cat->add($_POST['cat_pos']);
		}
		rebuild_forum_cat_order();
	}
	if ($edit && ($c = db_arr_assoc('SELECT parent, name, description, cat_opt FROM '.$tbl.'cat WHERE id='.$edit))) {
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
		q('DELETE FROM '.$tbl.'cat WHERE id='.$del);
		if (db_affected()) {
			require $GLOBALS['FORUM_SETTINGS_PATH'] . 'cat_cache.inc';
			$dell = array();
			if (!empty($cat_cache[$del][2])) {
				/* remove all child categories if available */
				$dell = $cat_cache[$del][2];
				q("DELETE FROM ".$tbl."cat WHERE id IN(".implode(',',  $cat_cache[$del][2]).")");
			}
			$dell[] = $del;
			q('UPDATE '.$tbl.'forum SET cat_id=0 WHERE cat_id IN('.implode(',', $dell). ')');
			cat_rebuild_order();
			rebuild_forum_cat_order();
		}
		db_unlock();
	}
	if (isset($_GET['chpos'], $_GET['newpos'],$_GET['par'])) {
		cat_change_pos((int)$_GET['chpos'], (int)$_GET['newpos'], (int)$_GET['par']);
		rebuild_forum_cat_order();
		unset($_GET['chpos'], $_GET['newpos']);
	}

	$ol = $cat_list = array();
	$c = uq("SELECT * FROM ".$tbl."cat ORDER BY parent, view_order");
	while ($r = db_rowobj($c)) {
		if (!isset($ol[$r->parent])) {
			$ol[$r->parent] = array();
		}
		$ol[$r->parent][] = $r;
	}
	unset($c);
	$lvl = array(0); 
	$i = $l = 0;
	while (1) {
		if (isset($ol[$l])) {
			while (list(,$v) = each($ol[$l])) {
				$v->lvl = $i;
				$cat_list[] = $v;
				if (isset($ol[$v->id])) {
					$lvl[++$i] = $l;
					$l = $v->id;
					continue;
				}
			}
		}
		if ($i < 1) {
			break;
		}
		$l = $lvl[$i];
		unset($lvl[$i--]);
	}
	unset($ol);
	require($WWW_ROOT_DISK . 'adm/header.php');
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
		<td><input type="text" name="cat_name" value="<?php echo $cat_name; ?>" maxlength="50" /></td>
	</tr>

	<tr class="field">
		<td>Description:</td>
		<td><input type="text" name="cat_description" value="<?php echo htmlspecialchars($cat_description); ?>" maxlength="255" /></td>
	</tr>

	<tr class="field">
		<td>Collapsible:</td>
		<td><?php draw_select('cat_allow_collapse', "Yes\nNo", "1\n0", $cat_opt & 1); ?></td>
	</tr>

	<tr class="field">
		<td>Default view: </td>
		<td><?php draw_select('cat_default_view', "Open\nCollapsed\nCompact", "2\n0\n4", ($cat_opt & (2|4))); ?></td>
	</tr>
	
	<tr class="field">
		<td>Parent Category: </td>
<?php
	$c_ids = $c_names = "\n";
	foreach ($cat_list as $c) {
		if ($edit == $c->id) {
			continue;
		}
		$c_ids .= $c->id . "\n";
		$c_names .= str_repeat("-- ", $c->lvl) . $c->name . "\n";
	}

	if ($c_names == "\n") {
		$c_names = $c_ids = '';
	}
?>
		<td><?php draw_select('cat_parent', "Top Level" . rtrim($c_names), "0" . rtrim($c_ids), $cat_parent); ?></td>
	</tr>

	<?php if (!$edit) { ?>

	<tr class="field">
		<td>Insert position:</td>
		<td><?php draw_select('cat_pos', "Last\nFirst", "LAST\nFIRST", $cat_pos); ?></td>
	</tr>

	<?php } ?>

	<tr class="fieldaction">
		<td colspan="2" align="right">
<?php
	if ($edit) {
		echo '<input type="submit" name="btn_cancel" value="Cancel" />&nbsp;';
	}
?>
			<input type="submit" value="<?php echo ($edit ? 'Update Category' : 'Add Category'); ?>" name="cat_submit" />
		</td>
	</tr>
</table>
<?php
		if ($edit) {
			echo '<input type="hidden" value="'.$edit.'" name="edit" />';
		}
		echo '</form>';
	} else {
		echo '<a href="admcat.php?'.__adm_rsid.'">Cancel</a><br />';
	}
?>
<br />
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
	$cpid = empty($_GET['chpos']) ? -1 : (int) q_singleval("SELECT parent FROM ".$tbl."cat WHERE id=".(int)$_GET['cpid']);
	$lp = '';

	$stat = array(0 => 'Collapsed', 2 => 'Open', 4 => 'Compact');

	foreach ($cat_list as $i => $r) {
		$bgcolor = (($i % 2) && ($edit != $r->id)) ? ' class="resultrow2"' : ' class="resultrow1"';

		if ($r->parent == $cpid) {
			if ($_GET['chpos'] == $r->view_order) {
				$bgcolor = ' class="resultrow2"';
			} else if ($_GET['chpos'] != ($r->view_order - 1)) {
				echo '<tr class="field"><td align="center" colspan="7"><font size="-1"><a href="admcat.php?chpos='.$_GET['chpos'].'&amp;newpos='.($r->view_order - ($_GET['chpos'] < $r->view_order ? 1 : 0)).'&amp;par='.$r->parent.'&amp;'.__adm_rsid.'">Place Here</a></font></td></tr>';
			}
			$lp = $r->view_order;
		} else if ($cpid > -1) {
			continue;
		}

		$parent = $r->parent;

		if ($r->description && !strncmp($r->description, ' - ', 3)) {
			$r->description = substr($r->description, 3);
		}

		echo '<tr '.$bgcolor.'>
			<td>'.str_repeat("-", $r->lvl) . " " . $r->name.'</td>
			<td>'.htmlspecialchars(substr($r->description, 0, 30)).'</td>
			<td>'.($r->cat_opt & 1 ? 'Yes' : 'No').'</td>
			<td>'.$stat[($r->cat_opt & (2|4))].'</td>
			<td nowrap="nowrap">[<a href="admforum.php?cat_id='.$r->id.'&amp;'.__adm_rsid.'">Edit Forums</a>] [<a href="admcat.php?edit='.$r->id.'&amp;'.__adm_rsid.'">Edit Category</a>] [<a href="admcat.php?del='.$r->id.'&amp;'.__adm_rsid.'">Delete</a>]</td>
			<td>[<a href="admcat.php?chpos='.$r->view_order.'&amp;cpid='.$r->id.'&amp;'.__adm_rsid.'">Change</a>]</td></tr>';
	}
	if ($lp && $parent == $cpid) {
		echo '<tr class="field"><td align="center" colspan="7"><font size="-1"><a href="admcat.php?chpos='.$_GET['chpos'].'&amp;newpos='.($lp + 1).'&amp;par='.$parent.'&amp;'.__adm_rsid.'">Place Here</a></font></td></tr>';
	}
?>
</table>
<?php readfile($WWW_ROOT_DISK . 'adm/footer.php'); ?>
