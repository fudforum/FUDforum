<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: tree.php.t,v 1.20 2002/10/28 22:32:03 hackie Exp $
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

	if( !empty($goto) ) {
		if( is_numeric($goto) ) {
			$mid = $goto;
			$th = q_singleval("SELECT thread_id FROM {SQL_TABLE_PREFIX}msg WHERE id=".$mid);
		}
		else if( $goto == 'end' && is_numeric($th) ) {
			list($mid) = db_singlearr(q("SELECT last_post_id FROM {SQL_TABLE_PREFIX}thread WHERE id=".$th));	
		}
		else
			invl_inp_err();
	}
	
	if( empty($th) || !is_numeric($th) ) {
		std_error('systemerr');
		exit;
	}
	
	if ( !empty($notify) && isset($usr) ) {
		$th_not = new fud_thread_notify;
		if ( $opt == 'on' ) 
			$th_not->add($usr->id, $th);
		else
			$th_not->delete($usr->id, $th);

		header("Location: {ROOT}?t=tree&th=".$th."&mid=".$mid.'&'._rsidl.'&rand='.get_random_value());
		exit();
	}

	$thread = new fud_thread;
	$frm = new fud_forum;
	$thread->get_by_id($th);
	$frm->get($thread->forum_id);
	if( empty($frm->cat_id) ) invl_inp_err();
	if ( $thread->moved_to ) {
		header("Location: {ROOT}?t=tree&goto=".$thread->root_msg_id."&"._rsidl);
		exit();
	}

	if( isset($usr) && !empty($unread) ) {
		if( ($lv = q_singleval("SELECT last_view FROM {SQL_TABLE_PREFIX}read WHERE thread_id=".$thread->id." AND user_id=".$usr->id)) ) {
			if( $usr->last_read > $lv ) $lv = $usr->last_read;
			if( ($new_msg_id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$thread->id." AND approved='Y' AND post_stamp>".$lv." ORDER BY id LIMIT 1")) ) {
				header("Location: {ROOT}?t=tree&goto=".$new_msg_id.'&'._rsidl);
				exit();
			}
		}
		
		header("Location: {ROOT}?t=tree&th=".$th.'&'._rsidl);
		exit;
	}

	$GLOBALS['__RESOURCE_ID'] = $frm->id;	
	if( !is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'READ') ) {
		if( !isset($HTTP_GET_VARS['logoff']) )
			error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
		else {
			header("Location: {ROOT}");
			exit;
		}	
	}	

	if( empty($mid) || !is_numeric($mid) ) $mid = $thread->root_msg_id;	
	
	$result = q("SELECT 
		{SQL_TABLE_PREFIX}msg.*, 
		{SQL_TABLE_PREFIX}thread.locked,
		{SQL_TABLE_PREFIX}thread.forum_id,
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
		{SQL_TABLE_PREFIX}users.icq,
		{SQL_TABLE_PREFIX}users.jabber,
		{SQL_TABLE_PREFIX}users.aim,
		{SQL_TABLE_PREFIX}users.msnm,
		{SQL_TABLE_PREFIX}users.invisible_mode,
		{SQL_TABLE_PREFIX}users.email_messages,
		{SQL_TABLE_PREFIX}users.last_visit AS time_sec,
		{SQL_TABLE_PREFIX}users.is_mod,
		{SQL_TABLE_PREFIX}users.yahoo,
		{SQL_TABLE_PREFIX}users.custom_status,
		{SQL_TABLE_PREFIX}level.name AS level_name,
		{SQL_TABLE_PREFIX}level.pri AS level_pri,
		{SQL_TABLE_PREFIX}level.img AS level_img
	FROM 
		{SQL_TABLE_PREFIX}msg 
		LEFT JOIN {SQL_TABLE_PREFIX}users 
			ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id 
		LEFT JOIN {SQL_TABLE_PREFIX}avatar 
			ON {SQL_TABLE_PREFIX}users.avatar={SQL_TABLE_PREFIX}avatar.id 
		INNER JOIN {SQL_TABLE_PREFIX}thread
			ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id	
		LEFT JOIN {SQL_TABLE_PREFIX}level
			ON {SQL_TABLE_PREFIX}users.level_id={SQL_TABLE_PREFIX}level.id
	WHERE 
		{SQL_TABLE_PREFIX}msg.id=".$mid." AND {SQL_TABLE_PREFIX}msg.approved='Y'");

	$msg_obj = db_singleobj($result);
	$mid = $msg_obj->id;

	if ( isset($usr) && ($frm->is_moderator($usr->id) || $usr->is_mod == 'A') ) $MOD = 1;
	
	if ( _uid ) {
		$last_thread_read = q_singleval("SELECT last_view FROM {SQL_TABLE_PREFIX}read WHERE thread_id=".$thread->id." AND user_id="._uid);
	}
	
	if ( isset($thread) && empty($prevloaded) ) {
		if ( isset($usr) ) $usr->register_forum_view($frm->id);
		$thread->inc_view_count();
	}

	$frm_id = $frm->id;
	{POST_HTML_PHP}
	$TITLE_EXTRA = ': {TEMPLATE: tree_title}';

	$r = q("SELECT 
			{SQL_TABLE_PREFIX}msg.*,
			{SQL_TABLE_PREFIX}users.alias AS login,
			{SQL_TABLE_PREFIX}users.last_visit AS time_sec,
			{SQL_TABLE_PREFIX}thread.root_msg_id
		FROM 
			{SQL_TABLE_PREFIX}msg 
			INNER JOIN {SQL_TABLE_PREFIX}thread 
				ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id 
			LEFT JOIN {SQL_TABLE_PREFIX}users 
				ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id 
		WHERE 
			thread_id=".$th." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY id");
	
	while ( $obj = db_rowobj($r) ) {
		$arr[$obj->id] = $obj;
		$arr[$obj->reply_to]->kiddie_count++;
		$arr[$obj->reply_to]->kiddies[] = &$arr[$obj->id];
		
		if ( $obj->reply_to == 0 ) {
			$tree->kiddie_count++;
			$tree->kiddies[] = &$arr[$obj->id];
		}	
	}
	qf($r);

	if ( isset($ses) ) $ses->update('{TEMPLATE: tree_update}', $GLOBALS['__RESOURCE_ID']);

	if ( isset($frm) ) {
		if ( !isset($cat) ) {
			$cat = new fud_cat;
			$cat->get_cat($frm->cat_id);
		}
		
		if ( $thread->rating ) 
			$thread_rating = '{TEMPLATE: thread_rating}';
		else 
			$thread_rating = '{TEMPLATE: no_thread_rating}';
			
		$msg_forum_path = '{TEMPLATE: msg_forum_path}';
	}
	
	$returnto_str = urlencode('{ROOT}?t=tree&th='.$th.'&mid='.$mid.'&prevloaded=1&'._rsid);
	$returnto = 'returnto='.$returnto_str;
	
	if( $MOD || is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'LOCK') )
		$lock_thread = ( $thread->locked == 'N' ) ? '{TEMPLATE: mod_lock_thread}' : '{TEMPLATE: mod_unlock_thread}';
	if( ($MOD || is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'SPLIT')) && $thread->replies )
		$split_thread = '{TEMPLATE: split_thread}';

	if( $ALLOW_EMAIL == 'Y' ) $email_page_to_friend = '{TEMPLATE: email_page_to_friend}';
		
	if ( isset($usr) ) $subscribe_status = ( q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}thread_notify WHERE thread_id=".$th." AND user_id=".$usr->id) ) ? '{TEMPLATE: unsub_to_thread}' : '{TEMPLATE: sub_from_thread}';
	if ( $thread->locked == 'N' ) $post_reply = '{TEMPLATE: post_reply}';
	
	$prev_msg = $next_msg = 0;

