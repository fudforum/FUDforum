<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: login.php.t,v 1.25 2003/05/01 18:34:35 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
/*{PRE_HTML_PHP}*/

	/* clear old sessions */
	q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE time_sec<'.(__request_timestamp__-$COOKIE_TIMEOUT).($SESSION_USE_URL == 'Y' ? ' OR (time_sec<'.(__request_timestamp__-$SESSION_TIMEOUT).' AND sys_id!=0)' : ''));

	/* Remove old unconfirmed users */
	if ($EMAIL_CONFIRMATION == 'Y') {
		$account_expiry_date = __request_timestamp__ - (86400 * $UNCONF_USER_EXPIRY);
		q("DELETE FROM {SQL_TABLE_PREFIX}users WHERE email_conf='N' AND join_date<".$account_expiry_date." AND posted_msg_count=0 AND last_visit<".$account_expiry_date." AND is_mod!='A'");
	}	

	if (!empty($_GET['logout'])) {
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
				$returnto = str_replace('S='.$tmp['S'], '', $usr->returnto);
				break;
		}
		
		ses_delete($usr->sid);
		header('Location: {ROOT}?'. $returnto);
		exit;
	}
	
	if (_uid) { /* send logged in users to profile page if they are not logging out */
		header('Location: {ROOT}?t=register&'._rsidl);
		exit();
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
	$GLOBALS['_ERROR_'] = NULL;

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
			ses_putvar((int)$usr->sid, NULL);
		}

		if (!($usr_d = db_sab('SELECT id, passwd, login, email, blocked, acc_status, email_conf, is_mod FROM {SQL_TABLE_PREFIX}users WHERE login=\''.addslashes($_POST['login']).'\''))) {
			login_php_set_err('login', '{TEMPLATE: login_invalid_radius}');
		} else if ($usr_d->passwd != md5($_POST['password'])) {
			if ($usr_d->is_mod == 'A') {
				logaction(0, 'WRONGPASSWD', 0, (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0'));
			}
			login_php_set_err('login', '{TEMPLATE: login_invalid_radius}');
		} else { /* Perform check to ensure that the user is allowed to login */

			/* Login & E-mail Filter & IP */
			if (is_login_blocked($usr_d->login) || is_email_blocked($usr_d->email) || $usr_d->blocked == 'Y' || (isset($_SERVER['REMOTE_ADDR']) && is_ip_blocked($_SERVER['REMOTE_ADDR']))) {
				error_dialog('{TEMPLATE: login_blocked_account_ttl}', '{TEMPLATE: login_blocked_account_msg}');
			}

			$ses_id = user_login($usr_d->id, $usr->ses_id, ((empty($_POST['use_cookie']) && $SESSION_USE_URL == 'Y') ? FALSE : TRUE));

			if ($usr_d->email_conf != 'Y') {
				error_dialog('{TEMPLATE: ERR_emailconf_ttl}', '{TEMPLATE: ERR_emailconf_msg}', NULL, $ses_id);
			}
			if ($usr_d->acc_status != 'A') {
				error_dialog('{TEMPLATE: login_unapproved_account_ttl}', '{TEMPLATE: login_unapproved_account_msg}', NULL, $ses_id);
			}

			if (isset($_POST['adm']) && $usr_d->is_mod == 'A') {
				header('Location: adm/admglobal.php?S='.$ses_id);
				exit;
			}

			check_return(str_replace('S='.s, '', $usr->returnto) . 'S=' . $ses_id);
		}
	}
	
	ses_update_status($usr->sid, '{TEMPLATE: login_update}', 0, 0);
	$TITLE_EXTRA = ': {TEMPLATE: login_title}';

/*{POST_HTML_PHP}*/

	$login_error_msg = (!empty($usr->data) && is_string($usr->data)) ? $usr->data : '';
	
	$login_error	= login_php_get_err('login');
	$passwd_error	= login_php_get_err('password');

	$login_use_cookies = $GLOBALS['SESSION_USE_URL'] == 'Y' ? '{TEMPLATE: login_use_cookies}' : '';

	if (!isset($_POST['adm'])) {
		$_POST['adm'] = '';
	}
	
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: LOGIN_PAGE}