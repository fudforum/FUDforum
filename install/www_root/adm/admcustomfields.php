<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('custom_fields_adm.inc', true);
	fud_use('widgets.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	require($WWW_ROOT_DISK .'adm/header.php');
	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	// Add or edit a profile field.
	if (isset($_POST['frm_submit'])) {
		$error = 0;

		if ($edit && !$error) {
			$cfields = new fud_cfields;
			$cfields->sync($edit);
			$edit = '';	
			echo successify('Field was successfully updated.');
		} else if (!$error) {
			$cfields = new fud_custom_fields;
			$cfields->add();
			echo successify('Field was successfully added.');
		}
	}

	/* Remove a profile field. */
	if (isset($_GET['del'])) {
		$cfields = new fud_custom_fields();
		$cfields->delete($_GET['del']);
		echo successify('Field was successfully deleted.');
	}

	if (isset($_GET['chpos'], $_GET['chdest'])) {
		$oldp = (int)$_GET['chpos'];
		$newp = (int)$_GET['chdest'];
		if ($oldp != $newp && $newp) {
			db_lock($GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields WRITE');
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields SET vieworder=2147483647 WHERE vieworder='. $oldp);
			if ($oldp < $newp) {
				q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields SET vieworder=vieworder-1 WHERE vieworder<='. $newp .' AND vieworder>'. $oldp);
				$maxp = q_singleval('SELECT MAX(vieworder) FROM '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields WHERE  vieworder!=2147483647');
				if ($newp > $maxp) {
					$newp = $maxp + 1;
				}
			} else {
				q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields SET vieworder=vieworder+1 WHERE vieworder<'. $oldp .' AND vieworder>='. $newp);
			}
			q('UPDATE '. $GLOBALS['DBHOST_TBL_PREFIX'] .'custom_fields SET vieworder='. $newp .' WHERE vieworder=2147483647');
			db_unlock();
			$_GET['chpos'] = null;
			echo successify('The field\'s position was succesfully changed.');
		}
	}

	/* Set defaults. */
	if ($edit && ($c = db_arr_assoc('SELECT * FROM '. $tbl .'custom_fields WHERE id='. $edit))) {
		foreach ($c as $k => $v) {
			${'custom_field_'.$k} = $v;
		}
	} else {
		$c = get_class_vars('fud_custom_fields');
		foreach ($c as $k => $v) {
			${'custom_field_'.$k} = '';
		}
	}
?>
<h2>Custom Profile Fields</h2>

<?php
echo '<h3>'. ($edit ? '<a name="edit">Edit Field:</a>' : 'Add New Field:') .'</h3>';
?>
<form method="post" id="frm_forum" action="admcustomfields.php">
<?php echo _hs; ?>
<table class="datatable">
	<tr class="field">
		<td>Field Name:<br /><font size="-2">Name of the custom profile field shown to users.</font></td>
		<td><input type="text" name="custom_field_name" value="<?php echo $custom_field_name; ?>" /></td>
	</tr>

	<tr class="field">
		<td>Description:<br /><font size="-2">Explanation of what users should type into the field.</font></td>
		<td><input type="text" name="custom_field_descr" value="<?php echo $custom_field_descr; ?>" /></td>
	</tr>

	<tr class="field">
		<td>Type:<br /><font size="-2">How to present the field.</font></td>
		<td><?php draw_select('custom_field_type_opt', "Single line\nText box\nDrop down\nRadio buttons", "0\n1\n2\n4", ($custom_field_type_opt & (1|2|4))); ?></td>
	</tr>

	<tr class="field">
		<td>Value(s):<br /><font size="-2">Default value or a list of possible values for a "drop down" or "radio buttons" field (enter one value per line).</font></td>
		<td><textarea name="custom_field_choice" cols="40" rows="2"><?php echo $custom_field_choice; ?></textarea></td>
	</tr>

	<tr class="field">
		<td>Section:<br /><font size="-2">Is the field part of the mandatory or optional section of the profile page.</font></td>
		<td><?php draw_select('custom_field_field_opt', "Optional\nMandatory", "0\n1", ($custom_field_field_opt & (1))); ?></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right">
<?php
	if ($edit) {
		echo '<input type="hidden" name="edit" value="'.$edit.'" />';
		echo '<input type="submit" value="Cancel" name="btn_cancel" /> ';
	}
?>
			<input type="submit" value="<?php echo ($edit ? 'Update Field' : 'Add Field'); ?>" name="frm_submit" />
		</td>
	</tr>
</table>
</form>

<h3>Defined fields:</h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Name</th><th>Description</th><th>Type</th><th>Value(s)</th><th>Action</th>
</tr></thead>
<?php
	$i = 0;
	$c = uq('SELECT id, name, descr, type_opt, choice, vieworder FROM '. $tbl .'custom_fields ORDER BY vieworder LIMIT 100');
	while ($r = db_rowobj($c)) {
		$i++;
		$bgcolor = ($edit == $r->id) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		if (isset($_GET['chpos'])) {
			if ($_GET['chpos'] == $r->vieworder) {
				$bgcolor = ' class="resultrow2"';
			} else if ($_GET['chpos'] != ($r->vieworder - 1)) {
				echo '<tr class="field"><td align="center" colspan="9"><a href="admcustomfields.php?chpos='. $_GET['chpos'] .'&amp;chdest='. ($r->vieworder - ($_GET['chpos'] < $r->vieworder ? 1 : 0)) .'&amp;'. __adm_rsid .'">Place Here</a></td></tr>';
			}
			$lp = $r->vieworder;
		}

		echo '<tr'. $bgcolor .'><td>'. $r->name .'</td><td>'. $r->descr .'</td><td>'. $r->type_opt .'</td><td>'. $r->choice .'</td>';
		echo '<td><a href="admcustomfields.php?edit='. $r->id .'&amp;'. __adm_rsid .'#edit">Edit</a> | <a href="admcustomfields.php?del='. $r->id .'&amp;'. __adm_rsid .'">Delete</a> | [<a href="admcustomfields.php?chpos='. $r->vieworder .'&amp;cpid='. $r->id .'&amp;'. __adm_rsid .'">Change Position</a>]</td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="6"><center>No fields found. Define some above.</center></td></tr>';
	}
?>
</table>

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
