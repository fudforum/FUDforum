<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
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
	fud_use('draw_pager.inc');

	require($WWW_ROOT_DISK .'adm/header.php');

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
	if ($act && $usr_id && !($u = db_sab('SELECT * FROM '. $DBHOST_TBL_PREFIX .'users WHERE id='. $usr_id))) {
		$usr_id = $act = '';
	}

	if ($usr_id && $acc_mod_only && $u->users_opt & (268435456|1048576) && !($usr_id == $u->id)) {
		echo errorify('Account moderators are not allowed to modify administrator accounts or accounts of other account moderators.');
		$u = $usr_id = null;
	}

	/* Check if ban had expired. */
	if ($usr_id && !$act && $u->users_opt & 65536 && $u->ban_expiry && $u->ban_expiry < __request_timestamp__) {
		q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET ban_expiry=0, users_opt='. q_bitand('users_opt', ~65536) .' WHERE id='. $usr_id);
	}

	/* Sanitize user alias to prevent possible xss. */
	if (isset($u->alias)) {
        	$u->alias = htmlspecialchars($u->alias);
	}

	$keys = array('block'=>65536, 'coppa'=>262144, 'econf'=>131072, 'sig'=>67108864, 'pm'=>33554432, 'conf'=>2097152, 'accmod'=>268435456, 'modposts'=>536870912);

	switch ($act) {
		case 'block':
		case 'coppa':
		case 'econf':
		case 'conf':
		case 'sig':
		case 'pm':
		case 'accmod':
		case 'modposts':
			/* Only admins can do this. */
			if ($act == 'accmod' && $acc_mod_only) {
				break;
			}

			if ($act == 'block') {	// Ban/ UnBan user.
				if ($GLOBALS['usr']->id == $usr_id) {
					echo errorify('Sorry, you cannot ban or unban yourself!');
					break;
				}

				$is_banned = $u->users_opt & $keys[$act];
				if (array_key_exists('block', $_POST)) {
					$should_be_banned = $_POST['block'];
				} else {
					// Not set, toggle it.
					$should_be_banned = ($is_banned) ? 0 : 1;
				}
				if ($should_be_banned) {	// Is currently banned.
					$duration = (isset($_POST['ban_duration'])) ? $_POST['ban_duration'] : 0;
					$u->ban_expiry = floatval($duration) * 86400;
					if ($u->ban_expiry) {
						$u->ban_expiry += __request_timestamp__;
					}
					$u->users_opt |= $keys[$act];
					logaction(_uid, 'User banned', 0, $u->login);
				} else {
					$u->ban_expiry = 0;
					if ($is_banned) $u->users_opt ^= $keys[$act];
					logaction(_uid, 'User unbanned', 0, $u->login);
				}
				$u->ban_reason = (isset($_POST['ban_reason'])) ? $_POST['ban_reason'] : '';
				q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET ban_reason='. ssn($u->ban_reason) .', ban_expiry='. $u->ban_expiry .', users_opt='. $u->users_opt .' WHERE id='. $usr_id);
				$block = $u->ban_expiry;
			} else {

				// Toggele rest of the settings.
				if ($u->users_opt & $keys[$act]) {
					q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET users_opt='. q_bitand('users_opt', q_bitnot($keys[$act])) .' WHERE id='. $usr_id);
					$u->users_opt ^= $keys[$act];
				} else {
					q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET users_opt='. q_bitor('users_opt', $keys[$act]) .' WHERE id='. $usr_id);
					$u->users_opt |= $keys[$act];
				}
			}

			echo successify('User options successfully updated.');
			if (isset($_GET['f'])) {
				echo '<p>[ <a href="'. $WWW_ROOT. __fud_index_name__ . $usr->returnto .'">return</a> ]</p>';
				exit;
			}
			break;
		case 'color':
			$u->custom_color = trim($_POST['custom_color']);
			q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET custom_color='. ssn($u->custom_color) .' WHERE id='. $usr_id);
			echo successify('Custom color was successfully updated.');
			break;
		case 'reset':
			$user_theme_name = q_singleval('SELECT name FROM '. $DBHOST_TBL_PREFIX .'themes WHERE '. (!$u->theme ? 'theme_opt>=2 AND '. q_bitand('theme_opt', 2) .' > 0' : 'id='. $u->theme));
			if ($FUD_OPT_2 & 1 && !($u->users_opt & 131072)) {	// EMAIL_CONFIRMATION enabled, but user's e-mail is not yet confirmed.
				$conf_key = usr_email_unconfirm($u->id);
				$url = $WWW_ROOT . __fud_index_name__ .'?t=emailconf&conf_key='. $conf_key;
				include_once($INCLUDE .'theme/'. $user_theme_name .'/rst.inc');	// Message variables.
				send_email($NOTIFY_FROM, $u->email, $register_conf_subject, $reset_confirmation, '');
				logaction(_uid, 'SEND_ECONF', 0, char_fix(htmlspecialchars($u->login)));
				echo successify('Registration confirmation was e-mailed to the user.');
			} else {
				q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET reset_key=\''. ($reset_key = md5(get_random_value(128))) .'\' WHERE id='. $u->id);

				$url = $WWW_ROOT . __fud_index_name__ .'?t=reset&reset_key='. $reset_key;
				include_once($INCLUDE .'theme/'. $user_theme_name .'/rst.inc');	// Message variables.
				send_email($NOTIFY_FROM, $u->email, $reset_newpass_title, $reset_reset, '');
				logaction(_uid, 'ADM_RESET_PASSWD', 0, char_fix(htmlspecialchars($u->login)));
				echo successify('A password reset key was generated and mailed to the user.');

			}
			break;
		case 'del':
			if ($usr_id == 1) {
				echo errorify('Sorry, the anonymous user cannot be deleted!');
				break;
			}
			if ($GLOBALS['usr']->id == $usr_id) {
				echo errorify('Sorry, you cannot delete your own account!');
				break;
			} else if ($u->users_opt & 1073741824) {
				echo errorify('This is a supporting user for a web crawler. Please remove the crawler instead!');
				break;
			}

			if (!isset($_POST['del_confirm'])) {
?>
<form method="post" action="admuser.php"><?php echo _hs; ?>
<input type="hidden" name="act" value="del" />
<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
<input type="hidden" name="del_confirm" value="1" />
<div align="center"><p>You are about to delete <font color="red"><b><?php echo $u->alias; ?></b></font>'s account!</p>
<?php
	if ($u->posted_msg_count > 1) {
		echo '<p>The user\'s '. $u->posted_msg_count .' message(s) will be assigned to the anonymous user.</p>';
	}
?>
<p>Are you sure you want to do this, once deleted the account cannot be recovered?<br />
<input type="submit" value="Yes" name="btn_yes" /> <input type="submit" value="No" name="btn_no" /></p>
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
				logaction(_uid, 'DELETE_USER', 0, $u->alias);
				usr_delete($usr_id);
				echo successify('User <b>'. $u->alias .'</b> was successfully removed.');
				unset($act, $u);
				$usr_id = '';
				if (isset($_POST['f']) || isset($_GET['f'])) {
					echo '<p>[ <a href="'. $WWW_ROOT . __fud_index_name__ .'?t=finduser">return</a> ]</p>';
					exit;
				}
			} else if (isset($_POST['btn_no'])) {
				if (isset($_POST['f']) || isset($_GET['f'])) {
					echo '<p>[ <a href="'. $WWW_ROOT. __fud_index_name__ . $usr->returnto .'">return</a> ]</p>';
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
						echo errorify('Sorry, you cannot abdicate from being an administrator. Ask another administrator to remove your account.');
						break;
					}
					if (q_singleval('SELECT count(*) FROM '. $DBHOST_TBL_PREFIX .'mod WHERE user_id='. $u->id)) {
						q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET users_opt='. q_bitor( q_bitand('users_opt', q_bitnot(1048576)), 524288) .' WHERE id='. $usr_id);
						$u->users_opt ^= 1048576;
					} else {
						q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET users_opt='. q_bitand('users_opt', q_bitnot(524288|1048576)) .' WHERE id='. $usr_id);
						$u->users_opt = $u->users_opt & ~(1048576|524288);
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
					q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET users_opt='. q_bitor( q_bitand('users_opt', q_bitnot(524288)), 1048576) .' WHERE id='. $usr_id);
					$u->users_opt |= 1048576;
					echo successify('User <b>'. $u->alias .'</b> was promoted to administrator.');
				}
			}
			break;
	}

	$search_error = $login_error = '';
	if ($usr_id) {
		/* Deal with custom tags. */
		if (!empty($_POST['c_tag'])) {
			q('INSERT INTO '. $DBHOST_TBL_PREFIX .'custom_tags (name, user_id) VALUES('. ssn($_POST['c_tag'] ).', '. $usr_id .')');
			echo successify('Custom tag was added.');
		} else if (!empty($_GET['deltag'])) {
			q('DELETE FROM '. $DBHOST_TBL_PREFIX .'custom_tags WHERE id='. (int)$_GET['deltag']);
			echo successify('Custom tag was removed.');
		} else {
			$nada = 1;
		}
		if (!isset($nada) && db_affected()) {
			ctag_rebuild_cache($usr_id);
		}

		/* Changing e-mail. */
		if (!empty($_POST['login_email']) && $u->email != $_POST['login_email']) {
			q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET email='. _esc($_POST['login_email']) .' WHERE id='. $usr_id);
			$u->email = $_POST['login_email'];
			echo successify('User <b>'. $u->alias .'</b>\'s e-mail address was successfully changed.');
		}
		/* Changing password. */
		if (!empty($_POST['login_passwd'])) {
			$salt   = substr(md5(uniqid(mt_rand(), true)), 0, 9);
			$passwd = sha1($salt . sha1($_POST['login_passwd']));
			q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET passwd=\''. $passwd .'\', salt=\''. $salt .'\' WHERE id='. $usr_id);
			logaction(_uid, 'ADM_SET_PASSWD', 0, char_fix(htmlspecialchars($u->login)));
			echo successify('User <b>'. $u->alias .'</b>\'s password was successfully changed.');
		/* Chanding login name. */
		}
		if (!empty($_POST['login_name']) && $u->login != $_POST['login_name']) {
			$alias = _esc(make_alias($_POST['login_name']));
			$login = _esc($_POST['login_name']);

			if ($FUD_OPT_2 & 128) {
				if (db_li('UPDATE '. $DBHOST_TBL_PREFIX .'users SET login='. $login .' WHERE id='. $usr_id, $ef) === null) {
					$login_error = errorify('Someone is already using that login name.');
				}
			} else {
				if (db_li('UPDATE '. $DBHOST_TBL_PREFIX .'users SET login='. $login .', alias='. $alias .' WHERE id='. $usr_id, $ef) === null) {
					if ($ef == 2) {
						$login_error = errorify('Someone is already using that login name.');
					} else {
						$login_error = errorify('Someone is already using that alias.');
					}
				}
			}

			if (!$login_error) {
				logaction(_uid, 'CHANGE_USER', 0, char_fix(htmlspecialchars($u->login .'->'. $login)));
				echo successify('User <b>'. $u->login .'</b>\'s login was successfully changed to '. $login);
				rebuildmodlist();
				$u->login = $_POST['login_name'];
				if (!($FUD_OPT_2 & 128)) {
					$u->alias = make_alias($u->alias);
				}
			}
		}
	}
