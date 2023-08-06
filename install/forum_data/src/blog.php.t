<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
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

	/* Display non-forum related announcements. */
	include $GLOBALS['FORUM_SETTINGS_PATH'] .'announce_cache';
	$announcements = '';
	foreach ($announce_cache as $a_id => $a) {
		if (!_uid && $a['ann_opt'] & 2) {
			continue;       // Only for logged in users.
		}
		if (_uid && $a['ann_opt'] & 4) {
			continue;       // Only for anonomous users.
		}
		if ($a['start'] <= __request_timestamp__ && $a['end'] >= __request_timestamp__) {
			$announce_subj = $a['subject'];
			$announce_body = $a['text'];
			if (defined('plugins')) {
				list($announce_subj, $announce_body) = plugin_call_hook('ANNOUNCEMENT', array($announce_subj, $announce_body));
			}
			$announcements .= '{TEMPLATE: announce_entry}';
		}
	}

	if (isset($_GET['start']) && (is_numeric($_GET['start'])) ) {
		$start = $_GET['start'];
	} else {
		$start = 0;
	}

	// Limit to a spesific user.
	$lmt = '';
	if (isset($_GET['user']) && (is_numeric($_GET['user'])) ) {
		$lmt = ' AND u.id = '. $_GET['user'];
	}

	// Limit to a spesific forum.
	if (isset($_GET['forum']) && (is_numeric($_GET['forum'])) ) {
		$frm_list = $_GET['forum'];
	} else {
		$frm_list = q_singleval('SELECT conf_value FROM {SQL_TABLE_PREFIX}settings WHERE conf_name =\'blog_forum_list\'');
		$frm_list = json_decode($frm_list, true);
		// Forum List not set or json error, limit to 1st forum.
		if ($frm_list === null) {
			$frm_list = array(1);
		}
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
	                INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=t.forum_id
	                LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='. _uid .' AND g2.resource_id=t.forum_id
	                LEFT JOIN {SQL_TABLE_PREFIX}mod mo ON mo.user_id='. _uid .' AND mo.forum_id=t.forum_id
		WHERE
			t.forum_id IN ('. $frm_list .')
			AND m.apr=1 AND moved_to=0
	                '. ($is_a ? '' : ' AND (mo.id IS NOT NULL OR '. q_bitand('COALESCE(g2.group_cache_opt, g1.group_cache_opt)', 1) .'> 0)') .'
			'. $lmt .'
		ORDER BY
			t.root_msg_id DESC', 10, $start));
	while ($topic = db_rowobj($c)) {
		/* Read message body. */
		$topic->body = read_msg_body($topic->foff, $topic->length, $topic->file_id);
		/* Cut-off portion after the teaser break. */
		if ( ($break = strpos($topic->body, '<!--break-->')) > 0)  {
			$topic->body = substr($topic->body, 0, $break);
		}
		$msg_list .= '{TEMPLATE: blog_msg_entry}';
	}

	// New posts for sidebar, without duplicates.
	$c = uq(q_limit('SELECT thread_id, subject
		FROM {SQL_TABLE_PREFIX}msg m
		INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
                INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id=t.forum_id
                LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='. _uid .' AND g2.resource_id=t.forum_id
                LEFT JOIN {SQL_TABLE_PREFIX}mod mo ON mo.user_id='. _uid .' AND mo.forum_id=t.forum_id
		WHERE apr=1
		  AND m.id > (select MAX(id) from {SQL_TABLE_PREFIX}msg) - 50
		'. ($is_a ? '' : ' AND (mo.id IS NOT NULL OR '. q_bitand('COALESCE(g2.group_cache_opt, g1.group_cache_opt)', 1) .'> 0)') .'
		GROUP BY thread_id
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

	// Most viewed for sidebar - past 90 days.
        $c = uq(q_limit('SELECT t.root_msg_id, t.id, t.replies, t.tdescr, m.thread_id, m.subject
			FROM {SQL_TABLE_PREFIX}thread t
			INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=t.root_msg_id
		WHERE m.apr=1
		  AND m.post_stamp > '. (__request_timestamp__ - 90*86400) .'
		ORDER BY views DESC', 10));
        $most_viewed_list = '<div class="item-list">';
        while ($topic = db_rowobj($c)) {
		$most_viewed_list .= '<p><a href="{TEMPLATE: blog_msg_subject_lnk}">' . $topic->subject . '</a></p>';
        }
	$most_viewed_list .= '</div>';

	// Best rated for sidebar.
	if ($FUD_OPT_2 & 4096) {	// ENABLE_THREAD_RATING
		$c = uq(q_limit('SELECT t.root_msg_id, t.id, t.rating, t.n_rating, t.tdescr, m.thread_id, m.subject
				FROM {SQL_TABLE_PREFIX}thread t
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=t.root_msg_id
			WHERE m.apr=1
			  AND t.rating > 0
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
