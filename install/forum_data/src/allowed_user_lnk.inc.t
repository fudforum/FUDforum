<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: allowed_user_lnk.inc.t,v 1.8 2003/04/20 10:45:19 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	fud_use('login.inc', true);

function is_allowed_user(&$usr)
{
	if ($GLOBALS['COPPA'] == 'Y' && $usr->coppa == 'Y') {
		error_dialog('{TEMPLATE: err_coppa_title}', '{TEMPLATE: err_coppa_msg}');
	}

	if ($GLOBALS['EMAIL_CONFIRMATION'] == 'Y' && $usr->email_conf == 'N') {
		std_error('emailconf');
		exit();
	}	
	
	if ($GLOBALS['MODERATE_USER_REGS'] == 'Y' && $usr->acc_status == 'P') {
		error_dialog('{TEMPLATE: err_mod_acc_ttl}', '{TEMPLATE: err_mod_acc_msg}');
	}
			
	if ($usr->blocked == 'Y' || is_email_blocked($usr->email) || is_blocked_login($usr->login) || (isset($_SERVER['REMOTE_ADDR']) && is_ip_blocked($_SERVER['REMOTE_ADDR']))) {
		ses_delete($usr);
		error_dialog('{TEMPLATE: err_blockedaccnt_title}', '{TEMPLATE: err_blockedaccnt_msg}'); 
	}
}

function is_ip_blocked($ip)
{
	if (!isset($GLOBALS['__FUD_IP_FILTER__'])) {
		include $GLOBALS['FORUM_SETTINGS_PATH'] . 'ip_filter_cache';
	}
	if (!count($GLOBALS['__FUD_IP_FILTER__'])) {
		return;
	}
	$block =& $GLOBALS['__FUD_IP_FILTER__'];
	$ipp = explode('.', $ip);

	if (isset($block[$ipp[0]]) && (isset($block[$ipp[1]]) || isset($block[256])) && (isset($block[$ipp[2]]) || isset($block[256])) && (isset($block[$ipp[3]]) || isset($block[256]))) {
		return 1;
	} else {
		return;	
	}
}
?>