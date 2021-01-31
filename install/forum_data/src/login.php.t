<?php
/**
* copyright            : (C) 2001-2021 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	/* Remove old unconfirmed users. */
	if ($FUD_OPT_2 & 1) {
		$account_expiry_date = __request_timestamp__ - (86400 * $UNCONF_USER_EXPIRY);
		$list = db_all('SELECT id FROM {SQL_TABLE_PREFIX}users WHERE '. q_bitand('users_opt', 131072) .'=0 AND join_date<'. $account_expiry_date .' AND posted_msg_count=0 AND last_visit<'. $account_expiry_date .' AND id!=1 AND '. q_bitand('users_opt', 1048576) .'=0');

		if ($list) {
			fud_use('private.inc');
			fud_use('users_adm.inc', true);
			usr_delete($list);
		}
		unset($list);
	}

	/* Log user out and redirect to correct page. */
	if (!empty($_GET['logout']) && sq_check(0, $usr->sq)) {
		if ($usr->returnto) {
			parse_str($usr->returnto, $tmp);
			$page = isset($tmp['t']) ? $tmp['t'] : '';
		} else {
			$page = '';
		}

		switch ($page) {
			case 'register':
			case 'pmsg_view':
			case 'pmsg':
			case 'subscribed':
			case 'referals':
			case 'buddy_list':
			case 'ignore_list':
			case 'modque':
			case 'mvthread':
			case 'groupmgr':
			case 'post':
			case 'ppost':
			case 'finduser':
			case 'error':
			case 'uc':
			case '':
				$returnto = '';
				break;
			default:
				if ($page == 'msg' || $page == 'tree') {
					if (empty($tmp['th'])) {
						if (empty($tmp['goto']) || !q_singleval('SELECT t.forum_id
								FROM {SQL_TABLE_PREFIX}msg m
								INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
								INNER JOIN {SQL_TABLE_PREFIX}group_cache g ON g.user_id=0 AND g.resource_id=t.forum_id AND '. q_bitand('g.group_cache_opt', 2) .' > 0
								WHERE m.id='. (int)$tmp['goto'])) {
							$returnto = '';
							break;
						}
					} else {
						if (!q_singleval('SELECT t.forum_id
								FROM {SQL_TABLE_PREFIX}thread t
								INNER JOIN {SQL_TABLE_PREFIX}group_cache g ON g.user_id=0 AND g.resource_id=t.forum_id AND '. q_bitand('g.group_cache_opt', 2) .' > 0
								WHERE t.id='. (int)$tmp['th'])) {
							$returnto = '';
							break;
						}
					}
				} else if ($page == 'thread' || $page == 'threadt') {
					if (!q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=0 AND resource_id='. (isset($tmp['frm_id']) ? (int)$tmp['frm_id'] : 0).' AND '. q_bitand('group_cache_opt', 2) .' > 0')) {
						$returnto = '';
						break;
					}
				}

				if (isset($tmp['S'])) {
					$returnto = str_replace('S='. $tmp['S'], '', $usr->returnto);
				} else {
					$returnto = $usr->returnto;
				}
				break;
		}

		ses_delete($usr->sid);
		if ($FUD_OPT_2 & 32768 && $returnto && $returnto[0] == '/') {
			header('Location: {FULL_ROOT}'. $returnto);
		} else {
			header('Location: {FULL_ROOT}?'. str_replace(array('?', '&&'), array('&', '&'), $returnto));
		}
		exit;
	}

	/* Send logged in users to profile page if they are not logging out. */
	if (_uid) {
		if ($FUD_OPT_2 & 32768) {
			header('Location: {ROOT}/re/'. _rsidl);
		} else {
			header('Location: {ROOT}?t=register&'. _rsidl);
		}
		exit;
	}

/** Signal error against type=login or type=password field. */
function login_php_set_err($type, $val)
{
	$GLOBALS['_ERROR_']            = 1;
	$GLOBALS['_ERROR_MSG_'][$type] = $val;
}

/** Display login error. This function is called from the login template. */
function login_php_get_err($type)
{
	if (empty($GLOBALS['_ERROR_MSG_'][$type])) {
		return;
	}
	return '{TEMPLATE: login_error_text}';
}

/** Check for obvious username and password errors before we attempt to authenticate. */
function error_check()
{
	if (empty($_POST['login']) || !strlen($_POST['login'] = trim((string)$_POST['login']))) {
		login_php_set_err('login', '{TEMPLATE: login_name_required}');
	}

	if (empty($_POST['password']) || !strlen($_POST['password'] = trim((string)$_POST['password']))) {
		login_php_set_err('password', '{TEMPLATE: login_passwd_required}');
	}

	return $GLOBALS['_ERROR_'];
}

	$_ERROR_ = 0;
	$_ERROR_MSG_ = array();

	/* Deal with quicklogin from if needed. */
	if (isset($_POST['quick_login']) && isset($_POST['quick_password'])) {
		$_POST['login']      = $_POST['quick_login'];
		$_POST['password']   = $_POST['quick_password'];
		$_POST['use_cookie'] = isset($_POST['quick_use_cookies']);
	}

	// Call authentication plugins.
	// Plugin should return 1 (allow access) or 0 (deny access).
	if (defined('plugins')) {
		$ok = plugin_call_hook('AUTHENTICATE');
		if (!empty($ok) && $ok != 1){
			login_php_set_err('login', 'plugin: Invalid login/password combination');
		}
	}

	// Call PRE authentication plugins.
	// If successfully autheticated, the plugin should return a full user object.
	// Return null to continue with FUDforum's default authentication.
	$usr_d = null;
	if (defined('plugins')) {
		$usr_d = plugin_call_hook('PRE_AUTHENTICATE', $usr_d);
	}

	if ($usr_d || isset($_POST['login']) && !error_check()) {
	
		/* Clear session variables. */
		if ($usr->data) {
			ses_putvar((int)$usr->sid, null);
		}

		/* Try to autenticate user. */
		if (!$usr_d && !($usr_d = db_sab('SELECT last_login, id, passwd, salt, login, email, users_opt, ban_expiry, ban_reason FROM {SQL_TABLE_PREFIX}users WHERE login='. _esc($_POST['login'])))) {
			/* Cannot login: user not in DB. */
			login_php_set_err('login', '{TEMPLATE: login_invalid_radius}');

		} else if (($usr_d->last_login + $MIN_TIME_BETWEEN_LOGIN) > __request_timestamp__) { 
			/* Flood control. */
			q('UPDATE {SQL_TABLE_PREFIX}users SET last_login='. __request_timestamp__ .' WHERE id='. $usr_d->id);
			login_php_set_err('login', '{TEMPLATE: login_min_time}');

		/* Check password: No salt -> old md5() auth; with salt -> new sha1() auth. */
		} else if (!isset($usr_d->alias) && (empty($usr_d->salt) && $usr_d->passwd != md5($_POST['password']) || 
			  !empty($usr_d->salt) && $usr_d->passwd != sha1($usr_d->salt . sha1($_POST['password'])))) 
		{
			logaction($usr_d->id, 'WRONGPASSWD', 0, 'Invalid '. ($usr_d->users_opt & 1048576 ? 'FORUM ADMIN ' : '') .'password for login '. htmlspecialchars(_esc($_POST['login'])) .' from IP '. get_ip() .'.');
			q('UPDATE {SQL_TABLE_PREFIX}users SET last_login='. __request_timestamp__ .' WHERE id='. $usr_d->id);
			login_php_set_err('login', '{TEMPLATE: login_invalid_radius}');
		}

		if ($GLOBALS['_ERROR_'] != 1) {
			/* Is user allowed to login. */
			q('UPDATE {SQL_TABLE_PREFIX}users SET last_login='. __request_timestamp__ .' WHERE id='. $usr_d->id);
			$usr_d->users_opt = (int) $usr_d->users_opt;
			$usr_d->sid = $usr_d->id;
			is_allowed_user($usr_d, 1);

			$ses_id = user_login($usr_d->id, $usr->ses_id, ((empty($_POST['use_cookie']) && $FUD_OPT_1 & 128) ? false : true));

			if (!($usr_d->users_opt & 131072)) {
				error_dialog('{TEMPLATE: ERR_emailconf_ttl_l}', '{TEMPLATE: ERR_emailconf_msg_l}', null, $ses_id);
			}
			if ($usr_d->users_opt & 2097152) {
				error_dialog('{TEMPLATE: login_unapproved_account_ttl}', '{TEMPLATE: login_unapproved_account_msg}', null, $ses_id);
			}

			if (!empty($_POST['adm']) && $usr_d->users_opt & 1048576) {
				header('Location: {BASE}adm/index.php?S='. $ses_id .'&SQ='. $new_sq);
				exit;
			}

			if (!$usr->returnto) { /* Nothing to do, send to front page. */
				check_return('');
			}

			if (s && ($sesp = strpos($usr->returnto, s)) !== false) { /* Replace old session with new session. */
				$usr->returnto = str_replace(s, $ses_id, $usr->returnto);
			}

			if ($usr->returnto[0] != '/') { /* No GET vars or no PATH_INFO. */
				$ret =& $usr->returnto;
				parse_str($ret, $args);
				$args['SQ'] = $new_sq;

				if ($FUD_OPT_1 & 128) { /* If URL sessions are supported. */
					$args['S'] = $ses_id;
				}

				$ret = '';
				foreach ($args as $k => $v) {
					$ret .= $k .'='. $v .'&';
				}
			} else { /* PATH_INFO url or GET url with no args. */
				if ($FUD_OPT_1 & 128 && $FUD_OPT_2 & 32768 && !$sesp) {
					if (preg_match('![a-z0-9]{32}!', $usr->returnto, $m)) {
						$usr->returnto = str_replace($m[0], $ses_id, $usr->returnto);
					}
				}
				$usr->returnto .= '?SQ='. $new_sq .'&S='. $ses_id;
			}

			check_return($usr->returnto);
		}
	}

	ses_update_status($usr->sid, '{TEMPLATE: login_update}', 0, 0);
	$TITLE_EXTRA = ': {TEMPLATE: login_title}';

/*{POST_HTML_PHP}*/

	/* Check if we have a 'password reset' message to display (from reset.php.t). */
	if (!empty($usr->data) && substr($usr->data, 0, 9) == 'resetmsg=') {
		$reset_login_notify = substr($usr->data, 9);
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: LOGIN_PAGE}

