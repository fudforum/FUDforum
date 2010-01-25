<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function grp_delete_member($id, $user_id)
{
	if (!$user_id || $user_id == '2147483647') {
		return;
	}

	q('DELETE FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='.$id.' AND user_id='.$user_id);

	if (q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}group_members WHERE user_id='.$user_id.' LIMIT 1')) {
		/* we rebuild cache, since this user's permission for a particular resource are controled by
		 * more the one group. */
		grp_rebuild_cache(array($user_id));
	} else {
		q('DELETE FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id='.$user_id);
	}
}

function grp_update_member($id, $user_id, $perm)
{
	q('UPDATE {SQL_TABLE_PREFIX}group_members SET group_members_opt='.$perm.' WHERE group_id='.$id.' AND user_id='.$user_id);
	grp_rebuild_cache(array($user_id));
}

function grp_rebuild_cache($user_id=null)
{
	$list = array();
	if ($user_id !== null) {
		$lmt = ' user_id IN('.implode(',', $user_id).') ';
	} else {
		$lmt = '';
	}

	/* generate an array of permissions, in the end we end up with 1ist of permissions */
	$r = uq('SELECT gm.user_id AS uid, gm.group_members_opt AS gco, gr.resource_id AS rid FROM {SQL_TABLE_PREFIX}group_members gm INNER JOIN {SQL_TABLE_PREFIX}group_resources gr ON gr.group_id=gm.group_id WHERE gm.group_members_opt>=65536 AND (gm.group_members_opt & 65536) > 0' . ($lmt ? ' AND '.$lmt : ''));
	while ($o = db_rowobj($r)) {
		foreach ($o as $k => $v) {
			$o->{$k} = (int) $v;
		}
		if (isset($list[$o->rid][$o->uid])) {
			if ($o->gco & 131072) {
				$list[$o->rid][$o->uid] |= $o->gco;
			} else {
				$list[$o->rid][$o->uid] &= $o->gco;
			}
		} else {
			$list[$o->rid][$o->uid] = $o->gco;
		}
	}
	unset($r);

	$tmp = array();
	foreach ($list as $k => $v) {
		foreach ($v as $u => $p) {
			$tmp[] = $k.','.$p.','.$u;
		}
	}

	if (!$tmp) {
		q('DELETE FROM {SQL_TABLE_PREFIX}group_cache' . ($lmt ? ' WHERE '.$lmt : ''));
		return;
	}

	if (__dbtype__ == 'mysql') {
		q('REPLACE INTO {SQL_TABLE_PREFIX}group_cache (resource_id, group_cache_opt, user_id) VALUES ('.implode('),(', $tmp).')');
		q('DELETE FROM {SQL_TABLE_PREFIX}group_cache WHERE '.($lmt ? $lmt . ' AND ' : '').' id < LAST_INSERT_ID()');
		return;
	}
	
	if (($ll = !db_locked())) {
		db_lock('{SQL_TABLE_PREFIX}group_cache WRITE');
	}

	q('DELETE FROM {SQL_TABLE_PREFIX}group_cache' . ($lmt ? ' WHERE '.$lmt : ''));
	ins_m('{SQL_TABLE_PREFIX}group_cache', 'resource_id, group_cache_opt, user_id', $tmp, 'integer, integer, integer');

	if ($ll) {
		db_unlock();
	}
}

function group_perm_array()
{
	return array(
		'p_VISIBLE' => array(1, 'Visible'),
		'p_READ' => array(2, 'Read'),
		'p_POST' => array(4, 'Create new topics'),
		'p_REPLY' => array(8, 'Reply to messages'),
		'p_EDIT' => array(16, 'Edit messages'),
		'p_DEL' => array(32, 'Delete messages'),
		'p_STICKY' => array(64, 'Make topics sticky'),
		'p_POLL' => array(128, 'Create polls'),
		'p_FILE' => array(256, 'Attach files'),
		'p_VOTE' => array(512, 'Vote on polls'),
		'p_RATE' => array(1024, 'Rate topics'),
		'p_SPLIT' => array(2048, 'Split/Merge topics'),
		'p_LOCK' => array(4096, 'Lock/Unlock topics'),
		'p_MOVE' => array(8192, 'Move topics'),
		'p_SML' => array(16384, 'Use smilies/emoticons'),
		'p_IMG' => array(32768, 'Use [img] tags'),
		'p_SEARCH' => array(262144, 'Can Search')
	);
}
?>
