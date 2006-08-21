<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admuser.php,v 1.72 2006/08/21 15:15:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	require('./GLOBALS.php');
	fud_use('adm.inc', true);
	fud_use('customtags.inc', true);
	fud_use('users_reg.inc');
	fud_use('users_adm.inc', true);
	fud_use('logaction.inc');
	fud_use('iemail.inc');
	fud_use('private.inc');

	if (isset($_GET['act'], $_GET['usr_id'])) {
		$act = $_GET['act'];
		$usr_id = (int)$_GET['usr_id'];
	} else if (isset($_POST['act'], $_POST['usr_id'])) {
		$act = $_POST['act'];
		$usr_id = (int)$_POST['usr_id'];
	} else {
		$usr_id = $act = '';
	}
	if ($act && $usr_id && !($u = db_sab('SELECT * FROM '.$DBHOST_TBL_PREFIX.'users WHERE id='.$usr_id))) {
		$usr_id = $act = '';
	}
	/* check if ban had expired */
	if ($usr_id && !$act && $u->users_opt & 65536 && $u->ban_expiry && $u->ban_expiry < __request_timestamp__) {
		q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET ban_expiry=0, users_opt=users_opt &~ 65536 WHERE id='.$usr_id);
	}

	$keys = array('block'=>65536, 'coppa'=>262144, 'econf'=>131072, 'sig'=>67108864, 'pm'=>33554432, 'conf'=>2097152, 'accmod'=>268435456);

	switch ($act) {
		case 'block':
		case 'coppa':
		case 'econf':
		case 'conf':
		case 'sig':
		case 'pm':
		case 'accmod':
			if ($act == 'block' && isset($_POST['ban_duration'])) {
				/* for post requests involving ban, do not act as a toggle */
				if (!isset($_POST['block'])) {
					$u->users_opt |= $keys[$act];
					$u->ban_expiry = 0;
				} else {
					$u->users_opt &= ~$keys[$act];
					$u->ban_expiry = (int) $_POST['ban_duration'] * 86400;
					if ($u->ban_expiry) {
						$u->ban_expiry += __request_timestamp__;
					}
				}
				$block = $u->ban_expiry;
			} else {
				$block = 'ban_expiry';
			}

			if ($u->users_opt & $keys[$act]) {
				q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET ban_expiry='.$block.', users_opt=users_opt & ~ '.$keys[$act].' WHERE id='.$usr_id);
				$u->users_opt ^= $keys[$act];
			} else {
				q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET ban_expiry='.$block.', users_opt=users_opt|'.$keys[$act].' WHERE id='.$usr_id);
				$u->users_opt |= $keys[$act];
			}

			if (isset($_GET['f'])) {
				header('Location: '.$WWW_ROOT.__fud_index_name__.$usr->returnto);
				exit;
			}
			break;
		case 'color':
			$u->custom_color = trim($_POST['custom_color']);
			q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET custom_color='.ssn($u->custom_color).' WHERE id='.$usr_id);
			break;
		case 'reset':
			$user_theme_name = q_singleval('SELECT name FROM '.$DBHOST_TBL_PREFIX.'themes WHERE '.(!$u->theme ? "theme_opt>=2 AND (theme_opt & 2) > 0" : 'id='.$u->theme));
			if ($FUD_OPT_2 & 1 && !($u->users_opt & 131072)) {
				$conf_key = usr_email_unconfirm($u->id);
				$url = $WWW_ROOT . __fud_index_name__ . '?t=emailconf&conf_key='.$conf_key;
				send_email($NOTIFY_FROM, $u->email, $register_conf_subject, $reset_confirmation, "");
				logaction(_uid, 'SEND_ECONF', 0, char_fix(htmlspecialchars($u->login)));
			} else {
				$user_theme_name = q_singleval('SELECT name FROM '.$DBHOST_TBL_PREFIX.'themes WHERE '.(!$u->theme ? 'theme_opt=3' : 'id='.$u->theme));
				q("UPDATE ".$DBHOST_TBL_PREFIX."users SET reset_key='".($reset_key = md5(get_random_value(128)))."' WHERE id=".$u->id);

				$url = $WWW_ROOT . __fud_index_name__ . '?t=reset&reset_key='.$reset_key;
				include_once($INCLUDE . 'theme/' . $user_theme_name . '/rst.inc');
				send_email($NOTIFY_FROM, $u->email, $reset_newpass_title, $reset_reset, "");
				logaction(_uid, 'ADM_RESET_PASSWD', 0, char_fix(htmlspecialchars($u->login)));
			}
			echo '<h2>Password was successfully reset and e-mailed to the user.</h2>';
			break;
		case 'del':
			if ($usr_id == 1) {
				break;
			}

			if (!isset($_POST['del_confirm'])) {
?>
<html>
<title>User Deletion confirmation</title>
<body color="white">
<form method="post" action="admuser.php"><?php echo _hs; ?>
<input type="hidden" name="act" value="del">
<input type="hidden" name="f" value="<?php echo (int) isset($_GET['f']); ?>">
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
<input type="hidden" name="del_confirm" value="1">
<div align="center">You are about to delete <font color="red"><b><?php echo $u->alias; ?></b></font>'s account!<br><br>
Are you sure you want to do this, once deleted the account cannot be recovered?<br>
<input type="submit" value="Yes" name="btn_yes"> <input type="submit" value="No" name="btn_no">
</div>
<?php
	if (isset($_GET['f'])) {
		echo '<input type="hidden" name="f" value="1">';
	}
?>
</form>
</body></html>
<?php
					exit;
			} else if (isset($_POST['btn_yes'])) {
				logaction(_uid, 'DELETE_USER', 0, char_fix(htmlspecialchars($usr->login)));
				usr_delete($usr_id);
				unset($act, $u);
				$usr_id = '';
			}
			if (isset($_POST['f']) || isset($_GET['f'])) {
				header('Location: '.$WWW_ROOT.__fud_index_name__.'?'.$usr->returnto);
				exit;
			}
			break;
		case 'admin':
			if ($u->users_opt & 1048576) {
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
</div>
</form>
</body></html>
<?php
					exit;
				} else if (isset($_POST['btn_yes'])) {
					if (q_singleval('SELECT count(*) FROM '.$DBHOST_TBL_PREFIX.'mod WHERE user_id='.$u->id)) {
						q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET users_opt=(users_opt & ~ 1048576) |524288 WHERE id='.$usr_id);
						$u->users_opt ^= 1048576;
					} else {
						q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET users_opt=users_opt & ~ (524288|1048576) WHERE id='.$usr_id);
						$u->users_opt = $u->users_opt &~ (1048576|524288);
					}
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
</div>
</form>
</body></html>
<?php
					exit;
				} else if (isset($_POST['btn_yes'])) {
					q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET users_opt=(users_opt & ~ 524288) | 1048576 WHERE id='.$usr_id);
					$u->users_opt |= 1048576;
				}
			}
			break;
	}

	$search_error = $login_error = '';
	if ($usr_id) {
		/* deal with custom tags */
		if (!empty($_POST['c_tag'])) {
			q('INSERT INTO '.$DBHOST_TBL_PREFIX.'custom_tags (name, user_id) VALUES('.ssn($_POST['c_tag']).', '.$usr_id.')');
		} else if (!empty($_GET['deltag'])) {
			q('DELETE FROM '.$DBHOST_TBL_PREFIX.'custom_tags WHERE id='.(int)$_GET['deltag']);
		} else {
			$nada = 1;
		}
		if (!isset($nada) && db_affected()) {
			ctag_rebuild_cache($usr_id);
		}

		/* changing password */
		if (!empty($_POST['login_passwd'])) {
			q("UPDATE ".$DBHOST_TBL_PREFIX."users SET passwd='".md5($_POST['login_passwd'])."' WHERE id=".$usr_id);
			logaction(_uid, 'ADM_SET_PASSWD', 0, char_fix(htmlspecialchars($u->login)));
		} else if (!empty($_POST['login_name']) && $u->login != $_POST['login_name']) { /* chanding login name */
			$alias = _esc(make_alias($_POST['login_name']));
			$login = _esc($_POST['login_name']);

			if ($FUD_OPT_2 & 128) {
				if (db_li('UPDATE '.$DBHOST_TBL_PREFIX.'users SET login='.$login.' WHERE id='.$usr_id, $ef) === null) {
					$login_error = errorify('Someone is already using that login name.');
				}
			} else {
				if (db_li('UPDATE '.$DBHOST_TBL_PREFIX.'users SET login='.$login.', alias='.$alias.' WHERE id='.$usr_id, $ef) === null) {
					if ($ef == 2) {
						$login_error = errorify('Someone is already using that login name.');
					} else {
						$login_error = errorify('Someone is already using that alias.');
					}
				}
			}

			if (!$login_error) {
				rebuildmodlist();
				$u->login = $_POST['login_name'];
				if (!($FUD_OPT_2 & 128)) {
					$u->alias = make_alias($u->alias);
				}
			}
		}
	} else if (!empty($_POST['usr_email']) || !empty($_POST['usr_login'])) {
		/* user searching logic */
		$item = !empty($_POST['usr_email']) ? $_POST['usr_email'] : $_POST['usr_login'];
		$field = !empty($_POST['usr_email']) ? 'email' : ($FUD_OPT_2 & 128 ? 'alias' : 'login');
		if (strpos($item, '*') !== false) {
			$like = 1;
			$item = str_replace('*', '%', $item);
			$item_s = str_replace('\\', '\\\\', $item);
		} else {
			$like = 0;
			$item_s = $item;
		}
		if ($FUD_OPT_2 & 128) {
			$item_s = char_fix(htmlspecialchars($item_s));
		}
		$item_s = _esc($item_s);

		if (($cnt = q_singleval('SELECT count(*) FROM '.$DBHOST_TBL_PREFIX.'users WHERE ' . $field . ($like ? ' LIKE ' : '=') . $item_s .' LIMIT 50'))) {
			$c = uq('SELECT id, alias, email FROM '.$DBHOST_TBL_PREFIX.'users WHERE ' . $field . ($like ? ' LIKE ' : '=') . $item_s .' LIMIT 50');
		}
		switch ($cnt) {
			case 0:
				$search_error = errorify('There are no users matching the specified '.$field.' mask.');
				unset($c);
				break;
			case 1:
				list($usr_id) = db_rowarr($c);
				unset($c);
				$u = db_sab('SELECT * FROM '.$DBHOST_TBL_PREFIX.'users WHERE id='.$usr_id);
				/* check if ban had expired */
				if ($u->users_opt & 65536 && $u->ban_expiry && $u->ban_expiry < __request_timestamp__) {
					q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET ban_expiry=0, users_opt=users_opt &~ 65536 WHERE id='.$usr_id);
				}
				break;
			default:
				echo 'There are '.$cnt.' users that match this '.$field.' mask:<br>';
				while ($r = db_rowarr($c)) {
					echo '<a href="admuser.php?usr_id='.$r[0].'&act=m&'.__adm_rsidl.'">Pick user</a> <b>'.$r[1].' / '.htmlspecialchars($r[2]).'</b><br>';
				}
				unset($c);
				exit;
		}
	}

	require($WWW_ROOT_DISK . 'adm/admpanel.php');
