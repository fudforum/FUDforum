<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: remail.php.t,v 1.5 2002/07/30 14:34:37 hackie Exp $
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

	if( !empty($done) ) check_return();
	
	if( empty($th) || !is_numeric($th) ) invl_inp_err();
	
	if( isset($usr) ) is_allowed_user();
	
	$thread = new fud_thread;
	$thread->get_by_id($th);

	if( !is_perms(_uid, $thread->forum_id, 'READ') ) {
		std_error('access');
		exit;	
	}

	if( empty($body) ) {
		$u = isset($usr) ? $usr->alias : $GLOBALS["ANON_NICK"];
		$rid = isset($usr) ? $usr->id : '';
		$bd = '{TEMPLATE: email_message}';
	}	
	
	{POST_HTML_PHP}
	if( !empty($GLOBALS["HTTP_POST_VARS"]["posted"]) && isset($usr) && !check_femail_form() ) {
		$to = empty($fname) ? $femail : $fname.' <'.$femail.'>';
		$from = $usr->alias. '<'.$usr->email.'>';
		send_email(stripslashes($from), stripslashes($to), stripslashes($subj), stripslashes($body));
	
		error_dialog('{TEMPLATE: remail_emailsent}', '{TEMPLATE: remail_sent_conf}', '{ROOT}?t='.d_thread_view.'&th='.$th.'&'._rsid);
		exit;
	}
	
	if( is_post_error() ) $error_data = '{TEMPLATE: remail_error}';
	
	$body = empty($body)?$bd:stripslashes($body);
	$body_error = get_err('body');
	if( isset($usr) ) {
		$femail_error = get_err('femail');
		$subject = empty($subj) ? $thread->subject:stripslashes($subj);
		$subject_error = get_err('subj');
		$form_data = '{TEMPLATE: registed_user}';	
	}
	else {
		$form_data = '{TEMPLATE: anon_user}';
	}
	$return_field = create_return();
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: REMAIL_PAGE}