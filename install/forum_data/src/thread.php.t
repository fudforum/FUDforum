<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: thread.php.t,v 1.28 2003/09/30 02:57:59 hackie Exp $
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

	ses_update_status($usr->sid, '{TEMPLATE: thread_update}', $frm_id);

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: thread_title}';

	$result = uq('SELECT 
		m.attach_cnt, m.poll_id, m.subject, m.icon, m.post_stamp,
		u.alias, u.id,
		u2.id, u2.alias,
		m2.id, m2.post_stamp, 
		f.id, f.name,
		t.id, t.moved_to, t.root_msg_id, t.replies, t.rating, t.thread_opt, t.views,
		r.last_view
		FROM {SQL_TABLE_PREFIX}thread_view tv
			INNER JOIN {SQL_TABLE_PREFIX}thread	t	ON tv.thread_id=t.id 
			INNER JOIN {SQL_TABLE_PREFIX}msg	m	ON t.root_msg_id=m.id
			INNER JOIN {SQL_TABLE_PREFIX}msg	m2	ON m2.id=t.last_post_id
			LEFT JOIN {SQL_TABLE_PREFIX}users	u	ON u.id=m.poster_id 
			LEFT JOIN {SQL_TABLE_PREFIX}users	u2	ON u2.id=m2.poster_id 
			LEFT JOIN {SQL_TABLE_PREFIX}forum	f	ON f.id=t.moved_to
			LEFT JOIN {SQL_TABLE_PREFIX}read 	r	ON t.id=r.thread_id AND r.user_id='._uid.'
			WHERE tv.forum_id='.$frm_id.' AND tv.page='.$cur_frm_page.' ORDER BY tv.pos ASC');
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

	if (!($r = @db_rowarr($result))) {
		$thread_list_table_data = '{TEMPLATE: no_messages}';
		$threaded_view = $rating_heading = $admin_heading_row = '';
	} else {
		$admin_heading_row = ($MOD || $frm->group_cache_opt & (32|8192)) ? '{TEMPLATE: admin_heading_row}' : '';
		$rating_heading = $FUD_OPT_2 & 4096 ? '{TEMPLATE: rating_heading}' : '';
		$threaded_view = $FUD_OPT_2 & 512 ? '{TEMPLATE: threaded_view}' : '';
		$thread_list_table_data = '';

		do {
			$r[18] = (int) $r[18];

			if ($r[14]) {
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
				
				for ($i; $i < $pgcount; $i++) {
					$st_pos = $i * $ppg;
					$pg_num = $i + 1;
					$mini_pager_data .= '{TEMPLATE: mini_pager_entry}';
				}
		
				if ($mini_pager_data) {
					$mini_thread_pager = '{TEMPLATE: mini_thread_pager}';
				} else {
					$mini_thread_pager = '';
				}
			} else {
				$mini_thread_pager = '';
			}

			$thread_poll_indicator = $r[1] ? '{TEMPLATE: thread_poll_indicator}' : '';
			$thread_attach_indicator = $r[0] ? '{TEMPLATE: thread_attach_indicator}' : '';
			$thread_icon = $r[3] ? '{TEMPLATE: thread_icon}' : '{TEMPLATE: thread_icon_none}';
			if ($FUD_OPT_2 & 4096) {
				$rating = $r[17] ? '{TEMPLATE: rating}' : '{TEMPLATE: rating_none}';
			} else {
				$rating = '';
			}
			if ($r[18] > 1) {
				$stick_status = $r[18] & 4 ? '{TEMPLATE: sticky}' : '{TEMPLATE: announcement}';
			} else {
				$stick_status = '';
			}
			$user_link = $r[8] ? '{TEMPLATE: reg_user_link}' : '{TEMPLATE: unreg_user_link}';
			$first_post_login = $r[5] ? '{TEMPLATE: first_post_reg_user_link}' : '{TEMPLATE: first_post_unreg_user_link}';

			$thread_read_status = $first_unread_msg_link = '';
			if (_uid && $usr->last_read < $r[10] && $r[10] > $r[20]) {
				if ($r[18] & 1) {
					$thread_read_status = '{TEMPLATE: thread_unread_locked}';
				} else {
					$thread_read_status = '{TEMPLATE: thread_unread}';
				}
				/* do not show 1st unread message link if thread has no replies */
				if ($r[16]) {
					$first_unread_msg_link = '{TEMPLATE: first_unread_msg_link}';
				}
			}

			if (!$thread_read_status) {
				if ($r[18] & 1) {
					$thread_read_status = '{TEMPLATE: thread_read_locked}';
				} else if (!_uid) {
					$thread_read_status = '{TEMPLATE: thread_read_unreg}';
				} else {
					$thread_read_status = '{TEMPLATE: thread_read}';	 
				}
			}
		
			$thread_first_post = '{TEMPLATE: thread_first_post}';
		
			if ($admin_heading_row) {
				if ($MOD || $frm->group_cache_opt & (32|8192)) {
					$admin_control_row = '{TEMPLATE: admin_control_row_all}';
				} else if ($frm->group_cache_opt & 32) {
					$admin_control_row = '{TEMPLATE: admin_control_row_del}';
				} else {
					$admin_control_row = '{TEMPLATE: admin_control_row_move}';
				}
			} else {
				$admin_control_row = '';
			}
			$thread_list_table_data .= '{TEMPLATE: thread_row}';
		} while (($r = db_rowarr($result)));
	}
	qf($result);

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