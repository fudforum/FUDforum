<?php
/**
* copyright            : (C) 2001-2023 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function draw_tmpl_perm_table($perm, $perms, $names)
{
	$str = '';
	foreach ($perms as $k => $v) {
		$str .= ($perm & $v[0]) ? '{TEMPLATE: perm_yes}' : '{TEMPLATE: perm_no}';
	}
	return $str;
}

/*{PRE_HTML_PHP}*/

	if (!_uid) {
		std_error('login');
	}
	$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : (isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0);

	if ($group_id && !$is_a && !q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='. $group_id .' AND user_id='. _uid .' AND group_members_opt>=131072 AND '. q_bitand('group_members_opt', 131072) .' > 0')) {
		std_error('access');
	}

	$hdr = group_perm_array();
	/* Fetch all the groups user has access to. */
	if ($is_a) {
		$r = uq('SELECT id, name, forum_id FROM {SQL_TABLE_PREFIX}groups WHERE id>2 AND forum_id NOT IN (SELECT id FROM {SQL_TABLE_PREFIX}forum WHERE cat_id=0 OR url_redirect IS NOT NULL) ORDER BY name');
	} else {
		$r = uq('SELECT g.id, g.name, g.forum_id FROM {SQL_TABLE_PREFIX}group_members gm INNER JOIN {SQL_TABLE_PREFIX}groups g ON gm.group_id=g.id WHERE gm.user_id='. _uid .' AND group_members_opt>=131072 AND '. q_bitand('group_members_opt', 131072) .' > 0 ORDER BY g.name');
	}

	/* Make a group selection form. */
	$n = 0;
	$vl = $kl = '';
	while ($e = db_rowarr($r)) {
		$vl .= $e[0] . "\n";
	        $kl .= ($e[2] ? '* ' : '') . htmlspecialchars($e[1]) ."\n";
		$n++;
	}
	unset($r);

	if (!$n) {
		std_error('access');
	} else if ($n == 1) {
		$group_id = rtrim($vl);
		$group_selection = '';
	} else {
		if (!$group_id) {
			$group_id = (int)$vl;
		}
		$group_selection = '{TEMPLATE: group_selection}';
	}

