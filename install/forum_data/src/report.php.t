<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: report.php.t,v 1.17 2004/03/08 15:28:59 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

	if ((!isset($_GET['msg_id']) || !($msg_id = (int)$_GET['msg_id'])) && (!isset($_POST['msg_id']) || !($msg_id = (int)$_POST['msg_id']))) {
		error_dialog('{TEMPLATE: report_err_nosuchmsg_title}', '{TEMPLATE: report_err_nosuchmsg_msg}');
	}
	if (!_uid) {
		std_error('login');
	}

	/* permission check */
	is_allowed_user($usr);

	$msg = db_sab('SELECT t.forum_id, m.subject, m.post_stamp, u.alias, mm.id AS md, ((CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) & 2) > 0 AS gco, mr.id AS reported
			FROM {SQL_TABLE_PREFIX}msg m
			INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=t.forum_id
			LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=t.forum_id AND mm.user_id='._uid.'
			LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
			LEFT JOIN {SQL_TABLE_PREFIX}msg_report mr ON mr.msg_id='.$msg_id.' AND mr.user_id='._uid.'
			WHERE m.id='.$msg_id.' AND m.apr=1');
	if (!$msg) {
		invl_inp_err();
	}

	if (!($usr->users_opt & 1048576) && !$msg->md && !$msg->gco) {
		std_error('access');
	}

	if ($msg->reported) {
		error_dialog('{TEMPLATE: report_already_reported_title}', '{TEMPLATE: report_already_reported_msg}');
	}

	if (!empty($_POST['reason']) && ($reason = trim($_POST['reason']))) {
		q("INSERT INTO {SQL_TABLE_PREFIX}msg_report (user_id, msg_id, reason, stamp) VALUES("._uid.", ".$msg_id.", '".addslashes(htmlspecialchars($reason))."', ".__request_timestamp__.")");
		check_return($usr->returnto);
	} else if (count($_POST)) {
		$reason_error = '{TEMPLATE: report_empty_report}';
	} else {
		$reason_error = '';
	}

/*{POST_HTML_PHP}*/

	$user_login = $msg->alias ? $msg->alias : $GLOBALS['ANON_NICK'];

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: REPORT_PAGE}