<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: allowed_user_lnk.inc.t,v 1.3 2003/02/01 20:14:29 hackie Exp $
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

function is_allowed_user()
{
	global $usr;
	
	if ( strtoupper($GLOBALS['COPPA']) == 'Y' && strtoupper($usr->coppa) == 'Y' )
		{ error_dialog('{TEMPLATE: err_coppa_title}', '{TEMPLATE: err_coppa_msg}', $returnto_d); exit(); }

	if ( $GLOBALS['EMAIL_CONFIRMATION'] == 'Y' && $usr->email_conf == 'N' ) 
		{ std_error('emailconf'); exit(); }	
	
	if ( $GLOBALS['MODERATE_USER_REGS'] == 'Y' && $usr->acc_status == 'P' )
		{ error_dialog('{TEMPLATE: err_mod_acc_ttl}', '{TEMPLATE: err_mod_acc_msg}', $returnto_d); exit(); }
			
	if ( $usr->blocked == 'Y' || is_email_blocked($usr->email) || is_blocked_login($usr->login) || (isset($GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']) && fud_ip_filter::is_blocked($GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']))) 
		{ error_dialog('{TEMPLATE: err_blockedaccnt_title}', '{TEMPLATE: err_blockedaccnt_msg}', $returnto_d, 'FATAL'); exit(); }
}
?>