<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: uc.php.t,v 1.10 2005/12/07 18:07:45 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

/*{PRE_HTML_PHP}*/

	if (__fud_real_user__) {
		is_allowed_user($usr);
	} else {
		std_error('login');
	}

	ses_update_status($usr->sid, '{TEMPLATE: uc_page_title}');

/*{POST_HTML_PHP}*/

	if (!empty($_GET['ufid']) && sq_check(0, $usr->sq)) {
		forum_notify_del(_uid, (int)$_GET['ufid']);
	}
	if (!empty($_GET['utid']) && sq_check(0, $usr->sq)) {
		thread_notify_del(_uid, (int)$_GET['utid']);
	}
	if (!empty($_GET['ubid']) && sq_check(0, $usr->sq)) {
		buddy_delete(_uid, (int)$_GET['ubid']);
	}

	$uc_buddy_ents = '';
	$c = uq("SELECT u.id, u.alias, u.last_visit, (users_opt & 32768) FROM {SQL_TABLE_PREFIX}buddy b INNER JOIN {SQL_TABLE_PREFIX}users u ON b.bud_id=u.id WHERE b.user_id="._uid." ORDER BY u.last_visit DESC");
	while ($r = db_rowarr($c)) {
		$uc_pm = ($FUD_OPT_1 & 1024) ? '{TEMPLATE: uc_pm}' : '';
		$obj->login = $r[1];
		$uc_online = (!$r[3] && ($r[2] + $LOGEDIN_TIMEOUT * 60) > __request_timestamp__) ? '{TEMPLATE: uc_online_indicator}' : '{TEMPLATE: uc_offline_indicator}';
		$uc_buddy_ents .= '{TEMPLATE: uc_buddy_ent}';
	}
	unset($c);

	$uc_new_pms = '';
	$c = uq("SELECT m.ouser_id, u.alias, m.post_stamp, m.subject, m.id FROM {SQL_TABLE_PREFIX}pmsg m INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=m.ouser_id WHERE m.duser_id="._uid." AND fldr=1 AND read_stamp=0 ORDER BY post_stamp DESC LIMIT ".($usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE));
	while ($r = db_rowarr($c)) {
		$uc_new_pms .= '{TEMPLATE: uc_new_pm_ent}';
	}
	unset($c);
	if ($uc_new_pms) {
		$uc_new_pms = '{TEMPLATE: uc_new_pm}';
	}

	$uc_sub_forum = '';
	$c = uq("SELECT
		f.name, f.id, f.descr, f.thread_count, f.post_count,
		u.alias,
		m.subject, m.id AS mid, m.post_stamp, m.poster_id,
		c.name AS cat_name
		FROM {SQL_TABLE_PREFIX}forum_notify fn
		INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=fn.forum_id
		INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
		INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=f.id
		LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id="._uid." AND g2.resource_id=f.id
		LEFT JOIN {SQL_TABLE_PREFIX}msg m ON f.last_post_id=m.id
		LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=m.poster_id
		LEFT JOIN {SQL_TABLE_PREFIX}forum_read fr ON fr.forum_id=f.id AND fr.user_id="._uid."
		LEFT JOIN {SQL_TABLE_PREFIX}mod mo ON mo.user_id="._uid." AND mo.forum_id=f.id
		WHERE fn.user_id="._uid."
		AND ".$usr->last_read." < m.post_stamp AND (fr.last_view IS NULL OR m.post_stamp > fr.last_view)
		".($is_a ? '' : " AND (mo.id IS NOT NULL OR (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 1) > 0)")."
		ORDER BY m.post_stamp DESC");
	while ($r = db_rowobj($c)) {
		$uc_sub_forum .= '{TEMPLATE: uc_sub_forum}';
	}
	if ($uc_sub_forum) {
		$uc_sub_forum = '{TEMPLATE: uc_sub_forums}';
	}
	unset($c);

	$uc_sub_topic = '';
	$ppg = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;
	$c = uq("SELECT
			m2.subject, m.post_stamp, m.poster_id,
			u.alias,
			t.replies, t.views, t.thread_opt, t.id, t.last_post_id
		FROM {SQL_TABLE_PREFIX}thread_notify tn
		INNER JOIN {SQL_TABLE_PREFIX}thread t ON tn.thread_id=t.id
		INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.last_post_id=m.id
		INNER JOIN {SQL_TABLE_PREFIX}msg m2 ON t.root_msg_id=m2.id
		INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=t.forum_id
		LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id="._uid." AND g2.resource_id=t.forum_id
		LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=m.poster_id
		LEFT JOIN {SQL_TABLE_PREFIX}read r ON t.id=r.thread_id AND r.user_id="._uid."
		LEFT JOIN {SQL_TABLE_PREFIX}mod mo ON mo.user_id="._uid." AND mo.forum_id=t.forum_id
		WHERE tn.user_id="._uid." AND m.post_stamp > ".$usr->last_read." AND m.post_stamp > r.last_view ".
		($is_a ? '' : " AND (mo.id IS NOT NULL OR (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 1) > 0)").
		"ORDER BY m.post_stamp DESC LIMIT ".($usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE));
	while ($r = db_rowobj($c)) {
		$msg_count = $r->replies + 1;
		if ($msg_count > $ppg && $usr->users_opt & 256) {
			if ($THREAD_MSG_PAGER < ($pgcount = ceil($msg_count / $ppg))) {
				$i = $pgcount - $THREAD_MSG_PAGER;
				$mini_pager_data = '{TEMPLATE: uc_mini_pager_limiter}';
			} else {
				$mini_pager_data = '';
				$i = 0;
			}
			while ($i < $pgcount) {
				$mini_pager_data .= '{TEMPLATE: uc_mini_pager_entry}';
			}
			$mini_thread_pager = $mini_pager_data ? '{TEMPLATE: uc_mini_thread_pager}' : '';
		} else {
			$mini_thread_pager = '';
		}

		$uc_sub_topic .= '{TEMPLATE: uc_sub_topic}';
	}
	if ($uc_sub_topic) {
		$uc_sub_topic = '{TEMPLATE: uc_sub_topics}';
	}
	unset($c);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: UC_PAGE}