if( @is_array($tree->kiddies) ) {

	reset($tree->kiddies);
	$stack[0] = &$tree;
	$stack_cnt = $tree->kiddie_count;
	$j=0;
	$lev = 0;
	
	$tree_data = '';
	while ($stack_cnt>0) {
		$cur = &$stack[$stack_cnt-1];
		
		if( isset($cur->subject) && empty($cur->sub_shown) ) {
			if( $cur->poster_id )
				$user_login = '{TEMPLATE: reg_user_link}';
			else
				$user_login = '{TEMPLATE: anon_user}';
			
			$width = 6*($lev-1);
				
			if( _uid && $cur->post_stamp > $usr->last_read && $cur->post_stamp > $last_thread_read )
				$read_indicator = '{TEMPLATE: tree_unread_message}';
			else
				$read_indicator = '{TEMPLATE: tree_read_message}';

			if( isset($cur->kiddies) && $cur->kiddie_count ) {
				if( $cur->id == $mid )
					$tree_data .= '{TEMPLATE: tree_branch_selected}';
				else 
					$tree_data .= '{TEMPLATE: tree_branch}';
			} else {
				if( $cur->id == $mid )
					$tree_data .= '{TEMPLATE: tree_entry_selected}';
				else 
					$tree_data .= '{TEMPLATE: tree_entry}';
			}
			
			if( $cur->id == $mid ) $prev_msg = $prev_id;
			if( $prev_id == $mid ) $next_msg = $cur->id;
			
			$prev_id = $cur->id;
			
			$cur->sub_shown = 1;
		}
		
		if( !isset($cur->kiddie_count) ) $cur->kiddie_count = 0;
		
		if ( $cur->kiddie_count && isset($cur->kiddie_pos) )
			++$cur->kiddie_pos;	
		else
			$cur->kiddie_pos = 0;
		
		if ( $cur->kiddie_pos < $cur->kiddie_count ) {
			++$lev;
			$stack[$stack_cnt++] = &$cur->kiddies[$cur->kiddie_pos];
		}
		else { // unwind the stack if needed
			unset($stack[--$stack_cnt]);
			--$lev;
		}
		
		unset($cur);
	}
}
	$message_data = tmpl_drawmsg($msg_obj,false,array($prev_msg,$next_msg));
	un_register_fps();

	if ( isset($usr) && $last_thread_read < $msg_obj->post_stamp ) $usr->register_thread_view($thread->id, $msg_obj->post_stamp, $msg_obj->id);
	
	$prev_th = $prev_thread_link = $next_th = $next_thread_link = NULL;
	get_prev_next_th_id($thread->forum_id, $thread->id, $prev_th, $next_th);

	if ( $prev_th )	$prev_thread_link = '{TEMPLATE: prev_thread_link}';
	if ( $next_th ) $next_thread_link = '{TEMPLATE: next_thread_link}';
	
	if ( _uid && ($MOD || is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'RATE')) && !bq("SELECT id FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id=".$thread->id." AND user_id="._uid) ) {
		$returnto_dec = urldecode($returnto_str);
		$rate_thread = '{TEMPLATE: rate_thread}';
	}
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: TREE_PAGE}