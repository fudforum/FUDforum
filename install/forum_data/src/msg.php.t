<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: msg.php.t,v 1.98 2006/07/24 17:24:36 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

/*{PRE_HTML_PHP}*/

	$count = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;
	$th = isset($_GET['th']) ? (int) $_GET['th'] : 0;

	if (isset($_GET['goto']) && $_GET['goto'] !== 'end') {
		$_GET['goto'] = (int) $_GET['goto'];
	}

	/* quick cheat to avoid a redirect
	 * When we need to determine the 1st unread message, we do it 1st, so that we can re-use the goto handling logic
	 */
	$msg_page_focus = 0;
	if (isset($_GET['unread']) && $th && _uid) {
		$_GET['goto'] = q_singleval('SELECT m.id from {SQL_TABLE_PREFIX}msg m LEFT JOIN {SQL_TABLE_PREFIX}read r ON r.thread_id=m.thread_id AND r.user_id='._uid.' WHERE m.thread_id='.$th.' AND m.apr=1 AND m.post_stamp>CASE WHEN (r.last_view IS NOT NULL OR r.last_view>'.$usr->last_read.') THEN r.last_view ELSE '.$usr->last_read.' END');
		if (!$_GET['goto']) {
			$_GET['goto'] = q_singleval('SELECT root_msg_id FROM {SQL_TABLE_PREFIX}thread WHERE id='.$th);
			$msg_page_focus = null;
		} else {
			$msg_page_focus = 1;
		}
	}

	if (!empty($_GET['goto'])) {
		if ($_GET['goto'] === 'end' && $th) {
			list($pos, $mid) = db_saq('SELECT replies+1,last_post_id FROM {SQL_TABLE_PREFIX}thread WHERE id='.$th);
			$mid = '#msg_'.$mid;
			$msg_page_focus = 1;
		} else if ($_GET['goto']) { /* verify that the thread & msg id are valid */
			if (!$th) {
				$th = (int) q_singleval('SELECT thread_id FROM {SQL_TABLE_PREFIX}msg WHERE id='.$_GET['goto']);
			}
			if (!($pos = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$th.' AND id<='.$_GET['goto'].' AND apr=1'))) {
				invl_inp_err();
			}
			if ($msg_page_focus !== null) {
				if ($msg_page_focus || (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)) {
					$mid = 'msg_'.$_GET['goto'];
					$msg_page_focus = 1;
				}
			}
		} else {
			invl_inp_err();
		}
		$_GET['start'] = (ceil($pos/$count) - 1) * $count;
	} else if (!$th) {
		invl_inp_err();
	}

	/* we create a BIG object frm, which contains data about forum,
	 * category, current thread, subscriptions, permissions, moderation status,
	 * rating possibilites and if we will need to update last_view field for registered user
	 */
	make_perms_query($fields, $join);

	$frm = db_sab('SELECT
			c.id AS cat_id,
			f.name,
			m.subject,
			t.id, t.forum_id, t.replies, t.rating, t.n_rating, t.root_msg_id, t.moved_to, t.thread_opt, t.last_post_date, '.
			(_uid ? ' tn.thread_id AS subscribed, mo.forum_id AS md, tr.thread_id AS cant_rate, r.last_view, r2.last_view AS last_forum_view, ' : ' 0 AS md, 1 AS cant_rate, ').'
			m2.thread_id AS last_thread,
			'.$fields.'
		FROM {SQL_TABLE_PREFIX}thread t
			INNER JOIN {SQL_TABLE_PREFIX}msg		m ON m.id=t.root_msg_id
			INNER JOIN {SQL_TABLE_PREFIX}forum		f ON f.id=t.forum_id
			INNER JOIN {SQL_TABLE_PREFIX}cat		c ON f.cat_id=c.id
			INNER JOIN {SQL_TABLE_PREFIX}msg 		m2 ON f.last_post_id=m2.id
			'.(_uid ? 'LEFT  JOIN {SQL_TABLE_PREFIX}thread_notify 	tn ON tn.user_id='._uid.' AND tn.thread_id='.$th.'
			LEFT  JOIN {SQL_TABLE_PREFIX}mod 		mo ON mo.user_id='._uid.' AND mo.forum_id=t.forum_id
			LEFT  JOIN {SQL_TABLE_PREFIX}thread_rate_track 	tr ON tr.thread_id='.$th.' AND tr.user_id='._uid.'
			LEFT  JOIN {SQL_TABLE_PREFIX}read 		r ON r.thread_id=t.id AND r.user_id='._uid.'
			LEFT  JOIN {SQL_TABLE_PREFIX}forum_read 	r2 ON r2.forum_id=t.forum_id AND r2.user_id='._uid : '')
			.$join.'
		WHERE t.id='.$th);

	if (!$frm) { /* bad thread, terminate request */
		invl_inp_err();
	}
	if ($frm->moved_to) { /* moved thread, we could handle it, but this case is rather rare, so it's cleaner to redirect */
		if ($FUD_OPT_2 & 32768) {
			header('Location: {FULL_ROOT}{ROOT}/m/'.$frm->root_msg_id.'/'._rsidl.'#msg_'.$frm->root_msg_id);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?t=msg&goto='.$frm->root_msg_id.'&'._rsidl.'#msg_'.$frm->root_msg_id);
		}
		exit;
	}

	$MOD = ($is_a || $frm->md);
	$perms = perms_from_obj($frm, $MOD);

	if (!($perms & 2)) {
		if (!isset($_GET['logoff'])) {
			std_error('login');
		}
		if ($FUD_OPT_2 & 32768) {
			header('Location: {FULL_ROOT}{ROOT}/i/' . _rsidl);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?' . _rsidl);
		}
		exit;
	}

	$_GET['start'] = (isset($_GET['start']) && $_GET['start'] > 0) ? (int)$_GET['start'] : 0;
	$total = $frm->replies + 1;

	if (_uid) {
		/* Deal with thread subscriptions */
		if (isset($_GET['notify'], $_GET['opt']) && sq_check(0, $usr->sq)) {
			if (($frm->subscribed = ($_GET['opt'] == 'on'))) {
				thread_notify_add(_uid, $th);
			} else {
				thread_notify_del(_uid, $th);
			}
		}

		$first_unread_message_link = (($total - $th) > $count) ? '{TEMPLATE: first_unread_message_link}' : '';
		$subscribe_status = $frm->subscribed ? '{TEMPLATE: unsub_to_thread}' : '{TEMPLATE: sub_from_thread}';
	} else {
		if (__fud_cache($frm->last_post_date)) {
			return;
		}
		$first_unread_message_link = $subscribe_status = '';
	}

	ses_update_status($usr->sid, '{TEMPLATE: msg_update}', $frm->forum_id);

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: msg_title}';

	$use_tmp = $FUD_OPT_3 & 4096 && $frm->replies > 250;

	/* This is an optimization intended for topics with many messages */
	if ($use_tmp) {
		q('CREATE TEMPORARY TABLE {SQL_TABLE_PREFIX}_mtmp_'.__request_timestamp__.' AS SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$th.' AND apr=1 ORDER BY id ASC LIMIT ' . qry_limit($count, $_GET['start']));
	}

	$result = $query_type('SELECT
		m.*,
		t.thread_opt, t.root_msg_id, t.last_post_id, t.forum_id,
		f.message_threshold,
		u.id AS user_id, u.alias AS login, u.avatar_loc, u.email, u.posted_msg_count, u.join_date, u.location,
		u.sig, u.custom_status, u.icq, u.jabber, u.affero, u.aim, u.msnm, u.yahoo, u.users_opt, u.last_visit AS time_sec,
		l.name AS level_name, l.level_opt, l.img AS level_img,
		p.max_votes, p.expiry_date, p.creation_date, p.name AS poll_name, p.total_votes,
		'.(_uid ? ' pot.id AS cant_vote ' : ' 1 AS cant_vote ').'
	FROM '.($use_tmp ? '{SQL_TABLE_PREFIX}_mtmp_'.__request_timestamp__.' mt INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=mt.id' : ' {SQL_TABLE_PREFIX}msg m').'
		INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
		INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
		LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
		LEFT JOIN {SQL_TABLE_PREFIX}level l ON u.level_id=l.id
		LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id'.
		(_uid ? ' LEFT JOIN {SQL_TABLE_PREFIX}poll_opt_track pot ON pot.poll_id=p.id AND pot.user_id='._uid : ' ').
		($use_tmp ? ' ORDER BY m.id ASC' : ' WHERE m.thread_id='.$th.' AND m.apr=1 ORDER BY m.id ASC LIMIT ' . qry_limit($count, $_GET['start'])));

	$obj2 = $message_data = '';

	$usr->md = $frm->md;

	$m_num = 0;
	while ($obj = db_rowobj($result)) {
		$message_data .= tmpl_drawmsg($obj, $usr, $perms, false, $m_num, array($_GET['start'], $count));
		$obj2 = $obj;
	}
	unset($result);

	if ($FUD_OPT_2 & 32768) {
		$page_pager = tmpl_create_pager($_GET['start'], $count, $total, '{ROOT}/mv/msg/' . $th . '/0/', '/' . reveal_lnk . unignore_tmp . _rsid);
	} else {
		$page_pager = tmpl_create_pager($_GET['start'], $count, $total, '{ROOT}?t=msg&amp;th=' . $th . '&amp;prevloaded=1&amp;' . _rsid . reveal_lnk . unignore_tmp);
	}

	get_prev_next_th_id($frm->forum_id, $th, $prev_thread_link, $next_thread_link);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MSG_PAGE}
<?php
	if (!isset($_GET['prevloaded'])) {
		while (@ob_end_flush());
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
?>