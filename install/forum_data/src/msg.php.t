<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: msg.php.t,v 1.59 2003/10/30 21:38:45 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

	$count = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;

	if (isset($_GET['th'])) {
		$th = $_GET['th'] = (int) $_GET['th'];
	}
	if (isset($_GET['goto']) && $_GET['goto'] !== 'end') {
		$_GET['goto'] = (int) $_GET['goto'];
	}

	/* quick cheat to avoid a redirect
	 * When we need to determine the 1st unread message, we do it 1st, so that we can re-use the goto handling logic
	 */
	if (isset($_GET['unread'], $_GET['th']) && _uid) {
		$_GET['goto'] = q_singleval('SELECT m.id from {SQL_TABLE_PREFIX}msg m LEFT JOIN {SQL_TABLE_PREFIX}read r ON r.thread_id=m.thread_id AND r.user_id='._uid.' WHERE m.thread_id='.$_GET['th'].' AND m.apr=1 AND m.post_stamp>CASE WHEN (r.last_view IS NOT NULL OR r.last_view>'.$usr->last_read.') THEN r.last_view ELSE '.$usr->last_read.' END');
		if (!$_GET['goto']) {
			$_GET['goto'] = 'end';
		}
	}

	if (isset($_GET['goto'])) {
		if ($_GET['goto'] === 'end' && isset($_GET['th'])) {
			list($pos, $mid) = db_saq('SELECT replies+1,last_post_id FROM {SQL_TABLE_PREFIX}thread WHERE id='.$_GET['th']);
			$mid = '#msg_'.$mid;
		} else if ($_GET['goto']) { /* verify that the thread & msg id are valid */
			if (!isset($_GET['th'])) {
				$_GET['th'] = (int) q_singleval('SELECT thread_id FROM {SQL_TABLE_PREFIX}msg WHERE id='.$_GET['goto']);
			}
			if (!($pos = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$_GET['th']." AND id<=".$_GET['goto']." AND apr=1"))) {
				invl_inp_err();
			}
			$mid = 'msg_'.$_GET['goto'];
		} else {
			invl_inp_err();
		}
		$msg_page_focus = '{TEMPLATE: msg_page_focus}';

		$_GET['start'] = (ceil($pos/$count) - 1) * $count;
	} else if (!isset($_GET['th'])) {
		invl_inp_err();
	} else {
		$msg_page_focus = '';
	}

	/* we create a BIG object frm, which contains data about forum,
	 * category, current thread, subscriptions, permissions, moderation status,
	 * rating possibilites and if we will need to update last_view field for registered user
	 */
	make_perms_query($fields, $join);

	$frm = db_sab('SELECT
			c.name AS cat_name,
			f.name AS frm_name,
			m.subject,
			t.id, t.forum_id, t.replies, t.rating, t.n_rating, t.root_msg_id, t.moved_to, t.thread_opt,
			tn.thread_id AS subscribed,
			mo.forum_id AS md,
			tr.thread_id AS cant_rate,
			r.last_view,
			r2.last_view AS last_forum_view,
			r.msg_id,
			tv.pos AS th_pos, tv.page AS th_page,
			m2.thread_id AS last_thread,
			'.$fields.'
		FROM {SQL_TABLE_PREFIX}thread t
			INNER JOIN {SQL_TABLE_PREFIX}msg		m ON m.id=t.root_msg_id
			INNER JOIN {SQL_TABLE_PREFIX}forum		f ON f.id=t.forum_id
			INNER JOIN {SQL_TABLE_PREFIX}cat		c ON f.cat_id=c.id
			INNER JOIN {SQL_TABLE_PREFIX}thread_view	tv ON tv.forum_id=t.forum_id AND tv.thread_id=t.id
			INNER JOIN {SQL_TABLE_PREFIX}msg 		m2 ON f.last_post_id=m2.id
			LEFT  JOIN {SQL_TABLE_PREFIX}thread_notify 	tn ON tn.user_id='._uid.' AND tn.thread_id='.$_GET['th'].'
			LEFT  JOIN {SQL_TABLE_PREFIX}mod 		mo ON mo.user_id='._uid.' AND mo.forum_id=t.forum_id
			LEFT  JOIN {SQL_TABLE_PREFIX}thread_rate_track 	tr ON tr.thread_id='.$_GET['th'].' AND tr.user_id='._uid.'
			LEFT  JOIN {SQL_TABLE_PREFIX}read 		r ON r.thread_id=t.id AND r.user_id='._uid.'
			LEFT  JOIN {SQL_TABLE_PREFIX}forum_read 	r2 ON r2.forum_id=t.forum_id AND r2.user_id='._uid.'
			'.$join.'
		WHERE t.id='.$_GET['th']);

	if (!$frm) { /* bad thread, terminate request */
		invl_inp_err();
	}
	if ($frm->moved_to) { /* moved thread, we could handle it, but this case is rather rare, so it's cleaner to redirect */
		if ($FUD_OPT_2 & 32768) {
			header('Location: {ROOT}/m/'.$frm->root_msg_id.'/'._rsidl);
		} else {
			header('Location: {ROOT}?t=msg&goto='.$frm->root_msg_id.'&'._rsidl);
		}
		exit();
	}

	$MOD = $sub_status = 0;
	$perms = perms_from_obj($frm, ($usr->users_opt & 1048576));

	if (!($perms & 2)) {
		if (!isset($_GET['logoff'])) {
			std_error('perms');
		} else {
			if ($FUD_OPT_2 & 32768) {
				header('Location: {ROOT}/i/' . _rsidl);
			} else {
				header('Location: {ROOT}?' . _rsidl);
			}
			exit;
		}
	}

	$msg_forum_path = '{TEMPLATE: msg_forum_path}';

	$_GET['start'] = isset($_GET['start']) ? (int)$_GET['start'] : 0;
	if ($_GET['start'] < 0) {
		$_GET['start'] = 0;
	}

	$total = $frm->replies + 1;

	if (_uid) {
		/* Deal with thread subscriptions */
		if (isset($_GET['notify'], $_GET['opt'])) {
			if ($_GET['opt'] == 'on') {
				thread_notify_add(_uid, $_GET['th']);
				$frm->subscribed = 1;
			} else {
				thread_notify_del(_uid, $_GET['th']);
				$frm->subscribed = 0;
			}
		}

		if (($total - $_GET['th']) > $count) {
			$first_unread_message_link = '{TEMPLATE: first_unread_message_link}';
		} else {
			$first_unread_message_link = '';
		}
		$subscribe_status = $frm->subscribed ? '{TEMPLATE: unsub_to_thread}' : '{TEMPLATE: sub_from_thread}';
	} else {
		$first_unread_message_link = $subscribe_status = '';
	}

	ses_update_status($usr->sid, '{TEMPLATE: msg_update}', $frm->forum_id);

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: msg_title}';

	if ($FUD_OPT_2 & 4096) {
		$thread_rating = $frm->rating ? '{TEMPLATE: thread_rating}' : '{TEMPLATE: no_thread_rating}';
		if ($perms & 1024 && !$frm->cant_rate) {
			$rate_thread = '{TEMPLATE: rate_thread}';
		} else {
			$rate_thread = '';
		}
	} else {
		$rate_thread = $thread_rating = '';
	}

	$post_reply = (!($frm->thread_opt & 1) || $perms & 4096) ? '{TEMPLATE: post_reply}' : '';
	$threaded_view = $FUD_OPT_3 & 2 ? '' : '{TEMPLATE: threaded_view}';
	$email_page_to_friend = $FUD_OPT_2 & 1073741824 ? '{TEMPLATE: email_page_to_friend}' : '';

	if ($perms & 4096) {
		$lock_thread = !($frm->thread_opt & 1) ? '{TEMPLATE: mod_lock_thread}' : '{TEMPLATE: mod_unlock_thread}';
	} else {
		$lock_thread = '';
	}

	$split_thread = ($frm->replies && $perms & 2048) ? '{TEMPLATE: split_thread}' : '';

	$result = $query_type('SELECT
		m.*,
		t.thread_opt, t.root_msg_id, t.last_post_id, t.forum_id,
		f.message_threshold,
		u.id AS user_id, u.alias AS login, u.avatar_loc, u.email, u.posted_msg_count, u.join_date, u.location,
		u.sig, u.custom_status, u.icq, u.jabber, u.affero, u.aim, u.msnm, u.yahoo, u.users_opt, u.last_visit AS time_sec,
		l.name AS level_name, l.level_opt, l.img AS level_img,
		p.max_votes, p.expiry_date, p.creation_date, p.name AS poll_name, p.total_votes,
		pot.id AS cant_vote
	FROM
		{SQL_TABLE_PREFIX}msg m
		INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
		INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
		LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
		LEFT JOIN {SQL_TABLE_PREFIX}level l ON u.level_id=l.id
		LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
		LEFT JOIN {SQL_TABLE_PREFIX}poll_opt_track pot ON pot.poll_id=p.id AND pot.user_id='._uid.'
	WHERE
		m.thread_id='.$_GET['th'].' AND m.apr=1
	ORDER BY m.id ASC LIMIT ' . qry_limit($count, $_GET['start']));

	$obj2 = $message_data = '';

	$usr->md = $frm->md;

	$m_num = 0;
	while ($obj = db_rowobj($result)) {
		$message_data .= tmpl_drawmsg($obj, $usr, $perms, false, $m_num, array($_GET['start'], $count));
		$obj2 = $obj;
	}
	unset($result);

	un_register_fps();

	if (!isset($_GET['prevloaded'])) {
		th_inc_view_count($frm->id);
		if (_uid && $obj2) {
			if ($frm->last_forum_view < $obj2->post_stamp) {
				user_register_forum_view($frm->forum_id);
			}
			if ($frm->last_view < $obj2->post_stamp) {
				user_register_thread_view($frm->id, $obj2->post_stamp, $obj2->id);
			}
		}
	}

	if ($FUD_OPT_2 & 32768) {
		$page_pager = tmpl_create_pager($_GET['start'], $count, $total, '{ROOT}/mv/msg/' . $_GET['th'] . '/0/', reveal_lnk . unignore_tmp . _rsid);
	} else {
		$page_pager = tmpl_create_pager($_GET['start'], $count, $total, '{ROOT}?t=msg&amp;th=' . $_GET['th'] . '&amp;prevloaded=1&amp;' . _rsid . reveal_lnk . unignore_tmp);
	}

	get_prev_next_th_id($frm, $prev_thread_link, $next_thread_link);

	$pdf_link = $FUD_OPT_2 & 2097152 ? '{TEMPLATE: msg_pdf_link}' : '';
	$xml_link = $FUD_OPT_2 & 1048576 ? '{TEMPLATE: msg_xml_link}' : '';

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MSG_PAGE}
