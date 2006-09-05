<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: pmsg_view.php.t,v 1.26 2006/09/05 13:16:49 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	if (!($FUD_OPT_1 & 1024)) {
		error_dialog('{TEMPLATE: pm_err_nopm_title}', '{TEMPLATE: pm_err_nopm_msg}');
	}

	if (__fud_real_user__) {
		is_allowed_user($usr);
	} else {
		std_error('login');
	}

/*{POST_HTML_PHP}*/

	if (!isset($_GET['id']) || !($id = (int)$_GET['id'])) {
		invl_inp_err();
	}

	$m = db_sab('SELECT
		p.*,
		u.id AS user_id, u.alias, u.users_opt, u.avatar_loc, u.email, u.posted_msg_count, u.join_date,
		u.location, u.sig, u.icq, u.aim, u.msnm, u.yahoo, u.jabber, u.affero, u.google, u.skype, u.custom_status, u.last_visit,
		l.name AS level_name, l.level_opt, l.img AS level_img
	FROM
		{SQL_TABLE_PREFIX}pmsg p
		INNER JOIN {SQL_TABLE_PREFIX}users u ON p.ouser_id=u.id
		LEFT JOIN {SQL_TABLE_PREFIX}level l ON u.level_id=l.id
	WHERE p.duser_id='._uid.' AND p.id='.$id);

	if (!$m) {
		invl_inp_err();
	}

	ses_update_status($usr->sid, '{TEMPLATE: pm_update}');

	/* Next Msg */
	if (($nid = q_singleval('SELECT p.id FROM {SQL_TABLE_PREFIX}pmsg p INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=p.ouser_id WHERE p.duser_id='._uid.' AND p.fldr='.$m->fldr.' AND post_stamp>'.$m->post_stamp.' ORDER BY p.post_stamp ASC LIMIT 1'))) {
		$dpmsg_next_message = '{TEMPLATE: dpmsg_next_message}';
	} else {
		$dpmsg_next_message = '';
	}

	/* Prev Msg */
	if (($pid = q_singleval('SELECT p.id FROM {SQL_TABLE_PREFIX}pmsg p INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=p.ouser_id WHERE p.duser_id='._uid.' AND p.fldr='.$m->fldr.' AND p.post_stamp<'.$m->post_stamp.' ORDER BY p.post_stamp DESC LIMIT 1'))) {
		$dpmsg_prev_message = '{TEMPLATE: dpmsg_prev_message}';
	} else {
		$dpmsg_prev_message = '';
	}

	if (!$m->read_stamp && $m->pmsg_opt & 16) {
		q('UPDATE {SQL_TABLE_PREFIX}pmsg SET read_stamp='.__request_timestamp__.', pmsg_opt=(pmsg_opt & ~ 4) |8 WHERE id='.$m->id);
		if ($m->ouser_id != _uid && $m->pmsg_opt & 4 && !isset($_GET['dr'])) {
			$track_msg = new fud_pmsg;
			$track_msg->ouser_id = $track_msg->duser_id = $m->ouser_id;
			$track_msg->ip_addr = $track_msg->host_name = null;
			$track_msg->post_stamp = __request_timestamp__;
			$track_msg->read_stamp = 0;
			$track_msg->fldr = 1;
			$track_msg->pmsg_opt = 16|32;
			$track_msg->subject = '{TEMPLATE: private_msg_notify_subj}';
			$track_msg->body = '{TEMPLATE: private_msg_notify_body}';
			$track_msg->add(1);
		}
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PMSG_PAGE}	