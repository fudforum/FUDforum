<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: login.php.t,v 1.59 2004/05/21 16:28:49 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

	/* clear old sessions */
	q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE time_sec<'.(__request_timestamp__- ($FUD_OPT_3 & 1 ? $SESSION_TIMEOUT : $COOKIE_TIMEOUT)));

	/* Remove old unconfirmed users */
	if ($FUD_OPT_2 & 1) {
		$account_expiry_date = __request_timestamp__ - (86400 * $UNCONF_USER_EXPIRY);
		$c = uq("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE (users_opt & 131072)=0 AND join_date<".$account_expiry_date." AND posted_msg_count=0 AND last_visit<".$account_expiry_date." AND id!=1 AND (users_opt & 1048576)=0");
		$list = array();
		while ($r = db_rowarr($c)) {
			$list[] = $r[0];
		}
		if ($list) {
			fud_use('private.inc');
			fud_use('users_adm.inc', true);
			usr_delete($list);
		}
		unset($c, $list);
	}

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
			case '':
				$returnto = '';
				break;
			default:
				if ($page == 'msg' || $page == 'tree') {
					if (empty($tmp['th'])) {
						if (empty($tmp['goto']) || !q_singleval("SELECT t.forum_id
								FROM {SQL_TABLE_PREFIX}msg m
								INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
								INNER JOIN {SQL_TABLE_PREFIX}group_cache g ON g.user_id=0 AND g.resource_id=t.forum_id AND (g.group_cache_opt & 2) > 0
								WHERE m.id=".(int)$tmp['goto'])) {
							$returnto = '';
							break;
						}
					} else {
						if (!q_singleval("SELECT t.forum_id
								FROM {SQL_TABLE_PREFIX}thread t
								INNER JOIN {SQL_TABLE_PREFIX}group_cache g ON g.user_id=0 AND g.resource_id=t.forum_id AND (g.group_cache_opt & 2) > 0
								WHERE t.id=".(int)$tmp['th'])) {
							$returnto = '';
							break;
						}
					}
				} else if ($page == 'thread' || $page == 'threadt') {
					if (!q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=0 AND resource_id=".(isset($tmp['frm_id']) ? (int) $tmp['frm_id'] : 0)." AND (group_cache_opt & 2) > 0")) {
						$returnto = '';
						break;
					}
				}

				if (isset($tmp['S'])) {
					$returnto = str_replace('S='.$tmp['S'], '', $usr->returnto);
				} else {
					$returnto = $usr->returnto;
				}
				break;
		}

		ses_delete($usr->sid);
		if ($FUD_OPT_2 & 32768 && $returnto && $returnto[0] == '/') {
			header('Location: {FULL_ROOT}{ROOT}'. $returnto);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?'. str_replace(array('?', '&&'), array('&', '&'), $returnto));
		}
		exit;
	}

	if (_uid) { /* send logged in users to profile page if they are not logging out */
		if ($FUD_OPT_2 & 32768) {
			header('Location: {FULL_ROOT}{ROOT}/re/'._rsidl);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?t=register&'._rsidl);
		}
		exit;
	}

function login_php_set_err($type, $val)
{
	$GLOBALS['_ERROR_'] = 1;
	$GLOBALS['_ERROR_MSG_'][$type] = $val;
}

function login_php_get_err($type)
{
	if (empty($GLOBALS['_ERROR_MSG_'][$type])) {
		return;
	}
	return '{TEMPLATE: login_error_text}';
}

