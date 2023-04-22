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
	fud_use('custom_field_adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('logaction.inc');

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	// AJAX call to reorder fields.
	if (!empty($_POST['ajax']) && $_POST['ajax'] == 'reorder') {
		$new_order = 1;
		foreach ($_POST['order'] as $id) {
			q('UPDATE '. $tbl .'custom_fields SET vieworder = '. $new_order++ .' WHERE id = '. $id);
		}
		fud_custom_field::rebuild_cache();
		exit('Profile fields successfully reordered.');	// End AJAX call.
	}

	require($WWW_ROOT_DISK .'adm/header.php');
	if (!empty($_POST['btn_cancel'])) {
		unset($_POST);
	}

	$edit = isset($_GET['edit']) ? (int)$_GET['edit'] : (isset($_POST['edit']) ? (int)$_POST['edit'] : '');

	// Add or edit a profile field.
	if (isset($_POST['frm_submit']) && !empty($_POST['custom_field_name'])) {
		$error = 0;

		if ($edit && !$error) {
			$cfield = new fud_custom_field;
			$cfield->sync($edit);
			$edit = '';	
			echo successify('Field was successfully updated.');
			logaction(_uid, 'Update custom field', 0, $_POST['custom_field_name']);
		} else if (!$error) {
			$cfield = new fud_custom_field;
			$cfield->add();
			echo successify('Field was successfully added.');
			logaction(_uid, 'Add custom field', 0, $_POST['custom_field_name']);
		}
	}

	/* Remove a profile field. */
	if (isset($_GET['del'])) {
		$id = (int)$_GET['del'];
		$cfield = new fud_custom_field();
		$cfield->delete($id);
		echo successify('Field was successfully deleted.');
		logaction(_uid, 'Delete custom field', 0, $id);
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
			fud_custom_field::rebuild_cache();
			echo successify('The field\'s position was succesfully changed.');
		}
	}

	/* Set defaults. */
	if ($edit && ($c = db_arr_assoc('SELECT * FROM '. $tbl .'custom_fields WHERE id='. $edit))) {
		foreach ($c as $k => $v) {
			${'custom_field_'.$k} = $v ?? '';
		}
	} else {
		$c = get_class_vars('fud_custom_field');
		foreach ($c as $k => $v) {
			${'custom_field_'. $k} = '';
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
		<td>Type:<br /><font size="-2">How to present the field?</font></td>
		<td><?php draw_select('custom_field_type_opt', "Single line\nText box\nDrop down\nRadio buttons", "0\n1\n2\n4", ((int)$custom_field_type_opt & (1|2|4))); ?></td>
	</tr>

	<tr class="field">
		<td>Value(s):<br /><font size="-2">Default value or a list of possible values for a "drop down" or "radio buttons" field (enter one value per line).</font></td>
		<td><textarea name="custom_field_choice" cols="40" rows="2"><?php echo $custom_field_choice; ?></textarea></td>
	</tr>

	<tr class="field">
		<td>Visible:<br /><font size="-2">Who can see the value entered into this field?</font></td>
		<td><?php draw_select('custom_field_field_opt[]', "Private\nAll users\nAll logged in users", "0\n2\n4", ((int)$custom_field_field_opt & (2|4))); ?></td>
	</tr>

	<tr class="field">
		<td>Editable:<br /><font size="-2">Who can edit this field?</font></td>
		<td><?php draw_select('custom_field_field_opt[]', "User\nAdmins only\nNobody", "0\n8\n16", ((int)$custom_field_field_opt & (8|16))); ?></td>
	</tr>

	<tr class="field">
		<td>Section:<br /><font size="-2">Is the field part of the mandatory or optional section of the profile page?</font></td>
		<td><?php draw_select('custom_field_field_opt[]', "Optional\nMandatory", "0\n1", ((int)$custom_field_field_opt & (1))); ?></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right">
<?php
	if ($edit) {
		echo '<input type="hidden" name="edit" value="'. $edit .'" />';
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
	<th>Name</th><th>Description</th><th>Value(s)</th><th>Action</th>
</tr></thead>
<tbody id="sortable">
<?php
	$i = 0;
	$c = uq(q_limit('SELECT id, name, descr, choice, vieworder FROM '. $tbl .'custom_fields ORDER BY vieworder', 100));
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

		echo '<tr id="order_'. $r->id .'"'. $bgcolor .'><td><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>'. $r->name .'</td><td>'. $r->descr .'</td><td>'. $r->choice .'</td>';
		echo '<td><a href="admcustomfields.php?edit='. $r->id .'&amp;'. __adm_rsid .'#edit">Edit</a> | <a href="admcustomfields.php?del='. $r->id .'&amp;'. __adm_rsid .'">Delete</a> | <a href="admcustomfields.php?chpos='. $r->vieworder .'&amp;cpid='. $r->id .'&amp;'. __adm_rsid .'">Change Position</a></td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="4"><center>No fields found. Define some above.</center></td></tr>';
	}
?>
</tbody></table>

<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
