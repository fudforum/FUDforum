<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: msg.php.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*#? Message Display Generator */
	include_once "GLOBALS.php";
	{PRE_HTML_PHP}
		
	$count = !empty($usr->posts_ppg) ? $usr->posts_ppg : $GLOBALS['POSTS_PER_PAGE'];
		
	if ( !empty($goto) ) {
		if ( empty($th) ) {
			if( !is_numeric($goto) ) invl_inp_err();
			$msg = new fud_msg;
			$msg->get_by_id($goto);
			$th = $msg->thread_id;
		} 
		else if( !is_numeric($th) ) invl_inp_err();
	
		$mid = '';
		if( $goto != 'end' ) {
			if( !is_numeric($goto) ) invl_inp_err();
			$pos = Q_SINGLEVAL("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$th." AND id<=".$goto." AND approved='Y'");
			$mid = '#msg_'.$msg->id;
		}	
		else
			$pos = Q_SINGLEVAL("SELECT replies+1 FROM {SQL_TABLE_PREFIX}thread WHERE id=".$th);
	
		$start = (ceil(($pos/$count))-1)*$count;
		if ( $start < 0 ) $start = 0;
		header("Location: {ROOT}?t=msg&th=".$th."&"._rsid."&start=".$start.$mid);
		exit();
	}
	
	$frm = new fud_forum;
	
	if ( !empty($th) ) {
		if( !is_numeric($th) ) invl_inp_err();
		$thread = new fud_thread;
		$thread->get_by_id($th);
		
		$frm->get($thread->forum_id);
		if( empty($frm->cat_id) ) invl_inp_err();
	}
	else error_dialog('{TEMPLATE: msg_err_ininfo_title}','{TEMPLATE: msg_err_ininfo_msg}','');
	
	if ( $thread->moved_to ) {
		header("Location: {ROOT}?t=msg&goto=$thread->root_msg_id&"._rsid);
		exit();
	}

	$GLOBALS['__RESOURCE_ID'] = $frm->id;	
	if( !is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'READ') )
		error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
	
	if ( empty($start) || !is_numeric($start) ) $start = 0;
	
	if( isset($usr) ) {
		if ( ($usr->is_mod == 'A' || $frm->is_moderator($usr->id)) ) $MOD = 1;
	
		if ( !empty($notify) ) {
			$th_not = new fud_thread_notify;
			if ( $opt == 'on' ) 
				$th_not->add($usr->id, $th);
			else
				$th_not->delete($usr->id, $th);

			header("Location: {ROOT}?t=msg&th=".$th."&start=".$start.'&'._rsid.'&rand='.get_random_value());
			exit();
		}
	
		if ( !empty($unread) ) {
			$r=Q("SELECT msg_id FROM {SQL_TABLE_PREFIX}read WHERE thread_id=".$thread->id." AND user_id=".$usr->id);
			if ( IS_RESULT($r) ) {
				list($msg_id) = DB_SINGLEARR($r);
				$rr=Q("SELECT {SQL_TABLE_PREFIX}msg.id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$thread->id." AND id>".$msg_id." ORDER BY id LIMIT 1");
				if ( IS_RESULT($rr) ) {
					list($new_msg_id) = DB_SINGLEARR($rr);
					header("Location: {ROOT}?t=msg&goto=".$new_msg_id.'&'._rsid);
					exit();
				}
			}
			header("Location: {ROOT}?t=msg&th=".$th.'&goto=end&'._rsid);
			exit();
		}
	}	

	if ( isset($ses) ) $ses->update('{TEMPLATE: msg_update}');

	$frm_id = $frm->id;
	
	{POST_HTML_PHP}
	$TITLE_EXTRA = ': {TEMPLATE: msg_title}';

	$returnto_dec = '{ROOT}?t=msg&th='.$th.'&start='.$start.'&prevloaded=1&'._rsid;
	$returnto_str = urlencode($returnto_dec);
	$returnto = 'returnto='.$returnto_str;

	if ( !isset($cat) ) {
		$cat = new fud_cat;
		$cat->get_cat($frm->cat_id);
	}

	if( $thread->rating )
		$thread_rating = '{TEMPLATE: thread_rating}';
	else 
		$thread_rating = '{TEMPLATE: no_thread_rating}';
			
	$msg_forum_path = '{TEMPLATE: msg_forum_path}';
	
	$total = $thread->replies+1;

	if( $MOD || is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'LOCK') )
		$lock_thread = ( $thread->locked == 'N' ) ? '{TEMPLATE: mod_lock_thread}' : '{TEMPLATE: mod_unlock_thread}';
	if( ($MOD || is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'SPLIT')) && $thread->replies )
		$split_thread = '{TEMPLATE: split_thread}';

	if( $ALLOW_EMAIL == 'Y' ) $email_page_to_friend = '{TEMPLATE: email_page_to_friend}';
				
	if ( isset($usr) ) {
		if( $total-$start > $count ) $first_unread_message_link = '{TEMPLATE: first_unread_message_link}';
		$subscribe_status = ( Q_SINGLEVAL("SELECT id FROM {SQL_TABLE_PREFIX}thread_notify WHERE thread_id=".$th." AND user_id=".$usr->id) ) ? '{TEMPLATE: unsub_to_thread}' : '{TEMPLATE: sub_from_thread}';
	}
	if ( $thread->locked == 'N' ) $post_reply = '{TEMPLATE: post_reply}';

	if ( isset($thread) && empty($prevloaded) ) {
		if ( isset($usr) ) $usr->register_forum_view($frm->id);
		$thread->inc_view_count();
	}

	$msg_list = Q("SELECT {SQL_TABLE_PREFIX}msg.id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$th." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY id ASC LIMIT ".$start.",".$count);
	if ( !DB_COUNT($msg_list) ) {
		error_dialog('{TEMPLATE: msg_err_nosuchmsg_title}','{TEMPLATE: msg_err_nosuchmsg_msg}', '', 'FATAL');
		exit();
	}
	$id_list='{SQL_TABLE_PREFIX}msg.id IN(';
	$m_count=0;
	while ( list($msgp_id) = DB_ROWARR($msg_list) ) { $id_list .= $msgp_id.','; $m_count++; }
	QF($msg_list);
	$id_list = substr($id_list, 0, -1).')';

	$result = Q('SELECT HIGH_PRIORITY
		{SQL_TABLE_PREFIX}msg.*, 
		{SQL_TABLE_PREFIX}thread.locked,
		{SQL_TABLE_PREFIX}thread.root_msg_id,
		{SQL_TABLE_PREFIX}thread.last_post_id,
		{SQL_TABLE_PREFIX}thread.forum_id,
		{SQL_TABLE_PREFIX}forum.message_threshold,
		{SQL_TABLE_PREFIX}avatar.img AS avatar, 
		{SQL_TABLE_PREFIX}users.id AS user_id, 
		{SQL_TABLE_PREFIX}users.login, 
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
		'.$id_list.'
	ORDER BY id ASC');
	
	$m_count--;
	
	set_row_color_alt(true);
	$message_data='';
	
	while ( $obj = DB_ROWOBJ($result) ) {
		$message_data .= tmpl_drawmsg($obj, $m_count, true);
		$mid = $obj->id;
	}
	QF($result);
	
	un_register_fps();

	if ( isset($usr) ) $usr->register_thread_view($thread->id, $mid);

	$page_pager = tmpl_create_pager($start, $count, $total, "{ROOT}?t=msg&th=".$th."&prevloaded=1&"._rsid.'&rev='.$rev.'&reveal='.$reveal);

	list($pg, $ps)= DB_SINGLEARR(Q("SELECT page, pos FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id=".$thread->forum_id." AND thread_id=".$thread->id));

	$r = Q('SELECT 
			{SQL_TABLE_PREFIX}msg.id AS msg_id,
			{SQL_TABLE_PREFIX}thread_view.pos,
			{SQL_TABLE_PREFIX}thread.id,
			{SQL_TABLE_PREFIX}msg.subject 
		FROM 
			{SQL_TABLE_PREFIX}thread_view 
			INNER JOIN {SQL_TABLE_PREFIX}thread 
				ON {SQL_TABLE_PREFIX}thread_view.thread_id={SQL_TABLE_PREFIX}thread.id 
			INNER JOIN {SQL_TABLE_PREFIX}msg 
				ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id 
			WHERE 
				{SQL_TABLE_PREFIX}thread_view.forum_id='.$thread->forum_id.' 
				AND {SQL_TABLE_PREFIX}thread_view.page='.$pg.' 
				AND {SQL_TABLE_PREFIX}thread_view.pos IN ('.($ps-1).', '.($ps+1).') 
			ORDER BY pos');
	$prev_th = $next_th = NULL;
	switch ( DB_COUNT($r) ) 
	{
		case 2:
			$next_th = DB_ROWOBJ($r);
			$prev_th = DB_ROWOBJ($r);
			break;
		case 1:
			$tmp_th = DB_ROWOBJ($r);
			if( $tmp_th->pos > $ps ) {
				$prev_th = $tmp_th;
			}
			else {
				$next_th = $tmp_th;
				if( $pg > 1 ) $prev_th = DB_SINGLEOBJ(Q('SELECT 
						{SQL_TABLE_PREFIX}msg.id AS msg_id,
						{SQL_TABLE_PREFIX}thread_view.pos,
						{SQL_TABLE_PREFIX}thread.id,
						{SQL_TABLE_PREFIX}msg.subject 
					FROM 
						{SQL_TABLE_PREFIX}thread_view 
						INNER JOIN {SQL_TABLE_PREFIX}thread 
							ON {SQL_TABLE_PREFIX}thread_view.thread_id={SQL_TABLE_PREFIX}thread.id 
						INNER JOIN {SQL_TABLE_PREFIX}msg 
							ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id 
						WHERE 
							{SQL_TABLE_PREFIX}thread_view.forum_id='.$thread->forum_id.' 
							AND {SQL_TABLE_PREFIX}thread_view.page='.($pg-1).' 
							AND {SQL_TABLE_PREFIX}thread_view.pos='.$GLOBALS['THREADS_PER_PAGE']));
			}		
			break;
	}
	
	if ( $prev_th )	$prev_thread_link = '{TEMPLATE: prev_thread_link}';
	if ( $next_th ) $next_thread_link = '{TEMPLATE: next_thread_link}';
		
	if ( _uid && ($MOD || is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'RATE')) && !BQ("SELECT id FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id=".$thread->id." AND user_id="._uid) ) 
		$rate_thread = '{TEMPLATE: rate_thread}';

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: MSG_PAGE}