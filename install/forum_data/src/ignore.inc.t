<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ignore.inc.t,v 1.15 2005/09/12 21:05:07 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function ignore_add($user_id, $ignore_id)
{
	q('INSERT INTO {SQL_TABLE_PREFIX}user_ignore (ignore_id, user_id) VALUES ('.$ignore_id.', '.$user_id.')');
	q('DELETE FROM {SQL_TABLE_PREFIX}buddy WHERE user_id='.$ignore_id.' AND bud_id='.$user_id);
	if (db_affected()) {
		fud_use('buddy.inc');
		buddy_rebuild_cache($ignore_id);
	}

	return ignore_rebuild_cache($user_id);
}

function ignore_delete($user_id, $ignore_id)
{
	q('DELETE FROM {SQL_TABLE_PREFIX}user_ignore WHERE user_id='.$user_id.' AND ignore_id='.$ignore_id);
	return ignore_rebuild_cache($user_id);
}

function ignore_rebuild_cache($uid)
{
	$arr = array();
	$q = uq('SELECT ignore_id FROM {SQL_TABLE_PREFIX}user_ignore WHERE user_id='.$uid);
	while ($ent = db_rowarr($q)) {
		$arr[$ent[0]] = 1;
	}
	unset($q);

	if ($arr) {
		q('UPDATE {SQL_TABLE_PREFIX}users SET ignore_list='._esc(serialize($arr)).' WHERE id='.$uid);
		return $arr;
	}
	q('UPDATE {SQL_TABLE_PREFIX}users SET ignore_list=NULL WHERE id='.$uid);
}
?>