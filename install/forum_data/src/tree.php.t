<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: tree.php.t,v 1.91 2006/09/19 14:37:56 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	if ($FUD_OPT_3 & 2) {
		std_error('disabled');
	}

	if (!isset($_GET['th']) || !($th = (int)$_GET['th'])) {
		$th = 0;
	}
	if (!isset($_GET['mid']) || !($mid = (int)$_GET['mid'])) {
		$mid = 0;
	}

	if (isset($_GET['goto'])) {
		if (($mid = (int)$_GET['goto']) && !$th) {
			$th = q_singleval('SELECT thread_id FROM {SQL_TABLE_PREFIX}msg WHERE id='.$mid);
		} else if ($_GET['goto'] == 'end' && $th) {
			$mid = q_singleval('SELECT last_post_id FROM {SQL_TABLE_PREFIX}thread WHERE id='.$th);
		} else if ($th) {
			$mid = (int)$_GET['goto'];
		} else {
			invl_inp_err();
		}
	}
	if (!$th) {
		invl_inp_err();
	}
	if (!$mid && isset($_GET['unread']) && _uid) {
		$mid = q_singleval('SELECT m.id FROM {SQL_TABLE_PREFIX}msg m LEFT JOIN {SQL_TABLE_PREFIX}read r ON r.thread_id=m.thread_id AND r.user_id='._uid.' WHERE m.thread_id='.$th.' AND m.apr=1 AND m.post_stamp > r.last_view AND m.post_stamp > '.$usr->last_read.' ORDER BY m.post_stamp ASC LIMIT 1');
		if (!$mid) {
			$mid = q_singleval('SELECT root_msg_id FROM {SQL_TABLE_PREFIX}thread WHERE id='.$th);
		}
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
			t.id, t.forum_id, t.replies, t.rating, t.n_rating, t.root_msg_id, t.moved_to, t.thread_opt, t.root_msg_id, t.last_post_date, '.
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
			header('Location: {FULL_ROOT}{ROOT}/mv/tree/'.$frm->root_msg_id.'/'._rsidl.'#msg_'.$frm->root_msg_id);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?t=tree&goto='.$frm->root_msg_id.'&'._rsidl.'#msg_'.$frm->root_msg_id);
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
			header('Location: {FULL_ROOT}{ROOT}?t=index&' . _rsidl);
		}
		exit;
	}

	if (_uid) {
		/* Deal with thread subscriptions */
		if (isset($_GET['notify'], $_GET['opt']) && sq_check(0, $usr->sq)) {
			if (($frm->subscribed = ($_GET['opt'] == 'on'))) {
				thread_notify_add(_uid, $th);
			} else {
				thread_notify_del(_uid, $th);
			}
		}
		$subscribe_status = $frm->subscribed ? '{TEMPLATE: unsub_to_thread}' : '{TEMPLATE: sub_from_thread}';
	} else {
		if (__fud_cache($frm->last_post_date)) {
			return;
		}
		$subscribe_status = '';
	}

	if (!$mid) {
		$mid = $frm->root_msg_id;
	}

	$msg_obj = db_sab('SELECT
		m.*, COALESCE(m.flag_cc, u.flag_cc) AS flag_cc, COALESCE(m.flag_country, u.flag_country) AS flag_country,
		t.thread_opt, t.root_msg_id, t.last_post_id, t.forum_id,
		f.message_threshold,
		u.id AS user_id, u.alias AS login, u.avatar_loc, u.email, u.posted_msg_count, u.join_date, u.location,
		u.sig, u.custom_status, u.icq, u.jabber, u.affero, u.aim, u.msnm, u.yahoo, u.skype, u.google, u.last_visit AS time_sec, u.users_opt,
		l.name AS level_name, l.level_opt, l.img AS level_img,
		p.max_votes, p.expiry_date, p.creation_date, p.name AS poll_name, p.total_votes,
		'.(_uid ? ' pot.id AS cant_vote ' : ' 1 AS cant_vote ').'
	FROM
		{SQL_TABLE_PREFIX}msg m
		INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
		INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
		LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
		LEFT JOIN {SQL_TABLE_PREFIX}level l ON u.level_id=l.id
		LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id'.
		(_uid ? ' LEFT JOIN {SQL_TABLE_PREFIX}poll_opt_track pot ON pot.poll_id=p.id AND pot.user_id='._uid : ' ').'
	WHERE
		m.id='.$mid.' AND m.apr=1 AND m.thread_id='.$th);

	if (!$msg_obj) { // invalid message id
		invl_inp_err();
	}

	if (!isset($_GET['prevloaded'])) {
		th_inc_view_count($th);
		if (_uid) {
			if ($frm->last_view < $msg_obj->post_stamp) {
				user_register_thread_view($th, $msg_obj->post_stamp, $mid);
			}
			if ($frm->last_forum_view < $msg_obj->post_stamp) {
				user_register_forum_view($frm->forum_id);
			}
		}
	}
	ses_update_status($usr->sid, '{TEMPLATE: tree_update}', $frm->id);

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: tree_title}';

	if ($perms & 4096) {
		$lock_thread = !($frm->thread_opt & 1) ? '{TEMPLATE: mod_lock_thread}' : '{TEMPLATE: mod_unlock_thread}';
	} else {
		$lock_thread = '';
	}

	$tree = $stack = $arr = null;
	$c = uq('SELECT m.poster_id, m.subject, m.reply_to, m.id, m.poll_id, m.attach_cnt, m.post_stamp, u.alias, u.last_visit FROM {SQL_TABLE_PREFIX}msg m INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id WHERE m.thread_id='.$th.' AND m.apr=1 ORDER BY m.id');
	error_reporting(0);
	while ($r = db_rowobj($c)) {
		$arr[$r->id] = $r;
		$arr[$r->reply_to]->kiddie_count++;
		$arr[$r->reply_to]->kiddies[] = &$arr[$r->id];

		if ($r->reply_to == 0) {
			$tree->kiddie_count++;
			$tree->kiddies[] = &$arr[$r->id];
		}
	}
	unset($c);
	error_reporting(2047);

	$prev_msg = $next_msg = 0;
	$rev = isset($_GET['rev']) ? $_GET['rev'] : '';
	$reveal = isset($_GET['reveal']) ? $_GET['reveal'] : '';
	$tree_data = '';

	if($arr) {
		if (isset($tree->kiddies)) {
			reset($tree->kiddies);
		}
		$stack[0] = &$tree;
		$stack_cnt = isset($tree->kiddie_count) ? $tree->kiddie_count : 0;
		$j = $lev = $prev_id = 0;

		while ($stack_cnt > 0) {
			$cur = &$stack[$stack_cnt-1];

			if (isset($cur->subject) && empty($cur->sub_shown)) {
				$tree_data .= '{TEMPLATE: tree_branch}';

				$cur->sub_shown = 1;

				if ($cur->id == $mid) {
					$prev_msg = $prev_id;
				}
				if ($prev_id == $mid) {
					$next_msg = $cur->id;
				}

				$prev_id = $cur->id;
			}

			if (!isset($cur->kiddie_count)) {
				$cur->kiddie_count = 0;
			}

			if ($cur->kiddie_count && isset($cur->kiddie_pos)) {
				++$cur->kiddie_pos;
			} else {
				$cur->kiddie_pos = 0;
			}

			if ($cur->kiddie_pos < $cur->kiddie_count) {
				++$lev;
				$stack[$stack_cnt++] = &$cur->kiddies[$cur->kiddie_pos];
			} else { // unwind the stack if needed
				unset($stack[--$stack_cnt]);
				--$lev;
			}

			unset($cur);
		}
	}
	$n = 0; $_GET['start'] = '';
	$usr->md = $frm->md;

	get_prev_next_th_id($frm->forum_id, $th, $prev_thread_link, $next_thread_link);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: TREE_PAGE}