<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: subscribed.php.t,v 1.7 2003/04/02 17:10:58 hackie Exp $
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
		exit();
	}

	/* delete forum subscription */
	if (isset($_GET['frm_id']) && (int)$_GET['frm_id']) {
		forum_notify_del(_uid, (int)$_GET['frm_id']);
	}

	/* delete thread subscription */
	if (isset($_GET['th']) && (int)$_GET['th']) {
		thread_notify_del(_uid, (int)$_GET['th']);
	}

	$ses->update('{TEMPLATE: subscribed_update}');
	
/*{POST_HTML_PHP}*/
	
	$c = uq('SELECT {SQL_TABLE_PREFIX}forum.id, {SQL_TABLE_PREFIX}forum.name, {SQL_TABLE_PREFIX}forum_notify.id FROM {SQL_TABLE_PREFIX}forum_notify LEFT JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}forum_notify.forum_id={SQL_TABLE_PREFIX}forum.id WHERE {SQL_TABLE_PREFIX}forum_notify.user_id='._uid.' ORDER BY last_post_id DESC');

	$subscribed_forum_data = '';
	while (($r = db_rowarr($c))) {
		$subscribed_forum_data .= '{TEMPLATE: subscribed_forum_entry}';
	}
	if (!$subscribed_forum_data) {
		$subscribed_forum_data = '{TEMPLATE: no_subscribed_forums}';
	}
	qf($c);

	/* Since a person can have MANY subscribed threads, we need a pager & for the pager we need a entry count */
	$total = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}thread_notify LEFT JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread_notify.thread_id={SQL_TABLE_PREFIX}thread.id LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}thread_notify.user_id='._uid);
	if (isset($_GET['start']) && (int)$_GET['start']) {
		$start = (int)$_GET['start'];
	} else {
		$start = 0;
	}
	
	$subscribed_thread_data = '';
	$c = q('SELECT {SQL_TABLE_PREFIX}thread.id, {SQL_TABLE_PREFIX}msg.subject FROM {SQL_TABLE_PREFIX}thread_notify INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread_notify.thread_id={SQL_TABLE_PREFIX}thread.id INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}thread_notify.user_id='._uid.' ORDER BY last_post_id DESC LIMIT '.qry_limit($THREADS_PER_PAGE, $start));
	
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