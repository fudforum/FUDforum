<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admcat.php,v 1.13 2003/09/26 18:49:03 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
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
		$_POST['cat_cat_opt'] = ($_POST['cat_allow_collapse'] == 'Y' ? 1 : 0) | ($_POST['cat_default_view'] == 'COLLAPSED' ? 2 : 0);
		unset($_POST['cat_allow_collapse'], $_POST['cat_allow_collapse']);

		$cat = new fud_cat;
		if ($edit) {
			$cat->sync($edit);
			$edit = '';
		} else {
			$cat->add($_POST['cat_pos']);
		}
	}
	if ($edit && ($c = db_arr_assoc('SELECT name, description, cat_opt FROM '.$tbl.'cat WHERE id='.$edit))) {
		foreach ($c as $k => $v) {
			${'cat_'.$k} = $v;
		}
		if ($cat_description && !strncmp($cat_description, ' - ', 3)) {
			$cat_description = substr($cat_description, 3);
		}
	} else {
		$c = get_class_vars('fud_cat');
		foreach ($c as $k => $v) {
			${'cat_'.$k} = '';
		}
		$cat_pos = 'LAST';
		$cat_opt = 1;
	}

	if (isset($_GET['del'])) {
		$del = (int)$_GET['del'];
		db_lock($tbl.'cat WRITE, '.$tbl.'forum WRITE');
		q_singleval('DELETE FROM '.$tbl.'cat WHERE id='.$del);
		if (db_affected()) {
			q('UPDATE '.$tbl.'forum SET cat_id=0 WHERE cat_id='.$del);
			cat_rebuild_order();
		}
		db_unlock();
	}
	if (isset($_GET['chpos'], $_GET['newpos'])) {
		cat_change_pos((int)$_GET['chpos'], (int)$_GET['newpos']);
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
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>Category Name:</td>
		<td><input type="text" name="cat_name" value="<?php echo htmlspecialchars($cat_name); ?>" maxLength=50></td>
	</tr>
		
	<tr bgcolor="#bff8ff">
		<td>Description:</td>
		<td><input type="text" name="cat_description" value="<?php echo htmlspecialchars($cat_description); ?>" maxLength=255></td>
	</tr>
	
	<tr bgcolor="#bff8ff">
		<td>Collapsible</td>
		<td><?php draw_select('cat_allow_collapse', "Yes\nNo", "Y\nN", $cat_opt & 1); ?></td>
	</tr>
		
	<tr bgcolor="#bff8ff">
		<td>Default view: </td>
		<td><?php draw_select('cat_default_view', "Open\nCollapsed", "OPEN\nCOLLAPSED", !($cat_opt & 2)); ?></td>
	</tr>
	
	<?php if (!$edit) { ?>
	
	<tr bgcolor="#bff8ff">
		<td>Insert position:</td>
		<td><?php draw_select('cat_pos', "Last\nFirst", "LAST\nFIRST", $cat_pos); ?></td>
	</tr>
	
	<?php } ?>
	
	<tr bgcolor="#bff8ff">
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
		echo '<a href="admcat.php?'._rsidl.'">Cancel</a><br>';
	}
?>
<br>
<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
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
			$bgcolor = ' bgcolor="#ffb5b5"';
		} else {
			$bgcolor = ($i++%2) ? ' bgcolor="#fffee5"' : '';
		}
		if (isset($_GET['chpos'])) {
			if ($_GET['chpos'] == $r->view_order) {
				$bgcolor = ' bgcolor="#ffb5b5"';
			} else if ($_GET['chpos'] != ($r->view_order - 1)) {
				echo '<tr bgcolor="#efefef"><td align=center colspan=7><font size=-1><a href="admcat.php?chpos='.$_GET['chpos'].'&newpos='.($r->view_order - ($_GET['chpos'] < $r->view_order ? 1 : 0)).'&'._rsidl.'">Place Here</a></font></td></tr>';
			}
			$lp = $r->view_order;
		}
		if ($r->description && !strncmp($r->description, ' - ', 3)) {
			$r->description = substr($r->description, 3);
		}

		echo '<tr '.$bgcolor.'>
			<td>'.$r->name.'</td>
			<td>'.substr($r->description, 0, 30).'</td>
			<td>'.($r->cat_opt & 1 ? 'Yes' : 'No').'</td>
			<td>'.($r->cat_opt & 2 ? 'Collapsed' : 'Open').'</td>
			<td nowrap>[<a href="admforum.php?cat_id='.$r->id.'&'._rsidl.'">Edit Forums</a>] [<a href="admcat.php?edit='.$r->id.'&'._rsidl.'">Edit Category</a>] [<a href="admcat.php?del='.$r->id.'&'._rsidl.'">Delete</a>]</td>
			<td>[<a href="admcat.php?chpos='.$r->view_order.'&'._rsidl.'">Change</a>]</td></tr>';

		if (isset($_GET['chpos']) && $_GET['chpos'] == ($r->view_order -1)) {
			
		}
	}
	qf($c);
	if (isset($lp)) {
		echo '<tr bgcolor="#efefef"><td align=center colspan=7><font size=-1><a href="admcat.php?chpos='.$_GET['chpos'].'&newpos='.($lp + 1).'&'._rsidl.'">Place Here</a></font></td></tr>';
	}
?>
</table>
<?php readfile($WWW_ROOT_DISK . 'adm/admclose.html'); ?>