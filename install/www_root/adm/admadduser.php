<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admadduser.php,v 1.6 2003/09/30 01:42:28 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('widgets.inc', true);

	$error = NULL;

function errorify($err)
{
	return '<font color="red">'.$err.'</font><br>';
}

function validate_input()
{
	if (empty($_POST['login'])) {
		$GLOBALS['err_login'] = errorify('Login cannot be blank');
		return 1;
	}
	
	if (q_singleval('SELECT id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WHERE login=\''.addslashes($_POST['login']).'\'')) {
		$GLOBALS['err_login'] = errorify('Login ('.htmlspecialchars($_POST['login']).') is already in use.');
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
	
	if (q_singleval('SELECT id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users WHERE email=\''.addslashes($_POST['email']).'\'')) {
		$GLOBALS['err_email'] = errorify('Email ('.htmlspecialchars($_POST['email']).') is already in use.');
		return 1;
	}
	
	return 0;
}

	if (isset($_POST['usr_add']) && !($error = validate_input())) {
		$default_theme = q_singleval('SELECT id FROM '.$DBHOST_TBL_PREFIX.'themes WHERE theme_opt=3');
		$alias = addslashes(htmlspecialchars($_POST['login']));
		db_lock($DBHOST_TBL_PREFIX.'users WRITE');
	
		if ($USE_ALIASES == 'Y') {
			$i = 0;
			while (q_singleval('SELECT id FROM '.$DBHOST_TBL_PREFIX.'users WHERE alias=\''.(!$i ? $alias : $alias . '_' . $i).'\'')) {
				++$i;
			}
			if ($i) {
				$alias .= '_' . $i;
			}
		}

//		$users_opt = 4488117 ^ 2097152 | 131072 | 
		
		$user_added = db_qid("INSERT INTO ".$DBHOST_TBL_PREFIX."users 
			(login, alias, passwd, name, email, time_zone, join_date, theme, users_opt, last_read) VALUES (
			'".addslashes($_POST['login'])."',
			'".$alias."',
			'".md5($_POST['passwd'])."',
			'".addslashes($_POST['name'])."',
			'".addslashes($_POST['email'])."',
			'".$SERVER_TZ."',
			".__request_timestamp__.",
			".$default_theme.",
			".$users_opt.",
			".__request_timestamp__."
			)");
			
		db_unlock();
		$login = $passwd = $email = $name = '';
	} else {
		$login = isset($_POST['login']) ? htmlspecialchars($_POST['login']) : '';
		$passwd = isset($_POST['passwd']) ? htmlspecialchars($_POST['passwd']) : '';
		$email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
		$name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 
?>
<h2>Add User</h2>
<?php
	if (!empty($error)) {
		echo '<h1 color="red">Error Has Occured</h1>';
	} else if (!empty($user_added)) {
		echo '<font size="+1" color="green">User ('.$_POST['login'].') was successfully added.<font><br>';
	}
?>
<form name="frm_usr" method="post" action="admadduser.php">
<?php echo _hs; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td colspan=2>Register a new forum user.</td>
	</tr>
	
	<tr bgcolor="#f1f1f1">
		<td>Login:</td>
		<td><?php if (isset($err_login)) { echo $err_login; } ?><input type="text" name="login" value="<?php echo $login; ?>" size="30"></td>
	</tr>
	<tr bgcolor="#f1f1f1">
		<td>Password:</td>
		<td><?php if (isset($err_passwd)) { echo $err_passwd; } ?><input type="text" name="passwd" value="<?php echo $passwd; ?>" size="30"></td>
	</tr>
	<tr bgcolor="#f1f1f1">
		<td>E-mail:</td>
		<td><?php if (isset($err_email)) { echo $err_email; } ?><input type="text" name="email" value="<?php echo $email; ?>" size="30"></td>
	</tr>
	<tr bgcolor="#f1f1f1">
		<td>Real Name:</td>
		<td><input type="text" name="name" value="<?php echo $name; ?>" size="30"></td>
	</tr>
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right><input type="submit" value="Add User" name="usr_add"></td>
	</tr>
</table>
</form>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>