?>
<h2>User Adminstration System</h2>
<form name="frm_usr" method="post" action="admuser.php">
<?php echo _hs . $search_error; ?>
<table class="datatable solidtable">
	<tr class="field">
		<td colspan=2>Search for User</td>
	</tr>

	<tr class="field">
		<td>By <?php echo ($FUD_OPT_2 & 128 ? 'Alias' : 'Login'); ?>:</td>
		<td><input tabindex="1" type="text" name="usr_login"></td>
	</tr>

	<tr class="field">
		<td>By Email:</td>
		<td><input tabindex="2" type="text" name="usr_email"></td>
	</tr>

	<tr class="fieldaction">
		<td colspan=2 align=right><input tabindex="3" type="submit" value="Search" name="usr_search"></td>
	</tr>
</table>
</form>
<script>
<!--
document.frm_usr.usr_login.focus();
//-->
</script>
<?php if ($usr_id) { ?>
<table class="datatable solidtable">

<form action="admuser.php" method="post"><?php echo _hs; ?>
	<tr class="field"><td>Login:</td><td><?php echo $login_error; ?><input type="text" value="<?php echo char_fix(htmlspecialchars($u->login)); ?>" maxLength="<?php echo $MAX_LOGIN_SHOW; ?>" name="login_name"> <input type="submit" name="submit" value="Change Login Name"></td></tr>
	<tr class="field"><td>Password:</td><td><input type="text" value="" name="login_passwd"> <input type="submit" name="submit" value="Change Password"></td></tr>
	<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
	<input type="hidden" name="act" value="nada">

</form>
<?php
	if($FUD_OPT_2 & 128) {
		echo '<tr class="field"><td>Alias:</td><td>'.$u->alias.'</td></tr>';
	}
?>
	<tr class="field"><td>Email:</td><td><?php echo $u->email; ?></td></tr>
	<tr class="field"><td>Name:</td><td><?php echo $u->name; ?></td></tr>
<?php
	if ($u->bday) {
		echo '<tr class="field"><td>Birthday:</td><td>' . strftime('%B, %d, %Y', strtotime($u->bday)) . '</td></tr>';
	}
	if ($u->reg_ip) {
		echo '<tr class="field"><td>Registration IP:</td><td>' . long2ip($u->reg_ip) . '</td></tr>';
	}

	echo '<tr class="field"><td align=middle colspan=2><font size="+1">&gt;&gt; <a href="../'.__fud_index_name__.'?t=register&mod_id='.$usr_id.'&'.__adm_rsidl.'">Change User\'s Profile</a> &lt;&lt;</font></td></tr>';
	echo '<tr class="field"><td nowrap><font size="+1"><b>Forum Administrator:</b></td><td>'.($u->users_opt & 1048576 ? '<b><font size="+2" color="red">Y</font>' : 'N').' [<a href="admuser.php?act=admin&usr_id='.$usr_id . '&' . __adm_rsidl.'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Email Confirmation:</td><td>'.($u->users_opt & 131072 ? 'Yes' : 'No').' [<a href="admuser.php?act=econf&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Confirmed Account:</td><td>'.($u->users_opt & 2097152 ? 'No' : 'Yes').' [<a href="admuser.php?act=conf&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Can use signature:</td><td>'.($u->users_opt & 67108864 ? 'No' : 'Yes').' [<a href="admuser.php?act=sig&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Can use private messaging:</td><td>'.($u->users_opt & 33554432 ? 'No' : 'Yes').' [<a href="admuser.php?act=pm&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Account Moderator:</td><td>'.($u->users_opt & 268435456 ? 'Yes' : 'No').' [<a href="admuser.php?act=accmod&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';

	if ($FUD_OPT_1 & 1048576) {
		echo '<tr class="field"><td>COPPA:</td><td>'.($u->users_opt & 262144 ? 'Yes' : 'No').' [<a href="admuser.php?act=coppa&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
	}

	echo '<tr class="field"><td nowrap valign="top">Moderating Forums:</td><td valign="top">';
	$c = uq('SELECT f.name FROM '.$DBHOST_TBL_PREFIX.'mod mm INNER JOIN '.$DBHOST_TBL_PREFIX.'forum f ON mm.forum_id=f.id WHERE mm.user_id='.$usr_id);
	if ($r = db_rowarr($c)) {
		echo '<table border=0 cellspacing=1 cellpadding=3>';
		do {
			echo '<tr><td>'.$r[0].'</td></tr>';
		} while ($r = db_rowarr($c));
		echo '</table>';
	} else {
		echo 'None<br>';
	}
	unset($c);
?>
	<a name="mod_here"> </a>
	<a href="#mod_here" onClick="javascript: window.open('admmodfrm.php?usr_id=<?php echo $usr_id . '&' . __adm_rsidl; ?>', 'frm_mod', 'menubar=false,width=200,height=400,screenX=100,screenY=100,scrollbars=yes');">Modify Moderation Permissions</a>


	<tr class="field"><td valign=top>Custom Tags:</td><td valign="top">
<?php
	$c = uq('SELECT name, id FROM '.$DBHOST_TBL_PREFIX.'custom_tags WHERE user_id='.$usr_id);
	while ($r = db_rowarr($c)) {
		echo $r[0] . ' [<a href="admuser.php?act=nada&usr_id='.$usr_id.'&deltag=' . $r[1] . '&' . __adm_rsidl . '">Delete</a>]<br>';
	}
	unset($c);
?>
	<form name="extra_tags" action="admuser.php" method="post">
	<?php echo _hs; ?>
	<input type="text" name="c_tag">
	<input type="submit" value="Add">
	<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
	<input type="hidden" name="act" value="nada">
	</form>
	</td></tr>

	<tr class="field"><td valign=top>Profile Link Color:</td>
		<td valign=top>
		<form name="extra_tags" method="post" action="admuser.php">
		<?php echo _hs; ?>
		<input type="text" name="custom_color" maxLength="255" value="<?php echo $u->custom_color; ?>">
		<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
		<input type="hidden" name="act" value="color"><input type="submit" value="Change">
		</form>
		</td>
	</tr>

<form name="ban" action="admuser.php" method="post">
<?php echo _hs; ?>
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
<input type="hidden" name="act" value="block">
	<tr class="field" align="center"><td colspan="2"><b>Ban User</b><br />
	<font size="-1">To set a temporary ban specify the duration of the ban in number of days, 
	for permanent ban leave duration value at 0. The value of the duration field for non-permanent bans will show
	days remaining till ban expiry.</font></td></tr>
	<tr class="field"><td>Is Banned:</td><td><input type="checkbox" name="block" value="65536" <?php echo ($u->users_opt & 65536 ? ' checked' : ''); ?>> Yes</td></tr>
	<tr class="field"><td colsan="2">Ban Duration (in days)</td><td><input type="text" value="<?php 
	if ($u->ban_expiry) {
		printf("%.2f", ($u->ban_expiry - __request_timestamp__) / 86400);
	} else {
		echo 0;	
	}
	?>" name="ban_duration">
	<input type="submit" name="ban_user" value="Ban/Unban"></td></tr>
</form>

	<tr class="field">
		<td colspan=2><br><br><b>Actions:</b></td>
	</tr>

	<tr class="field">
	<td colspan=2>
<?php
	if ($FUD_OPT_1 & 1024) {
		echo '<a href="../'.__fud_index_name__.'?t=ppost&'.__adm_rsidl.'&toi='.$usr_id.'">Send Private Message</a> | ';
	}
	if ($FUD_OPT_1 & 4194304) {
		echo '<a href="../'.__fud_index_name__.'?t=email&toi='.$usr_id.'&'.__adm_rsidl.'">Send Email</a> | ';
	} else {
		echo '<a href="mailto:'.$u->email.'">Send Email</a> | ';
	}

	echo '	<a href="../'.__fud_index_name__.'?t=showposts&id='.$usr_id.'&'.__adm_rsidl.'">See Posts</a> | <a href="admuser.php?act=reset&usr_id='.$usr_id.'&'.__adm_rsidl.'">Reset Password</a> | <a href="admuser.php?act=del&usr_id='.$usr_id.'&'.__adm_rsidl.'">Delete User</a>';
	if ($is_a) {	
		echo ' | <a href="admprune.php?usr_id='.$usr_id.'&'.__adm_rsidl.'">Delete All messages by this user.</a></td></tr>';
	}
}
?>
</table>
<?php require($WWW_ROOT_DISK . 'adm/admclose.html'); ?>
