<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: allperms.inc.t,v 1.5 2002/07/09 15:50:16 hackie Exp $
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

	if( empty($usr_id) ) {
		$r = q("SELECT resource_id FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=0 AND resource_type='forum' AND p_READ='Y' AND p_VISIBLE='Y'");
		while( list($resource_id) = db_rowarr($r) ) $fl .= $obj->resource_id.',';		
	}
	else {
		$tmp_arr = array();
		$r = q("SELECT user_id,resource_id,p_read,p_visible FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id IN(".$usr_id.",2147483647) AND resource_type='forum' ORDER BY user_id");
		while( $obj = db_rowobj($r) ) {
			if( $obj->p_read == 'Y' && $obj->p_read == 'Y' && ($obj->user_id == $usr_id || !isset($tmp_arr[$obj->resource_id])) ) 
				$fl .= $obj->resource_id.',';

			$tmp_arr[$obj->resource_id] = 1;	
		}		
	}
	qf($r);
	
	if( !empty($fl) ) $fl = substr($fl, 0, -1);
	
	return $fl;
}

function forum_perm_array($forum_id)
{
 	$r = q("SELECT p_READ AS p_read, user_id FROM {SQL_TABLE_PREFIX}group_cache WHERE resource_type='forum' AND resource_id=$forum_id AND user_id>0");
	while ( $obj = db_rowobj($r) ) $p[$obj->user_id] = $obj->p_read;
	qf($r);
	
	return $p;
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