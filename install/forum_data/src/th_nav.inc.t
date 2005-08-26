<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: th_nav.inc.t,v 1.11 2005/08/26 18:00:05 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function get_prev_next_th_id($frm_id, $th, &$prev, &$next)
{
	$next = $prev = '';
	$id = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$frm_id.' WHERE thread_id='.$th);
	if (!$id) {
		return;
	}

	$c = uq('SELECT m.id, m.subject, tv.seq FROM {SQL_TABLE_PREFIX}tv_'.$frm_id.' tv INNER JOIN {SQL_TABLE_PREFIX}thread t ON tv.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE tv.seq IN('.($id - 1).', '.($id + 1).')');
	while ($r = db_rowarr($c)) {
		if ($r[2] < $id) {
			$prev = '{TEMPLATE: prev_thread_link}';
		} else {
			$next = '{TEMPLATE: next_thread_link}';
		}		
	}
	unset($c);
}
?>