<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: groups.inc.t,v 1.11 2003/04/24 16:49:06 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$c = get_field_list('{SQL_TABLE_PREFIX}groups');
while ($r = db_rowarr($c)) {
	if (!strncmp($r[0], 'p_', 2)) {
		$GLOBALS['__GROUPS_INC']['permlist'][$field] = $field;
	}
}
qf($c);

function rebuild_group_cache()
{
	$r = q("SELECT id FROM {SQL_TABLE_PREFIX}groups");
	while ( $obj = db_rowobj($r) ) {
		$grp = new fud_group;
		$grp->get($obj->id);
		$grp->rebuild_cache();
	}
	qf($r);
}

function mk_perm_insert_qry($data, $prefix='p_', $in_prefix='') 
{	
	$fields = '';
	$vals = '';
	$plen = strlen($prefix);
	foreach($data as $k => $v) { 
		$s='';
		if ( substr($k, 0, $plen) == $prefix ) {
			$fields 	.= $in_prefix.$k.',';
			if ( is_object($data) )
				$s = $data->{$k};
			else if ( is_array($data) ) 
				$s = $data[$k];
			else exit('fatal error');
			
			if( !strlen($s) ) $s = 'N';
			
			$vals 		.= "'".$s."',";
		}
	}
	$ret['fields'] = substr($fields, 0, -1);
	$ret['vals'] = substr($vals, 0, -1);
	
	return $ret;
}

function mk_perm_update_qry($data, $prefix='p_', $in_prefix='')
{
		$fields = '';
		$plen = strlen($prefix);
		foreach($data as $k => $v) { 
			$s='';
			if ( substr($k, 0, $plen) == $prefix ) {
				
				if ( is_object($data) )
					$s = $data->{$k};
				else if ( is_array($data) ) 
					$s = $data[$k];
				else exit('fatal error');
				
				if( !strlen($s) ) $s = 'N';
				$fields .= $in_prefix.$k."='".$s."',";
			}
		}
		$fields = substr($fields, 0, -1);
		return $fields;
}

function draw_permissions($name, $perms_arr=NULL, $maxperms_arr=NULL)
{
	$arr = $GLOBALS['__GROUPS_INC']['permlist'];
	
	$perm_selection = '';
	foreach($arr as $k => $v) { 
		/* check if this permissions is allowed, depending on maxperms */
		if ( is_array($maxperms_arr) ) {
			if ( $maxperms_arr[$v] == 'N' ) {
				if ( defined('admin_form') ) 
					$perm_selection .= '<td align="center"><font size=-1>n/a</font></td>';
				else
					$perm_selection .= '{TEMPLATE: groups_na_perms}';

				continue;
			}
		}
		
		$selyes = $selno = '';
		if ( $perms_arr[$v] == 'Y' )	 		$selyes = ' selected';
		else if ( $perms_arr[$v] == 'N' ) 	$selno = ' selected';
		else if ( is_array($maxperms_arr)&&!($perms_arr[$v]=='Y') ) $selno = ' selected';
		$sel_name = $name.$v;
		
		if ( !is_array($maxperms_arr) ) {
			if ( defined('admin_form') ) 
				$perm_selection_inherit_row = '<option value="I">Inherit</option>';
			else
				$perm_selection_inherit_row = '{TEMPLATE: groups_perm_selection_inherit_row}';
		}
		else
			$perm_selection_inherit_row = '';
		
		if ( defined('admin_form') ) {
			$perm_selection .= '<td align="center"><font size=-1>
			<select name="'.$sel_name.'" style="font-size: x-small">
			'.$perm_selection_inherit_row.'
			<option value="Y"'.$selyes.'>Yes</option>
			<option value="N"'.$selno.'>No</option>
			</select>
			</font></td>';
		}
		else $perm_selection .= '{TEMPLATE: groups_perm_selection}';
	}

	return $perm_selection;
}

function draw_perm_table($perms_arr)
{
	$arr = $GLOBALS['__GROUPS_INC']['permlist'];
	$str = '';
	
	foreach($arr as $k => $v) {
		$inherit = ($perms_arr['origperms'][$k]!='I') ? '' : '<font color="#00AA00">Inherit</font>';
			
		$perm_str = '';
		if ( $perms_arr['perms'][$k] == 'Y' )
			$perm_str .= '<font color="#FF0000"> <b>(Yes)</b></font>';
		else if ( $perms_arr['perms'][$k] == 'N' )
			$perm_str .= '<font color="#0000FF"> <b>(No)</b></font>';
		else
			$perm_str .= '<font color="#00AA00">Inherit</font>';
		
		if ( $perms_arr['origperms'][$k] != $perms_arr['perms'][$k] && $perms_arr['perm_groups'][$k] )
			$val = '<b>from:</b><br>('.$perms_arr['perm_groups'][$k].')';
		else
			$val = '';
		
		$str .= '<td><font size=-1>'.$inherit.$perm_str.'<br>'.$val.'</font></td>';
	}
	
	return $str;
}

