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
	fud_use('customtags.inc', true);
	fud_use('users_reg.inc');
	fud_use('users_adm.inc', true);
	fud_use('logaction.inc');
	fud_use('iemail.inc');
	fud_use('private.inc');

	require($WWW_ROOT_DISK . 'adm/header.php');

	$acc_mod_only = !($GLOBALS['usr']->users_opt & 1048576) && $GLOBALS['usr']->users_opt & 268435456;

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

	if ($usr_id && $acc_mod_only && $u->users_opt & (268435456|1048576) && !($usr_id == $u->id)) {
		echo '<h3>Account moderators are not allowed to modify administrator accounts or accounts of other account moderators.</h3>';
		$u = $usr_id = null;
	}

	/* Check if ban had expired. */
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
			/* Only admins can do this. */
			if ($act == 'accmod' && $acc_mod_only) {
				break;
			}

			if ($act == 'block' && isset($_POST['ban_duration'])) {
				if ($GLOBALS['usr']->id == $usr_id) {
					echo errorify('Sorry, you cannot ban or unban yourself!');
					break;
				}
				
				/* For post requests involving ban, do not act as a toggle. */
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

			echo successify('User options successfully updated.');
			if (isset($_GET['f'])) {
				echo '<p>[ <a href="'. $WWW_ROOT.__fud_index_name__.$usr->returnto.'">return</a> ]</p>';
				exit;
			}
			break;
		case 'color':
			$u->custom_color = trim($_POST['custom_color']);
			q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET custom_color='.ssn($u->custom_color).' WHERE id='.$usr_id);
			echo successify('Custom color was successfully updated.');
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
			echo successify('Password was successfully reset and e-mailed to the user.');
			break;
		case 'del':
			if ($usr_id == 1) {	// Prevent deletion of "Anonymous".
				break;
			}

			if (!isset($_POST['del_confirm'])) {
?>
<form method="post" action="admuser.php"><?php echo _hs; ?>
<input type="hidden" name="act" value="del" />
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
<input type="hidden" name="del_confirm" value="1" />
<div align="center">You are about to delete <font color="red"><b><?php echo $u->alias; ?></b></font>'s account!<br /><br />
Are you sure you want to do this, once deleted the account cannot be recovered?<br />
<input type="submit" value="Yes" name="btn_yes" /> <input type="submit" value="No" name="btn_no" />
</div>
<?php
	if (isset($_GET['f'])) {
		echo '<input type="hidden" name="f" value="1" />';
	}
?>
</form>
<?php
					exit;
			} else if (isset($_POST['btn_yes'])) {
				if ($GLOBALS['usr']->id == $usr_id) {
					echo errorify('Sorry, you cannot delete your own account!');
					break;
				}
				logaction(_uid, 'DELETE_USER', 0, $u->alias);
				usr_delete($usr_id);
				echo successify('User <b>'.$u->alias.'</b> was successfully removed.');
				unset($act, $u);
				$usr_id = '';
				if (isset($_POST['f']) || isset($_GET['f'])) {
					echo '<p>[ <a href="'. $WWW_ROOT.__fud_index_name__.'?t=finduser">return</a> ]</p>';
					exit;
				}
			} else if (isset($_POST['btn_no'])) {
				if (isset($_POST['f']) || isset($_GET['f'])) {
					echo '<p>[ <a href="'. $WWW_ROOT.__fud_index_name__.$usr->returnto.'">return</a> ]</p>';
					exit;
				}
			}
			break;
		case 'admin':
			if ($u->users_opt & 1048576) {
				if (!isset($_POST['adm_confirm'])) {
?>
<form method="post" action="admuser.php"><?php echo _hs; ?>
<input type="hidden" name="act" value="admin" />
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
<input type="hidden" name="adm_confirm" value="1" />
<div align="center">You are taking away administration privileges from <font color="red"><b><?php echo $u->alias; ?></b></font>!<br /><br />
Are you sure you want to do this?<br />
<input type="submit" value="Yes" name="btn_yes" /> <input type="submit" value="No" name="btn_no" />
</div>
</form>
<?php
					exit;
				} else if (isset($_POST['btn_yes'])) {
					if ($GLOBALS['usr']->id == $usr_id) {
						echo errorify('Sorry, you cannot abdicate from being and administrator!');
						break;
					}
					if (q_singleval('SELECT count(*) FROM '.$DBHOST_TBL_PREFIX.'mod WHERE user_id='.$u->id)) {
						q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET users_opt=(users_opt & ~ 1048576) |524288 WHERE id='.$usr_id);
						$u->users_opt ^= 1048576;
					} else {
						q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET users_opt=users_opt & ~ (524288|1048576) WHERE id='.$usr_id);
						$u->users_opt = $u->users_opt &~ (1048576|524288);
					}
					echo successify('User <b>'.$u->alias.'</b> was demoted from being an administrator.');
				}
			} else {
				if (!isset($_POST['adm_confirm'])) {
?>
<form method="post" action="admuser.php"><?php echo _hs; ?>
<input type="hidden" name="act" value="admin" />
<input type="hidden" name="adm_confirm" value="1" />
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
<div align="center">WARNING: Making <font color="red"><b><?php echo $u->alias; ?></b></font> an <font color="red"><b>administrator</b></font> will give this person full
administration permissions to the forum. This individual will be able to do anything with the forum, including taking away your own administration permissions.
<br /><br />Are you sure you want to do this?<br />
<input type="submit" value="Yes" name="btn_yes" /> <input type="submit" value="No" name="btn_no" />
</div>
</form>
<?php
					exit;
				} else if (isset($_POST['btn_yes'])) {
					q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET users_opt=(users_opt & ~ 524288) | 1048576 WHERE id='.$usr_id);
					$u->users_opt |= 1048576;
					echo successify('User <b>'.$u->alias.'</b> was promoted to administrator.');
				}
			}
			break;
	}

	$search_error = $login_error = '';
	if ($usr_id) {
		/* Deal with custom tags. */
		if (!empty($_POST['c_tag'])) {
			q('INSERT INTO '.$DBHOST_TBL_PREFIX.'custom_tags (name, user_id) VALUES('.ssn($_POST['c_tag']).', '.$usr_id.')');
			echo successify('Custom tag was added.');
		} else if (!empty($_GET['deltag'])) {
			q('DELETE FROM '.$DBHOST_TBL_PREFIX.'custom_tags WHERE id='.(int)$_GET['deltag']);
			echo successify('Custom tag was removed.');
		} else {
			$nada = 1;
		}
		if (!isset($nada) && db_affected()) {
			ctag_rebuild_cache($usr_id);
		}

		/* Changing password. */
		if (!empty($_POST['login_passwd'])) {
			q("UPDATE ".$DBHOST_TBL_PREFIX."users SET passwd='".md5($_POST['login_passwd'])."' WHERE id=".$usr_id);
			logaction(_uid, 'ADM_SET_PASSWD', 0, char_fix(htmlspecialchars($u->login)));
			echo successify('User <b>'.$u->alias.'</b>\'s password was successfully changed.');
		} else if (!empty($_POST['login_name']) && $u->login != $_POST['login_name']) { /* Chanding login name. */
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
				echo successify('User <b>'.$u->alias.'</b>\'s login was successfully changed.');
			}
		}
	}
