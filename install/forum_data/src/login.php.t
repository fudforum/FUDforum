<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: login.php.t,v 1.18 2003/04/02 15:39:11 hackie Exp $
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
	q('DELETE FROM {SQL_TABLE_PREFIX}ses WHERE time_sec<'.(__request_timestamp__-$COOKIE_TIMEOUT).' OR (time_sec<'.(__request_timestamp__-$SESSION_TIMEOUT).' AND sys_id!=0)');

	/* Remove old unconfirmed users */
	if ($EMAIL_CONFIRMATION == 'Y') {
		$account_expiry_date = __request_timestamp__ - (86400 * $UNCONF_USER_EXPIRY);
		q("DELETE FROM {SQL_TABLE_PREFIX}users WHERE email_conf='N' AND join_date<".$account_expiry_date." AND posted_msg_count=0 AND last_visit<".$account_expiry_date." AND is_mod!='A'");
	}	

	if (!empty($_GET['logout'])) {
		preg_match('/\?t=([A-Z0-9a-z_]+)(\&|$)/', $ses->returnto, $regs);
		switch ($regs[1]) {
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
				$returnto = '';
				break;
			default:
				$returnto = $ses->returnto ? $ses->returnto . '&logoff=1' : '{ROOT}';
				break;
		}
		
		$ses->delete_session();
		header('Location: '.$returnto);
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
		$_POST['use_cookie'] = $_POST['quick_use_cookies'];
	}
	
	if (isset($_POST['login']) && !error_check()) {
		if (!($id = get_id_by_radius($_POST['login'], $_POST['password']))) {
			/* If failed login attempt it for admin user we log it */
			if (($aid = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE login='".addslashes($_POST['login'])."' AND is_mod='A'"))) {
				logaction($aid, 'WRONGPASSWD', 0, (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0'));
			}
			login_php_set_err('login', '{TEMPLATE: login_invalid_radius}');
		} else {
			fud_use('login.inc', true);
			$usr = new fud_user_reg;
			$usr->get_user_by_id($id);
			
			/* Perform check to ensure that the user is allowed to login */
			
			/* Login & E-mail Filter & IP */
			if (is_blocked_login($usr->login) || is_email_blocked($usr->email) || $usr->blocked == 'Y' || (isset($_SERVER['REMOTE_ADDR']) && fud_ip_filter::is_blocked($_SERVER['REMOTE_ADDR']))) {
				error_dialog('{TEMPLATE: login_blocked_account_ttl}', '{TEMPLATE: login_blocked_account_msg}', $returnto_d);
				exit();
			}
			
			$ses->save_session($id, (empty($_POST['use_cookie']) ? 1 : NULL));
			
			if ($usr->email_conf != 'Y') {
				std_error('emailconf');
				exit();
			}
			
			if (isset($_POST['adm']) && $usr->is_mod == 'A') {
				header('Location: adm/admglobal.php?'._rsidl);
				exit;
			}
			
			check_return($ses->returnto);
		}
	}
	
	$ses->update('{TEMPLATE: login_update}');
	$TITLE_EXTRA = ': {TEMPLATE: login_title}';

/*{POST_HTML_PHP}*/

	$login_error_msg = (isset($_REQUEST['msg']) && strlen($_REQUEST['msg'])) ? '{TEMPLATE: login_error_msg}' : '';
	
	$login_error	= login_php_get_err('login');
	$passwd_error	= login_php_get_err('password');

	if (!isset($_POST['adm'])) {
		$_POST['adm'] = '';
	}
	
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: LOGIN_PAGE}