<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: actions.php.t,v 1.17 2003/04/03 10:03:31 hackie Exp $
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
	
	if ($ACTION_LIST_ENABLED != 'Y') {
		std_error('disabled');
	}
	
	$ses->update('{TEMPLATE: actions_update}');

/*{POST_HTML_PHP}*/
	
	$rand_val = get_random_value();
	
	if ($usr->is_mod != 'A') {
		$limit = &get_all_read_perms(_uid);
	}
	
	$c = q("SELECT 
			{SQL_TABLE_PREFIX}ses.action,
			{SQL_TABLE_PREFIX}ses.user_id,
			{SQL_TABLE_PREFIX}ses.forum_id,
			{SQL_TABLE_PREFIX}users.alias,
			{SQL_TABLE_PREFIX}users.is_mod,
			{SQL_TABLE_PREFIX}users.custom_color,
			{SQL_TABLE_PREFIX}ses.time_sec,
			{SQL_TABLE_PREFIX}users.invisible_mode,
			{SQL_TABLE_PREFIX}msg.id,
			{SQL_TABLE_PREFIX}msg.subject,
			{SQL_TABLE_PREFIX}msg.post_stamp,
			{SQL_TABLE_PREFIX}thread.forum_id
		FROM {SQL_TABLE_PREFIX}ses 
		LEFT JOIN {SQL_TABLE_PREFIX}users 
			ON {SQL_TABLE_PREFIX}ses.user_id={SQL_TABLE_PREFIX}users.id
		LEFT JOIN {SQL_TABLE_PREFIX}msg
			ON {SQL_TABLE_PREFIX}users.u_last_post_id={SQL_TABLE_PREFIX}msg.id
		LEFT JOIN {SQL_TABLE_PREFIX}thread
			ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
		WHERE {SQL_TABLE_PREFIX}ses.time_sec>".(__request_timestamp__-($LOGEDIN_TIMEOUT*60))." AND {SQL_TABLE_PREFIX}ses.ses_id!='".$ses->ses_id."' ORDER BY {SQL_TABLE_PREFIX}users.alias, {SQL_TABLE_PREFIX}ses.time_sec DESC");
		
	$action_data = '';
	while ($r = db_rowarr($c)) {
		if ($r[7] == 'Y' && $usr->is_mod != 'A') {
			continue;
		}

		if ($r[3]) {
			$user_login = draw_user_link($r[3], $r[4], $r[5]);
			$user_login = '{TEMPLATE: reg_user_link}';
			
			if (!$r[10]) {
				$last_post = '{TEMPLATE: last_post_na}';
			} else {
				$last_post = ($usr->is_mod != 'A' && !isset($limit[$r[11]])) ? '{TEMPLATE: no_view_perm}' : '{TEMPLATE: last_post}';
			}
		} else {
			$user_login = '{TEMPLATE: anon_user}';
			$last_post = '{TEMPLATE: last_post_na}';
		}

		if (!$r[2] || $usr->is_mod == 'A' || isset($limit[$r[2]])) {
			if (($p = strpos($r[0], '?')) !== FALSE) {
				$action = substr_replace($r[0], '?'._rsid.'&', $p, 1);
			} else if (($p = strpos($r[0], '.php')) !== FALSE) {
				$action = substr_replace($r[0], '.php?'._rsid.'&', $p, 4);
			} else {
				$action = $r[0];	
			}
		} else {
			$action = '{TEMPLATE: no_view_perm}';
		}

		$action_data .= '{TEMPLATE: action_entry}';
	}
	qf($c);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: ACTION_PAGE}