<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: allperms.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	if( empty($usr_id) ) 
		$usr_id = $usr_str = 0;
	else 
		$usr_str = $usr_id.',4294967295';

	$fl = '';
	$tmp_arr = array();
	$r = Q("SELECT user_id,resource_id FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id IN(".$usr_str.") AND resource_type='forum' AND p_READ='Y' AND p_VISIBLE='Y' ORDER BY user_id");
	while( $obj = DB_ROWOBJ($r) ) {
		if( $obj->user_id == $usr_id ) {
			$fl .= $obj->resource_id.',';
			$tmp_arr[$obj->resource_id] = 1;
		}
		else if( empty($tmp_arr[$obj->resource_id]) )
			$fl .= $obj->resource_id.',';	
	}	
	QF($r);
	unset($tmp_arr);
	
	if( !empty($fl) ) $fl = substr($fl, 0, -1);
	
	return $fl;
}

function forum_perm_array($forum_id)
{
 	$r = Q("SELECT p_READ, user_id FROM {SQL_TABLE_PREFIX}group_cache WHERE resource_type='forum' AND resource_id=$forum_id AND user_id>0");
	while ( $obj = DB_ROWOBJ($r) ) {
		$p[$obj->user_id] = $obj->p_READ;
	}
	QF($r);
	
	return $p;
}

function is_allowed($user_id, $p)
{
	if ( isset($p[$user_id]) && $p[$user_id] != 'Y' ) 
		return 0;
	else if ( $p[$user_id] != 'Y' && $p['4294967295'] == 'N' ) 
		return 0;
	
	return 1;
}
?>