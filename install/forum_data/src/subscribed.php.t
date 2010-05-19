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

/*{PRE_HTML_PHP}*/

	if (!_uid) {
		std_error('login');
	}

	/* delete forum subscription */
	if (isset($_GET['frm_id']) && ($_GET['frm_id'] = (int)$_GET['frm_id']) && sq_check(0, $usr->sq)) {
		forum_notify_del(_uid, $_GET['frm_id']);
	}

	/* delete thread subscription */
	if (isset($_GET['th']) && ($_GET['th'] = (int)$_GET['th']) && sq_check(0, $usr->sq)) {
		thread_notify_del(_uid, $_GET['th']);
	}

	if (!empty($_POST['f_unsub_all'])) {
		q('DELETE FROM {SQL_TABLE_PREFIX}forum_notify WHERE user_id='._uid);
	} else if (!empty($_POST['t_unsub_all'])) {
		q('DELETE FROM {SQL_TABLE_PREFIX}thread_notify WHERE user_id='._uid);
	} else if (isset($_POST['f_unsub_sel'], $_POST['fe'])) {
		$list = array();
		foreach((array)$_POST['fe'] as $v) {
			$list[(int)$v] = (int) $v;
		}
		q('DELETE FROM {SQL_TABLE_PREFIX}forum_notify WHERE user_id='._uid.' AND forum_id IN('.implode(',', $list).')');
	} else if (isset($_POST['t_unsub_sel'], $_POST['te'])) {
		$list = array();
		foreach((array)$_POST['te'] as $v) {
			$list[(int)$v] = (int) $v;
		}
		q('DELETE FROM {SQL_TABLE_PREFIX}thread_notify WHERE user_id='._uid.' AND thread_id IN('.implode(',', $list).')');
	}

	ses_update_status($usr->sid, '{TEMPLATE: subscribed_update}');

/*{POST_HTML_PHP}*/

	/* fetch a list of all the accessible forums */
	$lmt = '';
	if (!$is_a) {
		$c = uq('SELECT g1.resource_id
				FROM {SQL_TABLE_PREFIX}group_cache g1
				LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g1.resource_id=g2.resource_id
				LEFT JOIN {SQL_TABLE_PREFIX}mod m ON m.forum_id=g1.resource_id AND m.user_id='._uid.'
				WHERE g1.user_id=2147483647 AND (m.id IS NULL AND (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 2)=0)');
		while ($r = db_rowarr($c)) {
			$lmt .= $r[0] . ',';
		}
		unset($c);
		if ($lmt) {
			$lmt[strlen($lmt) - 1] = ' ';
			$lmt = ' AND forum_id NOT IN('.$lmt.') ';
		} else {
			$lmt = ' AND forum_id NOT IN(0) ';
		}
	}

	$c = uq('SELECT f.id, f.name FROM {SQL_TABLE_PREFIX}forum_notify fn LEFT JOIN {SQL_TABLE_PREFIX}forum f ON fn.forum_id=f.id WHERE fn.user_id='._uid.' '.$lmt.' ORDER BY f.last_post_id DESC');

	$subscribed_thread_data = $subscribed_forum_data = '';
	while (($r = db_rowarr($c))) {
		$subscribed_forum_data .= '{TEMPLATE: subscribed_forum_entry}';
	}
	unset($c);

	if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
		$start = 0;
	}

	$c = uq(q_limit('SELECT /*!40000 SQL_CALC_FOUND_ROWS */ t.id, m.subject, f.name FROM {SQL_TABLE_PREFIX}thread_notify tn INNER JOIN {SQL_TABLE_PREFIX}thread t ON tn.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=t.forum_id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE tn.user_id='._uid.' '.$lmt.' ORDER BY t.last_post_id DESC',
			$THREADS_PER_PAGE, $start));

	while (($r = db_rowarr($c))) {
		$subscribed_thread_data .= '{TEMPLATE: subscribed_thread_entry}';
	}
	unset($c);

	/* Since a person can have MANY subscribed threads, we need a pager & for the pager we need a entry count */
	if (($total = (int) q_singleval('SELECT /*!40000 FOUND_ROWS(), */ -1')) < 0) {
		$total = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}thread_notify tn LEFT JOIN {SQL_TABLE_PREFIX}thread t ON tn.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE tn.user_id='._uid.' '.$lmt);
	}

	if ($FUD_OPT_2 & 32768) {
		$pager = tmpl_create_pager($start, $THREADS_PER_PAGE, $total, '{ROOT}/sl/start/', '/'._rsid.'#fff');
	} else {
		$pager = tmpl_create_pager($start, $THREADS_PER_PAGE, $total, '{ROOT}?t=subscribed&a=1&'._rsid, '#fff');
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: SUBSCRIBED_PAGE}
