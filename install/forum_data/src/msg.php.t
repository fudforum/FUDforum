<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: msg.php.t,v 1.20 2003/04/02 01:46:35 hackie Exp $
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
		
	$count = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;

	/* quick cheat to avoid a redirect
	 * When we need to determine the 1st unread message, we do it 1st, so that we can re-use the goto handling logic 
	 */
	if (isset($_GET['unread'], $_GET['th']) && _uid && (int)$_GET['th']) {
		if (($lv = q_singleval('SELECT last_view FROM {SQL_TABLE_PREFIX}read WHERE thread_id='.$_GET['th'].' AND user_id='._uid))) {
			if ($usr->last_read > $lv) {
				$lv = $usr->last_read;
			}
			$_GET['goto'] = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$_GET['th']." AND approved='Y' AND post_stamp>".$lv." ORDER BY id LIMIT 1");
		}
	}
		
	if (!empty($_GET['goto'])) {
		if ($_GET['goto'] === 'end' && isset($_GET['th']) && (int)$_GET['th']) {
			list($pos, $mid) = db_saq('SELECT replies+1,last_post_id FROM {SQL_TABLE_PREFIX}thread WHERE id='.$_GET['th']);
			$mid = '#msg_'.$mid;
		} else if ((int)$_GET['goto']) { /* verify that the thread & msg id are valid */
			if (!isset($_GET['th']) || !(int)$_GET['th']) {
				$_GET['th'] = (int) q_singleval('SELECT thread_id FROM {SQL_TABLE_PREFIX}msg WHERE id='.$_GET['goto']);
			}
			if (!($pos = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$_GET['th']." AND id<=".$_GET['goto']." AND approved='Y'"))) {
				invl_inp_err();				
			}
			$mid = '#msg_'.$_GET['goto'];
		} else {
			invl_inp_err();
		}
		
		$_GET['start'] = (ceil($pos/$count) - 1) * $count;
	} else if (!isset($_GET['th']) || !(int)$_GET['th']) {
		invl_inp_err();
	}

	/* we create a BIG object frm, which contains data about forum, category & current thread */
	$frm = db_sab('SELECT 
			{SQL_TABLE_PREFIX}cat.name AS cat_name,
			{SQL_TABLE_PREFIX}forum.name AS frm_name,
			{SQL_TABLE_PREFIX}msg.subject,
			{SQL_TABLE_PREFIX}thread.id,
			{SQL_TABLE_PREFIX}thread.forum_id,
			{SQL_TABLE_PREFIX}thread.replies,
			{SQL_TABLE_PREFIX}thread.rating,
			{SQL_TABLE_PREFIX}thread.root_msg_id,
			{SQL_TABLE_PREFIX}thread.moved_to,
			{SQL_TABLE_PREFIX}thread.locked
		FROM {SQL_TABLE_PREFIX}thread
			INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}msg.id={SQL_TABLE_PREFIX}thread.root_msg_id
			INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}thread.forum_id
			INNER JOIN {SQL_TABLE_PREFIX}cat ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id
		WHERE {SQL_TABLE_PREFIX}thread.id='.$_GET['th']);

	if (!$frm) { /* bad thread, terminate request */
		invl_inp_err();
	}
	if ($frm->moved_to) { /* moved thread, we could handle it, but this case is rather rare, so it's cleaner to redirect */
		header('Location: {ROOT}?t=msg&goto='.$frm->root_msg_id.'&'._rsidl);
		exit();
	}

	$MOD = $sub_status = 0;
	$USER_PERMS = init_single_user_perms($frm->forum_id, $usr->is_mod, $MOD);

	if ($USER_PERMS['read'] == 'N') {
		if (!isset($_GET['logoff'])) {
			error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
		} else {
			header('Location: {ROOT}');
			exit;
		}	
	}	
	
	$_GET['start'] = isset($_GET['start']) ? (int)$_GET['start'] : 0;

	$total = $frm->replies + 1;

	if (_uid) {
		/* Deal with thread subscriptions */
		if (isset($_GET['notify'])) {
			if ($opt == 'on') {
				fud_thread_notify::add(_uid, $_GET['th']);
				$sub_status = 1;
			} else {
				fud_thread_notify::delete(_uid, $_GET['th']);
			}
		}

		if (($total - $_GET['th']) > $count) {
			$first_unread_message_link = '{TEMPLATE: first_unread_message_link}';
		}
		$subscribe_status = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}thread_notify WHERE thread_id='.$_GET['th'].' AND user_id='._uid) ? '{TEMPLATE: unsub_to_thread}' : '{TEMPLATE: sub_from_thread}';
	} else {
		$subscribe_status = '';
	}

	$ses->update('{TEMPLATE: msg_update}', $frm->forum_id);

	{POST_HTML_PHP}
	$TITLE_EXTRA = ': {TEMPLATE: msg_title}';

	if ($ENABLE_THREAD_RATING == 'Y') {
		$thread_rating = $frm->rating ? '{TEMPLATE: thread_rating}' : '{TEMPLATE: no_thread_rating}';
		if (_uid && $USER_PERMS['lock'] == 'Y' && !bq('SELECT id FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id='.$frm->id.' AND user_id='._uid)) {
			$rate_thread = '{TEMPLATE: rate_thread}';
		}
	} else {
		$rate_thread = $thread_rating = '';
	}

	$post_reply = $frm->locked == 'Y' ? '{TEMPLATE: post_reply}' : '';
	$email_page_to_friend = $ALLOW_EMAIL == 'Y' ? '{TEMPLATE: email_page_to_friend}' : '';

	if ($USER_PERMS['lock'] == 'Y') {
		$lock_thread = ( $frm->locked == 'N' ) ? '{TEMPLATE: mod_lock_thread}' : '{TEMPLATE: mod_unlock_thread}';
	} else {
		$lock_thread = '';
	}
	if ($USER_PERMS['split'] == 'Y') {
		$split_thread = '{TEMPLATE: split_thread}';
	} else {
		$split_thread = '';
	}

	if (isset($_GET['prevloaded'])) {
		if (_uid) {
			$usr->register_forum_view($frm->forum_id);
		}
		th_inc_view_count($frm->id);
	}

	$result = uq('SELECT 
		{SQL_TABLE_PREFIX}msg.*, 
		{SQL_TABLE_PREFIX}thread.locked,
		{SQL_TABLE_PREFIX}thread.root_msg_id,
		{SQL_TABLE_PREFIX}thread.last_post_id,
		{SQL_TABLE_PREFIX}thread.forum_id,
		{SQL_TABLE_PREFIX}forum.message_threshold,
		{SQL_TABLE_PREFIX}avatar.img AS avatar, 
		{SQL_TABLE_PREFIX}users.id AS user_id, 
		{SQL_TABLE_PREFIX}users.alias AS login, 
		{SQL_TABLE_PREFIX}users.display_email, 
		{SQL_TABLE_PREFIX}users.avatar_approved,
		{SQL_TABLE_PREFIX}users.avatar_loc,
		{SQL_TABLE_PREFIX}users.email, 
		{SQL_TABLE_PREFIX}users.posted_msg_count, 
		{SQL_TABLE_PREFIX}users.join_date, 
		{SQL_TABLE_PREFIX}users.location, 
		{SQL_TABLE_PREFIX}users.sig,
		{SQL_TABLE_PREFIX}users.custom_status,
		{SQL_TABLE_PREFIX}users.icq,
		{SQL_TABLE_PREFIX}users.jabber,
		{SQL_TABLE_PREFIX}users.affero,
		{SQL_TABLE_PREFIX}users.aim,
		{SQL_TABLE_PREFIX}users.msnm,
		{SQL_TABLE_PREFIX}users.yahoo,
		{SQL_TABLE_PREFIX}users.invisible_mode,
		{SQL_TABLE_PREFIX}users.email_messages,
		{SQL_TABLE_PREFIX}users.is_mod,
		{SQL_TABLE_PREFIX}users.last_visit AS time_sec,
		{SQL_TABLE_PREFIX}level.name AS level_name,
		{SQL_TABLE_PREFIX}level.pri AS level_pri,
		{SQL_TABLE_PREFIX}level.img AS level_img
	FROM 
		{SQL_TABLE_PREFIX}msg
		INNER JOIN {SQL_TABLE_PREFIX}thread
			ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
		INNER JOIN {SQL_TABLE_PREFIX}forum
			ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id
		LEFT JOIN {SQL_TABLE_PREFIX}users 
			ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id 
		LEFT JOIN {SQL_TABLE_PREFIX}avatar 
			ON {SQL_TABLE_PREFIX}users.avatar={SQL_TABLE_PREFIX}avatar.id 
		LEFT JOIN {SQL_TABLE_PREFIX}level
			ON {SQL_TABLE_PREFIX}users.level_id={SQL_TABLE_PREFIX}level.id
	WHERE 
		{SQL_TABLE_PREFIX}msg.thread_id='.$_GET['th'].' AND approved=\'Y\'
	ORDER BY id ASC LIMIT ' . qry_limit($count, $_GET['start']));
	
	$message_data = '';

	while ($obj = db_rowobj($result)) {
		$message_data .= tmpl_drawmsg($obj, $m_count++, TRUE);
		$obj2 = $obj;
	}
	qf($result);
	
	un_register_fps();

	if (_uid && q_singleval('SELECT last_view FROM {SQL_TABLE_PREFIX}read WHERE thread_id='.$frm->id.' AND user_id='._uid) < $obj2->post_stamp) {
		$usr->register_thread_view($frm->id, $obj2->post_stamp, $obj2->id);
	}

	$page_pager = tmpl_create_pager($_GET['th'], $count, $total, '{ROOT}?t=msg&amp;th='.$_GET['th'].'&amp;prevloaded=1&amp;'._rsid.'&amp;rev='.$rev.'&amp;reveal='.$reveal);

	$prev_thread_link = $next_thread_link = '';
	get_prev_next_th_id($frm->forum_id, $frm->id, $prev_thread_link, $next_thread_link);
		
	if ($prev_th) {
		$prev_thread_link = '{TEMPLATE: prev_thread_link}';
	}
	if ($next_th) {
		$next_thread_link = '{TEMPLATE: next_thread_link}';
	}
		
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: MSG_PAGE}