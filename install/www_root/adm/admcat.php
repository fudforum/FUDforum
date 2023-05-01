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

	require ('./GLOBALS.php');

	fud_use('adm.inc', true);
	fud_use('cat.inc', true);
	fud_use('widgets.inc', true);
	fud_use('logaction.inc');

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	// AJAX call to reorder categories.
	if (!empty($_POST['ajax']) && $_POST['ajax'] == 'reorder') {
		$new_order = 1;
		foreach ($_POST['order'] as $id) {
			q('UPDATE '. $tbl .'cat SET view_order = '. $new_order++ .' WHERE id = '. $id);
		}
		rebuild_forum_cat_order();
		exit('Categories successfully reordered.');	// End AJAX call.
	}

	require($WWW_ROOT_DISK .'adm/header.php');

	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	if (isset($_POST['cat_submit']) && !empty($_POST['cat_name'])) {
		if (!empty($_POST['cat_description'])) {
			$_POST['cat_description'] = ' - '. $_POST['cat_description'];
		}
		$_POST['cat_cat_opt'] = (int)$_POST['cat_allow_collapse'] | (int)$_POST['cat_default_view'];
		unset($_POST['cat_allow_collapse'], $_POST['cat_allow_collapse']);

		$cat = new fud_cat;
		if ($edit) {
			if (isset($_POST['cat_parent']) && $edit != $_POST['cat_parent']) {
				$cat->sync($edit);
			}
			$edit = '';
			echo successify('Category successfully updated.');
		} else {
			$cat_id = $cat->add($_POST['cat_pos']);
			logaction(_uid, 'ADDCAT', $cat_id);
			echo successify('Category successfully added.');
		}
		rebuild_forum_cat_order();
	}
	if ($edit && ($c = db_arr_assoc('SELECT parent, name, description, cat_opt FROM '. $tbl .'cat WHERE id='. $edit))) {
		foreach ($c as $k => $v) {
			${'cat_'. $k} = $v ?? '';
		}
		if ($cat_description && !strncmp($cat_description, ' - ', 3)) {
			$cat_description = substr($cat_description, 3);
		}
		$cat_opt = $c['cat_opt'];
	} else {
		$c = get_class_vars('fud_cat');
		foreach ($c as $k => $v) {
			${'cat_'. $k} = '';
		}
		$cat_pos = 'LAST';
		$cat_opt = 3;
	}

	if (isset($_GET['del'])) {
		$del = (int)$_GET['del'];
		db_lock($tbl.'cat WRITE, '. $tbl .'cat c WRITE, '. $tbl .'forum WRITE, '. $tbl .'forum f WRITE, '. $tbl .'fc_view WRITE');
		q('DELETE FROM '. $tbl .'cat WHERE id='. $del);
		if (db_affected()) {
			require $GLOBALS['FORUM_SETTINGS_PATH'] .'cat_cache.inc';
			$dell = array();
			if (!empty($cat_cache[$del][2])) {
				/* Remove all child categories if available. */
				$dell = $cat_cache[$del][2];
				q('DELETE FROM '. $tbl .'cat WHERE id IN('. implode(',',  $cat_cache[$del][2]) .')');
			}
			$dell[] = $del;
			q('UPDATE '. $tbl .'forum SET cat_id=0 WHERE cat_id IN('. implode(',', $dell) .')');
			cat_rebuild_order();
			rebuild_forum_cat_order();
		}
		db_unlock();
		echo successify('Category was deleted. Forums assigned to this category were moved to the <b><a href="admforumdel.php?'. __adm_rsid .'">recycle bin</a></b>.');
		logaction(_uid, 'DELCAT', 0, $del);
	}
	if (isset($_GET['chpos'], $_GET['newpos'], $_GET['par'])) {
		cat_change_pos((int)$_GET['chpos'], (int)$_GET['newpos'], (int)$_GET['par']);
		rebuild_forum_cat_order();
		unset($_GET['chpos'], $_GET['newpos']);
		echo successify('Position successfully set.');
	}

	// Creat an ordered list of categories.
	$ol = array();
	$c = uq('SELECT * FROM '. $tbl .'cat ORDER BY parent, view_order');
	while ($r = db_rowobj($c)) {
		if (!isset($ol[$r->parent])) {
			$ol[$r->parent] = array();
		}
		$ol[$r->parent][] = $r;
	}
	unset($c);

	// Traverse list in display order and assign it a "level" for indentation.
	$cat_list = array();
	function buildTree($ol, $l=0, $i=0) {
		global $cat_list;
		foreach($ol[$l] as $v) {	// Loop through cats on level $l
			$v->lvl = $i;
			$cat_list[] = $v;
			if (isset($ol[$v->id])) {	// We have cats below that we need to traverse.
				buildTree($ol, $v->id, $i+1);
		        }
		}
	}
	buildTree($ol);
	unset($ol);
?>
<h2>Category Management System</h2>

