<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: allperms.inc.t,v 1.7 2003/04/02 20:58:55 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function get_all_perms($usr_id)
{
	$fl = '';

	if (!$usr_id) {
		$c = q("SELECT resource_id FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=0 AND resource_type='forum' AND p_READ='Y' AND p_VISIBLE='Y'");
		while ($r = db_rowarr($c)) {
			$fl .= $r[0] . ',';
		}
	} else {
		$tmp_arr = array();
		$c = q("SELECT user_id, resource_id, p_read, p_visible FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id IN(".$usr_id.",2147483647) AND resource_type='forum' ORDER BY user_id");
		while ($r = db_rowarr($c)) {
			if ($r[2] == 'Y' && $r[3] == 'Y' && ($usr_id == $r[0] || !isset($tmp[$r[1]]))) {
				$fl .= $r[1] . ','; 
			}
			$tmp[$r[1]] = 1;
		}
	}
	qf($c);
	
	return $fl ? substr($fl, 0, -1) : 0;
}

function forum_perm_array($forum_id)
{
 	$r = q("SELECT p_READ, user_id FROM {SQL_TABLE_PREFIX}group_cache WHERE resource_type='forum' AND resource_id=".$forum_id." AND user_id>0");
	while ($d = db_rowarr($r)) {
		$p[$d[1]] = $d[0];
	}
	qf($r);
	
	return isset($p) ? $p : NULL;
}

function is_allowed($user_id, $p)
{
	if ( isset($p[$user_id]) && $p[$user_id] != 'Y' ) 
		return 0;
	else if ( $p[$user_id] != 'Y' && $p['2147483647'] == 'N' ) 
		return 0;
	
	return 1;
}
?>