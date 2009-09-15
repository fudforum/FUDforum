<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: bookmarked.php.t,v 1.4 2009/09/15 18:11:29 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	if (!_uid) {
		std_error('login');
	}

	/* delete thread bookmark */
	if (isset($_GET['th']) && ($_GET['th'] = (int)$_GET['th']) && sq_check(0, $usr->sq)) {
		thread_bookmark_del(_uid, $_GET['th']);
	}

	if (!empty($_POST['t_unbookmark_all'])) {
		q("DELETE FROM {SQL_TABLE_PREFIX}bookmarks WHERE user_id="._uid);
	} else if (isset($_POST['t_unbookmark_sel'], $_POST['te'])) {
		$list = array();
		foreach((array)$_POST['te'] as $v) {
			$list[(int)$v] = (int) $v;
		}
		q("DELETE FROM {SQL_TABLE_PREFIX}bookmarks WHERE user_id="._uid." AND thread_id IN(".implode(',', $list).")");
	}

	ses_update_status($usr->sid, '{TEMPLATE: bookmarked_update}');

/*{POST_HTML_PHP}*/

	$bookmarked_thread_data = '';

	if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
		$start = 0;
	}

	$c = uq('SELECT /*!40000 SQL_CALC_FOUND_ROWS */ t.id, m.subject, f.name FROM {SQL_TABLE_PREFIX}bookmarks b INNER JOIN {SQL_TABLE_PREFIX}thread t ON b.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=t.forum_id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE b.user_id='._uid.' ORDER BY f.name, m.subject LIMIT '.qry_limit($THREADS_PER_PAGE, $start));

	while (($r = db_rowarr($c))) {
		$bookmarked_thread_data .= '{TEMPLATE: bookmarked_thread_entry}';
	}
	unset($c);

	/* Since a person can have MANY bookmarked threads, we need a pager & for the pager we need a entry count */
	if (($total = (int) q_singleval("SELECT /*!40000 FOUND_ROWS(), */ -1")) < 0) {
		$total = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}bookmarks b LEFT JOIN {SQL_TABLE_PREFIX}thread t ON b.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE b.user_id='._uid);
	}

	if ($FUD_OPT_2 & 32768) {
		$pager = tmpl_create_pager($start, $THREADS_PER_PAGE, $total, '{ROOT}/bml/start/', '/'._rsid.'#fff');
	} else {
		$pager = tmpl_create_pager($start, $THREADS_PER_PAGE, $total, '{ROOT}?t=bookmarked&a=1&'._rsid, '#fff');
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: BOOKMARKED_PAGE}
