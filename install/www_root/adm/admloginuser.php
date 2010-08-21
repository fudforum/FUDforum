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
	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('logaction.inc');
	fud_use('cookies.inc');
	fud_use('users.inc');
	fud_use('users_reg.inc');

	if (!empty($_POST['login']) && !empty($_POST['passwd'])) {
		$r = db_sab('SELECT id, passwd, salt FROM '.$DBHOST_TBL_PREFIX.'users WHERE login='._esc($_POST['login']).' AND users_opt>=1048576 AND (users_opt & 1048576) > 0 AND (last_login + '.$MIN_TIME_BETWEEN_LOGIN.') < '.__request_timestamp__);
		if ($r && (empty($r->salt) && $r->passwd == md5($_POST['passwd']) || $r->passwd == sha1($r->salt . sha1($_POST['passwd'])))) {
			$sid = user_login($r->id, $usr->ses_id, true);
			$GLOBALS['new_sq'] = regen_sq($r->id);
			header('Location: '. $WWW_ROOT .'adm/index.php?S='. $sid .'&SQ='. $new_sq);
			exit;
		} else {
			q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET last_login='.__request_timestamp__.' WHERE login='._esc($_POST['login']));
			logaction(0, 'WRONGPASSWD', 0, "Invalid admin login attempt from: ".get_ip()." using ".htmlspecialchars($_POST['login'], ENT_QUOTES)." / ".htmlspecialchars($_POST['passwd'], ENT_QUOTES));
			$err = 'Only administrators with proper access credentials can login via this control panel.<br />Incorrect username/password or flood check triggered.';
		}
	} else {
		$err = '';
	}

	require($WWW_ROOT_DISK .'adm/header.php');
?>
<h1>Admin login</h1>
<?php
	if ($err) {
		echo '<span style="color:red;">'. $err .'</span><br />';
	}
?>
<p>Please enter your username and password to continue.</p>
<form method="post" action="admloginuser.php" name="admloginuser" id="admloginuser"><?php echo _hs; ?>
<table border="0" cellspacing="0" cellpadding="3">
<tr>
	<td>Login:</td>
	<td><input type="text" name="login" value="<?php if (isset($_POST['login'])) { echo htmlspecialchars($_POST['login']); } ?>" size="25" /></td>
</tr>
<tr>
	<td>Password:</td>
	<td><input type="password" name="passwd" value="" size="25" /></td>
</tr>
<tr>
	<td align="right" colspan="2"><input type="submit" name="btn_login" value="Login" /></td>
</tr>
</table>
</form>
<br /><br /><br />
<?php 	require($WWW_ROOT_DISK .'adm/footer.php'); ?>
