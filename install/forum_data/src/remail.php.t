<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: remail.php.t,v 1.8 2003/04/15 19:17:57 hackie Exp $
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

	if (isset($_POST['done'])) {
		check_return($usr->returnto);
	}

	is_allowed_user($usr);

	if ((isset($_GET['th']) && ($th = (int)$_GET['th'])) || (isset($_POST['th']) && ($th = (int)$_POST['th']))) {
		$data = db_sab('SELECT m.subject, t.id, mm.id AS mod, (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END) AS p_read
				FROM {SQL_TABLE_PREFIX}thread t 
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=t.forum_id AND mm.user_id='._uid.'
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=t.forum_id 
				LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id
				WHERE t.id='.$th);
				
	}
	if (empty($data)) {
		invl_inp_err();
	}
	if ($usr->is_mod != 'A' && !$data->mod && $data->p_read != 'Y') {
		std_error('access');
	}

/*{POST_HTML_PHP}*/

	if (isset($_POST['posted']) && _uid && !check_femail_form()) {
		$to = empty($POST['fname']) ? $_POST['femail'] : $_POST['fname'].' <'.$_POST['femail'].'>';
		$from = $usr->alias. '<'.$usr->email.'>';
		send_email($from, $to, $_POST['subj'], $_POST['body']);

		error_dialog('{TEMPLATE: remail_emailsent}', '{TEMPLATE: remail_sent_conf}');
	} else if (!isset($_POST['posted'])) {
		if ($TREE_THREADS_ENABLE == 'N' || !strncmp($DEFAULT_THREAD_VIEW, 'msg', 3)) {
			$def_thread_view = 'msg';
		} else {
			$def_thread_view = 'tree';
		}
	}

	$remail_error = is_post_error() ? '{TEMPLATE: remail_error}' : '';
	
	$body = isset($_POST['body']) ? htmlspecialchars($_POST['body']) : '{TEMPLATE: email_message}';
	
	if (_uid) {
		$femail_error = get_err('femail');
		$subject_error = get_err('subj');
		$body_error = get_err('body');

		$fname = isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : '';
		$femail = isset($_POST['femail']) ? htmlspecialchars($_POST['femail']) : '';
		$subject = isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : $data->subject;

		$form_data = '{TEMPLATE: registed_user}';	
	} else {
		$form_data = '{TEMPLATE: anon_user}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: REMAIL_PAGE}