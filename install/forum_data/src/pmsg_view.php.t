<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: pmsg_view.php.t,v 1.10 2003/04/19 14:00:57 hackie Exp $
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

	if ($PM_ENABLED == 'N') {
		error_dialog('{TEMPLATE: pm_err_nopm_title}', '{TEMPLATE: pm_err_nopm_msg}');
	}
	if (!_uid) {
		std_error('login');
	}

/*{POST_HTML_PHP}*/
	
	if (!isset($_GET['id']) || !($id = (int)$_GET['id'])) {
		invl_inp_err();
	}

	$m = db_sab('SELECT 
		p.*,
		u.id AS user_id, u.alias, u.display_email, u.avatar_approved, u.avatar_loc, u.email, u.posted_msg_count, u.join_date, 
		u.location, u.sig, u.icq, u.is_mod, u.aim, u.msnm, u.yahoo, u.jabber, u.affero, u.invisible_mode, u.email_messages,
		u.custom_status, u.last_visit,
		l.name AS level_name, l.pri AS level_pri, l.img AS level_img
	FROM 
		{SQL_TABLE_PREFIX}pmsg p
		INNER JOIN {SQL_TABLE_PREFIX}users u ON p.ouser_id=u.id 
		LEFT JOIN {SQL_TABLE_PREFIX}level l ON u.level_id=l.id	
	WHERE p.duser_id='._uid.' AND p.id='.$id);	
	
	if (!$m) {
		invl_inp_err();
	}

	ses_update_status($usr->sid, '{TEMPLATE: pm_update}');

	$cur_ppage = tmpl_cur_ppage($m->folder_id, $folders, $m->subject);

	/* Next Msg */
	if (($nid = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id='._uid.' AND folder_id=\''.$m->folder_id.'\' AND post_stamp>'.$m->post_stamp.' ORDER BY post_stamp ASC LIMIT 1'))) {
		$dpmsg_next_message = '{TEMPLATE: dpmsg_next_message}';
	} else {
		$dpmsg_next_message = '';
	}
	/* Prev Msg */
	if (($pid = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id='._uid.' AND folder_id=\''.$m->folder_id.'\' AND post_stamp<'.$m->post_stamp.' ORDER BY post_stamp DESC LIMIT 1'))) {
		$dpmsg_prev_message = '{TEMPLATE: dpmsg_prev_message}';
	} else {
		$dpmsg_prev_message = '';
	}

	$private_message_entry = tmpl_drawpmsg($m, $usr, FALSE);

	if (!$m->read_stamp && $m->mailed == 'Y') {
		q('UPDATE {SQL_TABLE_PREFIX}pmsg SET read_stamp='.__request_timestamp__.', track=\'SENT\' WHERE id='.$m->id);
		if ($m->ouser_id != _uid && $m->track == 'Y' && !isset($_GET['dr'])) {
			$track_msg = new fud_pmsg;
			$track_msg->ouser_id = $track_msg->duser_id = $m->ouser_id;
			$track_msg->ip_addr = $track_msg->host_name = NULL;
			$track_msg->post_stamp = __request_timestamp__;
			$track_msg->read_stamp = 0;
			$track_msg->mailed = 'Y';
			$track_msg->folder_id = 'INBOX';
			$track_msg->track = 'N';
			$track_msg->subject = '{TEMPLATE: private_msg_notify_subj}';
			$track_msg->body = '{TEMPLATE: private_msg_notify_body}';
			$track_msg->add(1);
		}
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PMSG_PAGE}	