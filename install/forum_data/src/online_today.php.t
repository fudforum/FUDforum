<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: online_today.php.t,v 1.2 2002/06/18 16:12:36 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	include_once "GLOBALS.php";
	{PRE_HTML_PHP}
	
	if ( isset($ses) ) $ses->update('{TEMPLATE: online_today_update}');

	{POST_HTML_PHP}
	
	$today = mktime(0,0,0,date("m"),date("d"),date("Y"));
	
	$limit = array();
	if( $usr->is_mod != 'A' ) {
		$r = Q("SELECT resource_id FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id="._uid." AND resource_type='forum' AND p_READ='Y'");
		while( list($fid) = DB_ROWARR($r) ) $limit[$fid] = $fid;
		QF($r);

		if( _uid ) {
			$r = Q("SELECT resource_id FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id!="._uid." AND user_id=4294967295 AND resource_type='forum' AND p_READ='Y'");
			while( list($fid) = DB_ROWARR($r) ) $limit[$fid] = $fid;
			QF($r);
		}
	}

	$r = Q("SELECT 
			{SQL_TABLE_PREFIX}users.login,
			{SQL_TABLE_PREFIX}users.is_mod,
			{SQL_TABLE_PREFIX}users.id,
			{SQL_TABLE_PREFIX}users.last_visit,
			{SQL_TABLE_PREFIX}msg.id AS mid,
			{SQL_TABLE_PREFIX}msg.subject,
			{SQL_TABLE_PREFIX}msg.post_stamp,
			{SQL_TABLE_PREFIX}thread.forum_id
		FROM {SQL_TABLE_PREFIX}users 
		LEFT JOIN {SQL_TABLE_PREFIX}msg
			ON {SQL_TABLE_PREFIX}users.u_last_post_id={SQL_TABLE_PREFIX}msg.id
		LEFT JOIN {SQL_TABLE_PREFIX}thread
			ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id	
		WHERE 
			{SQL_TABLE_PREFIX}users.last_visit>".$today." AND 
			{SQL_TABLE_PREFIX}users.invisible_mode='N' AND
			{SQL_TABLE_PREFIX}users.id!="._uid."
			
		ORDER BY
			{SQL_TABLE_PREFIX}users.login, {SQL_TABLE_PREFIX}users.last_visit");
		
	$user_entries='';
	while ( $obj = DB_ROWOBJ($r) ) {
		switch( $obj->is_mod )
		{
			case 'A':
				$user_login = '{TEMPLATE: adm_user_link}';
				break;
			case 'Y':
				$user_login = '{TEMPLATE: mod_user_link}';
				break;
			default:
				$user_login = '{TEMPLATE: reg_user_link}';
		}
			
		if( empty($obj->post_stamp) )
			$last_post = '{TEMPLATE: last_post_na}';
		else {
			if( $usr->is_mod != 'A' && empty($limit[$obj->forum_id]) )
				$last_post = '{TEMPLATE: no_view_perm}';
			else 
				$last_post = '{TEMPLATE: last_post}';
		}
		
		$user_entries .= '{TEMPLATE: user_entry}';
	}
	QF($r);	

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: ONLINE_TODAY_PAGE}