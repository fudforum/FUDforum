<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admuser.php,v 1.21 2003/04/23 17:18:29 hackie Exp $
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

	require('GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('customtags.inc', true);
	fud_use('users_adm.inc', true);
	fud_use('logaction.inc');
	fud_use('iemail.inc');

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (isset($_GET['act'], $_GET['usr_id'])) {
		$act = $_GET['act'];
		$usr_id = (int)$_GET['usr_id'];
	} else if (isset($_POST['act'], $_POST['usr_id'])) {
		$act = $_POST['act'];
		$usr_id = (int)$_POST['usr_id'];
	} else {
		$usr_id = $act = '';
	}
	if ($act && $usr_id && !($u = db_sab('SELECT * FROM '.$tbl.'users WHERE id='.$usr_id))) {
		$usr_id = $act = '';
	}

	switch ($act) {
		case 'block':
			$u->blocked = $u->blocked == 'Y' ? 'N' : 'Y';
			q('UPDATE '.$tbl.'users SET blocked=\''.$u->blocked.'\' WHERE id='.$usr_id);
			break;
		case 'coppa':
			$u->coppa = $u->coppa == 'Y' ? 'N' : 'Y';
			q('UPDATE '.$tbl.'users SET coppa=\''.$u->coppa.'\' WHERE id='.$usr_id);
			break;
		case 'econf':
			$u->email_conf = $u->email_conf == 'Y' ? 'N' : 'Y';
			q('UPDATE '.$tbl.'users SET email_conf=\''.$u->email_conf.'\' WHERE id='.$usr_id);
			break;
		case 'color':
			$u->custom_color = trim($_POST['custom_color']);
			q('UPDATE '.$tbl.'users SET custom_color='.strnull(addslashes($u->custom_color)).' WHERE id='.$usr_id);
			break;
		case 'reset':
			$user_theme_name = q_singleval('SELECT name FROM '.$tbl.'themes WHERE '.(!$u->theme ? "t_default='Y'" : 'id='.$u->theme));
			if ($EMAIL_CONFIRMATION == 'Y' && $u->email_conf == 'N') {
				$conf_key = usr_email_unconfirm($u->id);
				$url = '{ROOT}?t=emailconf&conf_key='.$conf_key;
				
				send_email($GLOBALS['NOTIFY_FROM'], $u->email, $GLOBALS['register_conf_subject'], $GLOBALS['reset_confirmation'], "");
			} else {
				db_lock($tbl . 'users WRITE');
				do {
					$reset_key = md5(get_random_value(128));
				} while (q_singleval("SELECT id FROM ".$tbl."users WHERE reset_key='".$reset_key."'"));
				q("UPDATE ".$tbl."users SET reset_key='".$reset_key."' WHERE id=".$u->id);
				db_unlock();

				$url = '{ROOT}?t=reset&reset_key='.$reset_key;
				include_once($GLOBALS['INCLUDE'] . 'theme/' . $user_theme_name . '/rst.inc');
				send_email($GLOBALS['NOTIFY_FROM'], $u->email, $GLOBALS['reset_newpass_title'], $GLOBALS['reset_reset'], "");
			}
			break;
		case 'del':
			logaction(_uid, 'DELETE_USER', 0, addslashes(htmlspecialchars($usr->login)));
			usr_delete($usr_id);
			unset($act, $u);
			$usr_id = '';
			break;
		case 'admin':
			if ($u->is_mod == 'A') {
				if (!isset($_POST['adm_confirm'])) {
?>
<html>
<title>Adminstrator confirmation</title>
<body color="white">
<form method="post" action="admuser.php"><?php echo _hs; ?>
<input type="hidden" name="act" value="admin">
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
<input type="hidden" name="adm_confirm" value="1">
<div align="center">You are taking away administration privileges from <font color="red"><b><?php echo $u->alias; ?></b></font>!<br><br>
Are you sure you want to do this?<br>
<input type="submit" value="Yes" name="btn_yes"> <input type="submit" value="No" name="btn_no">
<div>
</form>
</body></html>
<?php
					exit;
				} else if (isset($_POST['btn_yes'])) {
					$u->is_mod = q_singleval('SELECT count(*) FROM '.$tbl.'mod WHERE user_id='.$u->id) ? 'Y' : 'N';
					q('UPDATE '.$tbl.'users SET is_mod=\''.$u->is_mod.'\' WHERE id='.$usr_id);
				}
			} else {
				if (!isset($_POST['adm_confirm'])) {
?>
<html>
<title>Adminstrator confirmation</title>
<body color="white">
<form method="post" action="admuser.php"><?php echo _hs; ?>
<input type="hidden" name="act" value="admin">
<input type="hidden" name="adm_confirm" value="1">
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
<div align="center">WARNING: Making <font color="red"><b><?php echo $u->alias; ?></b></font> an <font color="red"><b>administrator</b></font> will give this person full
administration permissions to the forum. This individual will be able to do anything with the forum, including taking away your own administration permissions.
<br><br>Are you sure you want to do this?<br>
<input type="submit" value="Yes" name="btn_yes"> <input type="submit" value="No" name="btn_no">
<div>
</form>
</body></html>
<?php
					exit;
				} else if (isset($_POST['btn_yes'])) {
					$u->is_mod = 'A';
					q('UPDATE '.$tbl.'users SET is_mod=\''.$u->is_mod.'\' WHERE id='.$usr_id);
				}
			}
			break;								
	}

	$search_error = $login_error = '';
	if ($usr_id) {
		/* deal with custom tags */
		if (!empty($_POST['c_tag'])) {
			q('INSERT INTO '.$tbl.'custom_tags (name, user_id) VALUES('.strnull(addslashes($_POST['c_tag'])).', '.$usr_id.')');
		} else if (!empty($_GET['deltag'])) {
			q('DELETE FROM '.$tbl.'custom_tags WHERE id='.(int)$_GET['deltag']);
		} else {
			$nada = 1;
		}
		if (!isset($nada) && db_affected()) {
			ctag_rebuild_cache($usr_id);
		}

		/* changing password */
		if (!empty($_POST['login_passwd'])) {
			q('UPDATE '.$tbl.'users SET passwd=\''.md5($_POST['login_passwd']).'\' WHERE id='.$usr_id);
		} else if (!empty($_POST['login_name']) && $u->login != $_POST['login_name']) { /* chanding login name */
			$login = addslashes($_POST['login_name']);
			$alias = "'" . substr(htmlspecialchars($login), 0, $GLOBALS['MAX_LOGIN_SHOW']) . "'";
			$login = "'" . $login . "'";
			db_lock($tbl.'users WRITE');
			if (!q_singleval('SELECT id FROM '.$tbl.'users WHERE alias='.$alias) && !q_singleval('SELECT id FROM '.$tbl.'users WHERE login='.$login)) {
				$u->login = $_POST['login_name'];
				if ($GLOBALS['USE_ALIASES'] != 'Y') {
					$u->alias = substr(htmlspecialchars($u->login), 0, $GLOBALS['MAX_LOGIN_SHOW']);
					q('UPDATE '.$tbl.'users SET login='.$login.', alias='.$alias.' WHERE id='.$usr_id);
				} else {
					q('UPDATE '.$tbl.'users SET login='.$login.' WHERE id='.$usr_id);
				}
			} else {
				$login_error = '<font color="#FF0000">Someone is already using that login name.</font><br>';
			}
			db_unlock();
		}
	} else if (!empty($_POST['usr_email']) || !empty($_POST['usr_login'])) {
		/* user searching logic */
		$item = !empty($_POST['usr_email']) ? $_POST['usr_email'] : $_POST['usr_login'];
		$field = !empty($_POST['usr_email']) ? 'email' : ($GLOBALS['USE_ALIASES'] == 'Y' ? 'alias' : 'login');
		if (strpos($item, '*') !== FALSE) {
			$like = 1;
			$item = str_replace('*', '%', $item);
			$item_s = str_replace('\\', '\\\\', $item);
			if ($GLOBALS['USE_ALIASES'] == 'Y') {
				$item_s = htmlspecialchars($item_s);
			}
		} else {
			$like = 0;
			$item_s = $item;
		}
		$item_s = "'" . addslashes($item_s) . "'";

		$c = q('SELECT id, alias, email FROM '.$tbl.'users WHERE ' . $field . ($like ? ' LIKE ' : '=') . $item_s .' LIMIT 50');
		switch (($cnt = db_count($c))) {
			case 0:
				$search_error = '<font color="red">There are no users matching the specified '.$field.' mask.</font><br>';
				qf($c);
				break;
			case 1:
				list($usr_id) = db_rowarr($c);
				$u = db_sab('SELECT * FROM '.$tbl.'users WHERE id='.$usr_id);
				qf($c);
				break;
			default:
				echo 'There are '.$cnt.' users that match this '.$field.' mask:<br>';
				while ($r = db_rowarr($c)) {
					echo '<a href="admuser.php?usr_id='.$r[0].'&act=m&'._rsidl.'">Pick user</a> <b>'.$r[1].' / '.htmlspecialchars($r[2]).'</b><br>';
				}
				qf($c);
				exit;
				break;
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php'); 
?>
<h2>User Adminstration System</h2>
<form name="frm_usr" method="post" action="admuser.php">
<?php echo _hs . $search_error; ?>
<table border=0 cellspacing=1 cellpadding=3>
	<tr bgcolor="#bff8ff">
		<td colspan=2>Search for User</td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>By <?php echo ($GLOBALS['USE_ALIASES'] != 'Y' ? 'Login' : 'Alias'); ?>:</td>
		<td><input type="text" name="usr_login"></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td>By Email:</td>
		<td><input type="text" name="usr_email"></td>
	</tr>

	<tr bgcolor="#bff8ff">
		<td colspan=2 align=right><input type="submit" value="Search" name="usr_search"></td>
	</tr>
</table>
</form>
<?php if ($usr_id) { ?>
<table border=0 cellspacing=0 cellpadding=3>

<form action="admuser.php" method="post"><?php echo _hs; ?>
	<tr bgcolor="#f1f1f1"><td>Login:</td><td><?php echo $login_error; ?><input type="text" value="<?php echo htmlspecialchars($u->login); ?>" maxLength="<?php echo $GLOBALS['MAX_LOGIN_SHOW'] ?>" name="login_name"> <input type="submit" name="submit" value="Change Login Name"></td></tr>
	<tr bgcolor="#f1f1f1"><td>Password:</td><td><input type="text" value="" name="login_passwd"> <input type="submit" name="submit" value="Change Password"></td></tr>
	<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
	<input type="hidden" name="act" value="nada">

</form>	
<?php
	if($GLOBALS['USE_ALIASES']=='Y') {
		echo '<tr bgcolor="#f1f1f1"><td>Alias:</td><td>'.$u->alias.'</td></tr>';
	}
?>
	<tr bgcolor="#f1f1f1"><td>Email:</td><td><?php echo $u->email; ?></td></tr>
	<tr bgcolor="#f1f1f1"><td>Name:</td><td><?php echo $u->name; ?></td></tr>
<?php
	if ($u->bday) {
		echo '<tr bgcolor="#f1f1f1"><td>Birthday:</td><td>' . strftime('%B, %d, %Y', strtotime($u->bday)) . '</td></tr>';
	}

	echo '<tr bgcolor="#f1f1f1"><td align=middle colspan=2><font size="+1">&gt;&gt; <a href="../'.__fud_index_name__.'t=register&mod_id='.$usr_id.'&'._rsidl.'">Change User\'s Profile</a> &lt;&lt;</font></td></tr>';
	echo '<tr bgcolor="#f1f1f1"><td nowrap><font size="+1"><b>Forum Administrator:</b></td><td>'.($u->is_mod != 'A' ? 'N' : '<b><font size="+2" color="red">Y</font>').' [<a href="admuser.php?act=admin&usr_id='.$usr_id . '&' . _rsidl.'">Toggle</a>]</td></tr>';
	echo '<tr bgcolor="#f1f1f1"><td>Blocked:</td><td>'.$u->blocked.' [<a href="admuser.php?act=block&usr_id=' . $usr_id . '&' . _rsidl.'">Toggle</a>]</td></tr>';
	echo '<tr bgcolor="#f1f1f1"><td>Email Confirmation:</td><td>'.$u->email_conf.' [<a href="admuser.php?act=econf&usr_id=' . $usr_id . '&' . _rsidl .'">Toggle</a>]</td></tr>';

	if ($GLOBALS['COPPA'] == 'Y') { 
		echo '<tr bgcolor="#f1f1f1"><td>COPPA:</td><td>'.$u->coppa.' [<a href="admuser.php?act=coppa&usr_id=' . $usr_id . '&' . _rsidl .'">Toggle</a>]</td></tr>';
	}

	echo '<tr bgcolor="#f1f1f1"><td nowrap valign="top">Moderating Forums:</td><td valign="top">';
	$c = q('SELECT f.name FROM '.$tbl.'mod mm INNER JOIN '.$tbl.'forum f ON mm.forum_id=f.id WHERE mm.user_id='.$usr_id);
	if (db_count($c)) {
		echo '<table border=0 cellspacing=1 cellpadding=3>';
		while ($r = db_rowarr($c)) {
			echo '<tr><td>'.$r[0].'</td></tr>';
		}
		echo '</table>';
	} else {
		echo 'None<br>';
	}
	qf($c);
?>	
	<a name="mod_here"> </a>
	<a href="#mod_here" onClick="javascript: window.open('admmodfrm.php?usr_id=<?php echo $usr_id . '&' . _rsidl; ?>', 'frm_mod', 'menubar=false,width=200,height=400,screenX=100,screenY=100,scrollbars=yes');">Modify Moderation Permissions</a>
	<tr bgcolor="#f1f1f1"><td valign=top>Custom Tags:</td><td valign="top">
<?php
	$c = uq('SELECT name, id FROM '.$tbl.'custom_tags WHERE user_id='.$usr_id);
	while ($r = db_rowarr($c)) {
		echo $r[0] . ' [<a href="admuser.php?act=nada&usr_id='.$usr_id.'&deltag=' . $r[1] . '&' . _rsidl . '">Delete</a>]<br>';
	}
	qf($c);
?>
	<form name="extra_tags" action="admuser.php" method="post">
	<?php echo _hs; ?>
	<input type="text" name="c_tag">
	<input type="submit" value="Add">
	<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
	<input type="hidden" name="act" value="nada">
	</form>
	</td></tr>

	<tr bgcolor="#f1f1f1"><td valign=top>Profile Link Color:</td>
		<td valign=top>
		<form name="extra_tags" method="post" action="admuser.php">
		<?php echo _hs; ?>
		<input type="text" name="custom_color" maxLength="255" value="<?php echo $u->custom_color; ?>">
		<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
		<input type="hidden" name="act" value="color"><input type="submit" value="Change">
		</form>
		</td>
	</tr>
	<tr bgcolor="#f1f1f1">
		<td colspan=2><br><br><b>Actions:</b></td>
	</tr>

	<tr bgcolor="#f1f1f1">
	<td colspan=2>
<?php
	if ($GLOBALS['PM_ENABLED'] == 'Y') {
		echo '<a href="../'.__fud_index_name__.'?t=ppost&'._rsidl.'&toi='.$usr_id.'">Send Private Message</a> | ';
	}
	if ($GLOBALS['ALLOW_EMAIL'] == 'Y') {
		echo '<a href="../'.__fud_index_name__.'?t=email&toi='.$usr_id.'&'._rsidl.'">Send Email</a> | ';
	} else {
		echo '<a href="mailto:'.$u->email.'">Send Email</a> | ';
	}

	echo '	<a href="../'.__fud_index_name__.'?t=showposts&id='.$usr_id.'&'._rsid.'">See Posts</a> | <a href="admuser.php?act=reset&usr_id='.$usr_id.'&'._rsidl.'">Reset Password</a> | <a href="admuser.php?act=del&usr_id='.$usr_id.'&'._rsidl.'">Delete User</a></td></tr>';
}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>