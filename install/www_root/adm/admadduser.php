<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admadduser.php,v 1.1 2002/11/11 04:30:50 hackie Exp $
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
	
	include_once "GLOBALS.php";
	
	fud_use('adm.inc', true);
	fud_use('users.inc');	
	fud_use('widgets.inc', true);
	fud_use('util.inc');
	
	list($ses, $usr_adm) = initadm();
	
	cache_buster();

	$error = NULL;

function errorify($err)
{
	return '<font color="red">'.$err.'</font><br>';
}

function validate_input($input)
{
	if (empty($input['login'])) {
		$GLOBALS['err_login'] = errorify("Login cannot be blank");
		return 1;
	}
	
	if (bq("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE login='".$input['login']."'")) {
		$GLOBALS['err_login'] = errorify("Login (".stripslashes($input['login']).") is already in use.");
		return 1;
	}
	
	if (empty($input['passwd'])) {
		$GLOBALS['err_passwd'] = errorify("Password cannot be blank");
		return 1;
	}
	
	if (empty($input['email'])) {
		$GLOBALS['err_email'] = errorify("E-mail cannot be blank");
		return 1;
	}	
	
	if (bq("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE email='".$input['email']."'")) {
		$GLOBALS['err_email'] = errorify("Email (".stripslashes($input['email']).") is already in use.");
		return 1;
	}
	
	return 0;
}

	if (count($HTTP_POST_VARS) && !($error=validate_input($HTTP_POST_VARS))) {
	
		$default_theme = q_singleval("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."themes WHERE t_default='Y' AND enabled='Y'");
	
		db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'users+');
	
		if ($GLOBALS['USE_ALIASES'] == 'Y') {
			$alias = addslashes(htmlspecialchars(stripslashes($HTTP_POST_VARS['login'])));
			$alias_len = strlen($alias);
			$i = 0;
			while (bq("SELECT id FROM ".$GLOBALS['DBHOST_TBL_PREFIX']."users WHERE alias='".$alias."'")) {
				if ($i) {
					$alias = substr_replace($alias, ' '.$i, $alias_len, (strlen($i-1) + 1));
					$i++;
				} else {
					$alias .= ' 1';
					$i++;
				}	
			}
		} else {
			$alias = addslashes(htmlspecialchars(stripslashes($HTTP_POST_VARS['login'])));
		}
		
		$r = q("INSERT INTO ".$GLOBALS['DBHOST_TBL_PREFIX']."users (
			login,
			alias,
			passwd,
			name,
			email,
			time_zone,
			join_date,
			theme,
			coppa,
			last_read,
			default_view,
			email_conf		
			)
			VALUES(
			'".$HTTP_POST_VARS['login']."',
			'".$alias."',
			'".md5($HTTP_POST_VARS['passwd'])."',
			'".$HTTP_POST_VARS['name']."',
			'".$HTTP_POST_VARS['email']."',
			'".$GLOBALS['SERVER_TZ']."',
			".__request_timestamp__.",
			".$default_theme.",
			'N',
			".__request_timestamp__.",
			'".$GLOBALS['DEFAULT_THREAD_VIEW']."',
			'Y'
			)");
			
		if ($r) {
			$user_added = 1;
		}	
			
		db_unlock();	
	}

	require('admpanel.php'); 
?>
<h2>Add User</h2>
<?php
	if (!empty($error)) {
		echo '<h1 color="red">Error Has Occured</h1>';
	}
	
	if (isset($user_added)) {
		echo '<font size="+1" color="green">User ('.stripslashes($HTTP_POST_VARS['login']).') was successfully added.<font><br>';
		$HTTP_POST_VARS = array();
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
		<td><?php echo $err_login; ?><input type="text" name="login" value="<?php echo stripslashes($HTTP_POST_VARS['login']); ?>" size="30"></td>
	</tr>
	<tr bgcolor="#f1f1f1">
		<td>Password:</td>
		<td><?php echo $err_passwd; ?><input type="text" name="passwd" value="<?php echo stripslashes($HTTP_POST_VARS['passwd']); ?>" size="30"></td>
	</tr>
	<tr bgcolor="#f1f1f1">
		<td>E-mail:</td>
		<td><?php echo $err_email; ?><input type="text" name="email" value="<?php echo stripslashes($HTTP_POST_VARS['email']); ?>" size="30"></td>
	</tr>
	<tr bgcolor="#f1f1f1">
		<td>Real Name:</td>
		<td><input type="text" name="name" value="<?php echo stripslashes($HTTP_POST_VARS['name']); ?>" size="30"></td>
	</tr>
	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right><input type="submit" value="Add User" name="usr_add"></td>
	</tr>
</table>
</form>
<?php require('admclose.html'); ?>