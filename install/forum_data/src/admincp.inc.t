<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admincp.inc.t,v 1.5 2002/07/22 18:06:34 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if( _uid ) {
	$thr_exch = $group_mgr = $reported_msgs = $custom_avatar_queue = $mod_que = '';

	if( $usr->is_mod == 'Y' || $usr->is_mod == 'A' ) {
		if( $GLOBALS["usr"]->is_mod == 'A' ) {
			if( $avatar_count = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}users WHERE avatar_approved='N'") ) 
				$custom_avatar_queue = '{TEMPLATE: custom_avatar_queue}';
	
			if( $report_count = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg_report") ) 
				$reported_msgs = '{TEMPLATE: reported_msgs}';
				
			if( $thr_exchc = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}thr_exchange") )
				$thr_exch = '{TEMPLATE: thr_exch}';
				
			$q_limit = '';	
		}	
		else {
			if( $report_count = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg_report INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}msg_report.msg_id={SQL_TABLE_PREFIX}msg.id INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id INNER JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}mod.forum_id AND {SQL_TABLE_PREFIX}mod.user_id=".$GLOBALS["usr"]->id) )
				$reported_msgs = '{TEMPLATE: reported_msgs}';
			
			if( $thr_exchc = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}thr_exchange INNER JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}mod.user_id=".$usr->id." AND {SQL_TABLE_PREFIX}thr_exchange.frm={SQL_TABLE_PREFIX}mod.forum_id") ) 
				$thr_exch = '{TEMPLATE: thr_exch}';
			
			$q_limit = " INNER JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}mod.forum_id AND {SQL_TABLE_PREFIX}mod.user_id="._uid;	
		}
		
		if( $approve_count = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id ".$q_limit." WHERE {SQL_TABLE_PREFIX}msg.approved='N' AND {SQL_TABLE_PREFIX}forum.moderated='Y'") ) 
			$mod_que = '{TEMPLATE: mod_que}';
	}
	if( $usr->is_mod=='A' || bq("SELECT id FROM {SQL_TABLE_PREFIX}group_members WHERE user_id="._uid." AND group_leader='Y' LIMIT 1") )
		$group_mgr = '{TEMPLATE: group_mgr}';
	
	if( $thr_exch || $group_mgr || $reported_msgs || $custom_avatar_queue || $mod_que ) $admin_cp = '{TEMPLATE: admin_cp}';
}	
?>