<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: admincp.inc.t,v 1.10 2003/04/21 14:14:38 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$thr_exch = $admin_cp = $accounts_pending_approval = $group_mgr = $reported_msgs = $custom_avatar_queue = $mod_que = '';

if (_uid) {
	if ($usr->is_mod == 'Y' || $usr->is_mod == 'A') {
		if ($usr->is_mod == 'A') {
			if ($avatar_count = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}users WHERE avatar_approved='N'")) {
				$custom_avatar_queue = '{TEMPLATE: custom_avatar_queue}';
			}
			if ($report_count = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}msg_report')) {
				$reported_msgs = '{TEMPLATE: reported_msgs}';
			}
				
			if ($thr_exchc = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}thr_exchange')) {
				$thr_exch = '{TEMPLATE: thr_exch}';
			}
			
			if ($GLOBALS['MODERATE_USER_REGS'] == 'Y' && ($accounts_pending_approval = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}users WHERE acc_status='P'"))) {
				$accounts_pending_approval = '{TEMPLATE: accounts_pending_approval}';
			}
				
			$q_limit = '';	
		} else {
			if ($report_count = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}msg_report mr INNER JOIN {SQL_TABLE_PREFIX}msg m ON mr.msg_id=m.id INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}mod mm ON t.forum_id=mm.forum_id AND mm.user_id='._uid)) {
				$reported_msgs = '{TEMPLATE: reported_msgs}';
			}
			
			if ($thr_exchc = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}thr_exchange INNER JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}mod.user_id='._uid.' AND {SQL_TABLE_PREFIX}thr_exchange.frm={SQL_TABLE_PREFIX}mod.forum_id')) {
				$thr_exch = '{TEMPLATE: thr_exch}';
			}
			
			$q_limit = ' INNER JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}mod.forum_id AND {SQL_TABLE_PREFIX}mod.user_id='._uid;
		}
		
		if ($approve_count = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id ".$q_limit." WHERE {SQL_TABLE_PREFIX}msg.approved='N' AND {SQL_TABLE_PREFIX}forum.moderated='Y'")) {
			$mod_que = '{TEMPLATE: mod_que}';
		}
	}
	if ($usr->is_mod == 'A' || $usr->group_leader_list) {
		$group_mgr = '{TEMPLATE: group_mgr}';
	}
	
	if ($thr_exch || $accounts_pending_approval || $group_mgr || $reported_msgs || $custom_avatar_queue || $mod_que) {
		$admin_cp = '{TEMPLATE: admin_cp}';
	} 
}
?>