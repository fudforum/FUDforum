<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: th_nav.inc.t,v 1.8 2004/01/04 16:38:27 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function get_prev_next_th_id(&$frm, &$prev, &$next)
{
	/* determine previous thread */
	if ($frm->th_page == 1 && $frm->th_pos == 1) {
		$prev = '';
	} else {
		if ($frm->th_pos - 1 == 0) {
			$page = $frm->th_page - 1;
			$pos = $GLOBALS['THREADS_PER_PAGE'];
		} else {
			$page = $frm->th_page;
			$pos = $frm->th_pos - 1;
		}

		$p = db_saq('SELECT m.id, m.subject FROM {SQL_TABLE_PREFIX}thread_view tv INNER JOIN {SQL_TABLE_PREFIX}thread t ON tv.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE tv.forum_id='.$frm->forum_id.' AND tv.page='.$page.' AND tv.pos='.$pos);

		$prev = $p ? '{TEMPLATE: prev_thread_link}' : '';
	}

	/* determine next thread */
	if ($frm->th_pos + 1 > $GLOBALS['THREADS_PER_PAGE']) {
		$page = $frm->th_page + 1;
		$pos = 1;
	} else {
		$page = $frm->th_page;
		$pos = $frm->th_pos + 1;
	}

	$n = db_saq('SELECT m.id, m.subject FROM {SQL_TABLE_PREFIX}thread_view tv INNER JOIN {SQL_TABLE_PREFIX}thread t ON tv.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE tv.forum_id='.$frm->forum_id.' AND tv.page='.$page.' AND tv.pos='.$pos);

	$next = $n ? '{TEMPLATE: next_thread_link}' : '';
}
?>