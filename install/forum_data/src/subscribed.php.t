<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: subscribed.php.t,v 1.8 2003/04/10 11:00:43 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/
	
	if (!_uid) {
		std_error('login');
	}

	/* delete forum subscription */
	if (isset($_GET['frm_id']) && ($_GET['frm_id'] = (int)$_GET['frm_id'])) {
		forum_notify_del(_uid, $_GET['frm_id']);
	}

	/* delete thread subscription */
	if (isset($_GET['th']) && ($_GET['th'] = (int)$_GET['th'])) {
		thread_notify_del(_uid, $_GET['th']);
	}

	ses_update_status($usr->sid, '{TEMPLATE: subscribed_update}');
	
/*{POST_HTML_PHP}*/
	
	$c = uq('SELECT f.id, f.name FROM {SQL_TABLE_PREFIX}forum_notify fn LEFT JOIN {SQL_TABLE_PREFIX}forum f ON fn.forum_id=f.id WHERE fn.user_id='._uid.' ORDER BY f.last_post_id DESC');

	$subscribed_forum_data = '';
	while (($r = db_rowarr($c))) {
		$subscribed_forum_data .= '{TEMPLATE: subscribed_forum_entry}';
	}
	if (!$subscribed_forum_data) {
		$subscribed_forum_data = '{TEMPLATE: no_subscribed_forums}';
	}
	qf($c);

	/* Since a person can have MANY subscribed threads, we need a pager & for the pager we need a entry count */
	$total = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}thread_notify tn LEFT JOIN {SQL_TABLE_PREFIX}thread t ON tn.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE tn.user_id='._uid);
	if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
		$start = 0;
	}
	
	$subscribed_thread_data = '';
	$c = q('SELECT t.id, m.subject FROM {SQL_TABLE_PREFIX}thread_notify tn INNER JOIN {SQL_TABLE_PREFIX}thread t ON tn.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE tn.user_id='._uid.' ORDER BY t.last_post_id DESC LIMIT '.qry_limit($THREADS_PER_PAGE, $start));
	
	while (($r = db_rowarr($c))) {
		$subscribed_thread_data .= '{TEMPLATE: subscribed_thread_entry}';
	}

	if (!$subscribed_thread_data) {
		$subscribed_thread_data = '{TEMPLATE: no_subscribed_threads}';
	}
	qf($c);
	
	$pager = tmpl_create_pager($start, $THREADS_PER_PAGE, $total, '{ROOT}?t=subscribed&a=1&'._rsid, '#fff');
		
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: SUBSCRIBED_PAGE}