function error_check()
{
	$GLOBALS['_ERROR_'] = null;

	$_POST['login'] = trim($_POST['login']);
	$_POST['password'] = trim($_POST['password']);

	if (!strlen($_POST['login'])) {
		login_php_set_err('login', '{TEMPLATE: login_name_required}');
	}

	if (!strlen($_POST['password'])) {
		login_php_set_err('password', '{TEMPLATE: login_passwd_required}');
	}

	return $GLOBALS['_ERROR_'];
}

	/* deal with quicklogin from if needed */
	if (isset($_POST['quick_login']) && isset($_POST['quick_password'])) {
		$_POST['login'] = $_POST['quick_login'];
		$_POST['password'] = $_POST['quick_password'];
		$_POST['use_cookie'] = isset($_POST['quick_use_cookies']);
	}

	if (isset($_POST['login']) && !error_check()) {
		if ($usr->data) {
			ses_putvar((int)$usr->sid, null);
		}

		if (!($usr_d = db_sab("SELECT id, passwd, login, email, users_opt FROM {SQL_TABLE_PREFIX}users WHERE login='".addslashes($_POST['login'])."'"))) {
			login_php_set_err('login', '{TEMPLATE: login_invalid_radius}');
		} else if ($usr_d->passwd != md5($_POST['password'])) {
			if ($usr_d->users_opt & 1048576) {
				logaction(0, 'WRONGPASSWD', 0, get_ip());
			}
			login_php_set_err('login', '{TEMPLATE: login_invalid_radius}');
		} else { /* Perform check to ensure that the user is allowed to login */
			$usr_d->users_opt = (int) $usr_d->users_opt;

			/* Login & E-mail Filter & IP */
			if (is_login_blocked($usr_d->login) || is_email_blocked($usr_d->email) || $usr_d->users_opt & 65536 || is_ip_blocked(get_ip())) {
				setcookie($COOKIE_NAME.'1', 'd34db33fd34db33fd34db33fd34db33f', __request_timestamp__+63072000, $COOKIE_PATH, $COOKIE_DOMAIN);
				error_dialog('{TEMPLATE: login_blocked_account_ttl}', '{TEMPLATE: login_blocked_account_msg}');
			}

			$ses_id = user_login($usr_d->id, $usr->ses_id, ((empty($_POST['use_cookie']) && $FUD_OPT_1 & 128) ? false : true));

			if (!($usr_d->users_opt & 131072)) {
				error_dialog('{TEMPLATE: ERR_emailconf_ttl}', '{TEMPLATE: ERR_emailconf_msg}', null, $ses_id);
			}
			if ($usr_d->users_opt & 2097152) {
				error_dialog('{TEMPLATE: login_unapproved_account_ttl}', '{TEMPLATE: login_unapproved_account_msg}', null, $ses_id);
			}

			if (!empty($_POST['adm']) && $usr_d->users_opt & 1048576) {
				header('Location: {FULL_ROOT}adm/admglobal.php?S='.$ses_id.'&SQ='.$new_sq);
				exit;
			}

			if ($FUD_OPT_1 & 128) {
				if (strpos($usr->returnto, s) !== false) {
					$usr->returnto = str_replace(s, $ses_id, $usr->returnto);
				} else {
					if ($FUD_OPT_2 & 32768) {
						$pos = strpos($usr->returnto, '?');
						if ($pos && $pos == strlen($usr->returnto)-1) {
							$usr->returnto = substr($usr->returnto, 0, -1);
						} else if (!$pos) {
							$usr->returnto .= $ses_id . '/';
						} else {
							$usr->returnto .= '&S=' . $ses_id;
						}
					} else {
						$usr->returnto .= '&S=' . $ses_id;
					}
				}
			}

			if (strpos($usr->returnto, '?') !== false || !($FUD_OPT_2 & 32768)) {
				$usr->returnto .= '&SQ='.$new_sq;
			} else {
				$usr->returnto .= '?SQ='.$new_sq;
			}
			check_return($usr->returnto);
		}
	}

	ses_update_status($usr->sid, '{TEMPLATE: login_update}', 0, 0);
	$TITLE_EXTRA = ': {TEMPLATE: login_title}';

/*{POST_HTML_PHP}*/

	$login_error_msg = (!empty($usr->data) && is_string($usr->data)) ? $usr->data : '';

	$login_error	= login_php_get_err('login');
	$passwd_error	= login_php_get_err('password');

	$login_use_cookies = $FUD_OPT_1 & 128 ? '{TEMPLATE: login_use_cookies}' : '';

	if (!isset($_POST['adm'])) {
		$_POST['adm'] = isset($_GET['adm']) ? '1' : '';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: LOGIN_PAGE}