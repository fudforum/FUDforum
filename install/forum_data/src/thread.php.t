<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: thread.php.t,v 1.13 2003/04/02 01:46:35 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	{PRE_HTML_PHP}
	
	if (!isset($_REQUEST['frm_id']) || !(int)$_REQUEST['frm_id']) {
		invl_inp_err();
	}

	$frm = new fud_forum;
	$frm->get($_REQUEST['frm_id']);
	
	if (!$frm->cat_id) {
		invl_inp_err();
	}
	
	if (!is_perms(_uid, $frm->id, 'READ')) {
		if (!isset($_GET['logoff'])) {
			error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
		} else {
			header("Location: {ROOT}");
			exit;
		}	
	}	
	
	$MOD = 0;

	if (isset($_REQUEST['start'])) {
		$start = (int) $_REQUEST['start'];
	} else {
		$start = 0;
	}	

	if (_uid) {
		$ses->update('{TEMPLATE: thread_update}', $frm->id);
		if (is_moderator($frm->id, _uid) || $usr->is_mod == 'A') {
			$MOD = 1;
		}

		$ppg = $usr->posts_ppg ? $usr->posts_ppg : $THREADS_PER_PAGE;

		if (isset($_GET['sub'])) {
			fud_forum_notify::add(_uid, $frm->id);
		} else if (isset($_GET['unsub'])) {
			fud_forum_notify::delete(_uid, $frm->id);
		}

		if (is_forum_notified(_uid, $frm->id)) {
			$subscribe = '{TEMPLATE: unsubscribe_link}';
		} else  {
			$subscribe = '{TEMPLATE: subscribe_link}';
		}

		$lread_s = ',{SQL_TABLE_PREFIX}read.last_view ';
		$lread_f = ' LEFT JOIN {SQL_TABLE_PREFIX}read ON {SQL_TABLE_PREFIX}thread.id={SQL_TABLE_PREFIX}read.thread_id AND {SQL_TABLE_PREFIX}read.user_id='._uid.' ';
	} else {
		$ppg = $THREADS_PER_PAGE;
		$MOVE = $DEL = $lread_s = $lread_f = $subscribe = '';
	}
	
	{POST_HTML_PHP}
	$TITLE_EXTRA = ': {TEMPLATE: thread_title}';

	$cat = new fud_cat;
	$cat->get_cat($frm->cat_id);
	
	$result = uq('SELECT 
		{SQL_TABLE_PREFIX}msg.attach_cnt, 
		{SQL_TABLE_PREFIX}msg.poll_id, 
		{SQL_TABLE_PREFIX}msg.subject, 
		{SQL_TABLE_PREFIX}msg.icon, 
		{SQL_TABLE_PREFIX}msg.post_stamp,
		{SQL_TABLE_PREFIX}users.alias, 
		{SQL_TABLE_PREFIX}users.id, 
		fud_users_2.id, 
		fud_users_2.alias, 
		fud_msg_2.id, 
		fud_msg_2.post_stamp, 
		{SQL_TABLE_PREFIX}forum.id,
		{SQL_TABLE_PREFIX}forum.name,
		{SQL_TABLE_PREFIX}thread.id, 
		{SQL_TABLE_PREFIX}thread.moved_to, 
		{SQL_TABLE_PREFIX}thread.root_msg_id, 
		{SQL_TABLE_PREFIX}thread.replies,
		{SQL_TABLE_PREFIX}thread.locked, 
		{SQL_TABLE_PREFIX}thread.rating, 
		{SQL_TABLE_PREFIX}thread.is_sticky,
		{SQL_TABLE_PREFIX}thread.ordertype, 
		{SQL_TABLE_PREFIX}thread.views
		'.$lread_s.' 
		FROM {SQL_TABLE_PREFIX}thread_view 
			INNER JOIN {SQL_TABLE_PREFIX}thread 
				ON {SQL_TABLE_PREFIX}thread_view.thread_id={SQL_TABLE_PREFIX}thread.id 
			INNER JOIN {SQL_TABLE_PREFIX}msg 
				ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id 
			INNER JOIN {SQL_TABLE_PREFIX}msg AS fud_msg_2 
				ON fud_msg_2.id={SQL_TABLE_PREFIX}thread.last_post_id
			LEFT JOIN {SQL_TABLE_PREFIX}users 
				ON {SQL_TABLE_PREFIX}users.id={SQL_TABLE_PREFIX}msg.poster_id 
			LEFT JOIN {SQL_TABLE_PREFIX}users AS fud_users_2 
				ON fud_users_2.id=fud_msg_2.poster_id '.$lread_f.'
			LEFT JOIN {SQL_TABLE_PREFIX}forum
				ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}thread.moved_to
			WHERE {SQL_TABLE_PREFIX}thread_view.forum_id='.$frm->id.' AND {SQL_TABLE_PREFIX}thread_view.page='.(floor($start/$ppg)+1).' ORDER BY {SQL_TABLE_PREFIX}thread_view.pos ASC');
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
	 * 17 thread.locked
	 * 18 thread.rating
	 * 19 thread.is_sticky
	 * 20 thread.ordertype
	 * 21 thread.views
	 * 22 read.last_view
	 */

	if (!($r = @db_rowarr($result))) {
		$no_messages = '{TEMPLATE: no_messages}';
	} else {
		if ($MOD || ($MOVE = is_perms(_uid, $frm->id, 'MOVE')) || ($DEL = is_perms(_uid, $frm->id, 'DEL'))) {
			$admin_heading_row = '{TEMPLATE: admin_heading_row}';
		} else {
			$admin_heading_row = '';
		}
		$rating_heading = $ENABLE_THREAD_RATING == 'Y' ? '{TEMPLATE: rating_heading}' : '';

		$threaded_view = $TREE_THREADS_ENABLE == 'N' ? '' : '{TEMPLATE: threaded_view}';
		$thread_list_table_data='';

		do {
			if ($r[14]) {
				$thread_list_table_data .= '{TEMPLATE: thread_row_moved}';
				continue;
			}
			$msg_count = $r[16] + 1;

			if ($msg_count > $ppg && d_thread_view=='msg') {
				if ($THREAD_MSG_PAGER < ($pgcount = ceil($msg_count/$ppg))) {
					$i = $pgcount - $THREAD_MSG_PAGER;
					$mini_pager_data = '{TEMPLATE: mini_pager_limiter}';
				} else {
					$mini_pager_data = '';
					$i = 0;
				}
				
				for ($i; $i < $pgcount; $i++) {
					$st_pos = $i * $POSTS_PER_PAGE;
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
			if ($ENABLE_THREAD_RATING == 'Y') {
				$rating = $r[18] ? '{TEMPLATE: rating}' : '{TEMPLATE: rating_none}';
			} else {
				$rating = '';
			}
			if ($r[19] == 'Y') {
				$stick_status = $r[20] == 'STICKY' ? '{TEMPLATE: sticky}' : '{TEMPLATE: announcement}';
			} else {
				$stick_status = '';
			}
			$user_link = $r[8] ? '{TEMPLATE: reg_user_link}' : '{TEMPLATE: unreg_user_link}';
			$first_post_login = $r[5] ? '{TEMPLATE: first_post_reg_user_link}' : '{TEMPLATE: first_post_unreg_user_link}';

			$thread_read_status = '';
			if (_uid) {
				if ($usr->last_read < $r[10] && $r[10] > $r[22]) {
					if ($r[17] == 'Y') {
						$thread_read_status = '{TEMPLATE: thread_unread_locked}';
					} else {
						$thread_read_status = '{TEMPLATE: thread_unread}';
					}
					$first_unread_msg_link = '{TEMPLATE: first_unread_msg_link}';
				}
			} else {
				$first_unread_msg_link = '';
			}

			if (!$thread_read_status) {
				if ($r[17] == 'Y') {
					$thread_read_status = '{TEMPLATE: thread_read_locked}';
				} else if (!_uid) {
					$thread_read_status = '{TEMPLATE: thread_read_unreg}';
				} else {
					$thread_read_status = '{TEMPLATE: thread_read}';	 
				}
			}
		
			$thread_first_post = '{TEMPLATE: thread_first_post}';
		
			if ($MOD || ($MOVE && $DEL)) {
				$admin_control_row = '{TEMPLATE: admin_control_row_all}';
			} else if ($MOVE) {
				$admin_control_row = '{TEMPLATE: admin_control_row_move}';
			} else if ($DEL) {
				$admin_control_row = '{TEMPLATE: admin_control_row_del}';
			} else {
				$admin_control_row = '';
			}

			$thread_list_table_data .= '{TEMPLATE: thread_row}';
	
		} while (($r = db_rowarr($result)));
	}
	qf($result);

	$page_pager = tmpl_create_pager($start, $ppg, $frm->thread_count, '{ROOT}?t=thread&amp;frm_id='.$_REQUEST['frm_id'].'&amp;'._rsid);

	{POST_PAGE_PHP_CODE}
?>	
{TEMPLATE: THREAD_PAGE}	
<?php	
	if (_uid) {
		$usr->register_forum_view($frm->id);
	}
?>