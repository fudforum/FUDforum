<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: groupmgr.php.t,v 1.12 2002/09/29 19:50:58 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	{PRE_HTML_PHP}
	
	if ( empty($usr) || (!empty($group_id) && !bq("SELECT id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id=".$group_id." AND user_id=".$usr->id." AND group_leader='Y'") && $usr->is_mod!='A') ) {
		std_error('access');
		exit;	
	}
	
	if( $usr->is_mod != 'A' ) { 
		$r = q("SELECT group_id, name FROM {SQL_TABLE_PREFIX}group_members INNER JOIN {SQL_TABLE_PREFIX}groups ON {SQL_TABLE_PREFIX}group_members.group_id={SQL_TABLE_PREFIX}groups.id WHERE user_id=".$usr->id." AND group_leader='Y' ORDER BY ltrim(name)");
		if( !($group_count = db_count($r)) ) {
			qf($r);
			std_error('access');
			exit;	
		}
	}
	else {
		$r = q("SELECT id AS group_id, name FROM {SQL_TABLE_PREFIX}groups WHERE id>2 ORDER BY ltrim(name)");
		$group_count = db_count($r);
	}	
	
	if( empty($group_id) && $group_count ) {
		list($group_id,) = db_rowarr($r);
		db_seek($r,0);
	}
	
	if( !empty($group_count) && $group_count>1 ) {
		$vl = $kl = '';
		while( list($gid,$gname) = db_rowarr($r) ) {
			$vl .= $gid."\n";
			$kl .= htmlspecialchars($gname)."\n";
		}
		$vl = substr($vl, 0, -1);
		$kl = substr($kl, 0, -1);
		
		$group_selection = tmpl_draw_select_opt($vl, $kl, $group_id, '', '');
		$group_selection = '{TEMPLATE: group_selection}';
	}
	qf($r);
	
	{POST_HTML_PHP}

