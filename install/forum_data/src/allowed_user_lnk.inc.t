<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: allowed_user_lnk.inc.t,v 1.13 2003/04/23 16:56:50 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	include $GLOBALS['FORUM_SETTINGS_PATH'] . 'ip_filter_cache';
	include $GLOBALS['FORUM_SETTINGS_PATH'] . 'login_filter_cache';
	include $GLOBALS['FORUM_SETTINGS_PATH'] . 'email_filter_cache';
	
function is_ip_blocked($ip)
{
	if (!count($GLOBALS['__FUD_IP_FILTER__'])) {
		return;
	}
	$block =& $GLOBALS['__FUD_IP_FILTER__'];
	list($a,$b,$c,$d) = explode('.', $ip);

	if (!isset($block[$a])) {
		return;
	}
	if (isset($block[$a][$b][$c][$d])) {
		return 1;
	}

	if (isset($block[$a][256])) {
		$t = $block[$a][256];
	} else if (isset($block[$a][$b])) {
		$t = $block[$a][$b];
	} else {
		return;
	}

	if (isset($t[$c])) {
		$t = $t[$c];
	} else if (isset($t[256])) {
		$t = $t[256];
	} else {
		return;
	}

	return (isset($t[$d]) || isset($t[256])) ? 1 : NULL;
}

function is_login_blocked($l)
{
	foreach ($GLOBALS['__FUD_LGN_FILTER__'] as $v) {
		if (preg_match($v, $l)) {
			return 1;
		}
	}
	return;
}

function is_email_blocked($addr)
{
	if (!count($GLOBALS['__FUD_EMAIL_FILTER__'])) {
		return;
	}
	$addr = strtolower($addr);
	foreach ($GLOBALS['__FUD_EMAIL_FILTER__'] as $k => $v) {
		if (($v == 'SIMPLE' && (strpos($addr, $k) !== false)) || ($v == 'REGEX' && preg_match($k, $addr))) {
			return 1;
		}
	}
	return;
}

function is_allowed_user(&$usr)
{
	if ($GLOBALS['COPPA'] == 'Y' && $usr->coppa == 'Y') {
		error_dialog('{TEMPLATE: err_coppa_title}', '{TEMPLATE: err_coppa_msg}');
	}

	if ($GLOBALS['EMAIL_CONFIRMATION'] == 'Y' && $usr->email_conf == 'N') {
		std_error('emailconf');
	}	
	
	if ($GLOBALS['MODERATE_USER_REGS'] == 'Y' && $usr->acc_status == 'P') {
		error_dialog('{TEMPLATE: err_mod_acc_ttl}', '{TEMPLATE: err_mod_acc_msg}');
	}
			
	if ($usr->blocked == 'Y' || is_email_blocked($usr->email) || is_login_blocked($usr->login) || (isset($_SERVER['REMOTE_ADDR']) && is_ip_blocked($_SERVER['REMOTE_ADDR']))) {
		ses_delete($usr);
		error_dialog('{TEMPLATE: err_blockedaccnt_title}', '{TEMPLATE: err_blockedaccnt_msg}'); 
	}
}
?>