?>
<h2>User Administration System</h2>
<?php if (!$usr_id) echo '<p>Use an asterisk (*) to match multiple user accounts.</p>'; ?>
<form id="frm_usr" method="post" action="admuser.php">
<fieldset class="tutor">
<legend><b>Search for user:</b></legend>
<?php echo _hs . $search_error; ?>
<table width="100%">
<tr><td>
	<table class="datatable solidtable">
	<tr class="field">
		<td>By <?php echo ($FUD_OPT_2 & 128 ? 'Alias' : 'Login'); ?>:</td>
		<td><input tabindex="1" type="text" name="usr_login" /></td>
	</tr>

	<tr class="field">
		<td>By E-mail:</td>
		<td><input tabindex="2" type="text" name="usr_email" /></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right"><input tabindex="3" type="submit" value="Search" name="usr_search" /></td>
	</tr>
	</table>
</td><td valign="bottom">
<center>[ <a href="admadduser.php?<?php echo __adm_rsid; ?>">Create new users</a> ]<br /><br />
[ <a href="admusermerge.php?<?php echo __adm_rsid; ?>">Merge users</a> ]</center>
</td></tr>
</table>
</fieldset>
</form>
<script type="text/javascript">
/* <![CDATA[ */
document.forms['frm_usr'].usr_login.focus();
/* ]]> */
</script>

