<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admloginuser.php,v 1.23 2005/09/08 14:17:13 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('err.inc');
	fud_use('db.inc');
	fud_use('logaction.inc');
	fud_use('cookies.inc');
	fud_use('users.inc');
	fud_use('users_reg.inc');

	if (isset($_POST['login'])) {
		if (($id = q_singleval("SELECT id FROM ".$DBHOST_TBL_PREFIX."users WHERE login="._esc($_POST['login'])." AND passwd='".md5($_POST['passwd'])."' AND users_opt>=1048576 AND (users_opt & 1048576) > 0"))) {
			$sid = user_login($id, $usr->ses_id, true);
			header('Location: '.$WWW_ROOT.'adm/admglobal.php?S='.$sid.'&SQ='.$new_sq);
			exit;
		} else {
			logaction(0, 'WRONGPASSWD', 0, "Invalid admin login attempt via adminstrative control panel from: ".get_ip());
			$err = 'Only administrators with proper access credentials can login via this control panel';
		}
	} else {
		$err = '';
	}
?>
<html>
<h2>Login Into the Forum</h2>
<?php
	if ($err) {
		echo '<font color="#ff0000">'.$err.'</font>';
	}
?>
<form method="post" action="admloginuser.php"><?php echo _hs; ?>
<table border=0 cellspacing=0 cellpadding=3>
<tr>
	<td>Login:</td>
	<td><input type="text" name="login" value="<?php if (isset($_POST['login'])) { echo htmlspecialchars($_POST['login']); } ?>" size=25></td>
</tr>
<tr>
	<td>Password:</td>
	<td><input type="password" name="passwd" size=25></td>
</tr>

<tr>
	<td align=right colspan=2><input type="submit" name="btn_login" value="Login"></td>
</tr>
</table>
</form>
</html>