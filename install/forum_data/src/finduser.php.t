<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: finduser.php.t,v 1.21 2003/09/18 14:16:47 hackie Exp $
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

	$adm = $usr->is_mod != 'A' ? 0 : 1;
	
	if ($MEMBER_SEARCH_ENABLED != 'Y' && !$adm) {
		std_error('disabled');
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
	$count = $GLOBALS['MEMBERS_PER_PAGE'];
	
	if (isset($_GET['pc'])) {
		$ord = 'posted_msg_count DESC';
	} else if (isset($_GET['us'])) {
		$ord = 'alias';
	} else {
		$ord = 'id DESC';
	}
	$usr_login = !empty($_GET['usr_login']) ? trim($_GET['usr_login']) : '';
	$usr_email = !empty($_GET['usr_email']) ? trim($_GET['usr_email']) : '';

	if ($usr_login) {
		$qry = "alias LIKE '".addslashes(htmlspecialchars(str_replace('\\', '\\\\', $usr_login)))."%' AND";
	} else if ($usr_email) {
		$qry = "email LIKE '".addslashes($usr_email)."%' AND";
	} else {
		$qry = '';
	}
	$lmt = ' LIMIT '.qry_limit($count, $start);

	$admin_opts = $adm ? '{TEMPLATE: findu_admin_opts_header}' : '';

	$find_user_data = '';
	$c = uq('SELECT blocked, home_page, email_messages, alias, join_date, posted_msg_count, id FROM {SQL_TABLE_PREFIX}users WHERE ' . $qry . ' id>1 ORDER BY ' . $ord . ' ' . $lmt);
	while ($r = db_rowobj($c)) {
		$pm_link = ($PM_ENABLED == 'Y' && _uid) ? '{TEMPLATE: pm_link}' : '';
		$homepage_link = strlen($r->home_page) ? '{TEMPLATE: homepage_link}' : '';
		$email_link = ($ALLOW_EMAIL == 'Y' && $r->email_messages == 'Y') ? '{TEMPLATE: email_link}' : '';

		if ($adm) {
			$admi = $r->blocked == 'Y' ? '{TEMPLATE: findu_unban}' : '{TEMPLATE: findu_ban}';
			$admi = '{TEMPLATE: findu_admin_opts}';
		} else {
			$admi = '';
		}

		$find_user_data .= '{TEMPLATE: find_user_entry}';
	}
	qf($c);
	if (!$find_user_data) {
		$find_user_data = '{TEMPLATE: find_user_no_results}';
	}

	$pager = '';
	if (!$qry) {
		$total = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}users ' . $qry);
		if ($total > $count) {
			if ($GLOBALS['USE_PATH_INFO'] == 'N') {
				$pg = '{ROOT}?t=finduser&amp;' . _rsid . '&amp;';
				if ($usr_login) {
					$pg .= urlencode($usr_login) . '&amp;';
				}
				if ($usr_email) {
					$pg .= urlencode($usr_email) . '&amp;';
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
			} else {
				$pg = '{ROOT}/ml/';
				if (isset($_GET['pc'])) {
					$pg .= '1/';
				} else if (isset($_GET['us'])) {
					$pg .= '2/';
				} else {
					$pg .= '/';
				}
				
				$pg2 = '';
				
				if ($usr_login) {
					$pg2 .= urlencode($usr_login) . '/';
				} else if ($usr_email) {
					$pg2 .= '/' . urlencode($usr_email) . '/';
				}
				if (isset($_GET['js_redr'])) {
					$pg2 .= '/';
				}
				if ($pg2) {
					$pg2 .= _rsid;
				} else {
					$pg2 = '/' . _rsid;
				}

				$pager = tmpl_create_pager($start, $count, $total, $pg, $pg2);
			}
		}
	}
	
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: FINDUSER_PAGE}