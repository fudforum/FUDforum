<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: subscribed.php.t,v 1.6 2002/08/07 12:18:43 hackie Exp $
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
	
	if ( !isset($usr) ) {
		std_error('login');
		exit();
	}
	
	if ( empty($start) ) $start = 0;
	if ( empty($count) ) $count = $THREADS_PER_PAGE;
	
	if ( isset($frm_id) && is_numeric($frm_id) ) {
		$frm_not = new fud_forum_notify;
		$frm_not->delete($usr->id, $frm_id);
		header("Location: {ROOT}?t=subscribed&"._rsidl.'rand='.get_random_value());
		exit();
	}
	
	if ( isset($th) && is_numeric($th) ) {
		$th_not = new fud_thread_notify;
		$th_not->delete($usr->id, $th);
		header("Location: {ROOT}?t=subscribed&"._rsidl.'rand='.get_random_value());
		exit();
	}
	
	if ( isset($ses) ) $ses->update('{TEMPLATE: subscribed_update}');
	
	{POST_HTML_PHP}
	
	$r=q("SELECT *, {SQL_TABLE_PREFIX}forum.id AS frm_id FROM {SQL_TABLE_PREFIX}forum_notify LEFT JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}forum_notify.forum_id={SQL_TABLE_PREFIX}forum.id WHERE {SQL_TABLE_PREFIX}forum_notify.user_id=".$usr->id." ORDER BY last_post_id DESC");

	$subscribed_forum_data = '';
	while ( $obj = db_rowobj($r) ) $subscribed_forum_data .= '{TEMPLATE: subscribed_forum_entry}';
	
	if( !db_count($r) ) $subscribed_forum_data = '{TEMPLATE: no_subscribed_forums}';
	qf($r);

	$total = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}thread_notify LEFT JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread_notify.thread_id={SQL_TABLE_PREFIX}thread.id LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}thread_notify.user_id=".$usr->id);
	
	$subscribed_thread_data = '';
	$r=q("SELECT *, {SQL_TABLE_PREFIX}thread.id AS th_id FROM {SQL_TABLE_PREFIX}thread_notify LEFT JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread_notify.thread_id={SQL_TABLE_PREFIX}thread.id LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}thread_notify.user_id=".$usr->id." ORDER BY last_post_id DESC LIMIT ".qry_limit($count,$start));
	
	while ( $obj = db_rowobj($r) ) $subscribed_thread_data .= '{TEMPLATE: subscribed_thread_entry}';
	
	if( !db_count($r) ) $subscribed_thread_data = '{TEMPLATE: no_subscribed_threads}';
	qf($r);
	
	$pager = tmpl_create_pager($start, $count, $total, "{ROOT}?t=subscribed&a=1&"._rsid, "#fff");
		
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: SUBSCRIBED_PAGE}