<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admemail.php,v 1.15 2004/04/21 21:17:46 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('widgets.inc', true);
	fud_use('email_filter.inc', true);
	fud_use('adm.inc', true);

	if (isset($_POST['edit'], $_POST['btn_update']) && !empty($_POST['e_string'])) {
		$e_email_block_opt = (int) $_POST['e_email_block_opt'];
		$e_string = "'".addslashes(trim($_POST['e_string']))."'";
		q('UPDATE '.$DBHOST_TBL_PREFIX.'email_block SET email_block_opt='.$e_email_block_opt.', string='.$e_string.' WHERE id='.(int)$_POST['edit']);
	} else if (isset($_POST['btn_submit']) && !empty($_POST['e_string'])) {
		$e_email_block_opt = (int) $_POST['e_email_block_opt'];
		$e_string = "'".addslashes(trim($_POST['e_string']))."'";
		q('INSERT INTO '.$DBHOST_TBL_PREFIX.'email_block (email_block_opt, string) VALUES('.$e_email_block_opt.', '.$e_string.')');
	} else if (isset($_GET['del'])) {
		q('DELETE FROM '.$DBHOST_TBL_PREFIX.'email_block WHERE id='.(int)$_GET['del']);
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

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Email Filter</h2>
<form method="post" action="admemail.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td>Type:</td>
		<td><?php draw_select("e_email_block_opt", "Simple\nRegexp", "1\n0", $e_email_block_opt); ?></td>
	</tr>

	<tr class="field">
		<td>String:</td>
		<td><input type="text" name="e_string" value="<?php echo htmlspecialchars($e_string); ?>"></td>
	</tr>

	<tr class="fieldaction">
		<td colspan=2 align=right>
		<?php
			if ($edit) {
				echo '<input type="submit" name="btn_cancel" value="Cancel"> <input type="submit" name="btn_update" value="Update">';
			} else {
				echo '<input type="submit" name="btn_submit" value="Add">';
			}
		?>
		</td>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>

<table class="resulttable fulltable">
<tr bgcolor="#e5ffe7">
	<td>Address/Regex</td>
	<td>Type</td>
	<td>Action</td>
</tr>
<?php
	$c = uq('SELECT id, email_block_opt, string FROM '.$DBHOST_TBL_PREFIX.'email_block');
	$i = 1;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
  
		echo '<tr '.$bgcolor.'><td>'.htmlspecialchars($r[2]).'</td><td>'.($r[1] ? 'Simple' : 'Regex').'</td><td>[<a href="admemail.php?edit='.$r[0].'&'.__adm_rsidl.'">Edit</a>] [<a href="admemail.php?del='.$r[0].'&'.__adm_rsidl.'">Delete</a>]</td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
