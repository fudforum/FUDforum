<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ignore_list.php.t,v 1.9 2003/04/02 16:17:11 hackie Exp $
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

	if (!_uid) {
		std_error('login');
		exit();
	}

function ignore_alias_fetch($al, &$is_mod)
{
	if (!($tmp = db_saq('SELECT id, is_mod FROM {SQL_TABLE_PREFIX}users WHERE alias=\''.addslashes(htmlspecialchars($al)).'\''))) {
		return;
	}
	$is_mod = $tmp[1] != 'A' ? 0 : 1;

	return $tmp[0];
}
	
	if (isset($_POST['add_login'])) {
		if (!($ignore_id = ignore_alias_fetch($_POST['add_login'], $is_mod))) {
			error_dialog('{TEMPLATE: ignore_list_err_nu_title}', '{TEMPLATE: ignore_list_err_nu_msg}', '');
		}
		if ($is_mod) {
			error_dialog('{TEMPLATE: ignore_list_err_info_title}', '{TEMPLATE: ignore_list_cantign_msg}', '');	
		}
		if (!empty($usr->ignore_list)) {
			$usr->ignore_list = @unserialize($usr->ignore_list);
		}
		if (!isset($usr->ignore_list[$ignore_id])) {
			ignore_add(_uid, $ignore_id);
		} else {
			error_dialog('{TEMPLATE: ignore_list_err_info_title}', '{TEMPLATE: ignore_list_err_dup_msg}', '');
		}
	}	

	/* incomming from message display page (ignore link) */
	if (isset($_GET['add']) && (int)$_GET['add']) {
		if (!empty($usr->ignore_list)) {
			$usr->ignore_list = @unserialize($usr->ignore_list);
		}

		if (($ignore_id = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}users WHERE id='.(int)$_GET['add'].' AND is_mod!=\'A\'')) && !isset($usr->buddy_list[$buddy_id])) {
			buddy_add(_uid, $ignore_id);
		}
		check_return($ses->returnto);
	}

	if (isset($_GET['del']) && (int)$_GET['del']) {
		ignore_delete(_uid, (int)$_GET['del']);
		/* needed for external links to this form */
		if (isset($_GET['redr'])) {
			check_return($ses->returnto);
		}
	}

	$ses->update('{TEMPLATE: ignore_list_update}');

	$ignore_member_search = ($MEMBER_SEARCH_ENABLED == 'Y') ? '{TEMPLATE: ignore_member_search}' : '';
	
/*{POST_HTML_PHP}*/
	
	$c = uq('SELECT 
			{SQL_TABLE_PREFIX}user_ignore.ignore_id,
			{SQL_TABLE_PREFIX}user_ignore.id as ignoreent_id,
			{SQL_TABLE_PREFIX}users.id,
			{SQL_TABLE_PREFIX}users.alias AS login,
			{SQL_TABLE_PREFIX}users.join_date,
			{SQL_TABLE_PREFIX}users.posted_msg_count,
			{SQL_TABLE_PREFIX}users.home_page 
		FROM {SQL_TABLE_PREFIX}user_ignore
		LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}user_ignore.ignore_id={SQL_TABLE_PREFIX}users.id 
		WHERE {SQL_TABLE_PREFIX}user_ignore.user_id='._uid);
	
	$ignore_list = '';
	if (($r = @db_rowarr($c))) {
		do {
			if ($r[0]) {
				$homepage_link = $r[6] ? '{TEMPLATE: homepage_link}' : '';
				$email_link = $ALLOW_EMAIL == 'Y'  ? '{TEMPLATE: email_link}' : '';
				$ignore_list .= '{TEMPLATE: ignore_user}';
			} else {
				$ignore_list .=	'{TEMPLATE: ignore_anon_user}';
			}
		} while (($r = db_rowarr($c)));
		$ignore_list = '{TEMPLATE: ignore_list}';
	}
	qf($res);
	
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: IGNORELIST_PAGE}