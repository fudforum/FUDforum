<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admlogin.php,v 1.17 2004/11/24 19:53:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('login_filter.inc', true);

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (isset($_POST['edit'], $_POST['btn_update']) && !empty($_POST['login'])) {
		q('UPDATE '.$tbl.'blocked_logins SET login=\''.addslashes(trim($_POST['login'])).'\' WHERE id='.(int)$_POST['edit']);
	} else if (isset($_POST['btn_submit']) && !empty($_POST['login'])) {
		q('INSERT INTO '.$tbl.'blocked_logins (login) VALUES(\''.addslashes(trim($_POST['login'])).'\')');
	} else if (isset($_GET['del'])) {
		q('DELETE FROM '.$tbl.'blocked_logins WHERE id='.(int)$_GET['del']);
	} else {
		$nada = 1;
	}
	if (!isset($nada) && db_affected()) {
		login_cache_rebuild();
	}

	if (isset($_GET['edit'])) {
		list($edit, $login) = db_saq('SELECT id, login FROM '.$tbl.'blocked_logins WHERE id='.(int)$_GET['edit']);
	} else {
		$edit = $login = '';
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Login Blocker</h2>
<form name="alf" method="post" action="admlogin.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td>Regex:</td>
		<td><input tabindex="1" type="text" name="login" value="<?php echo htmlspecialchars($login); ?>"></td>
	</tr>

	<tr class="fieldaction">
		<td colspan=2 align=right>
		<?php
			if ($edit) {
				echo '<input type="submit" name="btn_cancel" value="Cancel"> <input type="submit" name="btn_update" value="Update" tabindex="2">';
			} else  {
				echo '<input type="submit" name="btn_submit" value="Add" tabindex="2">';
			}
		?>
		</td>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>">
</form>
<script>
<!--
document.alf.login.focus();
//-->
</script>
<table class="resulttable fulltable">
<tr class="resulttopic">
	<td>Regex</td>
	<td>Action</td>
</tr>
<?php
	$c = uq('SELECT login,id FROM '.$tbl.'blocked_logins');
	$i = 1;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
		}
		echo '<tr '.$bgcolor.'><td>'.htmlspecialchars($r[0]).'</td><td>[<a href="admlogin.php?edit='.$r[1].'&'.__adm_rsidl.'">Edit</a>] [<a href="admlogin.php?del='.$r[1].'&'.__adm_rsidl.'">Delete</a>]</td></tr>';
	}
?>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
