<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: groupmgr.php.t,v 1.18 2003/04/21 22:24:43 hackie Exp $
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
		std_error('access');
	}
	$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : (isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0);

	if ($group_id && $usr->is_mod != 'A' && !q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='.$group_id.' AND user_id='._uid.' AND group_leader=\'Y\'')) {
		std_error('access');	
	}

	if ($usr->is_mod != 'A') { 
		$r = uq('SELECT g.group_id, g.name FROM {SQL_TABLE_PREFIX}group_members gm INNER JOIN {SQL_TABLE_PREFIX}groups g ON gm.group_id=g.id WHERE gm.user_id='._uid.' AND gm.group_leader=\'Y\' ORDER BY ltrim(g.name)');
	} else {
		$r = uq('SELECT id AS group_id, name FROM {SQL_TABLE_PREFIX}groups WHERE id>2 ORDER BY ltrim(name)');
	}	
	/* make a group selection form */
	$n = 0;
	$vl = $kl = '';
	while ($e = db_rowarr($r)) {
		$vl .= $e[0]."\n";
	        $kl .= htmlspecialchars($e[1])."\n";
		$n++;
	}
	qf($r);

	if (!$n) {
		std_error('access');
	} else if ($n == 1) {
		$group_id = rtrim($vl);
		$group_selection = '';
	} else {
		if (!$group_id) {
			$group_id = (int)$vl;
		}
		$group_selection = tmpl_draw_select_opt(rtrim($vl), rtrim($kl), $group_id, '', '');
		$group_selection = '{TEMPLATE: group_selection}';
	}

/*{POST_HTML_PHP}*/

function draw_tmpl_perm_table($perm_arr)
{
	$str = '';
	foreach($perm_arr as $k => $v) { 
		if (strncmp($k, 'up_', 3)) {
			continue;
		}
		$str .= $v == 'Y' ? '{TEMPLATE: perm_yes}' : '{TEMPLATE: perm_no}';
	}
	return $str;
}

/* type == 0 -> update ;; type == 1 -> insert */
function make_perm_str(&$max_perms, &$cur_perms, $type)
{
	$s1 = $s2 = '';
	foreach ($max_perms as $k => $v) {
		if ($type) {
			$s1 .= 'u' . $k . ','; 
			$s2 .= "'".(($v == 'Y' && $cur_perms[$k] != 'N') ? 'Y' : 'N')."',";
		} else {
			$s1 .= 'u' . $k . "='".(($v == 'Y' && $cur_perms[$k] != 'N') ? 'Y' : 'N')."',";
		}
	}
	if ($type) {
		return array($s1, $s2);
	} else {
		return substr($s1, 0, -1);
	}
}

function make_perms_ob(&$obj)
{
	foreach ($obj as $k => $v) {
		if (strncmp($k, 'p_', 2)) {
			continue;
		}
		$perms[$k] = $v;
	}
	return $perms;
}

