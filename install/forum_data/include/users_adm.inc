<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: users_adm.inc,v 1.15 2004/06/07 15:24:53 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function usr_delete($id)
{
	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (!db_locked()) {
		$ll = 1;
		db_lock($tbl.'forum WRITE, '.$tbl.'msg WRITE, '.$tbl.'group_members WRITE, '.$tbl.'poll_opt_track WRITE, '.$tbl.'users WRITE, '.$tbl.'pmsg WRITE, '.$tbl.'attach WRITE, '.$tbl.'mod WRITE, '.$tbl.'custom_tags WRITE, '.$tbl.'thread_notify WRITE, '.$tbl.'forum_notify WRITE, '.$tbl.'read WRITE, '.$tbl.'forum_read WRITE, '.$tbl.'thread_rate_track WRITE, '.$tbl.'user_ignore WRITE, '.$tbl.'buddy WRITE, '.$tbl.'mod mm WRITE, '.$tbl.'users u WRITE, '.$tbl.'forum f WRITE');
	}

	if (!is_array($id)) {
		$id = array($id);
	}

	q('DELETE FROM '.$tbl.'mod WHERE user_id IN('.implode(',', $id).')');
	if (db_affected()) {
		rebuildmodlist();
	}

	q('DELETE FROM '.$tbl.'custom_tags WHERE user_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'thread_notify WHERE user_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'forum_notify WHERE user_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'read WHERE user_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'forum_read WHERE user_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'thread_rate_track WHERE user_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'user_ignore WHERE user_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'user_ignore WHERE ignore_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'buddy WHERE user_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'buddy WHERE bud_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'poll_opt_track WHERE user_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'group_members WHERE user_id IN('.implode(',', $id).')');
	q('UPDATE '.$tbl.'msg SET poster_id=0 WHERE poster_id IN('.implode(',', $id).')');

	/* Delete the private messages of this user */
	$c = q('SELECT id FROM '.$tbl.'pmsg WHERE duser_id IN('.implode(',', $id).')');
	while ($r = db_rowarr($c)) {
		pmsg_del($r[0], 5);
	}
	q('UPDATE '.$tbl.'pmsg SET ouser_id=0, read_stamp=post_stamp WHERE ouser_id IN('.implode(',', $id).')');
	q('DELETE FROM '.$tbl.'users WHERE id IN('.implode(',', $id).')');

	if (isset($ll)) {
		db_unlock();
	}
}

function usr_adm_avatar($id, $del=0)
{
	if (!($ud = db_sab('SELECT u.login, u.email, t.name FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'users u INNER JOIN '.$GLOBALS['DBHOST_TBL_PREFIX'].'themes t ON t.id=u.theme WHERE u.id='.$id))) {
		return;
	}
	$ud->name = preg_replace('![^A-Za-z0-9]!', '_', $ud->name);

	include_once($GLOBALS['DATA_DIR'] . 'include/theme/' . $ud->name . '/avatar_msg.inc');

	if (!$del) {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET users_opt=(users_opt|4194304|16777216) & ~ (4194304|16777216) | 8388608 WHERE id='.$id);
		if (db_affected()) {
			send_status_update($id, $ud->login, $ud->email, $GLOBALS['approved_avatar_title'], $GLOBALS['approved_avatar_msg']);
		}
	} else {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET users_opt=(users_opt|8388608|16777216) & ~ (8388608|16777216) | 4194304, avatar_loc=NULL WHERE id='.$id);
		if (db_affected()) {
			send_status_update($id, $ud->login, $ud->email, $GLOBALS['unapproved_avatar_title'], $GLOBALS['unapproved_avatar_msg']);
		}
	}
}
?>