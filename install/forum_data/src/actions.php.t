<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: actions.php.t,v 1.20 2003/06/03 15:46:11 hackie Exp $
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
	
	ses_update_status($usr->sid, '{TEMPLATE: actions_update}');

/*{POST_HTML_PHP}*/
	
	$rand_val = get_random_value();
	
	if ($usr->is_mod != 'A') {
		$limit = &get_all_read_perms(_uid);
	}
	
	$c = uq('SELECT 
			s.action, s.user_id, s.forum_id,
			u.alias, u.is_mod, u.custom_color, s.time_sec,
			u.invisible_mode,
			m.id, m.subject, m.post_stamp,
			t.forum_id,
			mm1.id, mm2.id
		FROM {SQL_TABLE_PREFIX}ses s
		LEFT JOIN {SQL_TABLE_PREFIX}users u ON s.user_id=u.id
		LEFT JOIN {SQL_TABLE_PREFIX}msg m ON u.u_last_post_id=m.id
		LEFT JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
		LEFT JOIN {SQL_TABLE_PREFIX}mod mm1 ON mm1.forum_id=t.forum_id AND mm1.user_id='._uid.'
		LEFT JOIN {SQL_TABLE_PREFIX}mod mm2 ON mm2.forum_id=s.forum_id AND mm2.user_id='._uid.'
		WHERE s.time_sec>'.(__request_timestamp__ - ($LOGEDIN_TIMEOUT * 60)).' AND s.user_id!='._uid.' ORDER BY u.alias, s.time_sec DESC');
		
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
				$last_post = ($usr->is_mod != 'A' && !$r[12] && empty($limit[$r[11]])) ? '{TEMPLATE: no_view_perm}' : '{TEMPLATE: last_post}';
			}
		} else {
			$user_login = '{TEMPLATE: anon_user}';
			$last_post = '{TEMPLATE: last_post_na}';
		}

		if (!$r[2] || ($usr->is_mod == 'A' || !empty($limit[$r[2]]) || $r[13])) {
			if ($GLOBALS['USE_PATH_INFO'] == 'N') {
				if (($p = strpos($r[0], '?')) !== FALSE) {
					$action = substr_replace($r[0], '?'._rsid.'&', $p, 1);
				} else if (($p = strpos($r[0], '.php')) !== FALSE) {
					$action = substr_replace($r[0], '.php?'._rsid.'&', $p, 4);
				} else {
					$action = $r[0];	
				}
			} else {
				$s = strpos($r[0], '"', (strpos($r[0], 'href="') + 7));
				$action = substr_replace($r[0], _rsid, $s, 0);
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