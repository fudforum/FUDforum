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
	fud_use('widgets.inc', true);
	fud_use('logaction.inc');

	$error = 0;

function validate_input()
{
	if (empty($_POST['login'])) {
		$GLOBALS['err_login'] = errorify('Login cannot be blank.');
		return 1;
	}

	if (empty($_POST['passwd'])) {
		$GLOBALS['err_passwd'] = errorify('Password cannot be blank.');
		return 1;
	}

	if (empty($_POST['email'])) {
		$GLOBALS['err_email'] = errorify('E-mail cannot be blank.');
		return 1;
	}

	return 0;
}

	if (isset($_POST['usr_add']) && !($error = validate_input())) {
		$default_theme = q_singleval('SELECT id FROM '.$DBHOST_TBL_PREFIX.'themes WHERE theme_opt>=2 AND (theme_opt & 2) > 0 LIMIT 1');
		if (strlen($_POST['login']) > $MAX_LOGIN_SHOW) {
			$alias = substr($_POST['login'], 0, $MAX_LOGIN_SHOW);
		} else {
			$alias = $_POST['login'];
		}
		$alias = addslashes(htmlspecialchars($alias));

		$users_opt = 2|4|16|32|64|128|256|512|2048|4096|8192|16384|131072|4194304;

		if (!($FUD_OPT_2 & 4)) {
			$users_opt ^= 128;
		}

		if (!($FUD_OPT_2 & 8)) {
			$users_opt ^= 256;
		}

		$i = 0;
		$al = $alias;
		$salt   = substr(md5(uniqid(mt_rand(), true)), 0, 9);
		$passwd = sha1($salt . sha1($_POST['passwd']));
		while (($user_added = db_li('INSERT INTO '.$DBHOST_TBL_PREFIX.'users
			(login, alias, passwd, salt, name, email, time_zone, join_date, theme, users_opt, last_read) VALUES (
			'._esc($_POST['login']).', \''.$al.'\', \''.$passwd.'\', \''.$salt.'\',
			'._esc($_POST['name']).', '._esc($_POST['email']).', \''.$SERVER_TZ.'\',
			'.__request_timestamp__.', '.$default_theme.', '.$users_opt.', '.__request_timestamp__.')',
			$ef, 1)) === null) 
		{
			if (q_singleval('SELECT id FROM '.$DBHOST_TBL_PREFIX.'users WHERE login='._esc($_POST['login']))) {
				$error = 1;
				$err_login = errorify('Login ('.htmlspecialchars($_POST['login']).') is already in use.');
				break;
			} else if (q_singleval('SELECT id FROM '.$DBHOST_TBL_PREFIX.'users WHERE email='._esc($_POST['email']))) {
				$error = 1;
				$err_email = errorify('E-mail ('.htmlspecialchars($_POST['email']).') is already in use.');
				break;
			} else if ($ef == 4) {
				$al = $alias . '_' . ++$i;
			} else {
				$error = 1;
			}
			if ($error) {
				break;
			}
		}
	}

	if ($error) {
		foreach (array('login', 'passwd', 'email', 'name') as $v) {
			$$v = isset($_POST[$v]) ? htmlspecialchars($_POST[$v]) : '';
		}
	} else {
		$login = $passwd = $email = $name = '';
	}

	require($WWW_ROOT_DISK . 'adm/header.php');

	if ($error) {
		echo errorify('Error adding user.');
	} else if (!empty($user_added)) {
		logaction(_uid, 'CREATE_USER', 0, $_POST['login']);
		echo successify('User was successfully added. [ <a href="admuser.php?act=1&amp;usr_id='. $user_added .'&amp;'. __adm_rsid .'">Edit user '. $_POST['login'] .'</a> ]<br />');
	}
?>
<h2>Add User</h2>
<form id="frm_usr" method="post" action="admuseradd.php">
<?php echo _hs; ?>
Register a new forum user:
<table class="datatable solidtable">
	<tr class="field">
		<td>Login:</td>
		<td><?php if ($error && isset($err_login)) { echo $err_login; } ?><input tabindex="1" type="text" name="login" value="<?php echo $login; ?>" size="30" /></td>
	</tr>
	<tr class="field">
		<td>Password:</td>
		<td><?php if ($error && isset($err_passwd)) { echo $err_passwd; } ?><input tabindex="2" type="text" name="passwd" value="<?php echo $passwd; ?>" size="30" /> <font size="-1">[ <a href="#" onclick="randomPassword();">Generate</a> ]</font></td>
	</tr>
	<tr class="field">
		<td>E-mail:</td>
		<td><?php if ($error && isset($err_email)) { echo $err_email; } ?><input tabindex="3" type="text" name="email" value="<?php echo $email; ?>" size="30" /></td>
	</tr>
	<tr class="field">
		<td>Real Name:</td>
		<td><input type="text" name="name" value="<?php echo $name; ?>" tabindex="4" size="30" /></td>
	</tr>
	<tr class="fieldaction">
		<td colspan="2" align="right"><input type="submit" value="Add User" tabindex="5" name="usr_add" /></td>
	</tr>
</table>
</form>
<p><a href="admuser.php?<?php echo __adm_rsid; ?>">&laquo; Back to User Administration System</a></p>
<script type="text/javascript">
/* <![CDATA[ */
document.forms['frm_usr'].login.focus();

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
/* ]]> */
</script>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
