<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.

*   email                : forum@prohost.org
*
*   $Id: is_perms.inc.t,v 1.9 2003/04/07 14:23:14 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function is_perms($user_id, $r_id, $perm, $r_type='forum')
{
	if ($user_id && $GLOBALS['usr']->is_mod == 'A') {
		return TRUE;
	}
	if( __dbtype__ == 'pgsql' ) $perm = strtolower($perm);
	if( empty($user_id) ) $user_id = 0;

	if( @is_object($GLOBALS['__MEMPERM_CACHE'][$user_id][$r_id][$r_type]) ) 
		return ($GLOBALS['__MEMPERM_CACHE'][$user_id][$r_id][$r_type]->{'p_'.$perm}=='Y'?TRUE:FALSE);
	
	if ( $user_id == 0 ) {
		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=".$user_id." AND resource_type='".$r_type."' AND resource_id=".$r_id);
		if( !is_result($r) ) $r = q("SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id=1");
	}
	else {
		$r=q("SELECT * FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id IN(".$user_id.",2147483647) AND resource_type='".$r_type."' AND resource_id=".$r_id." ORDER BY user_id LIMIT 1");
		if( !is_result($r) ) $r = q("SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id=2");
	}
	
	$GLOBALS['__MEMPERM_CACHE'][$user_id][$r_id][$r_type] = db_singleobj($r);
	return ($GLOBALS['__MEMPERM_CACHE'][$user_id][$r_id][$r_type]->{'p_'.$perm}=='Y'?TRUE:FALSE);
}

function init_single_user_perms($id, $is_mod, &$MOD)
{
	if (!$id) { /* anon user */
		$MOD = 0;
		return db_arr_assoc('SELECT p_VISIBLE as p_visible, p_READ as p_read, p_POST as p_post, p_REPLY as p_reply, p_EDIT as p_edit, p_DEL as p_del, p_STICKY as p_sticky, p_POLL as p_poll, p_FILE as p_file, p_VOTE as p_vote, p_RATE as p_rate, p_SPLIT as p_split, p_LOCK as p_lock, p_MOVE as p_move, p_SML as p_sml, p_IMG as p_img FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=0 AND resource_type=\'forum\' AND resource_id='.$id);
	}
	if ($is_mod == 'A' || ($is_mod == 'Y' && is_moderator($id, _uid))) { /* administrator or moderator */
		$MOD = 1;
		
	} else { /* regular user */
		$MOD = 0;
		return db_arr_assoc('SELECT p_VISIBLE as p_visible, p_READ as p_read, p_POST as p_post, p_REPLY as p_reply, p_EDIT as p_edit, p_DEL as p_del, p_STICKY as p_sticky, p_POLL as p_poll, p_FILE as p_file, p_VOTE as p_vote, p_RATE as p_rate, p_SPLIT as p_split, p_LOCK as p_lock, p_MOVE as p_move, p_SML as p_sml, p_IMG as p_img FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id IN('._uid.',2147483647) AND resource_type=\'forum\' AND resource_id='.$id.' ORDER BY user_id ASC LIMIT 1');
	}
}

function &get_all_read_perms($uid)
{
	$r = q("SELECT resource_id, p_READ FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id="._uid." AND resource_type='forum'");
	while ($ent = db_rowarr($r)) {
		$limit[$ent[0]] = $ent[1] == 'Y' ? 1 : 0;
	}
	qf($r);

	if (_uid) {
		$r = q("SELECT resource_id FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=2147483647 AND resource_type='forum' AND p_READ='Y'");
		while ($ent = db_rowarr($r)) {
			if (!isset($limit[$ent[0]])) {
				$limit[$ent[0]] = 1;
			}
		}
		qf($r);
	}

	return $limit;
}

function &perms_from_obj(&$obj, $is_mod)
{
	$perms = array('visible'=>'Y', 'read'=>'Y', 'post'=>'Y', 'reply'=>'Y', 'edit'=>'Y', 'del'=>'Y', 'sticky'=>'Y', 'poll'=>'Y', 'file'=>'Y', 'vote'=>'Y', 'rate'=>'Y', 'split'=>'Y', 'lock'=>'Y', 'move'=>'Y', 'sml'=>'Y', 'img'=>'Y');

	if ($is_mod == 'A' || $obj->mod) {
		return $perms;
	}

	foreach ($perms as $k => $v) {
		$perms[$k] = $obj->{'p_'.$k};	
	}
	return $perms;
}
?>