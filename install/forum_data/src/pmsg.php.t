<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: pmsg.php.t,v 1.28 2003/10/02 15:27:24 hackie Exp $
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

	if (!($FUD_OPT_1 & 1024)) {
		error_dialog('{TEMPLATE: pm_err_nopm_title}', '{TEMPLATE: pm_err_nopm_msg}');
	}

	if (!_uid) {
		std_error('login');
	}

/*{POST_HTML_PHP}*/

	/* moving or deleting a message */
	if (isset($_POST['sel']) || isset($_GET['sel'])) {
		$sel = isset($_POST['sel']) ? $_POST['sel'] : $_GET['sel'];
		if (!is_array($sel)) {
			$sel = array($sel);
		}
		$move_to = (!isset($_POST['btn_delete']) && isset($_POST['moveto'], $folders[$_POST['moveto']])) ? (int) $_POST['moveto'] : 0;
		foreach ($sel as $m) {
			if ($move_to) {
				pmsg_move((int)$m, $move_to, false);
			} else {
				pmsg_del((int)$m);
			}
		}
	}

	if (isset($_GET['folder_id']) && isset($folders[$_GET['folder_id']])) {
		$folder_id = $_GET['folder_id'];
	} else if (isset($_POST['folder_id']) && isset($folders[$_POST['folder_id']])) {
		$folder_id = $_POST['folder_id'];
	} else {
		$folder_id = 1;
	}

	ses_update_status($usr->sid, '{TEMPLATE: pm_update}');

	$cur_ppage = tmpl_cur_ppage($folder_id, $folders);

	$lnk = $folder_id == 4 ? '{ROOT}?t=pmsg&amp;msg_id' : '';
	$author_dest_col = $folder_id == 3 ? '{TEMPLATE: pmsg_recepient}' : '{TEMPLATE: pmsg_author}';

	$select_options_cur_folder = tmpl_draw_select_opt(implode("\n", array_keys($folders)), implode("\n", $folders), $folder_id, '{TEMPLATE: cur_folder_opt}', '{TEMPLATE: cur_folder_opt_selected}');

	$disk_usage = q_singleval('SELECT SUM(length) FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id='._uid);
	$percent_full = ceil($disk_usage / $MAX_PMSG_FLDR_SIZE * 100);
	$full_indicator = ceil($percent_full * 1.69);

	if ($percent_full < 90) {
		$full_indicator = '{TEMPLATE: normal_full_indicator}';
	} else if ($percent_full >= 90 && $percent_full < 100) {
		$full_indicator = '{TEMPLATE: alert_full_indicator}';
	} else {
		$full_indicator = '{TEMPLATE: full_full_indicator}';
	}

	if (($all_v = empty($_GET['all']))) {
		$desc = '{TEMPLATE: pmsg_all}';
	} else {
		$desc = '{TEMPLATE: pmsg_none}';
	}

	$c = uq('SELECT p.id, p.read_stamp, p.post_stamp, p.duser_id, p.ouser_id, p.subject, p.pmsg_opt, p.fldr, p.pdest,
			u.users_opt, u.alias, u.last_visit AS time_sec,
			u2.users_opt AS users_opt2, u2.alias AS alias2, u2.last_visit AS time_sec2
		FROM {SQL_TABLE_PREFIX}pmsg p
		INNER JOIN {SQL_TABLE_PREFIX}users u ON p.ouser_id=u.id
		LEFT JOIN {SQL_TABLE_PREFIX}users u2 ON p.pdest=u2.id
		WHERE duser_id='._uid.' AND fldr='.$folder_id.' ORDER BY post_stamp DESC');

	$private_msg_entry = '';
	while ($obj = db_rowobj($c)) {
		switch ($obj->fldr) {
			case 1:
			case 2:
				$action = '{TEMPLATE: action_buttons_inbox}';
				break;
			case 3:
				$obj->users_opt = $obj->users_opt2;
				$obj->alias = $obj->alias2;
				$obj->time_sec = $obj->time_sec2;
				$obj->ouser_id = $obj->pdest;
				$action = '';
				break;
			case 5:
				$action = '{TEMPLATE: action_buttons_sent_trash}';
				break;
			case 4:
				$action = '{TEMPLATE: action_buttons_draft}';
				break;
		}
		if ($FUD_OPT_2 & 32768 && !empty($_SERVER['PATH_INFO'])) {
			$goto = $folder_id != 4 ? '{ROOT}/pmv/'.$obj->id.'/'._rsid : '{ROOT}/pmm/msg_id/'.$obj->id.'/'._rsid;
		} else {
			$goto = $folder_id != 4 ? '{ROOT}?t=pmsg_view&amp;'._rsid.'&amp;id='.$obj->id : '{ROOT}?t=ppost&amp;'._rsid.'&amp;msg_id='.$obj->id;
		}


		$pmsg_status = $obj->read_stamp ? '{TEMPLATE: pmsg_unread}' : '{TEMPLATE: pmsg_read}';
		if ($obj->pmsg_opt & 4 && $obj->pmsg_opt & 16 && $obj->duser_id == _uid && $obj->ouser_id != _uid) {
			$deny_recipt = '{TEMPLATE: deny_recipt}';
		} else {
			$deny_recipt = '';
		}

		if ($FUD_OPT_2 & 32 && (!($obj->users_opt & 32768) || $usr->users_opt & 1048576)) {
			$obj->login =& $obj->alias;
			if (($obj->time_sec + $LOGEDIN_TIMEOUT * 60) > __request_timestamp__) {
				$online_indicator = '{TEMPLATE: pmsg_online_indicator}';
			} else {
				$online_indicator = '{TEMPLATE: pmsg_offline_indicator}';
			}
		} else {
			$online_indicator = '';
		}

		if ($obj->pmsg_opt & 64) {
			$msg_type ='{TEMPLATE: replied_msg}';
		} else if ($obj->pmsg_opt & 32) {
			$msg_type = '{TEMPLATE: normal_msg}';
		} else {
			$msg_type ='{TEMPLATE: forwarded_msg}';
		}

		$checked = !$all_v ? ' checked' : '';

		$private_msg_entry .= '{TEMPLATE: private_msg_entry}';
	}
	qf($r);

	if (!$private_msg_entry) {
		$private_msg_entry = '{TEMPLATE: private_no_messages}';
		$private_tools = '';
	} else {
		$btn_action = $folder_id == 5 ? '{TEMPLATE: restore_to}' : '{TEMPLATE: move_to}';
		unset($folders[$folder_id]);
		$moveto_list = tmpl_draw_select_opt(implode("\n", array_keys($folders)), implode("\n", $folders), '', '{TEMPLATE: move_to_opt}', '{TEMPLATE: move_to_opt_selected}');
		$private_tools = '{TEMPLATE: private_tools}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PMSG_PAGE}