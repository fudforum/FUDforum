<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: th_nav.inc.t,v 1.2 2003/04/09 09:03:17 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function get_prev_next_th_id(&$frm, &$prev, &$next)
{
	/* determine previous thread */
	if ($frm->th_page == 1 && $frm->th_pos == 1) {
		$prev = '';
	} else {
		$cpg = ($frm->th_pos != 1) ? $frm->th_page : $frm->th_page - 1;
		
		$p = db_saq('SELECT m.id, m.subject FROM {SQL_TABLE_PREFIX}thread_view tv 
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON tv.thread_id=t.id
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id
			WHERE
				tv.forum_id='.$frm->forum_id.' AND tv.page IN ('.$cpg.', '.($cpg - 1).') AND t.moved_to=0
				AND (tv.page * '.$GLOBALS['THREADS_PER_PAGE'].' + tv.pos) < '.($frm->th_page * $GLOBALS['THREADS_PER_PAGE'] + $frm->th_pos).'
			ORDER BY tv.page DESC, tv.pos DESC LIMIT 1');

		if ($p) {
			$prev = '{TEMPLATE: prev_thread_link}';
		}
	}
	
	if ($frm->last_thread == $frm->id) { /* this is the last thread in the forum */
		$next = '';
	} else {
		/* determine next thread */
		$cpg = ($frm->th_pos != $GLOBALS['THREADS_PER_PAGE']) ? $frm->th_page : $frm->th_page + 1;
	
		$n = db_saq('SELECT m.id, m.subject FROM {SQL_TABLE_PREFIX}thread_view tv 
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON tv.thread_id=t.id
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id
			WHERE
				tv.forum_id='.$frm->forum_id.' AND tv.page IN ('.$cpg.', '.($cpg - 1).') AND t.moved_to=0
				AND (tv.page * '.$GLOBALS['THREADS_PER_PAGE'].' + tv.pos) > '.($frm->th_page * $GLOBALS['THREADS_PER_PAGE'] + $frm->th_pos).'
			ORDER BY tv.page ASC, tv.pos ASC LIMIT 1');
		if ($n) {
			$next = '{TEMPLATE: next_thread_link}';
		}
	}
}
?>