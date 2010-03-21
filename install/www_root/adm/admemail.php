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
	fud_use('widgets.inc', true);
	fud_use('email_filter.inc', true);
	fud_use('adm.inc', true);

	require($WWW_ROOT_DISK . 'adm/header.php');
		
	if (isset($_POST['edit'], $_POST['btn_update']) && !empty($_POST['e_string'])) {
		$e_email_block_opt = (int) $_POST['e_email_block_opt'];
		$e_string = _esc(trim($_POST['e_string']));
		q('UPDATE '.$DBHOST_TBL_PREFIX.'email_block SET email_block_opt='.$e_email_block_opt.', string='.$e_string.' WHERE id='.(int)$_POST['edit']);
		echo successify('Address ('.$_POST['e_string'].') was successfully updated.');
	} else if (isset($_POST['btn_submit']) && !empty($_POST['e_string'])) {
		$e_email_block_opt = (int) $_POST['e_email_block_opt'];
		$e_string = _esc(trim($_POST['e_string']));
		db_li('INSERT INTO '.$DBHOST_TBL_PREFIX.'email_block (email_block_opt, string) VALUES('.$e_email_block_opt.', '.$e_string.')', $tmp);
		echo successify('Address ('.$_POST['e_string'].') was successfully added.');
	} else if (isset($_GET['del'])) {
		q('DELETE FROM '.$DBHOST_TBL_PREFIX.'email_block WHERE id='.(int)$_GET['del']);
		echo successify('Address was successfully removed.');
	} else {
		$nada = 1;
	}

	if (!isset($nada) && db_affected()) {
		email_cache_rebuild();
	}

	if (isset($_GET['edit'])) {
		list($edit, $e_email_block_opt, $e_string) = db_saq('SELECT id, email_block_opt, string FROM '.$DBHOST_TBL_PREFIX.'email_block WHERE id='.(int)$_GET['edit']);
	} else {
		$edit = $e_email_block_opt = $e_string = '';
	}
?>
<h2>E-mail Filter</h2>
<p>Block users with matching E-mail address from registering or posting messages on the forum.</p>

<h3><?php echo $edit ? '<a name=edit">Edit filter:</a>' : 'Add New Filter:'; ?></h3>
<form id="ef" method="post" action="admemail.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td>Type:</td>
		<td><?php draw_select("e_email_block_opt", "Simple\nRegexp", "1\n0", $e_email_block_opt); ?></td>
	</tr>

	<tr class="field">
		<td>Address:</td>
		<td><input tabindex="1" type="text" name="e_string" value="<?php echo htmlspecialchars($e_string); ?>" /></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right">
		<?php
			if ($edit) {
				echo '<input type="submit" name="btn_cancel" value="Cancel" /> <input type="submit" tabindex="2" name="btn_update" value="Update" />';
			} else {
				echo '<input tabindex="2" type="submit" name="btn_submit" value="Add" />';
			}
		?>
		</td>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>" />
</form>

<h3>Defined filters:</h3>
<table class="resulttable fulltable">
<thead><tr class="resulttopic">
	<th>Address/Regex</th>
	<th>Type</th>
	<th>Action</th>
</tr></thead>
<?php
	$c = uq('SELECT id, email_block_opt, string FROM '.$DBHOST_TBL_PREFIX.'email_block');
	$i = 0;
	while ($r = db_rowarr($c)) {
		$i++;
		$bgcolor = ($edit == $r[0]) ? ' class="resultrow3"' : (($i%2) ? ' class="resultrow1"' : ' class="resultrow2"');

		echo '<tr'.$bgcolor.'><td>'.htmlspecialchars($r[2]).'</td><td>'.($r[1] ? 'Simple' : 'Regex').'</td><td>[<a href="admemail.php?edit='.$r[0].'&amp;'.__adm_rsid.'">Edit</a>] [<a href="admemail.php?del='.$r[0].'&amp;'.__adm_rsid.'">Delete</a>]</td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="3"><center>No filters found. Define some above.</center></td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
