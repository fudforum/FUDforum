<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: imsg.inc.t,v 1.17 2004/01/04 16:38:26 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function msg_get($id)
{
	if (($r = db_sab('SELECT * FROM {SQL_TABLE_PREFIX}msg WHERE id='.$id))) {
		$r->body = read_msg_body($r->foff, $r->length, $r->file_id);
		un_register_fps();
		return $r;
	}
	error_dialog('{TEMPLATE: imsg_err_message_title}', '{TEMPLATE: imsg_err_message_msg}');
}

function poll_cache_rebuild($poll_id, &$data)
{
	if (!$poll_id) {
		$data = null;
		return;
	}

	if (!$data) { /* rebuild from cratch */
		$c = uq('SELECT id, name, count FROM {SQL_TABLE_PREFIX}poll_opt WHERE poll_id='.$poll_id);
		while ($r = db_rowarr($c)) {
			$data[$r[0]] = array($r[1], $r[2]);
		}
		if (!$data) {
			$data = null;
		}
	} else { /* register single vote */
		$data[$poll_id][1] += 1;
	}
}
?>