<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admemail.php,v 1.5 2003/04/23 00:54:21 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('admin_form', 1);

	require('GLOBALS.php');
	fud_use('widgets.inc', true);
	fud_use('email_filter.inc', true);
	fud_use('adm.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (isset($_POST['edit'], $_POST['btn_update']) && !empty($_POST['e_string'])) {
		$e_type = $_POST['e_type'] == 'REGEX' ? "'REGEX'" : "'SIMPLE'";
		$e_string = "'".addslashes(trim($_POST['e_string']))."'";
		q('UPDATE '.$tbl.'email_block SET type='.$e_type.', string='.$e_string.' WHERE id='.(int)$_POST['edit']);
	} else if (isset($_POST['btn_submit']) && !empty($_POST['e_string'])) {
		$e_type = $_POST['e_type'] == 'REGEX' ? "'REGEX'" : "'SIMPLE'";
		$e_string = "'".addslashes(trim($_POST['e_string']))."'";
		q('INSERT INTO '.$tbl.'email_block (type, string) VALUES('.$e_type.', '.$e_string.')');
	} else if (isset($_GET['del'])) {
		q('DELETE FROM '.$tbl.'email_block WHERE id='.(int)$_GET['del']);
	} else {
		$nada = 1;
	}

	if (!isset($nada) && db_affected()) {
		email_cache_rebuild();
	}

	if (isset($_GET['edit'])) {
		list($edit, $e_type, $e_string) = db_saq('SELECT id, type, string FROM '.$tbl.'email_block WHERE id='.(int)$_GET['edit']);
	} else {
		$edit = $e_type = $e_string = '';
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Email Filter</h2>
<form method="post" action="admemail.php">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td>Type:</td>
		<td><?php draw_select("e_type", "Simple\nRegexp", "SIMPLE\nREGEX", $e_type); ?></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>String:</td>
		<td><input type="text" name="e_string" value="<?php echo htmlspecialchars($e_string); ?>"></td>
	</tr>

	<tr bgcolor="#bff8ff">
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

<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Address/Regex</td>
	<td>Type</td>
	<td>Action</td>
</tr>
<?php
	$c = uq('SELECT id, type, string FROM '.$tbl.'email_block');
	$i = 1;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' bgcolor="#ffb5b5"';
		} else {
			$bgcolor = ($i++%2) ? ' bgcolor="#fffee5"' : '';
		}
		echo '<tr '.$bgcolor.'><td>'.htmlspecialchars($r[2]).'</td><td>'.$r[1].'</td><td>[<a href="admemail.php?edit='.$r[0].'&'._rsid.'">Edit</a>] [<a href="admemail.php?del='.$r[0].'&'._rsid.'">Delete</a>]</td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>