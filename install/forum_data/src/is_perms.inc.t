<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: is_perms.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	if( $GLOBALS['usr']->is_mod == 'A' ) return TRUE;

	if( empty($user_id) ) $user_id = 0;

	if( @is_object($GLOBALS['__MEMPERM_CACHE'][$user_id][$r_id][$r_type]) ) 
		return ($GLOBALS['__MEMPERM_CACHE'][$user_id][$r_id][$r_type]->{'p_'.$perm}=='Y'?TRUE:FALSE);
	
	if ( empty($user_id) ) {
		$r = Q("SELECT * FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=".$user_id." AND resource_type='".$r_type."' AND resource_id=".$r_id);
		if( !IS_RESULT($r) ) $r = Q("SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id=1");
	}
	else {
		$r=Q("SELECT * FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id IN(".$user_id.",4294967295) AND resource_type='".$r_type."' AND resource_id=".$r_id." ORDER BY user_id LIMIT 1");
		if( !IS_RESULT($r) ) $r = Q("SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id=2");
	}
	
	$GLOBALS['__MEMPERM_CACHE'][$user_id][$r_id][$r_type] = DB_SINGLEOBJ($r);
	return ($GLOBALS['__MEMPERM_CACHE'][$user_id][$r_id][$r_type]->{'p_'.$perm}=='Y'?TRUE:FALSE);
}
?>