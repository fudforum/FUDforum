<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: online_today.php.t,v 1.11 2003/04/03 10:03:31 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/
	
	$ses->update('{TEMPLATE: online_today_update}');

/*{POST_HTML_PHP}*/
	
	$today = mktime(0, 0, 0, date("m"), date("d"), date("Y"));

	if ($usr->is_mod != 'A') {
		$limit = &get_all_read_perms(_uid);
	}

	$c = q("SELECT 
			{SQL_TABLE_PREFIX}users.alias AS login,
			{SQL_TABLE_PREFIX}users.is_mod,
			{SQL_TABLE_PREFIX}users.id,
			{SQL_TABLE_PREFIX}users.last_visit,
			{SQL_TABLE_PREFIX}users.custom_color,
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
			".($usr->is_mod!='A'?"{SQL_TABLE_PREFIX}users.invisible_mode='N' AND":"")."
			{SQL_TABLE_PREFIX}users.id!="._uid."
		ORDER BY
			{SQL_TABLE_PREFIX}users.alias, {SQL_TABLE_PREFIX}users.last_visit");
	/*
		array(9) { 
			   [0]=> string(4) "root" [1]=> string(1) "A" [2]=> string(4) "9944" [3]=> string(10) "1049362510" 
		           [4]=> string(5) "green" [5]=> string(6) "456557" [6]=> string(33) "Re: Deactivating TCP checksumming" 
		           [7]=> string(10) "1049299437" [8]=> string(1) "6"
		         }
	*/
		
	$user_entries='';
	while ($r = db_rowarr($c)) {
		$user_login = draw_user_link($r[0], $r[1], $r[4]);
		$user_login = '{TEMPLATE: reg_user_link}';

		if (!$r[7]) {
			$last_post = '{TEMPLATE: last_post_na}';
		} else {
			$last_post = ($usr->is_mod != 'A' && !isset($limit[$r[8]])) ?  '{TEMPLATE: no_view_perm}' : '{TEMPLATE: last_post}';
		}
		$user_entries .= '{TEMPLATE: user_entry}';
	}	
	qf($c);	

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: ONLINE_TODAY_PAGE}