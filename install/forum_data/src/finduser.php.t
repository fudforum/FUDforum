<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: finduser.php.t,v 1.42 2004/11/02 15:20:59 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

	$adm = $usr->users_opt & 1048576;

	if (!$adm && !($FUD_OPT_1 & 8388608) && (!($FUD_OPT_1 & 4194304) || !_uid)) {
		std_error((!_uid ? 'login' : 'disabled'));
	}

	if (isset($_GET['js_redr'])) {
		define('plain_form', 1);
		$adm = 0;
	}

	$TITLE_EXTRA = ': {TEMPLATE: finduser_title}';

	ses_update_status($usr->sid, '{TEMPLATE: finduser_update}');

/*{POST_HTML_PHP}*/

	if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
		$start = 0;
	}
	$count = $MEMBERS_PER_PAGE;

	if (isset($_GET['pc'])) {
		$ord = 'posted_msg_count DESC';
	} else if (isset($_GET['us'])) {
		$ord = 'alias';
	} else {
		$ord = 'id DESC';
	}
	$usr_login = !empty($_GET['usr_login']) ? trim($_GET['usr_login']) : '';

	if ($usr_login) {
		$qry = "alias LIKE '".addslashes(htmlspecialchars(str_replace('\\', '\\\\', $usr_login)))."%' AND";
	} else {
		$qry = '';
	}
	$lmt = ' LIMIT '.qry_limit($count, $start);

	$find_user_data = '';
	$c = uq('SELECT /*!40000 SQL_CALC_FOUND_ROWS */ home_page, users_opt, alias, join_date, posted_msg_count, id FROM {SQL_TABLE_PREFIX}users WHERE ' . $qry . ' id>1 ORDER BY ' . $ord . ' ' . $lmt);
	while ($r = db_rowobj($c)) {
		$find_user_data .= '{TEMPLATE: find_user_entry}';
	}
	if (!$find_user_data) {
		$colspan = $adm ? 5 : 4;
		$find_user_data = '{TEMPLATE: find_user_no_results}';
	}

	if ($FUD_OPT_2 & 32768) {
		$ul = $usr_login ? urlencode($usr_login) : 0;
	}

	$pager = '';
	if (($total = (int) q_singleval("SELECT /*!40000 FOUND_ROWS(), */ -1")) < 0) {
		$total = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}users WHERE ' . $qry . ' id > 1');
	}
	if ($total > $count) {
		if ($FUD_OPT_2 & 32768) {
			$pg = '{ROOT}/ml/';
			if (isset($_GET['pc'])) {
				$pg .= '1/';
			} else if (isset($_GET['us'])) {
				$pg .= '2/';
			} else {
				$pg .= '0/';
			}

			$pg2 = '/' . ($usr_login ? urlencode($usr_login) : 0) . '/';

			if (isset($_GET['js_redr'])) {
				$pg2 .= '1/';
			}
			$pg2 .= _rsid;

			$pager = tmpl_create_pager($start, $count, $total, $pg, $pg2);
		} else {
			$pg = '{ROOT}?t=finduser&amp;' . _rsid . '&amp;';
			if ($usr_login) {
				$pg .= 'usr_login='.urlencode($usr_login) . '&amp;';
			}
			if (isset($_GET['pc'])) {
				$pg .= 'pc=1&amp;';
			}
			if (isset($_GET['us'])) {
				$pg .= 'us=1&amp;';
			}
			if (isset($_GET['js_redr'])) {
				$pg .= 'js_redr='.urlencode($_GET['js_redr']).'&amp;';
			}
			$pager = tmpl_create_pager($start, $count, $total, $pg);
		}
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: FINDUSER_PAGE}