<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: online_today.php.t,v 1.16 2003/10/05 22:18:41 hackie Exp $
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

	ses_update_status($usr->sid, '{TEMPLATE: online_today_update}');

/*{POST_HTML_PHP}*/

	$dt = explode(' ', date('m d Y', __request_timestamp__));
	$today = mktime(0, 0, 0, $dt[0], $dt[1], $dt[2]);

	$c = uq('SELECT
			u.alias AS login, u.users_opt, u.id, u.last_visit, u.custom_color,
			m.id AS mid, m.subject, m.post_stamp,
			t.forum_id,
			mm.id,
			(CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) AS gco
		FROM {SQL_TABLE_PREFIX}users u
		LEFT JOIN {SQL_TABLE_PREFIX}msg m ON u.u_last_post_id=m.id
		LEFT JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
		LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=t.forum_id AND mm.user_id='._uid.'
		INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=t.forum_id
		LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id
		WHERE u.last_visit>'.$today.' AND '.(!($usr->users_opt & 1048576) ? "(u.users_opt & 32768)=0 AND" : '').' u.id!='._uid.'
		ORDER BY u.alias, u.last_visit');
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
		} else if ($r[10] & 1 || $r[9] || $usr->users_opt & 1048576) {
			$last_post = '{TEMPLATE: last_post}';
		} else {
			$last_post = '{TEMPLATE: no_view_perm}';
		}

		$user_entries .= '{TEMPLATE: user_entry}';
	}
	qf($c);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: ONLINE_TODAY_PAGE}