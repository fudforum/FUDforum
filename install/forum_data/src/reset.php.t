<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: reset.php.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
	include_once "GLOBALS.php";
	
	{PRE_HTML_PHP}
	$usr = fud_user_to_reg($usr);
	
	if ( $reset_key ) {
		if ( $parr = reset_user_passwd_by_key($reset_key) ) {
			send_email($GLOBALS['NOTIFY_FROM'], $parr['usr']->email, '{TEMPLATE: reset_newpass_title}', '{TEMPLATE: reset_newpass_msg}', "");
			header('Location: {ROOT}?t=login&'._rsid.'&msg='.urlencode('{TEMPLATE: reset_login_notify}'));
		}
		else {
			error_dialog('{TEMPLATE: reset_err_invalidkey_title}', '{TEMPLATE: reset_err_invalidkey_msg}', NULL, 'FATAL');
		}
		exit();
	}
	
	$error_msg = NULL;
	if ( !empty($GLOBALS['email']) ) {
		$email = $GLOBALS['email'];
		
		if ( $id=get_id_by_email($email) ) {
			$usr = new fud_user_reg;
			$usr->get_user_by_id($id);
			if ( $EMAIL_CONFIRMATION == 'Y' && $usr->email_conf == 'N' ) {
				$conf_key = $usr->email_unconfirm();
				$url = '{ROOT}?t=emailconf&conf_key='.$conf_key;
				send_email($GLOBALS['NOTIFY_FROM'], $usr->email, '{TEMPLATE: register_conf_subject}', '{TEMPLATE: reset_confirmation}', "");
			}
			else {
				$key = $usr->reset_key();
				$url = '{ROOT}?t=reset&reset_key='.$key;
				send_email($GLOBALS['NOTIFY_FROM'], $usr->email, '{TEMPLATE: reset_newpass_title}', '{TEMPLATE: reset_reset}', "");
			}
			
			if ( isset($ses) ) {
				$ses->delete_session();
			}
			error_dialog('{TEMPLATE: reset_err_rstconf_title}', '{TEMPLATE: reset_err_rstconf_msg}', $returnto);
			exit();
		}
		else {
			$no_such_email = '{TEMPLATE: no_such_email}';
		}
	}
	else $email = NULL;
	if ( empty($usr->id) ) unset($usr);
	
	set_row_color_alt(true);

	$TITLE_EXTRA = ': {TEMPLATE: reset_title}';
	{POST_HTML_PHP}
	
	$email = stripslashes($email);
	$return_field = create_return();
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: RESET_PAGE}