<?php
	/* User searching logic. */
	if (!empty($_POST['usr_email']) || !empty($_POST['usr_login'])) {
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
				/* Check if ban had expired. */
				if ($u->users_opt & 65536 && $u->ban_expiry && $u->ban_expiry < __request_timestamp__) {
					q('UPDATE '.$DBHOST_TBL_PREFIX.'users SET ban_expiry=0, users_opt=users_opt &~ 65536 WHERE id='.$usr_id);
				}
				break;
			default:
				echo '<p>There are '.$cnt.' users that match this '.$field.' mask:</p>';
				echo '<table class="resulttable fulltable">';
				echo '<tr class="resulttopic"><th>User Login</th><th>E-mail</th><th>Action</th></tr>';
				$i = 0;
				while ($r = db_rowarr($c)) {
					$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
					echo '<tr '. $bgcolor .'><td>'.$r[1].'</td><td>'.htmlspecialchars($r[2]).'</td><td>[ <a href="admuser.php?usr_id='.$r[0].'&amp;act=m&amp;'.__adm_rsid.'">Pick user</a> ]</td></tr>';
				}
				echo '</table>';
				unset($c);
				exit;
		}
	}

	/* Print user's details. */
	if ($usr_id) { ?>
<form action="admuser.php" method="post"><?php echo _hs; ?>
<h3>Admin Controls for: <i><?php echo char_fix(htmlspecialchars($u->login)); ?></i></h3>
<table class="datatable solidtable">

	<tr class="field"><td>Login:</td><td><?php echo $login_error; ?><input type="text" value="<?php echo char_fix(htmlspecialchars($u->login)); ?>" maxlength="<?php echo $MAX_LOGIN_SHOW; ?>" name="login_name" /> <input type="submit" name="submit" value="Change Login Name" /></td></tr>
	<tr class="field"><td>Password:</td><td><input type="text" value="" name="login_passwd" /> <input type="submit" name="submit" value="Change Password" /></td></tr>
</table>
	<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
	<input type="hidden" name="act" value="nada" />

</form>
<table class="datatable solidtable">
<?php
	if($FUD_OPT_2 & 128) {
		echo '<tr class="field"><td>Alias:</td><td>'. $u->alias .'</td></tr>';
	}
?>
	<tr class="field"><td>E-mail:</td><td><?php echo $u->email; ?></td></tr>
	<tr class="field"><td>Name:</td><td><?php echo $u->name; ?></td></tr>
<?php
	if ($u->home_page) {
		echo '<tr class="field"><td>Home page:</td><td><a href="'. $u->home_page .'" title="Visit user\'s homepage">'. $u->home_page . '</a></td></tr>';
	}
	if ($u->bio) {
		echo '<tr class="field"><td>Bio:</td><td>'. $u->bio .'</td></tr>';
	}
	if ($u->sig) {
		echo '<tr class="field"><td>Signature:</td><td>'. $u->sig .'</td></tr>';
	}
	if ($u->reg_ip) {
		echo '<tr class="field"><td>Registration:</td><td>'. strftime('%d %B %Y', $u->join_date) .' from <a href="../'. __fud_index_name__ .'?t=ip&amp;ip='. long2ip($u->reg_ip) .'&amp;'. __adm_rsid .'" title="Analyse IP usage">'. long2ip($u->reg_ip) .'</td></tr>';
	}
	if ($u->last_known_ip) {
		echo '<tr class="field"><td>Last visit:</td><td>'. strftime('%d %B %Y', $u->last_visit) .' from <a href="../'. __fud_index_name__ .'?t=ip&amp;ip='. long2ip($u->last_known_ip) .'&amp;'. __adm_rsid. '" title="Analyse IP usage">'. long2ip($u->last_known_ip) .'</a></td></tr>';
	}
	if ($u->posted_msg_count) {
		echo '<tr class="field"><td>Post count:</td><td>'. $u->posted_msg_count .' [ <a href="../'.__fud_index_name__.'?t=showposts&amp;id='.$usr_id.'&amp;'.__adm_rsid.'" title="View user\'s messages on the forum">See Messages</a> ]</td></tr>';
	}

	echo '<tr class="field"><td>E-mail Confirmation:</td><td>'.($u->users_opt & 131072 ? 'Yes' : '<font size="+1" color="red">No</font>').' [<a href="admuser.php?act=econf&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Confirmed Account:</td><td>'.($u->users_opt & 2097152 ? '<font size="+1" color="red">No</font>' : 'Yes').' [<a href="admuser.php?act=conf&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Can use signature:</td><td>'.($u->users_opt & 67108864 ? 'No' : 'Yes').' [<a href="admuser.php?act=sig&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Can use private messaging:</td><td>'.($u->users_opt & 33554432 ? 'No' : 'Yes').' [<a href="admuser.php?act=pm&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
	if ($FUD_OPT_1 & 1048576) {
		echo '<tr class="field"><td>COPPA:</td><td>'.($u->users_opt & 262144 ? 'Yes' : 'No').' [<a href="admuser.php?act=coppa&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
	}
if (!$acc_mod_only) {
	echo '<tr class="field"><td nowrap="nowrap">Forum Administrator:</td><td>'.($u->users_opt & 1048576 ? '<b><font size="+1" color="red">Yes</font></b>' : 'No').' [<a href="admuser.php?act=admin&usr_id='.$usr_id . '&' . __adm_rsidl.'">Toggle</a>]</td></tr>';
} else {
	echo '<tr class="field"><td nowrap="nowrap">Forum Administrator:</td><td>'.($u->users_opt & 1048576 ? '<b><font size="+1" color="red">Yes</font></b>' : 'No').'</td></tr>';
}	
if ($acc_mod_only) {
	echo '<tr class="field"><td>Account Moderator:</td><td>'.($u->users_opt & 268435456 ? '<b><font size="+1" color="red">Yes</font></b>' : 'No').'</td></tr>';
} else {
	echo '<tr class="field"><td>Account Moderator:</td><td>'.($u->users_opt & 268435456 ? '<b><font size="+1" color="red">Yes</font></b>' : 'No').' [<a href="admuser.php?act=accmod&usr_id=' . $usr_id . '&' . __adm_rsidl .'">Toggle</a>]</td></tr>';
}

	echo '<tr class="field"><td nowrap="nowrap" valign="top">Moderating Forums:</td><td valign="top">';
	$c = uq('SELECT f.name FROM '.$DBHOST_TBL_PREFIX.'mod mm INNER JOIN '.$DBHOST_TBL_PREFIX.'forum f ON mm.forum_id=f.id WHERE mm.user_id='.$usr_id);
	if ($r = db_rowarr($c)) {
		echo '<table border="0" cellspacing="1" cellpadding="3">';
		do {
			echo '<tr><td>'.$r[0].'</td></tr>';
		} while ($r = db_rowarr($c));
		echo '</table>';
	} else {
		echo 'None<br />';
	}
	unset($c);
?>
	<a name="mod_here"> </a>
	[ <a href="#mod_here" onclick="window.open('admmodfrm.php?usr_id=<?php echo $usr_id . '&amp;' . __adm_rsid; ?>', 'frm_mod', 'menubar=false,width=200,height=400,screenX=100,screenY=100,scrollbars=yes');">Modify Moderation Permissions</a> ]


	<tr class="field"><td valign="top">Custom Tags:</td><td valign="top">
<?php
	$c = uq('SELECT name, id FROM '.$DBHOST_TBL_PREFIX.'custom_tags WHERE user_id='.$usr_id);
	while ($r = db_rowarr($c)) {
		echo $r[0] . ' [<a href="admuser.php?act=nada&amp;usr_id='.$usr_id.'&amp;deltag=' . $r[1] . '&amp;' . __adm_rsid . '">Delete</a>]<br />';
	}
	unset($c);
?>
	<form id="extra_tags" action="admuser.php" method="post">
	<?php echo _hs; ?>
	<input type="text" name="c_tag" />
	<input type="submit" value="Add" />
	<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
	<input type="hidden" name="act" value="nada" />
	</form>
	</td></tr>

	<tr class="field"><td valign="top">Profile Link Color:</td>
		<td valign="top">
		<form id="extra_tags" method="post" action="admuser.php">
		<?php echo _hs; ?>
		<input type="text" name="custom_color" maxlength="255" value="<?php echo $u->custom_color; ?>" />
		<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>">
		<input type="hidden" name="act" value="color"><input type="submit" value="Change" />
		</form>
		</td>
	</tr>
<tr><td colspan="2">
<form id="ban" action="admuser.php" method="post">
<?php echo _hs; ?>
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
<input type="hidden" name="act" value="block" />
<br />

	<table cellspacing="0" border="0" cellpadding="0">
	<tr class="field" align="center"><td colspan="2"><b>Ban User</b><br />
	<div style="font-size: small;">To set a temporary ban, specify the duration of the ban in number of days. 
	For permanent bans, leave duration value at 0.
	The value of the duration field for non-permanent bans will show days remaining till ban expiry.</div></td></tr>
	<tr class="field"><td>Is Banned:</td><td><label><input type="checkbox" name="block" value="65536" <?php echo ($u->users_opt & 65536 ? ' checked /> <b><font color="red">Yes</font></b>' : ' /> No'); ?> </label></td></tr>
	<tr class="field"><td>Ban Duration (days left)</td><td><input type="text" value="<?php 
	if ($u->ban_expiry) {
		printf("%.2f", ($u->ban_expiry - __request_timestamp__) / 86400);
	} else {
		echo 0;
	}
	?>" name="ban_duration" />
	<input type="submit" name="ban_user" value="Ban/Unban" /></td></tr>
	<tr class="field">
		<td valign="top"><b>Group Membership:</b></td>
		<td><?php

	$c = uq('SELECT g.name FROM '.$DBHOST_TBL_PREFIX.'group_members m INNER JOIN '.$DBHOST_TBL_PREFIX.'groups g ON g.id=m.group_id WHERE m.user_id='.$usr_id);
	while ($r = db_rowarr($c)) {
		echo $r[0] .'<br />';
	}
	unset($c);
	
		?></td>
	</tr>

	<tr class="field">
		<td colspan="2"><br /><br /><b>Actions:</b></td>
	</tr>

	<tr class="field">
	<td colspan="2">
<?php
	echo '<a href="../'.__fud_index_name__.'?t=register&mod_id='.$usr_id.'&'.__adm_rsidl.'">Edit Profile</a> | ';
	if ($FUD_OPT_1 & 1024) {	// PM_ENABLED
		echo '<a href="../'.__fud_index_name__.'?t=ppost&amp;'.__adm_rsid.'&amp;toi='.$usr_id.'">Send PM</a> | ';
	}
	if ($FUD_OPT_2 & 1073741824) {	// ALLOW_EMAIL
		echo '<a href="../'.__fud_index_name__.'?t=email&amp;toi='.$usr_id.'&amp;'.__adm_rsid.'">Send E-mail</a> | ';
	} else {
		echo '<a href="mailto:'.$u->email.'">Send E-mail</a> | ';
	}

	echo '  <a href="admuser.php?act=reset&amp;usr_id='.$usr_id.'&amp;'.__adm_rsid.'">Reset Password</a> |';
	echo '  <a href="admuser.php?act=del&amp;usr_id='.$usr_id.'&amp;'.__adm_rsid.'">Delete User</a> |';
	echo '	<a href="../'.__fud_index_name__.'?t=showposts&amp;id='.$usr_id.'&amp;'.__adm_rsid.'">See Messages</a>';	
	if ($is_a) {
		if ($FUD_OPT_1 & 1024) {	// PM_ENABLED
			echo ' | <a href="admpmspy.php?user='.htmlspecialchars($u->login).'&amp;'.__adm_rsid.'">See Private Messages</a>';
		}
		echo ' | <a href="admprune.php?usr_id='.$usr_id.'&amp;'.__adm_rsid.'">Delete ALL messages by this user</a></td></tr>';
	}
?>
</td></tr></table>
<?php } ?> 
</form>
<?php require($WWW_ROOT_DISK . 'adm/footer.php'); ?>
