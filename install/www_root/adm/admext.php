<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admext.php,v 1.11 2003/10/09 14:34:31 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('ext.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (!empty($_POST['c_ext'])) {
		if (($p = strrpos($_POST['c_ext'], '.')) !== false) {
			$c_ext = rtrim(substr($_POST['c_ext'], ($p + 1)));
		} else {
			$c_ext = trim($_POST['c_ext']);
		}
	}

	if (isset($_POST['edit'], $_POST['btn_update']) && $c_ext) {
		q('UPDATE '.$tbl.'ext_block SET ext=\''.addslashes($c_ext).'\' WHERE id='.(int)$_POST['edit']);
	} else if (isset($_POST['btn_submit']) && $c_ext) {
		q('INSERT INTO '.$tbl.'ext_block (ext) VALUES(\''.addslashes($c_ext).'\')');
	} else if (isset($_GET['del'])) {
		q('DELETE FROM '.$tbl.'ext_block WHERE id='.(int)$_GET['del']);
	} else {
		$nada = 1;
	}

	if (!isset($nada) && db_affected()) {
		ext_cache_rebuild();
	}

	if (isset($_GET['edit'])) {
		list($edit, $c_ext) = db_saq('SELECT id, ext FROM '.$tbl.'ext_block WHERE id='.(int)$_GET['edit']);
	} else {
		$edit = $c_ext = '';
	}

	include($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Allowed Extensions</h2>
<form method="post" action="admext.php">
<table border=0 cellspacing=1 cellpadding=3>
	<tr>
		<td colspan=2 bgcolor="#FFFFFF"><b>note:</b> if no file extension is entered, all files will be allowed</td>
	</tr>
	<tr bgcolor="#bff8ff">
		<td>Extension:</td>
		<td><input type="text" name="c_ext" value="<?php echo htmlspecialchars($c_ext); ?>">
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
<?php echo _hs; ?>
</form>

<table border=0 cellspacing=3 cellpadding=2>
<tr bgcolor="#e5ffe7">
	<td>Extension</td>
	<td>Action</td>
</tr>
<?php
	$c = uq('SELECT ext,id FROM '.$tbl.'ext_block');
	$i = 1;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
	        	$bgcolor = ' bgcolor="#ffb5b5"';
		} else {
	        	$bgcolor = ($i++%2) ? ' bgcolor="#fffee5"' : '';
		}
		echo '<tr '.$bgcolor.'><td>'.htmlspecialchars($r[0]).'</td><td>[<a href="admext.php?edit='.$r[1].'&'._rsid.'">Edit</a>] [<a href="admext.php?del='.$r[1].'&'._rsid.'">Delete</a>]</td></tr>';
	}
	qf($c);
?>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>