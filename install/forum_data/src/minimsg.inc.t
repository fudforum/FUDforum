<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: minimsg.inc.t,v 1.13 2003/04/09 13:08:49 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$start = '';
if ($th_id && empty($GLOBALS['MINIMSG_OPT']['DISABLED'])) {
	$GLOBALS['DRAWMSG_OPTS']['NO_MSG_CONTROLS'] = 1;
	
	$count = $usr->posts_ppg ? $usr->posts_ppg : $GLOBALS['POSTS_PER_PAGE'];
	$start = isset($_GET['start']) ? (int)$_GET['start'] : (isset($_POST['minimsg_pager_switch']) ? (int)$_POST['minimsg_pager_switch'] : 0);
	$total = $thr->replies + 1;

	if ($reply_to && !isset($_POST['minimsg_pager_switch']) && $total > $count) {
		$start = ($total - q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$th_id." AND approved='Y' AND id>=".$reply_to));
		$msg_order_by = 'ASC';
	} else {
		$msg_order_by = 'DESC';
	}
		
	$c = uq('SELECT m.*, t.locked, t.root_msg_id, t.last_post_id, t.forum_id, 
			u.id AS user_id, u.alias AS login, u.invisible_mode, u.last_visit AS time_sec,
			p.max_votes, p.expiry_date, p.creation_date, p.name AS poll_name
		FROM 
			{SQL_TABLE_PREFIX}msg m
			INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
			LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
			LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id
		WHERE 
			m.thread_id='.$th_id.' AND m.approved=\'Y\'
		ORDER BY id '.$msg_order_by.' LIMIT '.qry_limit($count, $start));
	
	$message_data='';
	$m_count = 0;
	while ($obj = db_rowobj($c)) {
		$message_data .= tmpl_drawmsg($obj, $usr, $perms, true, $m_count, '');
		$mid = $obj->id;
	}
	qf($c);
	
	un_register_fps();
	
	$minimsg_pager = tmpl_create_pager($start, $count, $total, "javascript: document.post_form.minimsg_pager_switch.value='%s'; document.post_form.submit();", null, false, false);
	$minimsg = '{TEMPLATE: minimsg_form}';
		
	unset($GLOBALS['DRAWMSG_OPTS']['NO_MSG_CONTROLS']);
} else if ($th_id) {
	$start = isset($_GET['start']) ? (int)$_GET['start'] : (isset($_POST['minimsg_pager_switch']) ? (int)$_POST['minimsg_pager_switch'] : 0);
	$minimsg = '{TEMPLATE: minimsg_hidden}';
} else {
	$minimsg = '';
}
?>