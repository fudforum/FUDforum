<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: email.php.t,v 1.2 2002/07/08 23:15:18 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*#? Email Composition Form */
	include_once "GLOBALS.php";	
	{PRE_HTML_PHP}

	if ( empty($usr->id) ) std_error('login');
	
	if( $GLOBALS["ALLOW_EMAIL"]=='N' ) {
		error_dialog('{TEMPLATE: email_err_unabletoemail_title}', '{TEMPLATE: email_err_unabletoemail_msg}', '{ROOT}?t=index&'._rsid);
		exit;
	}
	
	is_allowed_user();
	
function set_err($type, $msg)
{
	$GLOBALS['_ERROR_'][$type] = $msg;
	$GLOBALS['error'] = 1;
}

function get_err($type)
{
	if ( !isset($GLOBALS['_ERROR_'][$type]) ) return;
	return '{TEMPLATE: email_error_text}';
}

function mail_check()
{
	$GLOBALS['error']=0;
	
	if ( !strlen(trim($GLOBALS['tx_body'])) )
		set_err('tx_body', '{TEMPLATE: email_error_body}');
	
	if ( !strlen(trim($GLOBALS['tx_subject'])) )
		set_err('tx_subject', '{TEMPLATE: email_error_subject}');
	
	if ( strlen(trim($GLOBALS['email_open'])) ) {
		if ( !strlen(trim($GLOBALS['tx_email'])) )
			set_err('tx_email', '{TEMPLATE: email_error_emailrequired}');
		else if ( validate_email($GLOBALS['tx_email']) )
			set_err('tx_email', '{TEMPLATE: email_error_invalidaddress}');
	}
	else {
		if ( !strlen(trim($GLOBALS['tx_name'])) )
			set_err('tx_name', '{TEMPLATE: email_error_namerequired}');
			
		$u_name = $GLOBALS['tx_name'];
		reverse_FMT($u_name);

		if ( !get_id_by_alias(addslashes($u_name)) ) {
			set_err('tx_name', '{TEMPLATE: email_error_invaliduser}');
		}
	}
	
	return $GLOBALS['error'];
}
	
	if( isset($email_open) ) {
		$tx_body = stripslashes($tx_body);
		$tx_subject = stripslashes($tx_subject);
		$tx_name = stripslashes($tx_name);
	}
	
	if( $HTTP_GET_VARS['tx_name'] ) $tx_name = stripslashes($HTTP_GET_VARS['tx_name']);
	
	if( empty($email_open) ) $email_open = '';
	if( empty($tx_subject) ) $tx_subject = '';
	if( empty($tx_body) ) $tx_body = '';
	
	if ( !empty($btn_submit) && !mail_check() ) {
		if ( $email_open ) {
			$tx_body = "\n\n".$tx_body;
			reverse_FMT($tx_name);
			$to = $tx_name.' <'.$tx_email.'>';
		}
		else {
			$usr_dst = new fud_user;
			reverse_FMT($tx_name);
			$usr_dst->get_user_by_id(get_id_by_alias(addslashes($tx_name)));
			if ( !strlen($usr_dst->email) || $usr_dst->email_messages!='Y') {
				error_dialog('{TEMPLATE: email_err_unabletoemail_title}', '{TEMPLATE: email_error_unabletolocaddr}', $GLOBALS['HTTP_REFERER'], 'FATAL');
				exit();
			}
			/* email here */
			reverse_FMT($usr->login);
			reverse_FMT($usr_dst->login);
			$to = $usr_dst->login.' <'.$usr_dst->email.'>';
		}
		
		send_email($usr->email, $to, $tx_subject, $tx_body, $to_str);
		check_return();
	}
	
	/* start page */
	$TITLE_EXTRA = ': {TEMPLATE: email_title}';
	{POST_HTML_PHP}
	
	$name_err = get_err('tx_name');
	
	if ( $email_open ) {
		$email_err = get_err('tx_email');
		$destination = '{TEMPLATE: dest_non_forum_user}';	
	}
	else 
		$destination = '{TEMPLATE: dest_forum_user}';
		
	$sub_err = get_err('tx_subject');
	$body_err = get_err('tx_body');
	
	$return = create_return();
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: EMAIL_PAGE}