<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: thread.php.t,v 1.4 2002/07/16 16:33:07 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	include_once "GLOBALS.php";
	{PRE_HTML_PHP}
	
	if( empty($frm_id) || !is_numeric($frm_id) ) invl_inp_err();
	
	$frm = new fud_forum;
	$frm->get($frm_id);
	
	if( empty($frm->cat_id) ) invl_inp_err();
	
	$GLOBALS['__RESOURCE_ID'] = $frm->id;
	
	if( !is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'READ') )
		error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');	
	
	
	if ( isset($ses) ) $ses->update('{TEMPLATE: thread_update}', $GLOBALS['__RESOURCE_ID']);

	if ( isset($usr) ) {
		if ( $frm->is_moderator($usr->id) || $usr->is_mod == 'A' ) $MOD = 1;
		$ppg = $usr->posts_ppg;
		
		fud_use('forum_notify.inc');
		$frm_not = new fud_forum_notify;
		
		if ( isset($sub) && $sub ) 
			$frm_not->add($usr->id, $frm->id);
		else if ( isset($unsub) && $unsub ) 
			$frm_not->delete($usr->id, $frm->id);
	}
	
	if ( $MOD ) {
		fud_use('imsg.inc');
		fud_use('imsg_edt.inc');
	}
	
	{POST_HTML_PHP}
	$TITLE_EXTRA = ': {TEMPLATE: thread_title}';

	if ( !isset($cat) ) {
		$cat = new fud_cat;
		$cat->get_cat($frm->cat_id);
	}
	
	$returnto = 'returnto='.urlencode("{ROOT}?t=thread&frm_id=".$frm_id.'&'._rsid);

	if ( isset($usr) ) {
		if( !isset($start) ) $start='';
		if( !isset($count) ) $count='';
		
		if ( is_forum_notified($usr->id, $frm->id) ) 
			$subscribe = '{TEMPLATE: unsubscribe_link}';
		else 
			$subscribe = '{TEMPLATE: subscribe_link}';
	}

	if ( empty($start) ) $start = 0;
	if ( empty($ppg) ) $ppg = $THREADS_PER_PAGE;

	if( isset($usr) ) {
		$lread_s = ' {SQL_TABLE_PREFIX}read.last_view AS last_thread_view, ';
		$lread_f = ' LEFT JOIN {SQL_TABLE_PREFIX}read ON {SQL_TABLE_PREFIX}thread.id={SQL_TABLE_PREFIX}read.thread_id AND {SQL_TABLE_PREFIX}read.user_id='.$usr->id.' ';
	}else $lread_s=$lread_f='';

	$result = q('SELECT {SQL_TABLE_PREFIX}thread.*, '.$lread_s.' {SQL_TABLE_PREFIX}msg.attach_cnt, {SQL_TABLE_PREFIX}msg.poll_id, {SQL_TABLE_PREFIX}msg.subject, {SQL_TABLE_PREFIX}users.alias AS login, {SQL_TABLE_PREFIX}users.id AS starter_id, {SQL_TABLE_PREFIX}msg.icon AS th_icon, fud_users_2.id AS last_poster_id, fud_users_2.alias AS last_poster_login, fud_msg_2.id AS last_post_id, fud_msg_2.post_stamp AS last_post_stamp, {SQL_TABLE_PREFIX}msg.post_stamp AS creation_date FROM {SQL_TABLE_PREFIX}thread_view INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread_view.thread_id={SQL_TABLE_PREFIX}thread.id LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}users.id = {SQL_TABLE_PREFIX}msg.poster_id LEFT JOIN {SQL_TABLE_PREFIX}msg AS fud_msg_2 ON fud_msg_2.id={SQL_TABLE_PREFIX}thread.last_post_id LEFT JOIN {SQL_TABLE_PREFIX}users AS fud_users_2 ON fud_users_2.id=fud_msg_2.poster_id '.$lread_f.' WHERE {SQL_TABLE_PREFIX}thread_view.forum_id='.$frm->id.' AND {SQL_TABLE_PREFIX}thread_view.page='.(floor($start/$ppg)+1).' ORDER BY {SQL_TABLE_PREFIX}thread_view.pos ASC');

	if ( !db_count($result) ) {
		$no_messages = '{TEMPLATE: no_messages}';
	}
	else {
		if ( $MOD || is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'MOVE') || is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'DEL') ) $admin_heading_row = '{TEMPLATE: admin_heading_row}';
	
	$POSTS_PER_PAGE = ($usr->posts_ppg)?$usr->posts_ppg:$GLOBALS['POSTS_PER_PAGE'];
	
	$thread_list_table_data='';
	while ( $obj = db_rowobj($result) ) {
		if ( $obj->moved_to ) {
			list($name, $d_frm_id) = db_singlearr(q("SELECT name, id FROM {SQL_TABLE_PREFIX}forum WHERE id=".$obj->moved_to));
			$thread_list_table_data .= '{TEMPLATE: thread_row_moved}';
			continue;
		}
		
		$mini_pager_data=$mini_thread_pager=$thread_poll_indicator=$thread_attach_indicator=$first_unread_msg_link=$thread_read_status='';
		
		$msg_count = $obj->replies+1;
		
		if ( $msg_count > $POSTS_PER_PAGE && d_thread_view=='msg' ) {
			$pgcount = ceil($msg_count/$POSTS_PER_PAGE);
			$i = ( $THREAD_MSG_PAGER < $pgcount ) ? $pgcount-$THREAD_MSG_PAGER : 0;
			if( $i ) $mini_pager_data = '{TEMPLATE: mini_pager_limiter}';

			for( $i; $i<$pgcount; $i++ ) {
				$st_pos = $i*$POSTS_PER_PAGE;
				$pg_num = $i+1;
				$mini_pager_data .= '{TEMPLATE: mini_pager_entry}';
			}
		
			if ( $mini_pager_data ) $mini_thread_pager = '{TEMPLATE: mini_thread_pager}';
		}
		
		if( $obj->poll_id ) 
			$thread_poll_indicator = '{TEMPLATE: thread_poll_indicator}';		
		
		if( $obj->attach_cnt ) 
			$thread_attach_indicator = '{TEMPLATE: thread_attach_indicator}';

		if ( isset($usr) ) {
			if( $usr->last_read < $obj->last_post_stamp && $obj->last_post_stamp>$obj->last_thread_view ) {
				if ( $obj->locked == 'Y' ) 
					$thread_read_status = '{TEMPLATE: thread_unread_locked}';
				else 
					$thread_read_status = '{TEMPLATE: thread_unread}';
	
				$first_unread_msg_link = '{TEMPLATE: first_unread_msg_link}';
			}
		}

		if( !$thread_read_status ) {
			if ( $obj->locked == 'Y' ) 
				$thread_read_status = '{TEMPLATE: thread_read_locked}';
			else if ( !isset($usr) )
				$thread_read_status = '{TEMPLATE: thread_read_unreg}';
			else
				$thread_read_status = '{TEMPLATE: thread_read}';	 
		}
		
		
		if ( $obj->th_icon )
			$thread_icon = '{TEMPLATE: thread_icon}';
		else 
		 	$thread_icon = '{TEMPLATE: thread_icon_none}';
		
		if ( $obj->rating )
			$rating = '{TEMPLATE: rating}';
		else 
			$rating = '{TEMPLATE: rating_none}';
		
		if ( $obj->is_sticky == 'Y' ) 
			$stick_status = $obj->ordertype=='STICKY' ? '{TEMPLATE: sticky}' : '{TEMPLATE: announcement}';
		else
			$stick_status = '';	
			
		if( $obj->last_poster_id ) {
			$reg_user_link = htmlspecialchars(trim_show_len($obj->last_poster_login,'LOGIN'));
			$user_link = '{TEMPLATE: reg_user_link}';
		}
		else {
			$unreg_user_link = htmlspecialchars(trim_show_len($GLOBALS["ANON_NICK"],'LOGIN'));
			$user_link = '{TEMPLATE: unreg_user_link}'; 
		}	

		if( $obj->starter_id ) {
			$first_post_login = htmlspecialchars(trim_show_len($obj->login,'LOGIN'));
			$first_post_login = '{TEMPLATE: first_post_reg_user_link}';
		}	
		else {
			$first_post_login = htmlspecialchars(trim_show_len($GLOBALS["ANON_NICK"],'LOGIN'));
			$first_post_login = '{TEMPLATE: first_post_unreg_user_link}';
		}	

		$thread_first_post = '{TEMPLATE: thread_first_post}';
		
		if( $MOD || (is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'DEL') && is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'MOVE')) ) 
			$admin_control_row = '{TEMPLATE: admin_control_row_all}';
		else if ( is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'MOVE') ) 
			$admin_control_row = '{TEMPLATE: admin_control_row_move}';
		else if ( is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'DEL') ) 
			$admin_control_row = '{TEMPLATE: admin_control_row_del}';	

		$thread_list_table_data .= '{TEMPLATE: thread_row}';
	}
	
	qf($result); 	
}

	$page_pager = tmpl_create_pager($start, $ppg, $frm->thread_count, '{ROOT}?t=thread&frm_id='.$frm_id.'&'._rsid);

	{POST_PAGE_PHP_CODE}
?>	
{TEMPLATE: THREAD_PAGE}	
<?php	
	if ( isset($usr) ) $usr->register_forum_view($frm->id);
?>