/*{POST_HTML_PHP}*/

	if (isset($_POST['btn_cancel'])) {
		unset($_POST);
	}
	if (!($grp = db_sab('SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id='. $group_id))) {
		invl_inp_err();
	}

	/* Fetch controlled resources. */
	if (!$grp->forum_id) {
		$group_resources = '{TEMPLATE: group_resources}';
		$c = uq('SELECT f.name FROM {SQL_TABLE_PREFIX}group_resources gr INNER JOIN {SQL_TABLE_PREFIX}forum f ON gr.resource_id=f.id WHERE gr.group_id='. $group_id);
		while ($r = db_rowarr($c)) {
			$group_resources .= '{TEMPLATE: group_resource_ent}';
		}
		unset($c);
	} else {
		$fname = q_singleval('SELECT name FROM {SQL_TABLE_PREFIX}forum WHERE id='. $grp->forum_id);
		$group_resources = '{TEMPLATE: primary_group_resource}';
	}

	if ($is_a) {
		$maxperms = 2147483647;
	} else {
		$maxperms = (int) $grp->groups_opt;
		$inh = (int) $grp->groups_opti;
		$inh_id = (int) $grp->inherit_id;
		if ($inh_id && $inh) {
			$res = array($group_id => $group_id);
			while ($inh > 0) {
				if (isset($res[$inh_id])) { // Permissions loop.
					break;
				} else if (!($row = db_saq('SELECT groups_opt, groups_opti, inherit_id FROM {SQL_TABLE_PREFIX}groups WHERE id='. $inh_id))) {
					break; // Invalid group id.
				}
				$maxperms |= $inh & $row[0]; // Fetch permissions of new group.
				if (!$row[2] || !$row[1]) { // Nothing more to inherit.
					break;
				}
				$inh &= (int) $row[1];
				$inh_id = (int) $row[2];
				$res[$inh_id] = $inh_id;
			}
		}
	}

	$login_error = '';
	$perm = 0;

	if (isset($_POST['btn_submit'])) {
		foreach ($hdr as $k => $v) {
			if (isset($_POST[$k]) && $_POST[$k] & $v[0]) {
				$perm |= $v[0];
			}
		}

		/* Auto approve members. */
		$perm |= 65536;

		if (empty($_POST['edit'])) {
			$gr_member = $_POST['gr_member'];

			if (!($usr_id = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}users WHERE alias='. _esc(char_fix(htmlspecialchars($gr_member)))))) {
				$login_error = '{TEMPLATE: groupmgr_no_user}';
			} else if (q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='. $group_id .' AND user_id='. $usr_id)) {
				$login_error = '{TEMPLATE: groupmgr_already_exists}';
			} else {
				q('INSERT INTO {SQL_TABLE_PREFIX}group_members (group_members_opt, user_id, group_id) VALUES ('. $perm .', '. $usr_id .', '. $group_id .')');
				grp_rebuild_cache(array($usr_id));
				logaction(_uid, 'ADDGRP', $group_id, $gr_member);

				if (defined('plugins')) {
					plugin_call_hook('GROUP_JOIN', array($usr_id, $group_id));
				}
			}
		} else if (($usr_id = q_singleval('SELECT user_id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='. $group_id .' AND id='. (int)$_POST['edit'])) !== null) {
			if (q_singleval('SELECT user_id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='. $group_id .' AND user_id='. $usr_id .' AND group_members_opt>=131072 AND '. q_bitand('group_members_opt', 131072) .' > 0')) {
				$perm |= 131072;
			}
			q('UPDATE {SQL_TABLE_PREFIX}group_members SET group_members_opt='. $perm .' WHERE id='. (int)$_POST['edit']);
			grp_rebuild_cache(array($usr_id));

			if ($usr_id == 0) $usr_id = 1; // Correct log entry for Anonymous.
			$gr_member = q_singleval('SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id='. $usr_id);
			logaction(_uid, 'EDITGRP', $group_id, $gr_member);
		}
		if (!$login_error) {
			unset($_POST);
			$gr_member = '';
		}
	}

	if (isset($_GET['del']) && ($del = (int)$_GET['del']) && $group_id && sq_check(0, $usr->sq)) {
		$is_gl = q_singleval('SELECT user_id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='. $group_id .' AND user_id='. $del .' AND group_members_opt>=131072 AND '. q_bitand('group_members_opt', 131072) .' > 0');
		grp_delete_member($group_id, $del);
		
		$gr_member = q_singleval('SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id='. $del);
		logaction(_uid, 'DELGRP', $group_id, $gr_member);

		/* If the user was a group moderator, rebuild moderation cache. */
		if ($is_gl) {
			fud_use('groups_adm.inc', true);
			rebuild_group_ldr_cache($del);
		}
	}

	$edit = 0;
	if (isset($_GET['edit']) && ($edit = (int)$_GET['edit'])) {
		if (!($mbr = db_sab('SELECT gm.*, u.alias FROM {SQL_TABLE_PREFIX}group_members gm LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=gm.user_id WHERE gm.group_id='. $group_id .' AND gm.id='. $edit))) {
			invl_inp_err();
		}
		if ($mbr->user_id == 0) {
			$gr_member = '{TEMPLATE: group_mgr_anon}';
		} else if ($mbr->user_id == '2147483647') {
			$gr_member = '{TEMPLATE: group_mgr_reged}';
		} else {
			$gr_member = $mbr->alias;
		}
		$perm = $mbr->group_members_opt;
	} else if ($group_id > 2 && !isset($_POST['btn_submit']) && ($luser_id = q_singleval('SELECT MAX(id) FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='. $group_id))) {
		/* Help trick, we fetch the last user added to the group. */
		if (!($mbr = db_sab('SELECT 1 AS user_id, group_members_opt FROM {SQL_TABLE_PREFIX}group_members WHERE id='. $luser_id))) {
			invl_inp_err();
		}
		$perm = $mbr->group_members_opt;
	} else {
		$mbr = 0;
	}

	/* Anon users cannot rate topics. */
	if ($mbr && !$mbr->user_id) {
		$maxperms = $maxperms &~ 1024;
	}

	/* No members inside the group. */
	if (!$perm && !$mbr) {
		$perm = $maxperms;
	}

	/* Translated permission names. */
	$ts_list = array(
'p_VISIBLE'=>'{TEMPLATE: p_VISIBLE}',
'p_READ'=>'{TEMPLATE: p_READ}',
'p_POST'=>'{TEMPLATE: p_POST}',
'p_REPLY'=>'{TEMPLATE: p_REPLY}',
'p_EDIT'=>'{TEMPLATE: p_EDIT}',
'p_DEL'=>'{TEMPLATE: p_DEL}',
'p_STICKY'=>'{TEMPLATE: p_STICKY}',
'p_POLL'=>'{TEMPLATE: p_POLL}',
'p_FILE'=>'{TEMPLATE: p_FILE}',
'p_VOTE'=>'{TEMPLATE: p_VOTE}',
'p_RATE'=>'{TEMPLATE: p_RATE}',
'p_SPLIT'=>'{TEMPLATE: p_SPLIT}',
'p_LOCK'=>'{TEMPLATE: p_LOCK}',
'p_MOVE'=>'{TEMPLATE: p_MOVE}',
'p_SML'=>'{TEMPLATE: p_SML}',
'p_IMG'=>'{TEMPLATE: p_IMG}',
'p_SEARCH'=>'{TEMPLATE: p_SEARCH}');

	$perm_sel_hdr = $perm_select = $tmp = '';
	$i = 0;
	foreach ($hdr as $k => $v) {
		$selyes = '';
		if ($maxperms & $v[0]) {
			if ($perm & $v[0]) {
				$selyes = ' selected="selected"';
			}
			$perm_select .= '{TEMPLATE: groups_perm_selection}';
		} else {
			/* Only show the permissions the user can modify. */
			continue;
		}
		$tmp .= '{TEMPLATE: groups_header_entry}';

		if (++$i == '{TEMPLATE: groups_perm_per_row}') {
			$perm_sel_hdr .= '{TEMPLATE: groups_header_entry_row}';
			$perm_select = $tmp = '';
			$i = 0;
		}
	}

	if ($tmp) {
		while (++$i < '{TEMPLATE: groups_perm_per_row}' + 1) {
			$tmp .= '{TEMPLATE: groups_hdr_sp}';
			$perm_select .= '{TEMPLATE: groups_col_sp}';
		}
		$perm_sel_hdr .= '{TEMPLATE: groups_header_entry_row}';
	}

	/* Draw list of group members. */
	$group_members_list = '';
	$r = uq('SELECT gm.id AS mmid, gm.*, g.*, u.alias FROM {SQL_TABLE_PREFIX}group_members gm INNER JOIN {SQL_TABLE_PREFIX}groups g ON gm.group_id=g.id LEFT JOIN {SQL_TABLE_PREFIX}users u ON gm.user_id=u.id WHERE gm.group_id='. $group_id .' ORDER BY gm.id');
	while ($obj = db_rowobj($r)) {
		$perm_table = draw_tmpl_perm_table($obj->group_members_opt, $hdr, $ts_list);

		if ($obj->user_id == '0') {
			$member_name = '{TEMPLATE: group_mgr_anon}';
			$group_members_list .= '{TEMPLATE: group_const_entry}';
		} else if ($obj->user_id == '2147483647')  {
			$member_name = '{TEMPLATE: group_mgr_reged}';
			$group_members_list .= '{TEMPLATE: group_const_entry}';
		} else {
			$member_name = $obj->alias;
			if ($obj->user_id == _uid && !$is_a) {
				$group_members_list .= '{TEMPLATE: group_const_entry}';
			} else {
				$group_members_list .= '{TEMPLATE: group_member_entry}';
			}
		}
	}
	unset($r);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: GROUP_MANAGER}