?>
<h2>User Administration System</h2>
<?php if (!$usr_id)	{
	echo '<p>Use an asterisk (*) to match multiple user accounts.</p>';
	if ( empty($_GET['usr_login']) && empty($_GET['usr_email']) ) {
		$_GET['usr_login'] = '*';	// Default search.
		$_GET['usr_email'] = '';
	} else {
		$_GET['usr_login'] = filter_var($_GET['usr_login'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);	// Sanitize input.
		$_GET['usr_email'] = filter_var($_GET['usr_email'], FILTER_SANITIZE_EMAIL);
	}
} else {
	// We are looking at a spesific record. Empty variables & collapse search box.
	$_GET['usr_login'] = '';
	$_GET['usr_email'] = '';
	echo '<script>jQuery(function() {
		jQuery("legend").siblings().toggle(); 
		jQuery("legend").parent().toggleClass("collapsed");
	      });</script>';
} ?>
<form id="frm_usr" method="GET" action="admuser.php">

<fieldset class="fieldtopic">
<legend><b>Search users</b></legend>
<?php echo _hs . $search_error; ?>
<table width="100%">
<tr><td>
	<table class="datatable solidtable">
	<tr class="field">
		<td>By <?php echo ($FUD_OPT_2 & 128 ? 'Alias' : 'Login'); ?>:</td>
		<td><input tabindex="1" type="search" id="usr_login" name="usr_login" value="<?php echo $_GET['usr_login']; ?>" /></td>
	</tr>

	<tr class="field">
		<td>By E-mail:</td>
		<td><input tabindex="2" type="email" id="usr_email" name="usr_email" value="<?php echo $_GET['usr_email']; ?>" /></td>
	</tr>

	<tr class="fieldaction">
		<td colspan="2" align="right"><input tabindex="3" type="submit" value="Search" name="usr_search" /></td>
	</tr>
	</table>