function draw_tmpl_perm_table($perm_arr)
{
	$str = '';
	foreach($perm_arr as $k => $v) { 
		if ( substr($k, 0, 3) != 'up_' ) continue;
		if ( $v == 'Y' ) 
			$str .= '{TEMPLATE: perm_yes}';
		else
			$str .= '{TEMPLATE: perm_no}';
	}
	return $str;
}

	if( $group_id ) {
		$grp = new fud_group;
		$grp->get($group_id);
		$indicator = '{TEMPLATE: indicator}';
		$pret = $grp->resolve_perms();
		$maxperms = $pret['perms'];	
	}
	
	if ( $btn_cancel ) {
		header("Location: {ROOT}?t=groupmgr&"._rsidl."&group_id=$grp->id&rnd=".get_random_value());
		exit();
	}
	
	if ( $usr->is_mod!='A' && !$grp->is_leader($usr->id) ) {
		std_error('access');
		exit();
	}
	
	if ( $btn_submit ) {
		$mbr = new fud_user_reg;
		$perms_arr = mk_perms_arr('', $perms, 'u');
		if ( empty($edit) ) {
			$mbr->id = get_id_by_alias($gr_member);
			if( $mbr->id ) $mbr->get_user_by_id($mbr->id);
			
			$gr_member = stripslashes($gr_member);
			if( empty($mbr->id) ) 
				$login_error = '{TEMPLATE: groupmgr_no_user}';
			else if( bq("SELECT id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id=".$grp->id." AND user_id=".$mbr->id) )
				$login_error = '{TEMPLATE: groupmgr_already_exists}';
			else
				$grp->add_member($mbr->id, $perms_arr);
		}
		else {
			$usr_id = q_singleval("SELECT user_id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id=$group_id AND id=$edit");
			$grp->update_member($usr_id, $perms_arr);
		}

		if( empty($login_error) ) {
			$grp->rebuild_cache($mbr->id);
			header("Location: {ROOT}?t=groupmgr&"._rsidl."&group_id=$grp->id&rnd=".get_random_value());
			exit();
		}	
	}
	
	if ( !empty($del) ) {
		$grp->delete_member($del);
		header("Location: {ROOT}?t=groupmgr&"._rsidl."&group_id=$grp->id&rnd=".get_random_value());
		exit();
	}
	
	if ( !empty($edit) ) {
		$mbr = $grp->get_member_by_ent_id($edit);
		
		if ( $mbr->user_id == 0 ) 
			$gr_member = '{TEMPLATE: group_mgr_anon}';
		else if ( $mbr->user_id == '2147483647' ) 
			$gr_member = '{TEMPLATE: group_mgr_reged}';
		else {
			$gr_member = $mbr->alias;
			reverse_FMT($gr_member);
		}	
		
		$perms = perm_obj_to_arr($mbr, 'up_');
		foreach($perms as $k => $v) $perms_new[substr($k, 1)] = $v;
		$perms = $perms_new;
	}
	else if( $grp->id>2 && ($luser_id = q_singleval("SELECT MAX(id) FROM {SQL_TABLE_PREFIX}group_members WHERE group_id=".$grp->id)) ) {
		if( empty($btn_submit) ) 
			$mbr = $grp->get_member_by_ent_id($luser_id);
		else
			$mbr = $perms_arr;
				
		$perms = perm_obj_to_arr($mbr, 'up_');
		foreach($perms as $k => $v) $perms_new[substr($k, 1)] = $v;
		$perms = $perms_new;
	}
		
	
	if( $group_id ) {
		if( $mbr->user_id == 0 ) $maxperms['p_VOTE'] = $maxperms['p_RATE'] = 'N';

		$perm_select = draw_permissions('', $perms, $maxperms);
		if ( empty($edit) ) {
			$member_input = '{TEMPLATE: member_add}';
			$submit_button = '{TEMPLATE: submit_button}';
		}
		else {
			$submit_button = '{TEMPLATE: update_buttons}';
			$member_input = '{TEMPLATE: member_edit}';
		}
	
		$r = q("SELECT 
				{SQL_TABLE_PREFIX}group_members.id AS mmid, 
				{SQL_TABLE_PREFIX}group_members.*, 
				{SQL_TABLE_PREFIX}groups.*, 
				{SQL_TABLE_PREFIX}users.alias AS login 
			FROM 
				{SQL_TABLE_PREFIX}group_members 
				LEFT JOIN {SQL_TABLE_PREFIX}users 
					ON {SQL_TABLE_PREFIX}group_members.user_id={SQL_TABLE_PREFIX}users.id  INNER JOIN {SQL_TABLE_PREFIX}groups ON {SQL_TABLE_PREFIX}group_members.group_id={SQL_TABLE_PREFIX}groups.id WHERE group_id=$grp->id AND {SQL_TABLE_PREFIX}group_members.group_leader='N' ORDER BY {SQL_TABLE_PREFIX}group_members.id");
	
		$group_members_list = '';
		while ( $obj = db_rowobj($r) ) {
			$perm_table = draw_tmpl_perm_table($obj);
			$rand = get_random_value();
			$delete_allowed = 0;
			if ( $obj->user_id == 0 )
				$member_name = '{TEMPLATE: group_mgr_anon}';
			else if ( $obj->user_id == '2147483647' ) 
				$member_name = '{TEMPLATE: group_mgr_reged}';
			else { $member_name = $obj->login; $delete_allowed = 1; }
		
			if ( $delete_allowed ) 
				$group_members_list .= '{TEMPLATE: group_member_entry}';
			else
				$group_members_list .= '{TEMPLATE: group_const_entry}';
		}
		qf($r);
		
		$group_control_panel = '{TEMPLATE: group_control_panel}';
	}
	else
		$group_control_panel = '{TEMPLATE: no_group_control_panel}';

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: GROUP_MANAGER}