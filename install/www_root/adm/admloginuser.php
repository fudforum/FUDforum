<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admloginuser.php,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	
	fud_use('db.inc');
	fud_use('static/login.inc');
	fud_use('static/widgets.inc');
	fud_use('static/adm.inc');
	fud_use('logaction.inc');

	if ( !empty($login) ) {
		
		if ( ($id = get_id_by_login($login)) ) {
			$usr = new fud_user;
			$usr->get_user_by_id($id);
			if ( $usr->passwd == md5($passwd) ) {
				if ( $usr->is_mod == 'A' ) {
					if ( !isset($ses) ) $ses = new fud_session;
					
					$ses->save_session($id, NULL);
					header("Location: admglobal.php?S=".$ses->ses_id);
					exit();
				}
				else $err = 'Only adminsitrators can login through this control panel';
			}
			else {
				logaction($id, 'WRONGPASSWD', 0, $GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']);
				$err = 'The password entered was incorrect';
			}
		}
		else $err = 'No such user';
	}
?>
<html>
<h2>Login Into the Forum</h2>
<?php
	if ( $err ) 
		echo '<font color="#ff0000">'.$err.'</font>';
?>
<form method="post">
<table border-0 cellspacing=0 cellpadding=3>
<tr>
	<td>Login:</td>
	<td><input type="text" name="login" value="<?php echo $login; ?>" size=25></td>
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