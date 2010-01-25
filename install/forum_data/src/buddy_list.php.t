<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	if (!_uid) {
		std_error('login');
	}

	if (isset($_POST['add_login']) && is_string($_POST['add_login'])) {
		if (!($buddy_id = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}users WHERE alias='._esc(char_fix(htmlspecialchars($_POST['add_login'])))))) {
			error_dialog('{TEMPLATE: buddy_list_err_nouser_title}', '{TEMPLATE: buddy_list_err_nouser}');
		}
		if ($buddy_id == _uid) {
			error_dialog('{TEMPLATE: err_info}', '{TEMPLATE: buddy_list_err_cantadd}');
		}
		if (q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}user_ignore WHERE user_id='.$buddy_id.' AND ignore_id='._uid)) {
			error_dialog('{TEMPLATE: err_info}', '{TEMPLATE: buddy_list_err_ignore}');
		}

		if (!empty($usr->buddy_list)) {
			$usr->buddy_list = unserialize($usr->buddy_list);
		}

		if (!isset($usr->buddy_list[$buddy_id]) && !q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}user_ignore WHERE user_id='.$buddy_id.' AND ignore_id='._uid)) {
			$usr->buddy_list = buddy_add(_uid, $buddy_id);
		} else {
			error_dialog('{TEMPLATE: err_info}', '{TEMPLATE: buddy_list_err_dup}');
		}
	}

	/* incomming from message display page (add buddy link) */
	if (isset($_GET['add']) && ($_GET['add'] = (int)$_GET['add'])) {
		if (!sq_check(0, $usr->sq)) {
			check_return($usr->returnto);
		}

		if (!empty($usr->buddy_list)) {
			$usr->buddy_list = unserialize($usr->buddy_list);
		}

		if (($buddy_id = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}users WHERE id='.$_GET['add'])) && !isset($usr->buddy_list[$buddy_id]) && _uid != $buddy_id && !q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}user_ignore WHERE user_id='.$buddy_id.' AND ignore_id='._uid)) {
			buddy_add(_uid, $buddy_id);
		}
		check_return($usr->returnto);
	}

	if (isset($_GET['del']) && ($_GET['del'] = (int)$_GET['del'])) {
		if (!sq_check(0, $usr->sq)) {
			check_return($usr->returnto);
		}

		buddy_delete(_uid, $_GET['del']);
		/* needed for external links to this form */
		if (isset($_GET['redr'])) {
			check_return($usr->returnto);
		}
	}

	ses_update_status($usr->sid, '{TEMPLATE: buddy_list_update}');

/*{POST_HTML_PHP}*/

	$c = uq('SELECT b.bud_id, u.id, u.alias, u.join_date, u.bday, (u.users_opt & 32768), u.posted_msg_count, u.home_page, u.last_visit AS time_sec
		FROM {SQL_TABLE_PREFIX}buddy b INNER JOIN {SQL_TABLE_PREFIX}users u ON b.bud_id=u.id WHERE b.user_id='._uid);

	$buddies = '';
	/* Result index
	 * 0 - bud_id	1 - user_id	2 - login	3 - join_date	4 - bday	5 - users_opt	6 - msg_count
	 * 7 - home_page	8 - last_visit
	 */

	if (($r = db_rowarr($c))) {
		$dt = getdate(__request_timestamp__);
		$md = sprintf('%02d%02d', $dt['mon'], $dt['mday']);

		do {
			if ((!($r[5] & 32768) && $FUD_OPT_2 & 32) || $is_a) {
				$online_status = (($r[8] + $LOGEDIN_TIMEOUT * 60) > __request_timestamp__) ? '{TEMPLATE: online_indicator}' : '{TEMPLATE: offline_indicator}';
			} else {
				$online_status = '';
			}

			if ($r[4] && substr($r[4], 4) == $md) {
				$age = $dt['year'] - substr($r[4], 0, 4);
				$bday_indicator = '{TEMPLATE: bday_indicator}';
			} else {
				$bday_indicator = '';
			}

			$buddies .= '{TEMPLATE: buddy}';
		} while (($r = db_rowarr($c)));
		$buddies = '{TEMPLATE: buddy_list}';
	}
	unset($c);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: BUDDYLIST_PAGE}
