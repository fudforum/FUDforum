<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: minimsg.inc.t,v 1.10 2002/08/13 17:09:35 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

if ( !empty($th) && empty($GLOBALS['MINIMSG_OPT']['DISABLED']) ) {
	$GLOBALS['DRAWMSG_OPTS']['NO_MSG_CONTROLS'] = 1;
	
	$count = !empty($usr->posts_ppg) ? $usr->posts_ppg : $GLOBALS['POSTS_PER_PAGE'];

	if ( empty($start) || !is_numeric($start) ) $start = 0;
	if ( is_numeric($minimsg_pager_switch) ) $start = $minimsg_pager_switch;
	
	/* get total */
	if ( !isset($total) ) $total = q_singleval("SELECT replies FROM {SQL_TABLE_PREFIX}thread WHERE id=".$th) + 1;

	if( $reply_to && !isset($minimsg_pager_switch) && $total > $count ) {
		$start = ($total - intzero(q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$th." AND approved='Y' AND id>=".$reply_to)));
		$msg_order_by = 'ASC';
	}
	else	
		$msg_order_by = 'DESC';
		
	$msg_list = q("SELECT {SQL_TABLE_PREFIX}msg.id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$th." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY id ".$msg_order_by." LIMIT ".qry_limit($count,$start));
	if( is_result($msg_list) ) {
		$id_list='{SQL_TABLE_PREFIX}msg.id IN(';
		$m_count=0;
		while ( list($msgp_id) = db_rowarr($msg_list) ) { $id_list .= $msgp_id.','; $m_count++; }
		qf($msg_list);
		$id_list = substr($id_list, 0, -1).')';

		$result = q('SELECT 
			{SQL_TABLE_PREFIX}msg.*, 
			{SQL_TABLE_PREFIX}thread.locked,
			{SQL_TABLE_PREFIX}thread.root_msg_id,
			{SQL_TABLE_PREFIX}thread.last_post_id,
			{SQL_TABLE_PREFIX}thread.forum_id,
			{SQL_TABLE_PREFIX}users.id AS user_id, 
			{SQL_TABLE_PREFIX}users.alias AS login, 
			{SQL_TABLE_PREFIX}users.invisible_mode, 
			{SQL_TABLE_PREFIX}users.posted_msg_count, 
			{SQL_TABLE_PREFIX}users.join_date, 
			{SQL_TABLE_PREFIX}users.last_visit AS time_sec
		FROM 
			{SQL_TABLE_PREFIX}msg
			INNER JOIN {SQL_TABLE_PREFIX}thread
				ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
			LEFT JOIN {SQL_TABLE_PREFIX}users 
				ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id 
		WHERE 
			'.$id_list.'
		ORDER BY id '.$msg_order_by);
	
		$m_count--;
	
		$message_data='';
		while ( $obj = db_rowobj($result) ) {
			$message_data .= tmpl_drawmsg($obj, $m_count, true);
			$mid = $obj->id;
		}
		qf($result);
	
		un_register_fps();
	
		$minimsg_pager = tmpl_create_pager($start, $count, $total, "javascript: document.post_form.minimsg_pager_switch.value='%s'; document.post_form.submit();", null, false, false);
		$minimsg = '{TEMPLATE: minimsg_form}';
		
		unset($GLOBALS['DRAWMSG_OPTS']['NO_MSG_CONTROLS']);
	}
}
else if( !empty($th) ) $minimsg = '{TEMPLATE: minimsg_hidden}';
?>