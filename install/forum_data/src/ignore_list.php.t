<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ignore_list.php.t,v 1.37 2006/09/05 13:16:49 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	if (!_uid) {
		std_error('login');
	}

function ignore_alias_fetch($al, &$is_mod)
{
	if (!($tmp = db_saq("SELECT id, (users_opt & 1048576) FROM {SQL_TABLE_PREFIX}users WHERE alias="._esc(char_fix(htmlspecialchars($al)))))) {
		return;
	}
	$is_mod = $tmp[1];

	return $tmp[0];
}

	if (isset($_POST['add_login']) && is_string($_POST['add_login'])) {
		if (!($ignore_id = ignore_alias_fetch($_POST['add_login'], $is_mod))) {
			error_dialog('{TEMPLATE: ignore_list_err_nu_title}', '{TEMPLATE: ignore_list_err_nu_msg}');
		}
		if ($is_mod) {
			error_dialog('{TEMPLATE: ignore_list_err_info_title}', '{TEMPLATE: ignore_list_cantign_msg}');
		}
		if (!empty($usr->ignore_list)) {
			$usr->ignore_list = unserialize($usr->ignore_list);
		}
		if (!isset($usr->ignore_list[$ignore_id])) {
			ignore_add(_uid, $ignore_id);
		} else {
			error_dialog('{TEMPLATE: ignore_list_err_info_title}', '{TEMPLATE: ignore_list_err_dup_msg}');
		}
	}

	/* incomming from message display page (ignore link) */
	if (isset($_GET['add']) && ($_GET['add'] = (int)$_GET['add'])) {
		if (!sq_check(0, $usr->sq)) {
			check_return($usr->returnto);
		}

		if (!empty($usr->ignore_list)) {
			$usr->ignore_list = unserialize($usr->ignore_list);
		}

		if (($ignore_id = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}users WHERE id='.$_GET['add'].' AND (users_opt & 1048576)=0')) && !isset($usr->ignore_list[$ignore_id])) {
			ignore_add(_uid, $ignore_id);
		}
		check_return($usr->returnto);
	}

	/* anon user hack */
	if (isset($_GET['del']) && $_GET['del'] === "0") {
		$_GET['del'] = 1;
	}

	if (isset($_GET['del']) && ($_GET['del'] = (int)$_GET['del'])) {
		if (!sq_check(0, $usr->sq)) {
			check_return($usr->returnto);
		}

		ignore_delete(_uid, $_GET['del']);
		/* needed for external links to this form */
		if (isset($_GET['redr'])) {
			check_return($usr->returnto);
		}
	}

	ses_update_status($usr->sid, '{TEMPLATE: ignore_list_update}');

/*{POST_HTML_PHP}*/

	$c = uq('SELECT ui.ignore_id, ui.id as ignoreent_id,
			u.id, u.alias AS login, u.join_date, u.posted_msg_count, u.home_page
		FROM {SQL_TABLE_PREFIX}user_ignore ui
		LEFT JOIN {SQL_TABLE_PREFIX}users u ON ui.ignore_id=u.id
		WHERE ui.user_id='._uid);

	$ignore_list = '';
	if (($r = db_rowarr($c))) {
		do {
			$ignore_list .= $r[0] ? '{TEMPLATE: ignore_user}' : '{TEMPLATE: ignore_anon_user}';
		} while (($r = db_rowarr($c)));
		$ignore_list = '{TEMPLATE: ignore_list}';
	}
	unset($c);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: IGNORELIST_PAGE}