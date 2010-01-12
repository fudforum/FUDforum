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
	fud_use('login_filter.inc', true);

	require($WWW_ROOT_DISK . 'adm/header.php');
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (isset($_POST['edit'], $_POST['btn_update']) && !empty($_POST['login'])) {
		q('UPDATE '.$tbl.'blocked_logins SET login='._esc(trim($_POST['login'])).' WHERE id='.(int)$_POST['edit']);
		echo successify('Regex ('.$_POST['login'].') was successfully updated.');
	} else if (isset($_POST['btn_submit']) && !empty($_POST['login'])) {
		if (preg_match('/'.addcslashes($_POST['login'], '\'/\\').'/i', $usr->login)) {
			echo errorify('Regex ('.$_POST['login'].') cannot be added. It will block your current login.');
		} else {
			q('INSERT INTO '.$tbl.'blocked_logins (login) VALUES('._esc(trim($_POST['login'])).')');
			echo successify('Regex ('.$_POST['login'].') was successfully added.');
		}
	} else if (isset($_GET['del'])) {
		q('DELETE FROM '.$tbl.'blocked_logins WHERE id='.(int)$_GET['del']);
		echo successify('Regex was successfully removed.');
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
?>
<h2>Login Blocker</h2>
<p>Block users with a matching login name from registering or posting messages on the forum.</p>
<form id="alf" method="post" action="admlogin.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td>Regex:</td>
		<td><input tabindex="1" type="text" name="login" value="<?php echo char_fix(htmlspecialchars($login)); ?>" /></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right">
		<?php
			if ($edit) {
				echo '<input type="submit" name="btn_cancel" value="Cancel" /> <input type="submit" name="btn_update" value="Update" tabindex="2" />';
			} else  {
				echo '<input type="submit" name="btn_submit" value="Add" tabindex="2" />';
			}
		?>
		</td>
	</tr>
</table>
<input type="hidden" name="edit" value="<?php echo $edit; ?>" />
</form>
<script type="text/javascript">
/* <![CDATA[ */
document.forms['alf'].login.focus();
/* ]]> */
</script>
<h3>Defined filters:</h3>
<table class="resulttable fulltable">
<tr class="resulttopic">
	<td>Regex</td>
	<td>Action</td>
</tr>
<?php
	$c = uq('SELECT login,id FROM '.$tbl.'blocked_logins');
	$i = 0;
	while ($r = db_rowarr($c)) {
		if ($edit == $r[0]) {
			$bgcolor = ' class="resultrow1"';
		} else {
			$bgcolor = ($i++%2) ? 'class="resultrow1"' : 'class="resultrow2"';
		}
		echo '<tr '.$bgcolor.'><td>'.char_fix(htmlspecialchars($r[0])).'</td><td>[<a href="admlogin.php?edit='.$r[1].'&amp;'.__adm_rsid.'">Edit</a>] [<a href="admlogin.php?del='.$r[1].'&amp;'.__adm_rsid.'">Delete</a>]</td></tr>';
	}
	unset($c);
	if (!$i) {
		echo '<tr class="field"><td colspan="2"><center>No filters found.</center></td></tr>';
	}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