<style>
	.ui-autocomplete-loading { background: white url("../theme/default/images/ajax-loader.gif") right center no-repeat; }
</style>
<script>
	jQuery(function() {
		jQuery("#usr_login").autocomplete({
			source: "../index.php?t=autocomplete&lookup=alias", minLength: 1
		});
		jQuery("#usr_email").autocomplete({
			source: "../index.php?t=autocomplete&lookup=email", minLength: 1
		});
	});
</script>

<!-- Links to control panels that Account Moderators can access. -->
</td><td>
	<b>Moderation:</b><br />
	&nbsp;[ <a href="admuseradd.php?<?php   echo __adm_rsid; ?>">Create users</a> ]<br />
	&nbsp;[ <a href="admuserapr.php?<?php   echo __adm_rsid; ?>">Approve users</a> ]<br />
	&nbsp;[ <a href="admusermerge.php?<?php echo __adm_rsid; ?>">Merge users</a> ]<br />
	&nbsp;[ <a href="admuserprune.php?<?php echo __adm_rsid; ?>">Prune users</a> ]<br /><br />
</td><td>
	<b>Show:</b><br />
	&nbsp;[ <a href="admprivlist.php?<?php  echo __adm_rsid; ?>">Privileged</a> ]<br />
	&nbsp;[ <a href="admbanlist.php?<?php   echo __adm_rsid; ?>">Banned</a> ]<br />
	&nbsp;[ <a href="admsession.php?<?php   echo __adm_rsid; ?>">Sessions</a> ]<br /><br />
