<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admadduser.php,v 1.20 2004/06/07 15:24:53 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	$error = 0;

function validate_input()
{
	if (empty($_POST['login'])) {
		$GLOBALS['err_login'] = errorify('Login cannot be blank');
		return 1;
	}

	if (empty($_POST['passwd'])) {
		$GLOBALS['err_passwd'] = errorify('Password cannot be blank');
		return 1;
	}

	if (empty($_POST['email'])) {
		$GLOBALS['err_email'] = errorify('E-mail cannot be blank');
		return 1;
	}

	return 0;
}

	if (isset($_POST['usr_add']) && !($error = validate_input())) {
		$default_theme = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."themes WHERE theme_opt>=2 AND (theme_opt & 2) > 0 LIMIT 1");
		if (strlen($_POST['login']) > $MAX_LOGIN_SHOW) {
			$alias = substr($_POST['login'], 0, $MAX_LOGIN_SHOW);
		} else {
			$alias = $_POST['login'];
		}
		$alias = addslashes(htmlspecialchars($alias));

		$users_opt = 1|2|4|16|32|64|128|256|512|2048|4096|8192|16384|131072|4194304;

		if (!($FUD_OPT_2 & 4)) {
			$users_opt ^= 128;
		}

		if (!($FUD_OPT_2 & 8)) {
			$users_opt ^= 256;
		}

		$i = 0;
		$al = $alias;
		while (($user_added = db_li("INSERT INTO ".$DBHOST_TBL_PREFIX."users
			(login, alias, passwd, name, email, time_zone, join_date, theme, users_opt, last_read) VALUES (
			'".addslashes($_POST['login'])."', '".$al."', '".md5($_POST['passwd'])."',
			'".addslashes($_POST['name'])."', '".addslashes($_POST['email'])."', '".$SERVER_TZ."',
			".__request_timestamp__.", ".$default_theme.", ".$users_opt.", ".__request_timestamp__.")",
			$ef, 1)) === null) {

			if (__dbtype__ == 'pgsql') {
				if ($ef == $DBHOST_TBL_PREFIX.'users_i_l') {
					$ef = 2;
				} else if ($ef == $DBHOST_TBL_PREFIX.'users_i_e') {
					$ef = 3;
				} else if ($ef == $DBHOST_TBL_PREFIX.'users_i_a') {
					$ef = 4;
				}
			} else {
				if (strpos(mysql_error(), $_POST['login'])) {
					$ef = 2;
				} else if (strpos(mysql_error(), $_POST['email'])) {
					$ef = 3;
				} else {
					$ef = 4;
				}
			}
			if ($ef == 2) {
				$error = 1;
				$err_login = errorify('Login ('.htmlspecialchars($_POST['login']).') is already in use.');
				break;
			} else if ($ef == 3) {
				$error = 1;
				$err_email = errorify('Email ('.htmlspecialchars($_POST['email']).') is already in use.');
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

	if (!count($_POST) || (!$error && count($_POST))) {
		$login = $passwd = $email = $name = '';
	}

	if ($error) {
		$login = isset($_POST['login']) ? htmlspecialchars($_POST['login']) : '';
		$passwd = isset($_POST['passwd']) ? htmlspecialchars($_POST['passwd']) : '';
		$email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
		$name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>Add User</h2>
<?php
	if ($error) {
		echo '<h1 style="color: red">Error Has Occured</h1>';
	} else if (!empty($user_added)) {
		echo '<font size="+1" color="green">User ('.$_POST['login'].') was successfully added.<font><br>';
	}
?>
<form name="frm_usr" method="post" action="admadduser.php">
<?php echo _hs; ?>
<table class="datatable solidtable">
	<tr class="fieldtopic">
		<td colspan=2>Register a new forum user.</td>
	</tr>

	<tr class="field">
		<td>Login:</td>
		<td><?php if (isset($err_login)) { echo $err_login; } ?><input tabindex="1" type="text" name="login" value="<?php echo $login; ?>" size="30"></td>
	</tr>
	<tr class="field">
		<td>Password:</td>
		<td><?php if (isset($err_passwd)) { echo $err_passwd; } ?><input tabindex="2" type="text" name="passwd" value="<?php echo $passwd; ?>" size="30"></td>
	</tr>
	<tr class="field">
		<td>E-mail:</td>
		<td><?php if (isset($err_email)) { echo $err_email; } ?><input tabindex="3" type="text" name="email" value="<?php echo $email; ?>" size="30"></td>
	</tr>
	<tr class="field">
		<td>Real Name:</td>
		<td><input type="text" name="name" value="<?php echo $name; ?>" tabindex="4" size="30"></td>
	</tr>
	<tr class="fieldaction">
		<td colspan=2 align=right><input type="submit" value="Add User" tabindex="5" name="usr_add"></td>
	</tr>
</table>
</form>
<script>
<!--
document.frm_usr.login.focus();
//-->
</script>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
