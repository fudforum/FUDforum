<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: actions.php.t,v 1.36 2004/11/02 15:42:53 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

	if (!($FUD_OPT_1 & 536870912)) {
		std_error('disabled');
	}

	ses_update_status($usr->sid, '{TEMPLATE: actions_update}');

/*{POST_HTML_PHP}*/

	$limit = &get_all_read_perms(_uid, ($usr->users_opt & 524288));
	$admin = $usr->users_opt & 1048576;

	$c = uq('SELECT
			s.action, s.user_id, s.forum_id,
			u.alias, u.custom_color, s.time_sec, u.users_opt,
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
		if ($r[6] & 32768 && !$admin) {
			continue;
		}

		if ($r[3]) {
			$user_login = '{TEMPLATE: reg_user_link}';

			if (!$r[9]) {
				$last_post = '{TEMPLATE: last_post_na}';
			} else {
				$last_post = (!$admin && !$r[11] && empty($limit[$r[10]])) ? '{TEMPLATE: no_view_perm}' : '{TEMPLATE: last_post}';
			}
		} else {
			$user_login = '{TEMPLATE: anon_user}';
			$last_post = '{TEMPLATE: last_post_na}';
		}

		if (!$r[2] || $admin || !empty($limit[$r[2]]) || $r[12])) {
			if ($FUD_OPT_2 & 32768) {
				if (($s = strpos($r[0], 'href="')) !== false) {
					$s += 6;
					$s = substr($r[0], $s, (strpos($r[0], '"', $s) - $s));

					if ($s{strlen($s) - 1} == '/') {
						$tmp = explode('/', substr(str_replace('{ROOT}', '', $s), 1, -1));
						if ($FUD_OPT_1 & 128) {
							array_pop($tmp);
						}
						if ($FUD_OPT_2 & 8192) {
							array_pop($tmp);
						}
						$tmp[] = _rsid;
						$sn = '{ROOT}/' . implode('/', $tmp);
					} else {
						$sn = $s . '/' . _rsid;
					}
					$action = str_replace($s, $sn, $r[0]);
				} else {
					$action = $r[0];
				}
			} else {
				if (($p = strpos($r[0], '?')) !== false) {
					$action = substr_replace($r[0], '?'._rsid.'&', $p, 1);
				} else if (($p = strpos($r[0], '.php')) !== false) {
					$action = substr_replace($r[0], '.php?'._rsid.'&', $p, 4);
				} else {
					$action = $r[0];
				}
			}
		} else {
			$action = '{TEMPLATE: no_view_perm}';
		}

		$action_data .= '{TEMPLATE: action_entry}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: ACTION_PAGE}