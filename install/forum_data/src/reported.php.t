<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: reported.php.t,v 1.22 2003/10/09 14:34:26 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

	if (isset($_GET['del']) && ($del = (int)$_GET['del'])) {
		if ($usr->users_opt & 1048576 || q_singleval('SELECT mr.id FROM {SQL_TABLE_PREFIX}msg_report mr INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=mr.msg_id INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.id=m.thread_id INNER JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=t.forum_id AND mm.user_id='._uid.' WHERE mr.id='.$del)) {
			q('DELETE FROM {SQL_TABLE_PREFIX}msg_report WHERE id='.$del);
			if (db_affected()) {
				logaction(_uid, 'DELREPORT');
			}
		} else {
			std_error('access');
		}
	}

/*{POST_HTML_PHP}*/

	$r = $query_type('SELECT
			m.*,
			t.thread_opt, t.root_msg_id, t.last_post_id, t.forum_id,
			f.message_threshold, f.name AS frm_name,
			u.id AS user_id, u.alias AS login, u.avatar_loc, u.email, u.posted_msg_count, u.join_date, u.location,
			u.sig, u.custom_status, u.icq, u.jabber, u.affero, u.aim, u.msnm, u.yahoo, u.users_opt, u.last_visit AS time_sec,
			l.name AS level_name, l.level_opt, l.img AS level_img,
			p.max_votes, p.expiry_date, p.creation_date, p.name AS poll_name, p.total_votes,
			mr.id AS report_id, mr.stamp AS report_stamp, mr.reason AS report_reason,
			u2.id AS report_user_id, u2.alias AS report_user_login, u2.last_visit AS time_sec_r,
			m2.subject AS thread_subject,
			pot.id AS cant_vote
		FROM {SQL_TABLE_PREFIX}msg_report mr
			INNER JOIN {SQL_TABLE_PREFIX}msg m ON mr.msg_id=m.id
			INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
			INNER JOIN {SQL_TABLE_PREFIX}msg m2 ON m2.id=t.root_msg_id
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
			'.($usr->users_opt & 1048576 ? '' : ' INNER JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=t.forum_id AND mm.user_id='._uid).'
			LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
			LEFT JOIN {SQL_TABLE_PREFIX}users u2 ON mr.user_id=u2.id
			LEFT JOIN {SQL_TABLE_PREFIX}level l ON u.level_id=l.id
			LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
			LEFT JOIN {SQL_TABLE_PREFIX}poll_opt_track pot ON pot.poll_id=p.id AND pot.user_id='._uid.'
		ORDER BY mr.id');

	$perms = perms_from_obj($r, ($usr->users_opt & 1048576));
	$MOD = 1;
	$reported_message = '';
	$n = 0;
	$_GET['start'] = 0;

	$usr->md = 1;
	while ($obj = db_rowobj($r)) {
		$user_login = $obj->report_user_id ? '{TEMPLATE: reported_reg_user_link}' : '{TEMPLATE: reported_anon_user}';
		if (empty($prev_thread_id) || $prev_thread_id != $obj->thread_id) {
			$prev_thread_id = $obj->thread_id;

		}
		$message = tmpl_drawmsg($obj, $usr, $perms, false, $n, null);

		$reported_message .= '{TEMPLATE: reported_message}';
	}
	qf($r);
	un_register_fps();

	if (!$reported_message) {
		$reported_message = '{TEMPLATE: reported_no_messages}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: REPORTED_PAGE}