function mk_perms_arr($prefix, $maxperms_arr, $arr_prefix=NULL)
{
	$arr = $GLOBALS['__GROUPS_INC']['permlist'];

	foreach($arr as $k => $v) {
		if ( $maxperms_arr[$k] == 'N' || $GLOBALS['HTTP_POST_VARS'][$prefix.$k] != 'Y' )
			$parr[$arr_prefix.$k] = 'N';
		else
			$parr[$arr_prefix.$k] = $GLOBALS['HTTP_POST_VARS'][$prefix.$k];
	}
	
	return $parr;
}

function perm_obj_to_arr($obj, $prefix='p_', $arr_prefix='')
{
	$plen = strlen($prefix);
	foreach($obj as $k => $v) { 
		if ( substr($k, 0, $plen) == $prefix ) $arr[$arr_prefix.$k] = $v;
	}
	
	return $arr;
}

function grp_resolve_perms(&$grp)
{
	if (!$grp->inherit_id) {
		return;	
	}
	$permlist = $GLOBALS['__GROUPS_INC']['permlist'];
	foreach ($permlist as $v){
		$permlist[$v] = 'p_'.$v;
		$ret['origperms'][$v] = $ret['perms'][$v] = $grp->{$v};
	}
	$inherit_id = $grp->inherit_id;
	$inh_list[$grp->id] = $grp->id;
	$todo = 0;
	do {
		$obj = db_sab('SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id='.$inherit_id);
		foreach ($permlist as $v) {
			if ($perms['perms'][$v] == 'I') {
				if ($obj->{$v} != 'I') {
					$perms['perms'][$v] = $obj->{$v};
				} else {
					$todo = 1;
				}
			}
		}
		if (isset($inh_list[$obj->inherit_id])) {
			break; /* circular inheritence */
		}
		$inherit_id = $obj->inherit_id;
		$inh_list[$obj->id] = $obj->id;
	} while ($inherit_id && $todo);

	return $ret;
}

function grp_delete_member($id, $user_id)
{
	if (!$user_id || $user_id == '2147483647') {
		return;
	}
	q('DELETE FROM {SQL_TABLE_PREFIX}group_members WHERE group_id='.$id.' AND user_id='.$user_id);
	q('DELETE FROM {SQL_TABLE_PREFIX}group_cache WHERE group_id='.$id.' AND user_id='.$user_id);
}

function grp_update_member($id, $usr_id, &$perms_arr)
{
	$str = mk_perm_update_qry($perms_arr, 'up_');
	q('UPDATE {SQL_TABLE_PREFIX}group_members SET '.$str.' WHERE group_id='.$id.' AND user_id='.$usr_id);
}

function grp_rebuild_cache($id, $user_id=0)
{
	$t = get_field_list('{SQL_TABLE_PREFIX}group_members');
	while ($e = db_rowarr($t)) {
		if (strncmp($e[0], 'up_', 3)) {
			continue;
		}
		$perms[] = $e[0];
	}
	qf($t);

	if (!db_locked()) {
		$ll = 1;
		db_lock('{SQL_TABLE_PREFIX}group_resources gr WRITE, {SQL_TABLE_PREFIX}group_members gm WRITE, {SQL_TABLE_PREFIX}group_cache WRITE');
	}
	if (!$user_id) {
		q('DELETE FROM {SQL_TABLE_PREFIX}group_cache WHERE group_id='.$id);
		$r = uq('SELECT gr.resource_id, gm.* FROM {SQL_TABLE_PREFIX}group_members gm INNER JOIN {SQL_TABLE_PREFIX}group_resources gr ON gr.group_id=gm.group_id WHERE gm.group_id='.$id.' AND gm.approved=\'Y\'');
	} else {
		q('DELETE FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id='.$user_id.' AND group_id='.$id);
		$r = uq('SELECT gr.resource_id, gm.* FROM {SQL_TABLE_PREFIX}group_members gm INNER JOIN {SQL_TABLE_PREFIX}group_resources gr ON gr.group_id=gm.group_id WHERE gm.group_id='.$id.' AND gm.user_id='.$user_id.' AND gm.approved=\'Y\'');
	}
	while ($obj = db_rowobj($r)) {
		if (!isset($ent[$obj->resource_id][$obj->user_id])) {
			$ent[$obj->resource_id][$obj->user_id] = array('ldr' => 0);
		}
		$pr =& $ent[$obj->resource_id][$obj->user_id];
		if ($obj->group_leader == 'Y') {
			$pr['ldr'] = 1;
		}

		foreach($perms as $v) {
			if (!isset($pr[$v])) {
				$pr[$v] = $obj->{$v};
			} else if ($pr['ldr'] && $obj->{$v} == 'Y') {
				$pr[$v] = 'Y';
			} else if ($obj->{$v} == 'N') {
				$pr[$v] = 'N';
			}
		} 
	}
	qf($r);

	$fields = str_replace('up_', 'p_', implode(',', $perms));

	/* do the inserts into cache */
	if (isset($ent)) {
		foreach ($ent as $k => $v) {
			foreach ($v as $k2 => $v2) {
				unset($v2['ldr']);
				$vals = '\'' . implode('\', \'', $v2) . '\'';
				q('INSERT INTO {SQL_TABLE_PREFIX}group_cache ('.$fields.', user_id, resource_id) VALUES('.$vals.', '.$k2.', '.$k.')');
			}
		}
	}

	if (isset($ll)) {
		db_unlock();
	}
}
?>