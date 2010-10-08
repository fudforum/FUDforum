<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

$start = isset($_GET['start']) ? (int)$_GET['start'] : (isset($_POST['minimsg_pager_switch']) ? (int)$_POST['minimsg_pager_switch'] : 0);
if ($start < 0) {
	$start = 0;
}
if ($th_id && !$GLOBALS['MINIMSG_OPT_DISABLED']) {
	$count = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;
	$total = $thr->replies + 1;

	if ($reply_to && !isset($_POST['minimsg_pager_switch']) && $total > $count) {
		$start = ($total - q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='. $th_id .' AND apr=1 AND id>='. $reply_to));
		if ($start < 0) {
			$start = 0;
		}
		$msg_order_by = 'ASC';
	} else {
		$msg_order_by = 'DESC';
	}

	$use_tmp = $FUD_OPT_3 & 4096 && $total > 250;

	/* This is an optimization intended for topics with many messages. */
	if ($use_tmp) {
		q(q_limit('CREATE TEMPORARY TABLE {SQL_TABLE_PREFIX}_mtmp_'. __request_timestamp__ .' AS SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='. $th_id .' AND apr=1 ORDER BY id '. $msg_order_by,
			$count, $start));
	}

	$q = 'SELECT m.*, t.thread_opt, t.root_msg_id, t.last_post_id, t.forum_id,
			u.id AS user_id, u.alias AS login, u.users_opt, u.last_visit AS time_sec,
			p.max_votes, p.expiry_date, p.creation_date, p.name AS poll_name,  p.total_votes
		FROM
			'.($use_tmp ? '{SQL_TABLE_PREFIX}_mtmp_'. __request_timestamp__ .' mt INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=mt.id' : ' {SQL_TABLE_PREFIX}msg m') .'
			INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
			LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
			LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id';
	if ($use_tmp) {
		$q .= ' ORDER BY m.id '. $msg_order_by;
	} else {
		$q = q_limit($q .' WHERE m.thread_id='. $th_id .' AND m.apr=1 ORDER BY m.id '. $msg_order_by, $count, $start);
	}
	$c = q($q);

	$message_data='';
	$m_count = 0;
	while ($obj = db_rowobj($c)) {
		$message_data .= tmpl_drawmsg($obj, $usr, $perms, true, $m_count, '');
	}
	unset($c);

	if ($use_tmp && $FUD_OPT_1 & 256) {
		q('DROP TEMPORARY TABLE {SQL_TABLE_PREFIX}_mtmp_'. __request_timestamp__);
	}

	$minimsg_pager = tmpl_create_pager($start, $count, $total, 'javascript: document.post_form.minimsg_pager_switch.value=\'%s\'; document.post_form.submit();', '', 0, 0, 1);
	$minimsg = '{TEMPLATE: minimsg_form}';
} else if ($th_id) {
	$minimsg = '{TEMPLATE: minimsg_hidden}';
} else {
	$minimsg = '';
}
?>
