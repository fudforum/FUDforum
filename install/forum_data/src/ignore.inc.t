<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ignore.inc.t,v 1.6 2003/10/09 14:34:26 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it 
* under the terms of the GNU General Public License as published by the 
* Free Software Foundation; either version 2 of the License, or 
* (at your option) any later version.
***************************************************************************/

function ignore_add($user_id, $ignore_id)
{
	q('INSERT INTO {SQL_TABLE_PREFIX}user_ignore (ignore_id, user_id) VALUES ('.$ignore_id.', '.$user_id.')');
	return ignore_rebuild_cache($user_id);
}

function ignore_delete($user_id, $ignore_id)
{
	q('DELETE FROM {SQL_TABLE_PREFIX}user_ignore WHERE user_id='.$user_id.' AND ignore_id='.$ignore_id);
	return ignore_rebuild_cache($user_id);
}

function ignore_rebuild_cache($uid)
{
	$q = uq('SELECT ignore_id FROM {SQL_TABLE_PREFIX}user_ignore WHERE user_id='.$uid);
	while ($ent = db_rowarr($q)) {
		$arr[$ent[0]] = 1;
	}
	qf($q);

	if (isset($arr)) {
		q('UPDATE {SQL_TABLE_PREFIX}users SET ignore_list=\''.addslashes(serialize($arr)).'\' WHERE id='.$uid);
		return $arr;
	} else {
		q('UPDATE {SQL_TABLE_PREFIX}users SET ignore_list=NULL WHERE id='.$uid);
		return;
	}
}
?>