<?php
/**
* copyright            : (C) 2001-2018 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

if (!($FUD_OPT_4 & 16)) {	// Blog is disabled.
	std_error('disabled');
}

ses_update_status($usr->sid, '{TEMPLATE: blog_update}');

$TITLE_EXTRA=': Blog';
$RSS = '{TEMPLATE: blog_RSS}';

	if (isset($_GET['start']) && (is_numeric($_GET['start'])) ) {
		$start = $_GET['start'];
	} else {
		$start = 0;
	}

	$frm_list = q_singleval('SELECT conf_value FROM {SQL_TABLE_PREFIX}settings WHERE conf_name =\'blog_forum_list\'');
	if (empty($frm_list)) {
		$frm_list = '1';	// Default to first forum.
	} else {
		$frm_list = json_decode($frm_list, true);
        	$frm_list = join(',', array_values($frm_list));
	}

	$msg_list = null;
	$c = q(q_limit('SELECT /*!40000 SQL_CALC_FOUND_ROWS */ t.root_msg_id, t.id, t.rating, t.replies, t.tdescr,
				f.name AS forum_name, f.id AS forum_id,
				m.thread_id, m.poster_id, m.icon, m.subject, m.post_stamp, m.foff, m.length, m.file_id,
				u.alias
			FROM {SQL_TABLE_PREFIX}thread t
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
			INNER JOIN {SQL_TABLE_PREFIX}msg   m ON m.id=t.root_msg_id
			INNER JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
		WHERE
			forum_id IN ('. $frm_list .') AND m.apr=1 AND moved_to=0
		ORDER BY
			t.root_msg_id DESC', 10, $start));
	while ($topic = db_rowobj($c)) {
/*
                if (_uid && ($perms & 16 || (_uid == $topic->poster_id && (!$GLOBALS['EDIT_TIME_LIMIT'] || __request_timestamp__ - $topic->post_stamp < $GLOBALS['EDIT_TIME_LIMIT'] * 60)))) {
                        $edit_link = '{TEMPLATE: blog_msg_edit}';
                } else {
                        $edit_link = '';
                }
*/
		$thread_read_status = $first_unread_msg_link = '';
/*
		if (_uid && $usr->last_read < $thread->last_post_stamp && $thread->last_post_stamp > $thread->post_stamp) {
			$thread_read_status = ($thread->rating & 1) ? '{TEMPLATE: blog_thread_unread_locked}' : '{TEMPLATE: blog_thread_unread}';
			// Do not show 1st unread message link if thread has no replies.
			if ($thread->replies) {
				$first_unread_msg_link = '{TEMPLATE: blog_first_unread_msg_link}';
			}
		} else if ($thread->rating & 1) {
			$thread_read_status = '{TEMPLATE: blog_thread_read_locked}';
		} else if (!_uid) {
			$thread_read_status = '{TEMPLATE: blog_thread_read_unreg}';
		} else {
			$thread_read_status = '{TEMPLATE: blog_thread_read}';
		}
*/
		$topic->body = read_msg_body($topic->foff, $topic->length, $topic->file_id);

		$msg_list .= '{TEMPLATE: blog_msg_entry}';
	}

	// New posts for sidebar.
	$c = uq(q_limit('SELECT thread_id, subject
		FROM {SQL_TABLE_PREFIX}msg m
		INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
                INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=t.forum_id
                LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='. _uid .' AND g2.resource_id=t.forum_id
                LEFT JOIN {SQL_TABLE_PREFIX}mod mo ON mo.user_id='. _uid .' AND mo.forum_id=t.forum_id
		WHERE apr=1
                '. ($is_a ? '' : ' AND (mo.id IS NOT NULL OR '. q_bitand('COALESCE(g2.group_cache_opt, g1.group_cache_opt)', 1) .'> 0)') .'
		ORDER BY m.post_stamp DESC', 10));
        $new_topic_list = '<div class="item-list">';
        while ($topic = db_rowobj($c)) {
		if (strlen($topic->subject) > 40) {
			$topic->subject = substr($topic->subject, 0, 40);
   			$topic->subject = substr($topic->subject, 0, strrpos($topic->subject, ' ')) .'…';
		}
		$new_topic_list .= '<p><a href="{TEMPLATE: blog_msg_subject_lnk}">' . $topic->subject . '</a></p>';
        }
	$new_topic_list .= '</div>';

	// Most viewed for sidebar.
        $c = uq(q_limit('SELECT t.root_msg_id, t.id, t.rating, t.n_rating, t.replies, t.tdescr, m.thread_id, m.subject
			FROM {SQL_TABLE_PREFIX}thread t
			INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=t.root_msg_id
		WHERE m.apr=1
		ORDER BY views DESC', 10));
        $most_viewed_list = '<div class="item-list">';
        while ($topic = db_rowobj($c)) {
		$most_viewed_list .= '<p><a href="{TEMPLATE: blog_msg_subject_lnk}">' . $topic->subject . '</a></p>';
        }
	$most_viewed_list .= '</div>';

	// Best rated for sidebar.
	if ($FUD_OPT_2 & 4096) {	// ENABLE_THREAD_RATING
        	$c = uq(q_limit('SELECT t.root_msg_id, t.id, t.rating, t.n_rating, t.replies, t.tdescr, m.thread_id, m.subject
				FROM {SQL_TABLE_PREFIX}thread t
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=t.root_msg_id
			WHERE m.apr=1
			ORDER BY rating*n_rating DESC, m.post_stamp DESC', 10));
	        $best_rated_list = '<div class="item-list">';
        	while ($topic = db_rowobj($c)) {
			$best_rated_list .= '<p><a href="{TEMPLATE: blog_msg_subject_lnk}">' . $topic->subject . '</a></p>';
	        }
		$best_rated_list .= '</div>';
	}

	// New members for sidebar.
        $c = uq(q_limit('SELECT id, login FROM {SQL_TABLE_PREFIX}users ORDER BY join_date DESC', 5));
        $recent_member_list = '<div class="item-list">';
        while ($member = db_rowobj($c)) {
		$recent_member_list .= '<p><a href="{TEMPLATE: blog_member_lnk}">' . $member->login . '</a></p>';
        }
	$recent_member_list .= '</div>';

	// Pager
	$topic_count = q_singleval('SELECT COUNT(*) FROM {SQL_TABLE_PREFIX}thread WHERE forum_id IN ('. $frm_list .')');
	if ($FUD_OPT_2 & 32768) {
		$page_pager = tmpl_create_pager($start, 10, $topic_count, '{ROOT}/blog/', '/' ._rsid);
	} else {
		$page_pager = tmpl_create_pager($start, 10, $topic_count, '{ROOT}?t=blog&amp;'. _rsid);
	}

	$page_data = '{TEMPLATE: blog_msg_list}';

/*{POST_HTML_PHP}*/
/*{POST_PAGE_PHP_CODE}*/

?>
{TEMPLATE: BLOG_PAGE}