function make_perms_uob(&$obj)
{
	foreach ($obj as $k => $v) {
		if (strncmp($k, 'up_', 3)) {
			continue;
		}
		$perms[substr($k, 1)] = $v;
	}
	return $perms;
}

	if (isset($_POST['btn_cancel'])) {
		unset($_POST);
	}
	if (!($grp = db_sab('SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id='.$group_id))) {
		invl_inp_err();
	}
	$maxperms = make_perms_ob($grp);
	$indicator = '{TEMPLATE: indicator}';

	$login_error = '';
	$gr_member = isset($_POST['gr_member']) ? $_POST['gr_member'] : '';
	$find_user = $MEMBER_SEARCH_ENABLED == 'Y' ? '{TEMPLATE: grp_find_user}' : '';

	if (isset($_POST['btn_submit'])) {
		if (empty($_POST['edit'])) {
			if (!($usr_id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}users WHERE alias='".addslashes(htmlspecialchars($gr_member))."'"))) {
				$login_error = '{TEMPLATE: groupmgr_no_user}';
			} else if (q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='.$group_id.' AND user_id='.$usr_id)) {
				$login_error = '{TEMPLATE: groupmgr_already_exists}';
			} else {
				$p = make_perm_str($maxperms, $_POST, 1);
				q('INSERT INTO {SQL_TABLE_PREFIX}group_members ('.$p[0].' user_id, group_id) VALUES ('.$p[1].' '.$usr_id.', '.$group_id.')');
				grp_rebuild_cache($group_id, $usr_id);
			}
		} else if (($usr_id = q_singleval('SELECT user_id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='.$group_id.' AND id='.(int)$_POST['edit']))) {
			q('UPDATE {SQL_TABLE_PREFIX}group_members SET '.make_perm_str($maxperms, $_POST, 0).' WHERE id='.(int)$_POST['edit']);
			grp_rebuild_cache($group_id, $usr_id);
		}
		unset($_POST['btn_submit']);
	}

	if (isset($_GET['del']) && ($del = (int)$_GET['del']) && $group_id) {
		grp_delete_member($group_id, $del);
	}

	$edit = '0';
	if (isset($_GET['edit']) && ($edit = (int)$_GET['edit'])) {
		if (!($mbr = db_sab('SELECT gm.*, u.alias FROM {SQL_TABLE_PREFIX}group_members gm LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=gm.user_id WHERE gm.group_id='.$group_id.' AND gm.id='.$edit))) {
			invl_inp_err();
		}
		
		if ($mbr->user_id == 0) {
			$gr_member = '{TEMPLATE: group_mgr_anon}';
		} else if ($mbr->user_id == '2147483647') {
			$gr_member = '{TEMPLATE: group_mgr_reged}';
		} else {
			$gr_member = $mbr->alias;
		}	
		$perms = make_perms_uob($mbr);
	} else if ($group_id > 2 && !isset($_POST['btn_submit']) && ($luser_id = q_singleval('SELECT MAX(id) FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='.$group_id))) {
		/* help trick, we fetch the last user added to the group */
		if (!($mbr = db_sab('SELECT * FROM {SQL_TABLE_PREFIX}group_members WHERE id='.$luser_id))) {
			invl_inp_err();
		}
		$perms = make_perms_uob($mbr);
		unset($mbr);
	}

	/* anon users cannot vote or rate */
	if (isset($mbr) && !$mbr->user_id) {
		$maxperms['p_VOTE'] = $maxperms['p_RATE'] = 'N';
	}

	$perm_select = draw_permissions('', $perms, $maxperms);

	if (!$edit) {
		$member_input = '{TEMPLATE: member_add}';
		$submit_button = '{TEMPLATE: submit_button}';
	} else {
		$submit_button = '{TEMPLATE: update_buttons}';
		$member_input = '{TEMPLATE: member_edit}';
	}

	/* draw list of group members */
	$group_members_list = '';
	$r = uq('SELECT gm.id AS mmid, gm.*, g.*, u.alias
			FROM 
				{SQL_TABLE_PREFIX}group_members gm
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON gm.user_id=u.id 
				INNER JOIN {SQL_TABLE_PREFIX}groups g ON gm.group_id=g.id 
			WHERE 
				gm.group_id='.$group_id.' AND gm.group_leader=\'N\'
			ORDER BY gm.id');

	while ($obj = db_rowobj($r)) {
		$perm_table = draw_tmpl_perm_table($obj);

		if ($obj->user_id == 0) {
			$member_name = '{TEMPLATE: group_mgr_anon}';
			$group_members_list .= '{TEMPLATE: group_const_entry}';
		} else if ($obj->user_id == '2147483647')  {
			$member_name = '{TEMPLATE: group_mgr_reged}';
			$group_members_list .= '{TEMPLATE: group_const_entry}';
		} else {
			$member_name = $obj->alias;
			$group_members_list .= '{TEMPLATE: group_member_entry}';
		}
	}
	qf($r);
	$group_control_panel = '{TEMPLATE: group_control_panel}';

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: GROUP_MANAGER}