<?php
	if (!isset($_GET['chpos'])) {	// Hide this if we are changing category order.
?>

<div class="tutor">
	A catagory is an organizational container that holds related forums.
	Categories are displayed on the forum's front page and can be nested.
	You must create at least one category before you can create a new forum.
</div>
<?php
echo '<h3>'. ($edit ? '<a name="edit">Edit Category:</a>' : 'Add New Category:') .'</h3>';
?>
<script>
function imposeMaxLength(Object, len)
{
	if (Object.value.length > len) {
		alert('Maximum length exceeded: ' + len);
		Object.value = Object.value.substr(0, len);
		return false;
	} else {
		return true;
	}
}
</script>
<form method="post" action="admcat.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Category name:<br /><font size="-2">The category's name.</font></td>
		<td><input type="text" name="cat_name" value="<?php echo $cat_name; ?>" maxlength="50" /></td>
	</tr>

	<tr class="field">
		<td>Description:<br /><font size="-2">Description to show on the forums main index page. This field can contain HTML.</font></td>
		<td><textarea name="cat_description" cols="40" rows="3" onkeypress="return imposeMaxLength(this, 255);"><?php echo htmlspecialchars($cat_description); ?></textarea></td>
	</tr>

	<tr class="field">
		<td>Collapsible:<br /><font size="-2">Can users expand/collapse (show/hide) this category?</font></td>
		<td><?php draw_select('cat_allow_collapse', "Yes\nNo", "1\n0", $cat_opt & 1); ?></td>
	</tr>

	<tr class="field">
		<td>Default view:<br /><font size="-2">Default appearance of this category.</font></td>
		<td><?php draw_select('cat_default_view', "Open\nCollapsed\nCompact", "2\n0\n4", ($cat_opt & (2|4))); ?></td>
	</tr>
	
	<tr class="field">
		<td>Parent category: </td>
<?php
	$c_ids = $c_names = "\n";
	foreach ($cat_list as $c) {
		if ($edit == $c->id) {
			continue;
		}
		$c_ids .= $c->id . "\n";
		$c_names .= str_repeat('-- ', $c->lvl) . $c->name ."\n";
	}

	if ($c_names == "\n") {
		$c_names = $c_ids = '';
	}
?>
		<td><?php draw_select('cat_parent', 'Top Level'. rtrim($c_names), '0'. rtrim($c_ids), $cat_parent); ?></td>
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
		echo '<input type="hidden" value="'. $edit .'" name="edit" />';
		echo '<input type="submit" name="btn_cancel" value="Cancel" />&nbsp;';
	}
?>
			<input type="submit" value="<?php echo ($edit ? 'Update Category' : 'Add Category'); ?>" name="cat_submit" />
		</td>
	</tr>
</table>
</form>

<h3>Available Categories:</h3>
<?php
	} else {	// Busy changing position.
		echo '<a href="admcat.php?'. __adm_rsid .'">Cancel reorder operation</a><br />';
	}
?>

<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Name</th>
	<th>Description</th>
	<th>Collapsible</th>
	<th>Default View</th>
	<th align="center">Action</th>
</tr></thead>
<tbody id="sortable">
<?php
	$cpid = empty($_GET['chpos']) ? -1 : (int)q_singleval('SELECT parent FROM '. $tbl .'cat WHERE id='. (int)$_GET['cpid']);
	$lp = '';

	$stat = array(0 => 'Collapsed', 2 => 'Open', 4 => 'Compact');

	foreach ($cat_list as $i => $r) {
		$i++;
		$bgcolor = ($edit == $r->id) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		if ($r->parent == $cpid) {
			if ($_GET['chpos'] == $r->view_order) {
				$bgcolor = ' class="resultrow2"';
			} else if ($_GET['chpos'] != ($r->view_order - 1)) {
				echo '<tr class="field"><td align="center" colspan="7"><font size="-1"><a href="admcat.php?chpos='. $_GET['chpos'] .'&amp;newpos='. ($r->view_order - ($_GET['chpos'] < $r->view_order ? 1 : 0)) .'&amp;par='. $r->parent .'&amp;'. __adm_rsid .'">Place Here</a></font></td></tr>';
			}
			$lp = $r->view_order;
		} else if ($cpid > -1) {
			continue;
		}

		$parent = $r->parent;

		$r->description = $r->description ?? '';
		if ($r->description && !strncmp($r->description, ' - ', 3)) {
			$r->description = substr($r->description, 3);
		}

		echo '<tr id="order_'. $r->id .'"'. $bgcolor .' title="'. htmlspecialchars($r->description) .'">
			<td><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>'. str_repeat('-', $r->lvl) .' '. $r->name .'</td>
			<td><font size="-1">'. htmlspecialchars(substr($r->description, 0, 30)) .'...</font></td>
			<td>'. ($r->cat_opt & 1 ? 'Yes' : 'No') .'</td>
			<td>'. $stat[($r->cat_opt & (2|4))] .'</td>
			<td nowrap="nowrap">
				[<a href="admcat.php?edit='. $r->id .'&amp;'. __adm_rsid .'#edit" title="Edit category">Edit</a>]
				[<a href="admcat.php?del='. $r->id .'&amp;'. __adm_rsid .'" title="Delete category">Delete</a>]
				[<a href="admcat.php?chpos='. $r->view_order .'&amp;cpid='. $r->id .'&amp;'. __adm_rsid .'" title="Change display position">Change Position</a>]
				[<a href="admforum.php?cat_id='. $r->id .'&amp;'. __adm_rsid .'" title="Add/edit this category\'s forums">Manage Forums</a>]
			</td></tr>';
	}
	if ($lp && $parent == $cpid) {
		echo '<tr class="field"><td align="center" colspan="6"><font size="-1"><a href="admcat.php?chpos='. $_GET['chpos'] .'&amp;newpos='. ($lp + 1) .'&amp;par='. $parent .'&amp;'. __adm_rsid .'">Place Here</a></font></td></tr>';
	}
	if (!$i) {
		echo '<tr class="field"><td colspan="6"><center>No categories found. Define some above.</center></td></tr>';
	}
?>
</tbody>
</table>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