</td></tr>
</table>
</fieldset>
</form>

<?php
	/* User searching logic. */
	if (!empty($_GET['usr_email']) || !empty($_GET['usr_login'])) {
		$item  = !empty($_GET['usr_email']) ? $_GET['usr_email'] : $_GET['usr_login'];
		$field = !empty($_GET['usr_email']) ? 'email' : ($FUD_OPT_2 & 128 ? 'alias' : 'login');
		$start = !empty($_GET['start']) ? (int) $_GET['start'] : 0;
		if (strpos($item, '*') !== false) {
			$like   = 1;
			$item   = str_replace('*', '%', $item);
			$item_s = str_replace('\\', '\\\\', $item);
		} else {
			$like   = 0;
			$item_s = $item;
		}
		if ($FUD_OPT_2 & 128) {
			$item_s = char_fix(htmlspecialchars($item_s));
		}
		$item_s = _esc($item_s);

		if (($cnt = q_singleval('SELECT count(*) FROM '. $DBHOST_TBL_PREFIX .'users WHERE '. $field . ($like ? ' LIKE ' : '=') . $item_s))) {
			$c = uq(q_limit('SELECT id, alias, email, last_visit, last_used_ip, posted_msg_count FROM '. $DBHOST_TBL_PREFIX .'users WHERE '. $field . ($like ? ' LIKE ' : '=') . $item_s . ' ORDER BY last_login DESC', 40, $start));
		}
		switch ($cnt) {
			case 0:
				echo '<p>There are no users matching the specified '. $field .' mask.</p>';
				unset($c);
				break;
			case 1:
				list($usr_id) = db_rowarr($c);
				unset($c);
				$u = db_sab('SELECT * FROM '. $DBHOST_TBL_PREFIX .'users WHERE id='. $usr_id);
				/* Check if ban had expired. */
				if ($u->users_opt & 65536 && $u->ban_expiry && $u->ban_expiry < __request_timestamp__) {
					q('UPDATE '. $DBHOST_TBL_PREFIX .'users SET ban_expiry=0, users_opt='. q_bitand('users_opt', q_bitnot(65536)) .' WHERE id='. $usr_id);
				}
				break;
			default:
				echo '<p>There are '. $cnt .' users that match this '. $field .' mask:</p>';
				echo '<table class="resulttable fulltable">';
				echo '<thead><tr class="resulttopic">';
				echo '	<th>Login</th><th>E-mail</th><th>Last visit</th><th>Last IP</th><th>Posts</th><th>Action</th>';
				echo '</tr></thead>';
				$i = 0;
				while ($r = db_rowarr($c)) {
					$bgcolor = ($i++%2) ? ' class="resultrow2"' : ' class="resultrow1"';
					echo '<tr'. $bgcolor .'>';
					echo '<td><a href="admuser.php?usr_id='. $r[0] .'&amp;act=m&amp;'. __adm_rsid .'">'. htmlspecialchars($r[1]) .'</a></td>';
					echo '<td>'. htmlspecialchars($r[2]) .'</td>';
					echo '<td>'. fdate($r[3], 'd M Y H:i:s') .'</td>';
					echo '<td>'. htmlspecialchars($r[4]) .'</td><td>'. $r[5] .'</td>';
					echo '<td><a href="admuser.php?usr_id='. $r[0] .'&amp;act=m&amp;'. __adm_rsid .'">Edit</a> | <a href="admuser.php?act=del&amp;usr_id='. $r[0] .'&amp;'. __adm_rsid .'">Delete</a></td>';
					echo '</tr>';
				}
				echo '</table>';
				unset($c);
				echo tmpl_create_pager($start, 40, $cnt, 'admuser.php?usr_login=*&amp;usr_email='. $_GET['usr_email'] .'&amp;usr_search='. $_GET['usr_login'], '&amp;'. __adm_rsid);
				require($WWW_ROOT_DISK .'adm/footer.php');
				exit;
		}
	}

	/* Print user's details. */
	if ($usr_id) { ?>

<h3>Admin Controls for: <i><?php echo char_fix(htmlspecialchars($u->login)); ?></i></h3>

<table class="datatable solidtable">
<form action="admuser.php" method="post"><?php echo _hs; ?>
	<tr class="field"><td>Login:</td><td><?php echo $login_error; ?><input type="text" value="<?php echo char_fix(htmlspecialchars($u->login)); ?>" maxlength="<?php echo $MAX_LOGIN_SHOW; ?>" name="login_name" /> <input type="submit" name="submit" value="Change Login Name" /></td></tr>

	<tr class="field"><td>E-mail:</td><td><input type="text" value="<?php echo char_fix(htmlspecialchars($u->email)); ?>" name="login_email" /> <input type="submit" name="submit" value="Change E-mail" /></td></tr>

	<tr class="field"><td>Password:</td><td><input type="text" value="" name="login_passwd" /> <input type="submit" name="submit" value="Change Password" /></td></tr>
	<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
	<input type="hidden" name="act" value="nada" />
</form>

<?php
	if($FUD_OPT_2 & 128) {
		echo '<tr class="field"><td>Alias:</td><td>'. $u->alias .'</td></tr>';
	}
	if ($u->name) {
		echo '<tr class="field"><td>Name:</td><td>'. $u->name .'</td></tr>';
	}
	if ($u->home_page) {
		echo '<tr class="field"><td>Home page:</td><td><a href="'. $u->home_page .'" title="Visit user\'s homepage">'. $u->home_page .'</a></td></tr>';
	}
	if ($u->bio) {
		echo '<tr class="field"><td>Bio:</td><td>'. $u->bio .'</td></tr>';
	}
	if ($u->sig) {
		echo '<tr class="field"><td>Signature:</td><td>'. $u->sig .'</td></tr>';
	}
	if ($u->registration_ip) {
		echo '<tr class="field"><td>Registration:</td><td>'. fdate($u->join_date, 'd M Y') .' from <a href="../'. __fud_index_name__ .'?t=ip&amp;ip='. $u->registration_ip .'&amp;'. __adm_rsid .'" title="Analyse IP usage">'. $u->registration_ip .'</a></td></tr>';
	}
	if ($u->last_used_ip) {
		echo '<tr class="field"><td>Last visit:</td><td>'. fdate($u->last_visit, 'd M Y') .' from <a href="../'. __fud_index_name__ .'?t=ip&amp;ip='. $u->last_used_ip .'&amp;'. __adm_rsid .'" title="Analyse IP usage">'. $u->last_used_ip .'</a></td></tr>';
	}
	if ($u->posted_msg_count) {
		echo '<tr class="field"><td>Post count:</td><td>'. $u->posted_msg_count .' [ <a href="../'.__fud_index_name__.'?t=showposts&amp;id='.$usr_id.'&amp;'.__adm_rsid.'" title="View user\'s messages on the forum">View Messages</a> ]</td></tr>';
	}

	echo '<tr class="field"><td>E-mail address confirmed:</td><td>'.($u->users_opt & 131072 ? 'Yes' : '<font size="+1" color="red">No</font>').' [<a href="admuser.php?act=econf&amp;usr_id='. $usr_id .'&amp;'. __adm_rsidl .'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Account approved:</td><td>'.($u->users_opt & 2097152 ? '<font size="+1" color="red">No</font>' : 'Yes').' [<a href="admuser.php?act=conf&amp;usr_id='. $usr_id .'&amp;'. __adm_rsidl .'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Can use signature:</td><td>'.($u->users_opt & 67108864 ? '<font size="+1" color="red">No</font>' : 'Yes').' [<a href="admuser.php?act=sig&amp;usr_id='. $usr_id .'&amp;'. __adm_rsidl .'">Toggle</a>]</td></tr>';
	echo '<tr class="field"><td>Can use private messaging:</td><td>'.($u->users_opt & 33554432 ? '<font size="+1" color="red">No</font>' : 'Yes').' [<a href="admuser.php?act=pm&amp;usr_id='. $usr_id .'&amp;'. __adm_rsidl .'">Toggle</a>]</td></tr>';
	if ($FUD_OPT_1 & 1048576) {
		echo '<tr class="field"><td>COPPA:</td><td>'. ($u->users_opt & 262144 ? 'Yes' : '<font size="+1" color="red">No</font>') .' [<a href="admuser.php?act=coppa&amp;usr_id='. $usr_id .'&amp;'. __adm_rsidl .'">Toggle</a>]</td></tr>';
	}
	echo '<tr class="field"><td>Queue user\'s posts for moderation:</td><td>'.($u->users_opt & 536870912 ? '<font size="+1" color="red">Yes</font>' : 'No').' [<a href="admuser.php?act=modposts&amp;usr_id='. $usr_id .'&amp;'. __adm_rsidl .'">Toggle</a>]</td></tr>';
if (!$acc_mod_only) {
	echo '<tr class="field"><td nowrap="nowrap">Forum Administrator:</td><td>'. ($u->users_opt & 1048576 ? '<b><font size="+1" color="red">Yes</font></b>' : 'No') .' [<a href="admuser.php?act=admin&amp;usr_id='. $usr_id .'&amp;'. __adm_rsidl .'">Toggle</a>]</td></tr>';
} else {
	echo '<tr class="field"><td nowrap="nowrap">Forum Administrator:</td><td>'. ($u->users_opt & 1048576 ? '<b><font size="+1" color="red">Yes</font></b>' : 'No') .'</td></tr>';
}
if ($acc_mod_only) {
	echo '<tr class="field"><td>Account Moderator:</td><td>'. ($u->users_opt & 268435456 ? '<b><font size="+1" color="red">Yes</font></b>' : 'No') .'</td></tr>';
} else {
	echo '<tr class="field"><td>Account Moderator:</td><td>'. ($u->users_opt & 268435456 ? '<b><font size="+1" color="red">Yes</font></b>' : 'No') .' [<a href="admuser.php?act=accmod&amp;usr_id='. $usr_id .'&amp;'. __adm_rsidl .'">Toggle</a>]</td></tr>';
}

	echo '<tr class="field"><td nowrap="nowrap" valign="top">Moderating Forums:</td><td valign="top">';
	$c = uq('SELECT f.name FROM '. $DBHOST_TBL_PREFIX .'mod mm INNER JOIN '. $DBHOST_TBL_PREFIX .'forum f ON mm.forum_id=f.id WHERE mm.user_id='. $usr_id);
	if ($r = db_rowarr($c)) {
		echo '<table border="0" cellspacing="1" cellpadding="3">';
		do {
			echo '<tr><td>'. $r[0] .'</td></tr>';
		} while ($r = db_rowarr($c));
		echo '</table>';
	} else {
		echo 'None<br />';
	}
	unset($c);
?>
	<a name="mod_here"> </a>
	[ <a href="#mod_here" onclick="window.open('admmodfrm.php?usr_id=<?php echo $usr_id .'&amp;'. __adm_rsid; ?>', 'frm_mod', 'menubar=false,width=300,height=400,screenX=100,screenY=100,scrollbars=yes');">Modify Moderation Permissions</a> ]</td></tr>

	<tr class="field"><td valign="top">Custom Tags:</td><td valign="top">
<?php
	$c = uq('SELECT name, id FROM '. $DBHOST_TBL_PREFIX .'custom_tags WHERE user_id='. $usr_id);
	while ($r = db_rowarr($c)) {
		echo $r[0] .' [<a href="admuser.php?act=nada&amp;usr_id='. $usr_id .'&amp;deltag='. $r[1] .'&amp;'. __adm_rsid .'">Delete tag</a>]<br />';
	}
	unset($c);
?>
	<form action="admuser.php" method="post">
	<?php echo _hs; ?>
	<input type="text" name="c_tag" />
	<input type="submit" value="Add tag" />
	<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
	<input type="hidden" name="act" value="nada" />
	</form>
	</td></tr>

	<tr class="field"><td valign="top">Profile Link Color:</td>
		<td valign="top">
		<form method="post" action="admuser.php">
		<?php echo _hs; ?>
		<input type="text" name="custom_color" maxlength="255" value="<?php echo $u->custom_color; ?>" />
		<input type="hidden" name="usr_id" value="<?php echo $usr_id; ?>" />
		<input type="hidden" name="act" value="color" /><input type="submit" value="Change" />
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
	<tr class="field"><td colspan="2"><b>Ban user:</b><br />
	<div class="tiny">
		To set a temporary ban, specify the duration of the ban in number of days. 
		For permanent bans, leave duration value at 0.
		The value of the duration field for non-permanent bans will show days remaining till ban expiry.
	</div></td></tr>
	<tr class="field">
		<td>Is Banned:</td>
		<td><label>
			<input type="hidden"   name="block" value="0" /><!-- Hack to ensure that $POST['block;] is always set -->
			<input type="checkbox" name="block" value="65536" <?php echo ($u->users_opt & 65536 ? ' checked />
			<b><font color="red">Yes</font></b>' : ' /> No'); ?> </label>
	</td></tr>
	<tr class="field"><td>Duration (days left)</td><td><input type="number" value="<?php 
	if ($u->ban_expiry) {
		printf("%.2f", ($u->ban_expiry - __request_timestamp__) / 86400);
	} else {
		echo 0;
	}
	?>" name="ban_duration" />
	<tr class="field"><td>Reason:</td><td><input type="text" name="ban_reason" maxlength="255" value="<?php echo $u->ban_reason; ?>" />
	<input type="submit" name="ban_user" value="Ban/Unban" /></td></tr>
	</table>
	</form>
	<br />
</td></tr>
	
<tr class="field">
	<td valign="top">Group Membership:</td>
	<td><?php
	$i = 0;
	$c = uq('SELECT g.name FROM '. $DBHOST_TBL_PREFIX .'group_members m INNER JOIN '. $DBHOST_TBL_PREFIX .'groups g ON g.id=m.group_id WHERE m.user_id='. $usr_id);
	while ($r = db_rowarr($c)) {
		echo $r[0] .'<br />';
		$i++;
	}
	unset($c);
	if (!$i) {
		echo 'No group membership.<br />';
	}
	?></td>
</tr>

<tr><td colspan="2">&nbsp;</td></tr>

<tr class="field">
	<td valign="top">Recent sessions:</td>
	<td><?php
	$i = 0;
	$c = uq('SELECT time_sec, action, useragent, ip_addr FROM '. $DBHOST_TBL_PREFIX .'ses s WHERE s.user_id='. $usr_id);
	while ($r = db_rowarr($c)) {
		$r[1] = preg_replace('/href="/', 'href="'. $GLOBALS['WWW_ROOT'], $r[1]); // Fix URL.
		echo fdate($r[0], 'd M Y H:i') .': '. $r[1] .' <span class="tiny">'. htmlspecialchars($r[2]) .' ('. htmlspecialchars($r[3]) .')</span><br />';
		$i++;
	}
	unset($c);
	$c = uq('SELECT time_sec, action, useragent FROM '. $DBHOST_TBL_PREFIX .'ses s WHERE s.ip_addr='. _esc($u->last_used_ip) .' AND s.user_id<>'. $usr_id);
	while ($r = db_rowarr($c)) {
		echo 'FROM SAME IP: '. fdate($r[0], 'd M Y H:i') .': '. $r[1] .' <span class="tiny">'. htmlspecialchars($r[2]) .'</span><br />';
		$i++;
	}
	unset($c);
	if (!$i) {
		echo 'No recent sessions.<br />';
	}
	?></td>
</tr>

<tr><td colspan="2">&nbsp;</td></tr>

<tr class="field">
	<td colspan="2"><b>Actions:</b></td>
</tr>

<tr class="field">
	<td colspan="2">
<?php
	echo '<a href="../'. __fud_index_name__ .'?t=register&amp;mod_id='. $usr_id .'&amp;'. __adm_rsidl .'">Edit Profile</a> | ';
	if ($FUD_OPT_1 & 1024) {	// PM_ENABLED
		echo '<a href="../'. __fud_index_name__ .'?t=ppost&amp;'. __adm_rsid .'&amp;toi='. $usr_id .'">Send PM</a> | ';
	}
	if ($FUD_OPT_2 & 1073741824) {	// ALLOW_EMAIL
		echo '<a href="../'. __fud_index_name__ .'?t=email&amp;toi='. $usr_id .'&amp;'. __adm_rsid .'">Send E-mail</a> | ';
	} else {
		echo '<a href="mailto:'. $u->email .'">Send E-mail</a> | ';
	}

	echo '  <a href="admuser.php?act=reset&amp;usr_id='. $usr_id .'&amp;'. __adm_rsid .'">Reset Password</a> |';
	echo '  <a href="admuser.php?act=del&amp;usr_id='. $usr_id .'&amp;'. __adm_rsid .'">Delete User</a> |';
	echo '	<a href="../'. __fud_index_name__ .'?t=showposts&amp;id='. $usr_id .'&amp;'. __adm_rsid .'">View Messages</a>';	
	if ($is_a) {
		if ($FUD_OPT_1 & 1024) {	// PM_ENABLED
			echo ' | <a href="admpmspy.php?user='. htmlspecialchars($u->alias) .'&amp;'. __adm_rsid .'">View Private Messages</a>';
		}
		echo ' | <a href="admuserprune.php?usr_id='. $usr_id .'&amp;'. __adm_rsid .'">Delete ALL messages by this user</a>';
	}
?>
</td></tr></table>
<?php 
} 
require($WWW_ROOT_DISK .'adm/footer.php'); 
?>
