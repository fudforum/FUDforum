<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: login.php.t,v 1.11 2002/09/30 21:57:07 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
	{PRE_HTML_PHP}	
	$usr = fud_user_to_reg($usr);
	clear_old_sessions();

	/* Remove old unconfirmed users */
	if( $EMAIL_CONFIRMATION == 'Y' ) {
		$account_expiry_date = __request_timestamp__-(86400*$UNCONF_USER_EXPIRY);
		q("DELETE FROM {SQL_TABLE_PREFIX}users WHERE email_conf='N' AND join_date<".$account_expiry_date." AND posted_msg_count=0 AND last_visit<".$account_expiry_date." AND is_mod!='A'");
	}	

	if ( !empty($HTTP_GET_VARS['logout']) && isset($ses) ) {
		preg_match('/\?t=([A-Z0-9a-z_]+)(\&|$)/', $returnto, $regs);
		switch( $regs[1] )
		{
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
			case 'error':
				$returnto = $GLOBALS['returnto'] = '';
				break;
			default:
				$GLOBALS['returnto'] .= '&logoff=1';
				break;
		}
		
		$ses->delete_session();
		check_return();
	}
	
	if ( is_object($usr) ) {
		header("Location: {ROOT}?t=register&"._rsidl);
		exit();
	}

function login_php_set_err($type, $val)
{
	$GLOBALS['_ERROR_'] = 1;
	$GLOBALS['_ERROR_MSG_'][$type] = $val;
}

function login_php_get_err($type)
{
	if ( empty($GLOBALS['_ERROR_MSG_'][$type]) || !strlen($GLOBALS['_ERROR_MSG_'][$type]) ) return;
	$msg = $GLOBALS['_ERROR_MSG_'][$type];
	return '{TEMPLATE: login_error_text}';
}
	
function error_check()
{
	$GLOBALS['_ERROR_'] = NULL;

	if ( !strlen(trim($GLOBALS['HTTP_POST_VARS']['login'])) ) {
		login_php_set_err('login', '{TEMPLATE: login_name_required}');
	}
	
	if ( !strlen(trim($GLOBALS['HTTP_POST_VARS']['password'])) ) {
		login_php_set_err('password', '{TEMPLATE: login_passwd_required}');
	}
	
	return $GLOBALS['_ERROR_'];
}
	
	/* deal with quicklogin from if needed */
	if ( isset($HTTP_POST_VARS['quick_login']) && isset($HTTP_POST_VARS['quick_password']) ) {
		$HTTP_POST_VARS['login'] = $HTTP_POST_VARS['quick_login'];
		$HTTP_POST_VARS['password'] = $HTTP_POST_VARS['quick_password'];
		$GLOBALS["HTTP_POST_VARS"]["use_cookie"] = $HTTP_POST_VARS['quick_use_cookies'];
	}
	
	if ( @count($HTTP_POST_VARS) && !error_check() ) {
		if ( !($id = get_id_by_radius($HTTP_POST_VARS['login'], $HTTP_POST_VARS['password'])) ) {
			/* check if admin */
			$id = get_id_by_login($HTTP_POST_VARS['login']);
			
			if ( $id ) {
				$ausr = new fud_user;
				$ausr->get_user_by_id($id);
				if ( $ausr->is_mod == 'A' ) {
					logaction($id, 'WRONGPASSWD', 0, $GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']);
				}
				$id = NULL;					
			}
			login_php_set_err('login', '{TEMPLATE: login_invalid_radius}');
		}
		else {
			$usr = new fud_user_reg;
			$usr->get_user_by_id($id);
			
			if ( !isset($ses) ) $ses = new fud_session;
			$uck = empty($GLOBALS["HTTP_POST_VARS"]["use_cookie"]) ? 1 : NULL;
			$ses->save_session($id,$uck);
			
			if ( $usr->email_conf != 'Y' ) {
				std_error('emailconf');
				exit();
			}
			
			if( !empty($GLOBALS["HTTP_POST_VARS"]["adm"]) && $usr->is_mod == 'A' ) {
				header("Location: adm/admglobal.php?"._rsidl);
				exit;
			}
			
			check_return();
		}
	}
	
	if ( isset($ses) ) $ses->update('{TEMPLATE: login_update}');
	$TITLE_EXTRA = ': {TEMPLATE: login_title}';
	{POST_HTML_PHP}

	if( isset($msg) ) {
		$msg = stripslashes($msg);
		if ( strlen($msg) ) 
			$login_error_msg = '{TEMPLATE: login_error_msg}';
	} else $login_error_msg = NULL;
	
	$login_error = login_php_get_err('login');
	$passwd_error = login_php_get_err('password');
	
	$returnto = create_return();
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: LOGIN_PAGE}