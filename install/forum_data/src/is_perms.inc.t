<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.

*   email                : forum@prohost.org
*
*   $Id: is_perms.inc.t,v 1.19 2003/07/09 07:55:46 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function &get_all_read_perms($uid)
{
	$r = uq('SELECT resource_id, p_READ FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id='._uid);
	while ($ent = db_rowarr($r)) {
		$limit[$ent[0]] = $ent[1] == 'Y' ? $ent[0] : 0;
	}
	qf($r);

	if (_uid) {
		$r = uq("SELECT resource_id FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=2147483647 AND p_READ='Y'");
		while ($ent = db_rowarr($r)) {
			if (!isset($limit[$ent[0]])) {
				$limit[$ent[0]] = $ent[0];
			}
		}
		qf($r);
		$r = q('SELECT forum_id FROM {SQL_TABLE_PREFIX}mod WHERE user_id='._uid);
		while ($ent = db_rowarr($r)) {
			$limit[$ent[0]] = $ent[0];
		}
		qf($r);
	}

	return $limit;
}

function &perms_from_obj(&$obj, $is_mod)
{
	$perms = array('p_visible'=>'Y', 'p_read'=>'Y', 'p_post'=>'Y', 'p_reply'=>'Y', 'p_edit'=>'Y', 'p_del'=>'Y', 'p_sticky'=>'Y', 'p_poll'=>'Y', 'p_file'=>'Y', 'p_vote'=>'Y', 'p_rate'=>'Y', 'p_split'=>'Y', 'p_lock'=>'Y', 'p_move'=>'Y', 'p_sml'=>'Y', 'p_img'=>'Y');

	if ($is_mod == 'A' || $obj->md) {
		return $perms;
	}

	foreach ($perms as $k => $v) {
		$perms[$k] = $obj->{$k};	
	}
	return $perms;
}

function make_perms_query(&$fields, &$join, $fid='')
{
	if (!$fid) {
		$fid = 'f.id';	
	}

	if (_uid) {
		$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id='.$fid.' LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id='.$fid.' ';
		$fields = ' (CASE WHEN g2.id IS NOT NULL THEN g2.p_VISIBLE ELSE g1.p_VISIBLE END) AS p_visible,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END) AS p_read,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_POST ELSE g1.p_POST END) AS p_post,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_REPLY ELSE g1.p_REPLY END) AS p_reply,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_EDIT ELSE g1.p_EDIT END) AS p_edit,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_DEL ELSE g1.p_DEL END) AS p_del,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_STICKY ELSE g1.p_STICKY END) AS p_sticky,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_POLL ELSE g1.p_POLL END) AS p_poll,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_FILE ELSE g1.p_FILE END) AS p_file,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_VOTE ELSE g1.p_VOTE END) AS p_vote,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_RATE ELSE g1.p_RATE END) AS p_rate,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_SPLIT ELSE g1.p_SPLIT END) AS p_split,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_LOCK ELSE g1.p_LOCK END) AS p_lock,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_MOVE ELSE g1.p_MOVE END) AS p_move,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_SML ELSE g1.p_SML END) AS p_sml,
			(CASE WHEN g2.id IS NOT NULL THEN g2.p_IMG ELSE g1.p_IMG END) AS p_img ';
	} else {
		$join = ' INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=0 AND g1.resource_id='.$fid.' ';
		$fields = ' p_VISIBLE as p_visible, p_READ as p_read, p_POST as p_post, p_REPLY as p_reply, p_EDIT as p_edit, p_DEL as p_del, p_STICKY as p_sticky, p_POLL as p_poll, p_FILE as p_file, p_VOTE as p_vote, p_RATE as p_rate, p_SPLIT as p_split, p_LOCK as p_lock, p_MOVE as p_move, p_SML as p_sml, p_IMG as p_img ';
	}
}
?>