<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: logedin.inc.t,v 1.15 2003/03/17 22:28:45 hackie Exp $
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
		$r = q("SELECT id,alias,is_mod,invisible_mode,custom_color FROM {SQL_TABLE_PREFIX}users WHERE last_visit>".$tm_expire);
		if( empty($annon) ) $annon = 0;

		$reg_u=$inv_u=0;
		$loged_in_list='';
		while ( $obj = db_rowobj($r) ) {
			if( $obj->invisible_mode == 'Y' && $usr->is_mod != 'A' ) {
				$inv_u++;
				continue;
			}
			
			if( $reg_u ) $loged_in_list .= '{TEMPLATE: online_user_separator}';
			
			$reg_u++;
			
			$profile_link = '{ROOT}?t=usrinfo&amp;id='.$obj->id.'&amp;'._rsid;
					
			$ul = draw_user_link($obj->alias, $obj->is_mod, $obj->custom_color);
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
		$ulogin = q_singleval("SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id=".$uid);
		$ulink = '{ROOT}?t=usrinfo&amp;id='.$uid.'&amp;'._rsid;
		$reg_users = q_singleval("select count(*) FROM {SQL_TABLE_PREFIX}users");
		
		if( !_uid )
			$lmid=q_singleval("SELECT MAX(last_post_id) FROM {SQL_TABLE_PREFIX}forum INNER JOIN {SQL_TABLE_PREFIX}group_cache ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}group_cache.resource_id AND {SQL_TABLE_PREFIX}group_cache.resource_type='forum' WHERE cat_id!=0 AND p_READ='Y' AND user_id=0");
		else {
			if ($GLOBALS['usr']->is_mod != 'A') {
				$lp_lmt = get_all_perms(_uid);
				if ($lp_lmt) {
					$lmid=q_singleval("SELECT MAX(last_post_id) FROM {SQL_TABLE_PREFIX}forum WHERE id IN(".$lp_lmt.") AND cat_id!=0");
				} else {
					$lmid = 0;
				}
			} else {
				$lmid=q_singleval("SELECT MAX(last_post_id) FROM {SQL_TABLE_PREFIX}forum WHERE cat_id!=0");
			}
		}
		
		if( $lmid ) {
			$lsubj = q_singleval("SELECT subject FROM {SQL_TABLE_PREFIX}msg WHERE id=".$lmid);
			$last_msg = '{TEMPLATE: last_msg}';
		}
		$forum_info = '{TEMPLATE: forum_info}';
	}
	else
		$forum_info = '';
		
	$loged_in_list = ( $logedin || $forum_info ) ? '{TEMPLATE: loged_in_list}' : '';
?>