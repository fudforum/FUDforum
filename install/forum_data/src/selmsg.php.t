<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: selmsg.php.t,v 1.17 2002/09/10 00:24:41 hackie Exp $
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
	
function ifstr($opt1, $opt2, $str)
{	
	return ( strlen($str) ) ? $opt1 : $opt2;
}
	
	if ( isset($ses) ) $ses->update('{TEMPLATE: selmsg_update}');
	
	if ( !isset($usr) && empty($count) ) 
		$count = $POSTS_PER_PAGE;
	else if ( isset($usr) ) 
		$count = ($usr->posts_ppg)?$usr->posts_ppg:$GLOBALS['POSTS_PER_PAGE'];

	if ( !isset($start) ) $start = 0;
		
	if ( ((isset($reply_count)&&!strlen($reply_count))||!isset($reply_count)) && empty($date) && empty($unread) && empty($sub_forums) && empty($sub_threads) ) {
		$date = 'today';
	}	
	
	if( isset($unread) && !strlen($unread) ) $unread='';
	if( isset($reply_count) && !strlen($reply_count) ) $reply_count='';
	if( isset($date) && !strlen($date) ) $date = '';
	if( isset($unread_join) && !strlen($unread_join) ) $unread_join = '';
	if( isset($frm_id) && !strlen($frm_id) ) $frm_id='';
	if( isset($th) && !strlen($th) ) $th='';
	
	/* figure out the query */

	list($day, $month, $year) = explode(" ", strftime("%d %m %Y", __request_timestamp__));
	$tm_today_start = mktime(0, 0, 0, $month, $day, $year);
	$tm_today_end = $tm_today_start + 86400;
	
	$reply_lmt=$thread_lmt=$forum_lmt='';
	
	if ( isset($frm_id) && strlen($frm_id) ) $forum_lmt = 'AND {SQL_TABLE_PREFIX}forum.id='.$frm_id.' ';
	if ( isset($th) && strlen($th) ) $thread_lmt = 'AND {SQL_TABLE_PREFIX}thread.id='.$th.' ';
	if ( isset($reply_count) && strlen($reply_count) ) $reply_lmt = ' AND {SQL_TABLE_PREFIX}thread.replies='.$reply_count.' ';
	
	$unread_where='';
	if ( isset($unread) && strlen($unread) && isset($usr) ) {
		$unread_join = 'LEFT JOIN {SQL_TABLE_PREFIX}read ON {SQL_TABLE_PREFIX}read.user_id='.$usr->id.' AND {SQL_TABLE_PREFIX}read.thread_id={SQL_TABLE_PREFIX}msg.thread_id';
		$unread_where = ' AND (({SQL_TABLE_PREFIX}read.last_view<{SQL_TABLE_PREFIX}msg.post_stamp AND {SQL_TABLE_PREFIX}read.msg_id IS NOT NULL) OR ({SQL_TABLE_PREFIX}read.msg_id IS NULL AND {SQL_TABLE_PREFIX}msg.post_stamp>'.intzero($usr->last_read).'))';
	}
	
	$date_limit = '';
	if ( isset($date) && $date=='today' ) {
		$date_limit = "
			AND {SQL_TABLE_PREFIX}msg.post_stamp>".$tm_today_start."
			AND {SQL_TABLE_PREFIX}msg.post_stamp<".$tm_today_end;
	}
	
	
	if ( isset($usr) ) {
		if ( !empty($sub_threads) ) {
			$sub_thread_join = 'INNER JOIN {SQL_TABLE_PREFIX}thread_notify
						ON {SQL_TABLE_PREFIX}thread_notify.thread_id={SQL_TABLE_PREFIX}thread.id AND {SQL_TABLE_PREFIX}thread_notify.user_id='.$usr->id;
		}
		
		if ( !empty($sub_forums) ) {
			$sub_forum_join = 'INNER JOIN {SQL_TABLE_PREFIX}forum_notify
						ON {SQL_TABLE_PREFIX}forum_notify.forum_id={SQL_TABLE_PREFIX}forum.id AND {SQL_TABLE_PREFIX}forum_notify.user_id='.$usr->id;
		}
	}
	
	if ( !empty($last_id) && !empty($mark_page_read) ) {
		$id_limit = ' AND {SQL_TABLE_PREFIX}msg.id<='.$last_id;
	}
	
	if( $usr->is_mod!='A' ) {
		$fids = get_all_perms(_uid);
		if( empty($fids) ) $fids = 0;
		$qry_limit = '{SQL_TABLE_PREFIX}forum.id IN ('.$fids.') AND ';
	}
	else
		$qry_limit = '';		
		
	$total = q_singleval('SELECT
			count(*)
		FROM
			{SQL_TABLE_PREFIX}msg
			INNER JOIN {SQL_TABLE_PREFIX}thread
				ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
			INNER JOIN {SQL_TABLE_PREFIX}forum
				ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id
			INNER JOIN {SQL_TABLE_PREFIX}cat
				ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id
			'.$perms.'
			'.$sub_forum_join.'
			'.$sub_thread_join.'
			'.$unread_join.'
		WHERE
			'.$qry_limit.'
			{SQL_TABLE_PREFIX}msg.approved=\'Y\'
			'.$forum_lmt.'
			'.$thread_lmt.'
			'.$reply_lmt.'
			'.$unread_where.'
			'.$date_limit.'
			'.(isset($id_limit)?$id_limit:''));

	$rid = q('SELECT 
			{SQL_TABLE_PREFIX}msg.id 
		FROM
			{SQL_TABLE_PREFIX}msg
			INNER JOIN {SQL_TABLE_PREFIX}thread
				ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
			INNER JOIN {SQL_TABLE_PREFIX}forum
				ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id
			INNER JOIN {SQL_TABLE_PREFIX}cat
				ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id
			'.$sub_forum_join.'
			'.$sub_thread_join.'
			'.$unread_join.'
		WHERE
			'.$qry_limit.'
			{SQL_TABLE_PREFIX}msg.approved=\'Y\'
			'.$forum_lmt.'
			'.$thread_lmt.'
			'.$reply_lmt.'
			'.$unread_where.'
			'.$date_limit.'
			'.(isset($id_limit)?$id_limit:'').'
	ORDER BY 
		{SQL_TABLE_PREFIX}forum.last_post_id,
		{SQL_TABLE_PREFIX}thread.last_post_id, 
		{SQL_TABLE_PREFIX}msg.post_stamp
	LIMIT '.qry_limit($count, $start));
	
	
	if( db_count($rid) ) {
		$id_list='{SQL_TABLE_PREFIX}msg.id IN(';
		$m_count=0;
		while ( list($msgp_id) = db_rowarr($rid) ) { $id_list .= $msgp_id.','; $m_count++; }
		qf($rid);
		$id_list = substr($id_list, 0, -1).')';
	
		$r = q('SELECT 
			{SQL_TABLE_PREFIX}msg.*, 
			{SQL_TABLE_PREFIX}forum.id AS frm_id,
			{SQL_TABLE_PREFIX}forum.message_threshold,
			{SQL_TABLE_PREFIX}thread.last_post_id,
			{SQL_TABLE_PREFIX}thread.id AS th_id,
			{SQL_TABLE_PREFIX}thread.locked AS locked,
			{SQL_TABLE_PREFIX}thread.forum_id,
			{SQL_TABLE_PREFIX}thread.replies,
			{SQL_TABLE_PREFIX}users.id AS user_id, 
			{SQL_TABLE_PREFIX}users.alias AS login,
			{SQL_TABLE_PREFIX}users.display_email,
			{SQL_TABLE_PREFIX}users.custom_status,
			{SQL_TABLE_PREFIX}users.email, 
			{SQL_TABLE_PREFIX}users.posted_msg_count, 
			{SQL_TABLE_PREFIX}users.join_date, 
			{SQL_TABLE_PREFIX}users.location,
			{SQL_TABLE_PREFIX}users.avatar_approved,
			{SQL_TABLE_PREFIX}users.avatar_loc,
			{SQL_TABLE_PREFIX}users.sig,
			{SQL_TABLE_PREFIX}users.icq,
			{SQL_TABLE_PREFIX}users.jabber,
			{SQL_TABLE_PREFIX}users.aim,
			{SQL_TABLE_PREFIX}users.msnm,
			{SQL_TABLE_PREFIX}users.yahoo,
			{SQL_TABLE_PREFIX}users.email_messages,
			{SQL_TABLE_PREFIX}users.invisible_mode,
			{SQL_TABLE_PREFIX}users.is_mod,
			{SQL_TABLE_PREFIX}forum.name AS frm_name, 
			fud_msg_thread.subject AS thr_subject,
			{SQL_TABLE_PREFIX}avatar.img AS avatar,
			{SQL_TABLE_PREFIX}level.name AS level_name,
			{SQL_TABLE_PREFIX}level.pri AS level_pri,
			{SQL_TABLE_PREFIX}level.img AS level_img,
			{SQL_TABLE_PREFIX}ses.time_sec
		FROM
			{SQL_TABLE_PREFIX}msg 
			INNER JOIN {SQL_TABLE_PREFIX}thread 
				ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
			INNER JOIN {SQL_TABLE_PREFIX}forum 
				ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}thread.forum_id 
			INNER JOIN {SQL_TABLE_PREFIX}cat
				ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id	
			INNER JOIN {SQL_TABLE_PREFIX}msg AS fud_msg_thread 
				ON fud_msg_thread.id={SQL_TABLE_PREFIX}thread.root_msg_id 
			LEFT JOIN {SQL_TABLE_PREFIX}users 
				ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id 
			LEFT JOIN {SQL_TABLE_PREFIX}ses
				ON {SQL_TABLE_PREFIX}ses.user_id={SQL_TABLE_PREFIX}msg.poster_id
			LEFT JOIN {SQL_TABLE_PREFIX}avatar
				ON {SQL_TABLE_PREFIX}users.avatar={SQL_TABLE_PREFIX}avatar.id
			LEFT JOIN {SQL_TABLE_PREFIX}level
				ON {SQL_TABLE_PREFIX}users.level_id={SQL_TABLE_PREFIX}level.id
			WHERE
				'.$id_list.' 
			ORDER BY {SQL_TABLE_PREFIX}forum.last_post_id,{SQL_TABLE_PREFIX}thread.last_post_id, {SQL_TABLE_PREFIX}msg.post_stamp');
	}
	else $r = $rid;	

	$url_param = _rsid.'&amp;unread='.(isset($unread)?$unread:'').'&amp;reply_count='.$reply_count.'&amp;date='.(isset($date)?$date:'').'&amp;frm_id='.(isset($frm_id)?$frm_id:'').'&amp;th='.(isset($th)?$th:'').'&amp;rand='.get_random_value();
	
	if ( !empty($mark_page_read) && isset($usr) ) {
		while ( $obj = db_rowobj($r) ) {
			if ( isset($obj2) && $obj2->th_id != $obj->th_id ) { 
				$usr->register_thread_view($obj2->th_id, $obj2->post_stamp, $obj2->id);
				$usr->register_forum_view($obj->frm_id);
			}
			$obj2 = $obj;
		}
		
		if ( isset($obj2) ) { 
			$usr->register_thread_view($obj2->th_id, $obj2->post_stamp, $obj2->id);
			$usr->register_forum_view($obj2->frm_id);
		}
		
		header('Location: {ROOT}?t=selmsg&'.str_replace('&amp;', '&', $url_param).'rand='.get_random_value());
		exit();
	}
	$dth_id='';
	
	{POST_HTML_PHP}
	
	function valstat($a)
	{
		return ( ($a) ? '{TEMPLATE: status_indicator_on}' : '{TEMPLATE: status_indicator_off}');
	}

	if ( !empty($date) && $date == 'today' ) {
		$dt_opt = '';
		$s_today = valstat(1);
	}
	else {
		$dt_opt = 'today';
		$s_today = valstat(0);
	}

	if ( isset($reply_count) && strlen($reply_count) ) {
		$rp_opt = '';
		$s_unu = valstat(1);
	}
	else {
		$rp_opt = '0';
		$s_unu = valstat(0);
	}
	
	if ( isset($usr) ) {
		if ( !empty($unread) && strlen($unread) ) {
			$un_opt = '';
			$s_unread = valstat(1);
		}
		else {
			$un_opt = '1';
			$s_unread = valstat(0);
		}
		
		if ( !empty($sub_forums) && isset($usr) ) {
			$frm_opt = '';
			$s_subf = valstat(1);
		}
		else if ( isset($usr) ) {
			$frm_opt = '1';
			$s_subf = valstat(0);
		}
	
		if ( $sub_threads && isset($usr) ) {
			$th_opt = '';
			$s_subt = valstat(1);
		}
		else if ( isset($usr) ) {
			$th_opt = '1';
			$s_subt = valstat(0);
		}	
		
		$subscribed_thr = '{TEMPLATE: subscribed_thr}';
		$subscribed_frm = '{TEMPLATE: subscribed_frm}';
		$unread_messages = '{TEMPLATE: unread_messages}';
	}
	
	$todays_posts = '{TEMPLATE: todays_posts}';
	$unanswered = '{TEMPLATE: unanswered}';	

	if ( $unread && $total ) {
		$LAST_ID=NULL;
		while ( $obj = db_rowobj($r) ) {
			if ( $LAST_ID < $obj->id ) $LAST_ID = $obj->id;
		}
		db_seek($r, 0);
		$LAST_ID = 'last_id='.$LAST_ID;
		$more_unread_messages = '{TEMPLATE: more_unread_messages}';
	}
	
	if ( ($m_count = db_count($r)) ) {
		$m_count--;
		$message_data='';
		while ( $obj = db_rowobj($r) ) {
			if ( $dfrm_id != $obj->frm_id ) { 
				if( isset($usr) ) {
					unset($frm);
					$frm = new fud_forum;
					$frm->id = $obj->frm_id;
					unset($MOD);
					if ( $frm->is_moderator($usr->id) || $usr->is_mod == 'A' ) $MOD = 1;
				}	
				$message_data .= '{TEMPLATE: forum_row}';
				$dfrm_id = $obj->frm_id; 
			}
			
			if ( $dth_id != $obj->th_id ) { 
				
				
				if( isset($usr) ) {
					unset($thread);
					$thread = new fud_thread;
					$thread->id = $obj->th_id;
					$thread->locked = $obj->locked;
					$thread->inc_view_count();
				}
				
				$message_data .= '{TEMPLATE: thread_row}';
				$dth_id = $obj->th_id; 
			}
			
			$GLOBALS["returnto"] = 'returnto='.urlencode($GLOBALS["HTTP_SERVER_VARS"]["REQUEST_URI"]);
			$o_start = $start;
			$start = $obj->replies-$GLOBALS['POSTS_PER_PAGE'];
			if( $start < 0 ) $start = 0;
			$message_data .= tmpl_drawmsg($obj, $m_count);
			$start = $o_start;
			$msg_id = $obj->id;
			$p_frm_id = $obj->frm_id;
		}
		un_register_fps();
	}
	else 
		$message_data = '{TEMPLATE: no_result}';

	if( empty($unread) ) 
		$pager = tmpl_create_pager($start, $count, $total, '{ROOT}?t=selmsg&amp;'."date=".$date."&amp;unread=".$unread."&amp;"._rsid."&amp;frm_id=".$frm_id."&amp;th=".$th."&amp;reply_count=".$reply_count);
	else if ( $unread && $total ) {
		/* nop */
	}
	else if ( !(empty($unread) && empty($total)) && isset($usr) && empty($th) && empty($frm_id) && empty($date) && $reply_count!=='0' ) 
		$usr->mark_all_read();

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: SELMSG_PAGE}