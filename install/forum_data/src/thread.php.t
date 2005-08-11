<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: thread.php.t,v 1.48 2005/08/11 00:44:21 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

/*{PRE_HTML_PHP}*/

	ses_update_status($usr->sid, '{TEMPLATE: thread_update}', $frm_id);

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: thread_title}';

	$result = q('SELECT
		m.attach_cnt, m.poll_id, m.subject, m.icon, m.post_stamp,
		u.alias, u.id,
		u2.id, u2.alias,
		m2.id, m2.post_stamp,
		f.id, f.name,
		t.id, t.moved_to, t.root_msg_id, t.replies, t.rating, t.thread_opt, t.views,
		r.last_view
		FROM {SQL_TABLE_PREFIX}tv_'.$frm_id.' tv
			INNER JOIN {SQL_TABLE_PREFIX}thread	t	ON tv.thread_id=t.id
			INNER JOIN {SQL_TABLE_PREFIX}msg	m	ON t.root_msg_id=m.id
			INNER JOIN {SQL_TABLE_PREFIX}msg	m2	ON m2.id=t.last_post_id
			LEFT JOIN {SQL_TABLE_PREFIX}users	u	ON u.id=m.poster_id
			LEFT JOIN {SQL_TABLE_PREFIX}users	u2	ON u2.id=m2.poster_id
			LEFT JOIN {SQL_TABLE_PREFIX}forum	f	ON f.id=t.moved_to
			LEFT JOIN {SQL_TABLE_PREFIX}read 	r	ON t.id=r.thread_id AND r.user_id='._uid.'
			WHERE tv.id BETWEEN '.($frm->last_view_id - ($cur_frm_page * $THREADS_PER_PAGE)).' AND '.($frm->last_view_id - (($cur_frm_page - 1) * $THREADS_PER_PAGE)).'
			ORDER BY tv.id DESC');
	/* Field Defenitions
	 * 0 msg.attach_cnt
	 * 1 msg.poll_id
	 * 2 msg.subject
	 * 3 msg.icon
	 * 4 msg.post_stamp
	 * 5 users.alias
	 * 6 users.id
	 * 7 fud_users_2.id
	 * 8 fud_users_2.alias
	 * 9 fud_msg_2.id
	 * 10 fud_msg_2.post_stamp
	 * 11 forum.id
	 * 12 forum.name
	 * 13 thread.id
	 * 14 thread.moved_to
	 * 15 thread.root_msg_id
	 * 16 thread.replies
	 * 17 thread.thread_opt
	 * 18 thread.rating
	 * 19 thread.views
	 * 20 read.last_view
	 */

	if (!($r = db_rowarr($result))) {
		$thread_list_table_data = '{TEMPLATE: no_messages}';
		$threaded_view = $admin_heading_row = ''; $mo = 0;
	} else {
		$admin_heading_row = ($MOD || ($mo = $frm->group_cache_opt & 8224));
		$admin_control_row = $thread_list_table_data = '';

		do {
			$r[18] = (int) $r[18];

			if ($r[14]) {
				/* additional security check for moved forums */
				if (!$is_a && $r[11] && !th_moved_perm_chk($r[11])) {
					continue;
				}
				$thread_list_table_data .= '{TEMPLATE: thread_row_moved}';
				continue;
			}
			$msg_count = $r[16] + 1;

			if ($msg_count > $ppg && $usr->users_opt & 256) {
				if ($THREAD_MSG_PAGER < ($pgcount = ceil($msg_count/$ppg))) {
					$i = $pgcount - $THREAD_MSG_PAGER;
					$mini_pager_data = '{TEMPLATE: mini_pager_limiter}';
				} else {
					$mini_pager_data = '';
					$i = 0;
				}

				while ($i < $pgcount) {
					$mini_pager_data .= '{TEMPLATE: mini_pager_entry}';
				}

				$mini_thread_pager = $mini_pager_data ? '{TEMPLATE: mini_thread_pager}' : '';
			} else {
				$mini_thread_pager = '';
			}

			$thread_read_status = $first_unread_msg_link = '';
			if (_uid && $usr->last_read < $r[10] && $r[10] > $r[20]) {
				$thread_read_status = ($r[18] & 1) ? '{TEMPLATE: thread_unread_locked}' : '{TEMPLATE: thread_unread}';
				/* do not show 1st unread message link if thread has no replies */
				if ($r[16]) {
					$first_unread_msg_link = '{TEMPLATE: first_unread_msg_link}';
				}
			} else if ($r[18] & 1) {
				$thread_read_status = '{TEMPLATE: thread_read_locked}';
			} else if (!_uid) {
				$thread_read_status = '{TEMPLATE: thread_read_unreg}';
			} else {
				$thread_read_status = '{TEMPLATE: thread_read}';
			}

			if ($admin_heading_row) {
				if ($MOD || $mo == 8224) {
					$admin_control_row = '{TEMPLATE: admin_control_row_all}';
				} else if ($mo & 32) {
					$admin_control_row = '{TEMPLATE: admin_control_row_del}';
				} else {
					$admin_control_row = '{TEMPLATE: admin_control_row_move}';
				}
			}
			$thread_list_table_data .= '{TEMPLATE: thread_row}';
		} while (($r = db_rowarr($result)));
	}

	if ($FUD_OPT_2 & 32768) {
		$page_pager = tmpl_create_pager($start, $THREADS_PER_PAGE, $frm->thread_count, '{ROOT}/sf/thread/'.$frm_id.'/1/', '/' ._rsid);
	} else {
		$page_pager = tmpl_create_pager($start, $THREADS_PER_PAGE, $frm->thread_count, '{ROOT}?t=thread&amp;frm_id='.$frm_id.'&amp;'._rsid);
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: THREAD_PAGE}
<?php
	if (_uid) {
		user_register_forum_view($frm_id);
	}
?>