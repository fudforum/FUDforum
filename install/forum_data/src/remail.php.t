<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: remail.php.t,v 1.34 2009/01/29 18:37:17 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	if (isset($_POST['done'])) {
		check_return($usr->returnto);
	}

	if (__fud_real_user__) {
		is_allowed_user($usr);
	} else if (is_ip_blocked(get_ip())) {
		invl_inp_err();
	}

	if ((isset($_GET['th']) && ($th = (int)$_GET['th'])) || (isset($_POST['th']) && ($th = (int)$_POST['th']))) {
		$data = db_sab('SELECT m.subject, t.id, mm.id AS md, COALESCE(g2.group_cache_opt, g1.group_cache_opt) AS gco
				FROM {SQL_TABLE_PREFIX}thread t
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=t.forum_id AND mm.user_id='._uid.'
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=t.forum_id
				LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id
				WHERE t.id='.$th);
		if (!$data) {
			invl_inp_err();
		}
	} else {
		invl_inp_err();
	}

	if (!$is_a && !$data->md && !($data->gco & 2)) {
		std_error('access');
	}

/*{POST_HTML_PHP}*/

	if (isset($_POST['posted']) && _uid && !check_femail_form()) {
		$to = empty($POST['fname']) ? $_POST['femail'] : $_POST['fname'].' <'.$_POST['femail'].'>';
		$from = $usr->alias. '<'.$usr->email.'>';
		send_email($from, $to, $_POST['subj'], $_POST['body']);

		error_dialog('{TEMPLATE: remail_emailsent}', '{TEMPLATE: remail_sent_conf}');
	} else if (!isset($_POST['posted'])) {
		$def_thread_view = $FUD_OPT_2 & 4 ? 'msg' : 'tree';
	}

	$form_data = _uid ? '{TEMPLATE: registed_user}' : '{TEMPLATE: anon_user}';

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: REMAIL_PAGE}