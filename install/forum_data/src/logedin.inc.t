<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: logedin.inc.t,v 1.6 2002/07/09 15:50:47 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	if ( $GLOBALS['LOGEDIN_LIST'] != 'N' ) {
		if ( $GLOBALS['ACTION_LIST_ENABLED'] == 'Y' ) $astr = '{TEMPLATE: i_spy}';
	
		$tm_expire = __request_timestamp__-($GLOBALS['LOGEDIN_TIMEOUT']*60);
		
		$annon = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}ses WHERE {SQL_TABLE_PREFIX}ses.time_sec>".$tm_expire." AND user_id>2000000000");
		$r = q("SELECT id,alias,is_mod,invisible_mode FROM {SQL_TABLE_PREFIX}users WHERE last_visit>".$tm_expire);
		if( empty($annon) ) $annon = 0;

		$reg_u=$inv_u=0;
		$loged_in_list='';
		while ( $obj = db_rowobj($r) ) {
			if( $obj->invisible_mode == 'Y' ) {
				$inv_u++;
				continue;
			}
			
			if( $reg_u ) $loged_in_list .= '{TEMPLATE: online_user_separator}';
			
			$reg_u++;
			
			$user_nick = htmlspecialchars($obj->alias);
			$profile_link = '{ROOT}?t=usrinfo&id='.$obj->id.'&'._rsid;
					
			if( $obj->is_mod == 'A' ) 
				$loged_in_list .= '{TEMPLATE: online_user_link_admin}';
			else if ( $obj->is_mod=='Y' ) 
				$loged_in_list .= '{TEMPLATE: online_user_link_mod}';
			else
				$loged_in_list .= '{TEMPLATE: online_user_link}';
		}
		$logedin = '{TEMPLATE: logedin}';
	}
	else
		$logedin ='';
	
	if ( $GLOBALS['FORUM_INFO'] != 'N' ) {
		$r = q("select sum(post_count) AS post_count, sum(thread_count) AS thread_count FROM {SQL_TABLE_PREFIX}forum");
		list($post_count, $thread_count) = db_singlearr($r);
		
		$uid = q_singleval("SELECT MAX(id) FROM {SQL_TABLE_PREFIX}users");
		$ulogin = htmlspecialchars(q_singleval("SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id=".$uid));
		$ulink = '{ROOT}?t=usrinfo&id='.$uid.'&'._rsid;
		$reg_users = q_singleval("select count(*) FROM {SQL_TABLE_PREFIX}users");
		
		if( !_uid )
			$lmid=q_singleval("SELECT MAX(last_post_id) FROM {SQL_TABLE_PREFIX}forum INNER JOIN {SQL_TABLE_PREFIX}group_cache ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}group_cache.resource_id AND {SQL_TABLE_PREFIX}group_cache.resource_type='forum' WHERE p_READ='Y' AND user_id=0");
		else
			$lmid=q_singleval("SELECT MAX(last_post_id) FROM {SQL_TABLE_PREFIX}forum ".($GLOBALS['usr']->is_mod != 'A' ? " WHERE id IN(".get_all_perms(_uid).")" : ''));
		
		if( $lmid ) {
			$lsubj = q_singleval("SELECT subject FROM {SQL_TABLE_PREFIX}msg WHERE id=".$lmid);
			$url = ( _uid ) ? $usr->default_view : 'msg';
			$last_msg = '{TEMPLATE: last_msg}';
		}
		$forum_info = '{TEMPLATE: forum_info}';
	}
	else
		$forum_info = '';
		
	$loged_in_list = ( $logedin || $forum_info ) ? '{TEMPLATE: loged_in_list}' : '';
?>