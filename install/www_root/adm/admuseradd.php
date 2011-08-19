<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/** Validate user input. */
function validate_input()
{
	if (empty($_POST['login'])) {
		$GLOBALS['err_login'] = errorify('Login is required.');
		return 1;
	}

	if (empty($_POST['passwd'])) {
		$GLOBALS['err_passwd'] = errorify('Password is required.');
		return 1;
	}

	if (empty($_POST['email'])) {
		$GLOBALS['err_email'] = errorify('E-mail address is required.');
		return 1;
	}

	return 0;
}

/* main */
	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);
	fud_use('logaction.inc');
	fud_use('users_reg.inc');

	$error = 0;

	if (isset($_POST['usr_add']) && !($error = validate_input())) {
		$user = new fud_user_reg;
		$user->login            = $_POST['login'];
		$user->plaintext_passwd = $_POST['passwd'];
		$user->email            = $_POST['email'];
		$user->name             = $_POST['name'];
		try {
			$uid = $user->add();
		} catch (Exception $e) {
			$error = $e->getMessage();
		}
	}

	require($WWW_ROOT_DISK .'adm/header.php');

	if ($error) {
		if ($error == 1) {
			pf(errorify('Error adding user.'));
		} else {
			pf(errorify($error));
		}
		foreach (array('login', 'passwd', 'email', 'name') as $v) {
			$$v = isset($_POST[$v]) ? htmlspecialchars($_POST[$v]) : '';
		}
	} else {
		if (!empty($uid)) {
			logaction(_uid, 'CREATE_USER', 0, $_POST['login']);
			pf(successify('User was successfully added. [ <a href="admuser.php?act=1&amp;usr_id='. $uid .'&amp;'. __adm_rsid .'">Edit user '. $_POST['login'] .'</a> ]'));
		}
		$login = $passwd = $email = $name = '';
	}
?>
<h2>Add User</h2>
<form id="frm_usr" method="post" action="admuseradd.php">
<?php echo _hs; ?>
Register a new forum user:
<table class="datatable solidtable">
	<tr class="field">
		<td>Login:</td>
		<td>
			<?php if ($error && isset($err_login)) { echo $err_login; } ?>
			<input tabindex="1" type="text" name="login" value="<?php echo $login; ?>" size="30" />
		</td>
	</tr>
	<tr class="field">
		<td>Password:</td>
		<td>
			<?php if ($error && isset($err_passwd)) { echo $err_passwd; } ?>
			<input tabindex="2" type="text" name="passwd" value="<?php echo $passwd; ?>" size="30" /> 
			<font size="-1">[ <a href="#" onclick="randomPassword();">Generate</a> ]</font>
		</td>
	</tr>
	<tr class="field">
		<td>E-mail:</td>
		<td>
			<?php if ($error && isset($err_email)) { echo $err_email; } ?>
			<input tabindex="3" type="text" name="email" value="<?php echo $email; ?>" size="30" />
		</td>
	</tr>
	<tr class="field">
		<td>Real Name:</td>
		<td>
			<input type="text" name="name" value="<?php echo $name; ?>" tabindex="4" size="30" />
		</td>
	</tr>
	<tr class="fieldaction">
		<td colspan="2" align="right"><input type="submit" value="Add User" tabindex="5" name="usr_add" /></td>
	</tr>
</table>
</form>
<p><a href="admuser.php?<?php echo __adm_rsid; ?>">&laquo; Back to User Administration System</a></p>
<script>
function randomPassword() {
	var chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz";
	var string_length = 8;
	var randomstring = '';
	for (var i=0; i<string_length; i++) {
		var rnum = Math.floor(Math.random() * chars.length);
		randomstring += chars.substring(rnum, rnum+1);
	}
	document.forms['frm_usr'].passwd.value = randomstring;
}
</script>
<?php require($WWW_ROOT_DISK .'adm/footer.php'); ?>
