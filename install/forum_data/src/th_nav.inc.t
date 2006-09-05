<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: th_nav.inc.t,v 1.15 2006/09/05 13:16:49 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License.
**/

function get_prev_next_th_id($frm_id, $th, &$prev, &$next)
{
	$next = $prev = '';
	$id = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$frm_id.' WHERE thread_id='.$th);
	if (!$id) {
		return;
	}

	$nn = $np = 0;

	$c = uq('SELECT m.id, m.subject, tv.seq, t.moved_to FROM {SQL_TABLE_PREFIX}tv_'.$frm_id.' tv INNER JOIN {SQL_TABLE_PREFIX}thread t ON tv.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE tv.seq IN('.($id - 1).', '.($id + 1).')');
	while ($r = db_rowarr($c)) {
		if ($r[2] < $id) {
			if ($r[3]) { /* moved topic, let's try to find another */
				$np = 1; continue;
			}
			$prev = '{TEMPLATE: prev_thread_link}';
		} else {
			if ($r[3]) { /* moved topic, let's try to find another */
				$nn = 1; continue;
			}
			$next = '{TEMPLATE: next_thread_link}';
		}		
	}
	unset($c);

	if ($np) {
		$r = db_saq('SELECT m.id, m.subject FROM {SQL_TABLE_PREFIX}tv_'.$frm_id.' tv INNER JOIN {SQL_TABLE_PREFIX}thread t ON tv.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE tv.seq IN('.($id - 10).', '.($id - 2).') ORDER BY tv.seq ASC  LIMIT 1');
		$prev = '{TEMPLATE: prev_thread_link}';
	}
	if ($nn) {
		$r = db_saq('SELECT m.id, m.subject FROM {SQL_TABLE_PREFIX}tv_'.$frm_id.' tv INNER JOIN {SQL_TABLE_PREFIX}thread t ON tv.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE tv.seq IN('.($id + 2).', '.($id + 10).') ORDER BY tv.seq DESC LIMIT 1');
		$next = '{TEMPLATE: next_thread_link}';
	}
}
?>