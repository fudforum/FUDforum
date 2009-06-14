<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: admincp.inc.t,v 1.32 2009/06/14 09:03:48 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

if (_uid) {
	$admin_cp = $accounts_pending_approval = $group_mgr = $reported_msgs = $custom_avatar_queue = $mod_que = $thr_exch = '';

	if ($usr->users_opt & 524288 || $is_a) {
		if ($is_a) {
			if ($FUD_OPT_1 & 32 && ($avatar_count = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}users WHERE users_opt>=16777216 AND (users_opt & 16777216) > 0'))) {
				$custom_avatar_queue = '{TEMPLATE: custom_avatar_queue}';
			}
			if ($report_count = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}msg_report')) {
				$reported_msgs = '{TEMPLATE: reported_msgs}';
			}

			if ($thr_exchc = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}thr_exchange')) {
				$thr_exch = '{TEMPLATE: thr_exch}';
			}

			if ($FUD_OPT_2 & 1024 && ($accounts_pending_approval = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}users WHERE users_opt>=2097152 AND (users_opt & 2097152) > 0 AND id > 0'))) {
				$accounts_pending_approval = '{TEMPLATE: accounts_pending_approval}';
			} else {
				$accounts_pending_approval = '';
			}

			$q_limit = '';
		} else {
			if ($report_count = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}msg_report mr INNER JOIN {SQL_TABLE_PREFIX}msg m ON mr.msg_id=m.id INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}mod mm ON t.forum_id=mm.forum_id AND mm.user_id='._uid)) {
				$reported_msgs = '{TEMPLATE: reported_msgs}';
			}

			if ($thr_exchc = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}thr_exchange te INNER JOIN {SQL_TABLE_PREFIX}mod m ON m.user_id='._uid.' AND te.frm=m.forum_id')) {
				$thr_exch = '{TEMPLATE: thr_exch}';
			}

			$q_limit = ' INNER JOIN {SQL_TABLE_PREFIX}mod mm ON f.id=mm.forum_id AND mm.user_id='._uid;
		}

		if ($approve_count = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}msg m INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id '.$q_limit.' WHERE m.apr=0 AND (f.forum_opt>=2 AND (f.forum_opt & 2) > 0)')) {
			$mod_que = '{TEMPLATE: mod_que}';
		}
	} else if ($usr->users_opt & 268435456 && $FUD_OPT_2 & 1024 && ($accounts_pending_approval = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}users WHERE users_opt>=2097152 AND (users_opt & 2097152) > 0 AND id > 0'))) {
		$accounts_pending_approval = '{TEMPLATE: accounts_pending_approval}';
	} else {
		$accounts_pending_approval = '';
	}
	if ($is_a || $usr->group_leader_list) {
		$group_mgr = '{TEMPLATE: group_mgr}';
	}

	if ($thr_exch || $accounts_pending_approval || $group_mgr || $reported_msgs || $custom_avatar_queue || $mod_que) {
		$admin_cp = '{TEMPLATE: admin_cp}';
	}
} else {
	$admin_cp = '';
}
?>
