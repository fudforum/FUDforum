<?php
/**
* copyright            : (C) 2001-2021 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	$count = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;
	$th = isset($_GET['th']) ? (int) $_GET['th'] : 0;
	$RSS = '{TEMPLATE: msg_RSS}';

	if (isset($_GET['goto']) && $_GET['goto'] !== 'end') {
		$_GET['goto'] = (int) $_GET['goto'];
	}

	/* Quick cheat to avoid a redirect.
	 * When we need to determine the 1st unread message, we do it 1st, so that we can re-use the goto handling logic.
	 */
	$msg_page_focus = 0;
	if (isset($_GET['unread']) && $th && _uid) {
		$_GET['goto'] = q_singleval('SELECT m.id from {SQL_TABLE_PREFIX}msg m LEFT JOIN {SQL_TABLE_PREFIX}read r ON r.thread_id=m.thread_id AND r.user_id='. _uid .' WHERE m.thread_id='. $th .' AND m.apr=1 AND m.post_stamp>CASE WHEN (r.last_view IS NOT NULL OR r.last_view>'. $usr->last_read .') THEN r.last_view ELSE '. $usr->last_read .' END');
		if (!$_GET['goto']) {
			$_GET['goto'] = q_singleval('SELECT root_msg_id FROM {SQL_TABLE_PREFIX}thread WHERE id='. $th);
			$msg_page_focus = null;
		} else {
			$msg_page_focus = 1;
		}
	}

	if (!empty($_GET['goto'])) {
		if ($_GET['goto'] === 'end' && $th) {
			list($pos, $mid) = db_saq('SELECT /* USE MASTER */ replies+1,last_post_id FROM {SQL_TABLE_PREFIX}thread WHERE id='. $th);
			$mid = '#msg_'. $mid;
			$msg_page_focus = 1;
		} else if ($_GET['goto']) { /* Verify that the thread & msg id are valid. */
			if (!$th) {
				$th = (int) q_singleval('SELECT /* USE MASTER */ thread_id FROM {SQL_TABLE_PREFIX}msg WHERE id='. $_GET['goto']);
			}
			if (!($pos = q_singleval('SELECT /* USE MASTER */ count(*) FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='. $th .' AND id<='. $_GET['goto'] .' AND apr=1'))) {
				invl_inp_err();
			}
			if ($msg_page_focus !== null) {
				if ($msg_page_focus || (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)) {
					$mid = 'msg_'. $_GET['goto'];
					$msg_page_focus = 1;
				}
			}
		} else {
			invl_inp_err();
		}
		$_GET['start'] = (ceil($pos/$count) - 1) * $count;
	} else if (!$th) {
                // Try to lookup ThreadID from URL, similar to what Wikipedia does.
                // This can produce insonsitent results if you have more than one topic with the same subject.
		// Narro down a forum with forum:subject.
                // Example URL: http://your.forum.com/t/subject
                list($subj, $frm) = array_reverse(explode(':', $_GET['th'], 2));
                $count = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}msg m
                                        LEFT JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id = t.id
                                        LEFT JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id = f.id
                                        WHERE m.subject='. _esc($subj) .' AND f.name like '. _esc($frm.'%'). ' AND m.reply_to=0');
		if (!$count) {
			// Normal search
			if ($GLOBALS['FUD_OPT_2'] & 32768) {
                        	header('Location: {ROOT}/s/'. $subj . _rsidl);
	                } else {
	                        header('Location: {ROOT}?t=search&srch='. $subj . _rsidl);
                	}
			exit;
		} elseif ($count > 1) {
			// Title search
			if ($GLOBALS['FUD_OPT_2'] & 32768) {
                        	header('Location: {ROOT}/s/'. $subj .'/subject/'. _rsidl);
	                } else {
	                        header('Location: {ROOT}?t=search&srch='. $subj .'&field=subject'. _rsidl);
                	}
			exit;
		} else {
			// Load topic
	                $th = q_singleval(q_limit('SELECT thread_id FROM {SQL_TABLE_PREFIX}msg m
                                        LEFT JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id = t.id
                                        LEFT JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id = f.id
                                        WHERE m.subject='. _esc($subj) .' AND f.name like '. _esc($frm.'%'). ' AND m.reply_to=0', 1));
                }
	}

	/* We create a BIG object frm, which contains data about forum,
	 * category, current thread, subscriptions, permissions, moderation status,
	 * rating possibilites and if we will need to update last_view field for registered user.
	 */
	make_perms_query($fields, $join);

	$frm = db_sab('SELECT
			c.id AS cat_id,
			f.name,
			m.subject,
			t.tdescr, t.id, t.forum_id, t.replies, t.rating, t.n_rating, t.root_msg_id, t.moved_to, t.thread_opt, t.last_post_date, '.
			(_uid ? ' tn.thread_id AS subscribed, tb.thread_id AS bookmarked, mo.forum_id AS md, tr.thread_id AS cant_rate, r.last_view, r2.last_view AS last_forum_view, ' : ' 0 AS md, 1 AS cant_rate, ').'
			m2.thread_id AS last_thread,
			'. $fields .'
		FROM {SQL_TABLE_PREFIX}thread t
			INNER JOIN {SQL_TABLE_PREFIX}msg		m ON m.id=t.root_msg_id
			INNER JOIN {SQL_TABLE_PREFIX}forum		f ON f.id=t.forum_id
			INNER JOIN {SQL_TABLE_PREFIX}cat		c ON f.cat_id=c.id
			INNER JOIN {SQL_TABLE_PREFIX}msg 		m2 ON f.last_post_id=m2.id
			'. (_uid ? 'LEFT  JOIN {SQL_TABLE_PREFIX}thread_notify 	tn ON tn.user_id='. _uid .' AND tn.thread_id='. $th .'
			LEFT  JOIN {SQL_TABLE_PREFIX}bookmarks		tb ON tb.user_id='. _uid .' AND tb.thread_id='. $th .' 
			LEFT  JOIN {SQL_TABLE_PREFIX}mod 		mo ON mo.user_id='. _uid .' AND mo.forum_id=t.forum_id
			LEFT  JOIN {SQL_TABLE_PREFIX}thread_rate_track 	tr ON tr.thread_id='. $th .' AND tr.user_id='. _uid .'
			LEFT  JOIN {SQL_TABLE_PREFIX}read 		r ON r.thread_id=t.id AND r.user_id='. _uid .'
			LEFT  JOIN {SQL_TABLE_PREFIX}forum_read 	r2 ON r2.forum_id=t.forum_id AND r2.user_id='. _uid : '')
			. $join .'
		WHERE t.id='. $th);

	if (!$frm) { /* Bad thread, terminate request. */
		invl_inp_err();
	}
	if ($frm->moved_to) { /* Moved thread, we could handle it, but this case is rather rare, so it's cleaner to redirect. */
		if ($FUD_OPT_2 & 32768) {
			header('Location: {ROOT}/m/'. $frm->root_msg_id .'/'. _rsidl .'#msg_'. $frm->root_msg_id);
		} else {
			header('Location: {ROOT}?t=msg&goto='. $frm->root_msg_id .'&'. _rsidl .'#msg_'. $frm->root_msg_id);
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
			header('Location: {ROOT}/i/'. _rsidl);
		} else {
			header('Location: {ROOT}?'. _rsidl);
		}
		exit;
	}

	$_GET['start'] = (isset($_GET['start']) && $_GET['start'] > 0) ? (int)$_GET['start'] : 0;
	$total = $frm->replies + 1;

	if (_uid) {
		/* Deal with thread subscriptions. */
		if (isset($_GET['notify'], $_GET['opt']) && sq_check(0, $usr->sq)) {
			if (($frm->subscribed = ($_GET['opt'] == 'on'))) {
				thread_notify_add(_uid, $th);
			} else {
				thread_notify_del(_uid, $th);
			}
		}

		/* Deal with bookmarks. */
		if (isset($_GET['bookmark'], $_GET['opt']) && sq_check(0, $usr->sq)) {
			if (($frm->bookmarked = ($_GET['opt'] == 'on'))) {
				thread_bookmark_add(_uid, $th);
			} else {
				thread_bookmark_del(_uid, $th);
			}
		}

		/* UnPublish thread. */
		/* TODO: For future implementation.
		if (isset($_GET['unpublish']) && $MOD && sq_check(0, $usr->sq)) {
			echo "DEBUG: UNPUBLISH ALL MESSAGES IN THREAD [$th]<hr>";
			q('UPDATE fud30_msg SET apr=0 WHERE thread_id='. $th);
			if ($FUD_OPT_2 & 32768) {
				header('Location: '. $GLOBALS['WWW_ROOT'] .'index.php/i/'. _rsidl);
			} else {
				header('Location: '. $GLOBALS['WWW_ROOT'] .'index.php?'. _rsidl);
			}
			exit;
		}
		*/

		$first_unread_message_link = (($total - $th) > $count) ? '{TEMPLATE: first_unread_message_link}' : '';
		$subscribe_status = $frm->subscribed ? '{TEMPLATE: unsub_to_thread}' : '{TEMPLATE: sub_from_thread}';
		$bookmark_status  = $frm->bookmarked ? '{TEMPLATE: unbookmark_thread}' : '{TEMPLATE: bookmark_thread}';
	} else {
		if (__fud_cache($frm->last_post_date)) {
			return;
		}
		$first_unread_message_link = $subscribe_status = $bookmark_status = '';
	}

	ses_update_status($usr->sid, '{TEMPLATE: msg_update}', $frm->forum_id);

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: msg_title}';
	$META_DESCR = $frm->subject .' '. $frm->tdescr;	// Description for page header.

	/* This is an optimization intended for topics with many messages. */
	$use_tmp = $FUD_OPT_3 & 4096 && $frm->replies > 250;
	if ($use_tmp) {
		q(q_limit('CREATE TEMPORARY TABLE {SQL_TABLE_PREFIX}_mtmp_'. __request_timestamp__ .' AS SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='. $th .' AND apr=1 ORDER BY id ASC',
			$count, $_GET['start']));
	}

	$q = 'SELECT
		m.*, COALESCE(m.flag_cc, u.flag_cc) AS disp_flag_cc, COALESCE(m.flag_country, u.flag_country) AS disp_flag_country,
		t.thread_opt, t.root_msg_id, t.last_post_id, t.forum_id,
		f.message_threshold,
		u.id AS user_id, u.alias AS login, u.avatar_loc, u.email, u.posted_msg_count, u.join_date, u.location,
		u.sig, u.custom_status, u.icq, u.jabber, u.facebook, u.yahoo, u.google, u.skype, u.twitter, u.users_opt, u.last_visit AS time_sec, u.karma,
		l.name AS level_name, l.level_opt, l.img AS level_img,
		p.max_votes, p.expiry_date, p.creation_date, p.name AS poll_name, p.total_votes,
		karma.id AS cant_karma,
		'. ($perms & 512 ? ' pot.id' : ' 1') .' AS cant_vote
	FROM '. ($use_tmp ? '{SQL_TABLE_PREFIX}_mtmp_'. __request_timestamp__ .' mt INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=mt.id' : ' {SQL_TABLE_PREFIX}msg m') .'
		INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
		INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
		LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
		LEFT JOIN {SQL_TABLE_PREFIX}level l ON u.level_id=l.id
		LEFT JOIN {SQL_TABLE_PREFIX}karma_rate_track karma ON karma.msg_id=m.id AND karma.user_id='. _uid . '
		LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id'.
		($perms & 512 ? ' LEFT JOIN {SQL_TABLE_PREFIX}poll_opt_track pot ON pot.poll_id=p.id AND pot.user_id='. _uid . (!_uid ? ' AND pot.ip_addr='. _esc(get_ip()) : '') : '');
	if ($use_tmp) {
		$q .= ' ORDER BY m.id ASC';
	} else {
		$q = q_limit($q .' WHERE m.thread_id='. $th .' AND m.apr=1 ORDER BY m.id ASC', $count, $_GET['start']);
	}
	$result = q($q);

	$obj2 = $message_data = '';

	$usr->md = $frm->md;

	$m_num = 0;	// Will be incremented in tmpl_drawmsg().
	while ($obj = db_rowobj($result)) {
		$message_data .= tmpl_drawmsg($obj, $usr, $perms, false, $m_num, array($_GET['start'], $count));
		$obj2 = $obj;
	}
	unset($result);

	if ($use_tmp && $FUD_OPT_1 & 256) {
		q('DROP TEMPORARY TABLE {SQL_TABLE_PREFIX}_mtmp_'. __request_timestamp__);
	}

	/* No messages to display. Something is wrong, terminate request. */
	if ($m_num == 0) {
		invl_inp_err();
	}

	if ($FUD_OPT_2 & 32768) {
		$page_pager = tmpl_create_pager($_GET['start'], $count, $total, '{ROOT}/mv/msg/'. $th .'/0/', '/'. reveal_lnk . unignore_tmp . _rsid);
	} else {
		$page_pager = tmpl_create_pager($_GET['start'], $count, $total, '{ROOT}?t=msg&amp;th='. $th .'&amp;prevloaded=1&amp;'. _rsid . reveal_lnk . unignore_tmp);
	}

	get_prev_next_th_id($frm->forum_id, $th, $prev_thread_link, $next_thread_link);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: MSG_PAGE}
