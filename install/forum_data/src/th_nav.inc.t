<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: th_nav.inc.t,v 1.1 2002/10/28 22:32:03 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function get_prev_next_th_id($forum_id, $thread_id, &$prev, &$next)
{
	list($c_th_pos, $c_pg) = db_singlearr(q("SELECT pos,page FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id=".$forum_id." AND thread_id=".$thread_id));
	
	/* determine previous thread */
	if ($c_pg == 1 && $c_th_pos == 1) {
		$prev = NULL;
	} else {
		$cpg = ($c_th_pos != 1) ? $c_pg : $c_pg - 1;
		
		$prev = db_singleobj(q("SELECT {SQL_TABLE_PREFIX}msg.id, {SQL_TABLE_PREFIX}msg.subject FROM {SQL_TABLE_PREFIX}thread_view INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread_view.thread_id={SQL_TABLE_PREFIX}thread.id INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id
			WHERE
				{SQL_TABLE_PREFIX}thread_view.forum_id=".$forum_id."
				AND {SQL_TABLE_PREFIX}thread_view.page IN(".$cpg.", ".($cpg - 1).")
				AND {SQL_TABLE_PREFIX}thread.moved_to=0
				AND ({SQL_TABLE_PREFIX}thread_view.page * ".$GLOBALS['THREADS_PER_PAGE']." + {SQL_TABLE_PREFIX}thread_view.pos) < ".($c_pg * $GLOBALS['THREADS_PER_PAGE'] + $c_th_pos)."
			ORDER BY page DESC, pos DESC LIMIT 1"));
	}
	
	/* determine next thread */
	$cpg = ($c_th_pos != $GLOBALS['THREADS_PER_PAGE']) ? $c_pg : $c_pg + 1;
	
	$next = db_singleobj(q("SELECT {SQL_TABLE_PREFIX}msg.id, {SQL_TABLE_PREFIX}msg.subject FROM {SQL_TABLE_PREFIX}thread_view INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread_view.thread_id={SQL_TABLE_PREFIX}thread.id INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id
			WHERE
				{SQL_TABLE_PREFIX}thread_view.forum_id=".$forum_id."
				AND {SQL_TABLE_PREFIX}thread_view.page IN(".$cpg.", ".($cpg + 1).")
				AND {SQL_TABLE_PREFIX}thread.moved_to=0
				AND ({SQL_TABLE_PREFIX}thread_view.page * ".$GLOBALS['THREADS_PER_PAGE']." + {SQL_TABLE_PREFIX}thread_view.pos) > ".($c_pg * $GLOBALS['THREADS_PER_PAGE'] + $c_th_pos)."
			ORDER BY page ASC, pos ASC LIMIT 1"));
}
?>