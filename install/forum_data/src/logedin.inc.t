<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: logedin.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
		
		$annon = Q_SINGLEVAL("SELECT HIGH_PRIORITY count(*) FROM {SQL_TABLE_PREFIX}ses WHERE {SQL_TABLE_PREFIX}ses.time_sec>".$tm_expire." AND user_id>2000000000");
		$r = Q("SELECT id,login,is_mod,invisible_mode FROM {SQL_TABLE_PREFIX}users WHERE last_visit>".$tm_expire);
		if( empty($annon) ) $annon = 0;

		$reg_u=$inv_u=0;
		$loged_in_list='';
		while ( $obj = DB_ROWOBJ($r) ) {
			if( $obj->invisible_mode == 'Y' ) {
				$inv_u++;
				continue;
			}
			
			if( $reg_u ) $loged_in_list .= '{TEMPLATE: online_user_separator}';
			
			$reg_u++;
			
			$user_nick = htmlspecialchars($obj->login);
			$profile_link = '{ROOT}?t=usrinfo&id='.$obj->id.'&'._rsid;
					
			if( $obj->is_mod == 'A' ) 
				$loged_in_list .= '{TEMPLATE: online_user_link_admin}';
			else if ( $obj->is_mod=='Y' ) 
				$loged_in_list .= '{TEMPLATE: online_user_link_mod}';
			else
				$loged_in_list .= '{TEMPLATE: online_user_link}';
		}
		
	}
	
	if ( $GLOBALS['FORUM_INFO'] != 'N' ) {
		$r = Q("select sum(post_count) AS post_count, sum(thread_count) AS thread_count FROM {SQL_TABLE_PREFIX}forum");
		list($post_count, $thread_count) = DB_SINGLEARR($r);
		
		$uid = Q_SINGLEVAL("SELECT MAX(id) FROM {SQL_TABLE_PREFIX}users");
		$ulogin = htmlspecialchars(Q_SINGLEVAL("SELECT login FROM {SQL_TABLE_PREFIX}users WHERE id=".$uid));
		$ulink = '{ROOT}?t=usrinfo&id='.$uid.'&'._rsid;
		$reg_users = Q_SINGLEVAL("select count(*) FROM {SQL_TABLE_PREFIX}users");
		
		if( ($lmid=Q_SINGLEVAL("SELECT MAX(last_post_id) FROM {SQL_TABLE_PREFIX}forum")) ) {
			$lsubj = Q_SINGLEVAL("SELECT subject FROM {SQL_TABLE_PREFIX}msg WHERE id=".$lmid);
			$url = ( _uid ) ? $usr->default_view : 'msg';
			$last_msg = '{TEMPLATE: last_msg}';
		}
		$forum_status = '{TEMPLATE: forum_info}';
	}
?>