<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: pmsg.php.t,v 1.68 2009/09/15 18:11:29 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	if (!($FUD_OPT_1 & 1024)) {
		error_dialog('{TEMPLATE: pm_err_nopm_title}', '{TEMPLATE: pm_err_nopm_msg}');
	}

	if (__fud_real_user__) {
		is_allowed_user($usr);
	} else {
		std_error('login');
	}

/*{POST_HTML_PHP}*/

	/* empty trash */
	if (isset($_POST['btn_trash'])) {
		$c = q("SELECT id FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id="._uid." AND fldr=5");
		while ($r = db_rowarr($c)) {
			pmsg_del((int)$r[0], 5);
		}
		unset($c, $_POST['sel'], $_GET['sel']); /* prevent message selection cofusion */
		if (isset($_POST['old_folder_id'])) {
			$_GET['folder_id'] = $_POST['old_folder_id'];
		}
	}

	/* moving or deleting a message */
	if (isset($_POST['sel']) || isset($_GET['sel'])) {
		if (!empty($_POST['btn_pdf'])) {
			header('Location: {FULL_ROOT}pdf.php?sel[]='.implode('&sel[]=', $_POST['sel']).'&'._rsidl);
			exit;		
		}
		$sel = isset($_POST['sel']) ? (array)$_POST['sel'] : (array)$_GET['sel'];
		$move_to = (!isset($_POST['btn_delete']) && isset($_POST['moveto'], $folders[$_POST['moveto']])) ? (int) $_POST['moveto'] : 0;

		if (!$move_to && !isset($_POST['old_folder_id'])) {
			$_POST['old_folder_id'] = q_singleval('SELECT fldr FROM {SQL_TABLE_PREFIX}pmsg WHERE id='.$sel[0]);
		}

		foreach ($sel as $m) {
			if ($move_to) {
				pmsg_move((int)$m, $move_to, 0);
			} else {
				pmsg_del((int)$m);
			}
		}
		if (!$move_to && isset($_POST['old_folder_id'])) {
			$_GET['folder_id'] = $_POST['old_folder_id'];
		}
	}

	if (isset($_GET['folder_id'], $folders[(int)$_GET['folder_id']])) {
		$folder_id = (int) $_GET['folder_id'];
	} else if (isset($_POST['folder_id'], $folders[(int)$_POST['folder_id']])) {
		$folder_id = (int) $_POST['folder_id'];
	} else {
		$folder_id = 1;
	}

	ses_update_status($usr->sid, '{TEMPLATE: pm_update}');

	$select_options_cur_folder = tmpl_draw_select_opt(implode("\n", array_keys($folders)), implode("\n", $folders), $folder_id);

	if ($GLOBALS['FUD_OPT_3'] & 32768) {
		$disk_usage  = q_singleval('SELECT SUM(length) FROM {SQL_TABLE_PREFIX}pmsg WHERE foff>0 AND duser_id='._uid);
		$disk_usage += q_singleval('SELECT SUM(LENGTH(data)) FROM {SQL_TABLE_PREFIX}pmsg p INNER JOIN {SQL_TABLE_PREFIX}msg_store m ON p.length=m.id WHERE foff<0 AND duser_id='._uid);
	} else {
		$disk_usage = q_singleval('SELECT SUM(length) FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id='._uid);
	}
	if ($usr->users_opt & 524288) {
		$ms = $MAX_PMSG_FLDR_SIZE_PM;
	} else if ($usr->users_opt & 1048576) {
		$ms = $MAX_PMSG_FLDR_SIZE_AD;
	} else {
		$ms = $MAX_PMSG_FLDR_SIZE;
	}

	$percent_full = ceil($disk_usage / $ms * 100);
	$full_indicator = ceil($percent_full * 1.69);

	if ($percent_full < 90) {
		$full_indicator = '{TEMPLATE: normal_full_indicator}';
	} else if ($percent_full >= 90 && $percent_full < 100) {
		$full_indicator = '{TEMPLATE: alert_full_indicator}';
	} else {
		$full_indicator = '{TEMPLATE: full_full_indicator}';
	}

	if (isset($_GET['o'])) {
		switch ($_GET['o']) {
			case 'subject':
				$o = 'p.subject';
			case 'alias':
				$o = 'u.alias';
			case 'alias2':
				$o = 'u2.alias';
				break;
			default:
				$o = 'p.post_stamp';
		}	
	} else {
		$o = 'post_stamp';
	}

	if (isset($_GET['s']) && $_GET['s'] == 'a') {
		$s = 'ASC';
	} else {
		$s = 'DESC';
	}

	$ttl = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id="._uid." AND fldr=".$folder_id);
	$count = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;
	$start = (empty($_GET['start']) || $_GET['start'] >= $ttl) ? 0 : (int) $_GET['start'];

	$c = uq('SELECT p.id, p.read_stamp, p.post_stamp, p.duser_id, p.ouser_id, p.subject, p.pmsg_opt, p.fldr, p.pdest, p.to_list,
			u.users_opt, u.alias, u.last_visit AS time_sec,
			u2.users_opt AS users_opt2, u2.alias AS alias2, u2.last_visit AS time_sec2
		FROM {SQL_TABLE_PREFIX}pmsg p
		INNER JOIN {SQL_TABLE_PREFIX}users u ON p.ouser_id=u.id
		LEFT JOIN {SQL_TABLE_PREFIX}users u2 ON p.pdest=u2.id
		WHERE duser_id='._uid.' AND fldr='.$folder_id.' ORDER BY '.$o.' '.$s.' LIMIT '.qry_limit($count, $start));

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

		if ($FUD_OPT_2 & 32 && (!($obj->users_opt & 32768) || $is_a)) {
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

		$private_msg_entry .= '{TEMPLATE: private_msg_entry}';
	}
	unset($c);

	if (!$private_msg_entry) {
		$private_msg_entry = '{TEMPLATE: private_no_messages}';
		$private_tools = '';
	} else {
		if ($folder_id == 5) {
			$btn_action = '{TEMPLATE: restore_to}';
			$btn_del_name = 'btn_trash';
			$btn_del_title = '{TEMPLATE: pmsg_trash}';
		} else {
			$btn_action = '{TEMPLATE: move_to}';
			$btn_del_name = 'btn_delete';
			$btn_del_title = '{TEMPLATE: pmsg_delete}';
		}
		$tmp = $folders;
		unset($tmp[$folder_id]);
		$moveto_list = tmpl_draw_select_opt(implode("\n", array_keys($tmp)), implode("\n", $tmp), 2);
		$private_tools = '{TEMPLATE: private_tools}';
	}

	if ($FUD_OPT_2 & 32768) {
		$page_pager = tmpl_create_pager($start, $count, $ttl, '{ROOT}/pdm/' . $folder_id . '/0/'.strtolower($s[0]).'/'.$o.'/', '/' . _rsid);
	} else {
		$page_pager = tmpl_create_pager($start, $count, $ttl, '{ROOT}?t=pmsg&amp;s='.strtolower($s[0]).'&amp;o='.$o.'&amp;folder_id=' . $folder_id . '&amp;'. _rsid);
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PMSG_PAGE}
