<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: groups.inc.t,v 1.9 2003/04/21 22:24:43 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
 if ( defined('_groups_inc_') ) return; else define('_groups_inc_', 1);

$r = get_field_list('{SQL_TABLE_PREFIX}groups');
while ( list($field) = db_rowarr($r) ) {
	if ( substr($field, 0, 2) == 'p_' ) {
		$GLOBALS['__GROUPS_INC']['permlist'][$field] = $field;
	}
}
QF($r);

class fud_group
{
	var $id=NULL;
	var $name=NULL;
	var $permissions=NULL;
	var $inherit_id=NULL;
	var $joinmode=NULL;
	var $res_id=NULL;
	var $res=NULL;
	
	function add($res=NULL, $res_id=NULL, $name=NULL, $ramasks=FALSE)
	{
		if ( !db_locked() ) { db_lock('{SQL_TABLE_PREFIX}groups+, {SQL_TABLE_PREFIX}group_resources+, {SQL_TABLE_PREFIX}group_members+, {SQL_TABLE_PREFIX}group_cache+'); $ll=1; }
		
		$ret = mk_perm_insert_qry($this);
		if ( $res ) {
			$resstr = "res, res_id,";
			$resval = "'$res', $res_id,";
		}
		
		if ( !empty($name) ) $this->name = $name;
		
		$r = q("INSERT INTO {SQL_TABLE_PREFIX}groups(
			name,
			inherit_id,
			joinmode,
			$resstr
			".$ret['fields']."
		)
		VALUES(
			'".$this->name."',
			".intzero($this->inherit_id).",
			'".($this->joinmode?$this->joinmode:'NONE')."',
			$resval
			".$ret['vals']."
		)");
		$this->id = db_lastid("{SQL_TABLE_PREFIX}groups", $r);
		
		if ( $res || $ramasks ) {
			$obj = db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id=1"));
			$perms = $this->resolve_perms();
			foreach($perms['perms'] as $k => $v ) {
				if ( substr($k, 0, 2) != 'p_' ) continue;
				if ( $v!='Y' ) $obj->{$k} = 'N';
			}
			$ret = mk_perm_insert_qry($obj, 'p_', 'u');
			$r = q("INSERT INTO {SQL_TABLE_PREFIX}group_members(user_id, group_id, approved, group_leader, ".$ret['fields'].") 
				VALUES(0, $this->id, 'Y', 'N', ".$ret['vals'].")");
			$anon_id = db_lastid("{SQL_TABLE_PREFIX}group_members", $r);
		
			$obj = db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id=2"));
			foreach($perms['perms'] as $k => $v ) {
				if ( substr($k, 0, 2) != 'p_' ) continue;
				if ( $v!='Y' ) $obj->{$k} = 'N';
			}
			$ret = mk_perm_insert_qry($obj, 'p_', 'u');
			$r = q("INSERT INTO {SQL_TABLE_PREFIX}group_members(user_id, group_id, approved, group_leader, ".$ret['fields'].") 
				VALUES(2147483647, $this->id, 'Y', 'N', ".$ret['vals'].")");
			$reg_id = db_lastid("{SQL_TABLE_PREFIX}group_members", $r);
			$rval['anon_id'] = $anon_id;
			$rval['reg_id'] = $reg_id;
		}

		if ( $ll ) db_unlock();
		$rval['id'] = $this->id;
		
		return $rval;
	}
	
	function sync()
	{
		$fields = mk_perm_update_qry($this);
		$obj = db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id=".$this->id));
		$pf = '';
		foreach($obj as $k => $v) {
			if ( substr($k, 0, 2) != 'p_' ) continue;
			if ( $v == 'Y' && $this->{$k} == 'N' ) $pf .= 'u'.$k."='N', ";
		}
		
		if ( strlen($pf) ) {
			$pf = substr($pf, 0, -2);
			q("UPDATE {SQL_TABLE_PREFIX}group_members SET $pf WHERE group_id=$this->id");
		}
		q("UPDATE {SQL_TABLE_PREFIX}groups SET name='".$this->name."', $fields, inherit_id=".intzero($this->inherit_id).", joinmode='".($this->joinmode?$this->joinmode:'NONE')."' WHERE id=".$this->id);
	}
	
	function get($id)
	{
		$obj = db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id=".$id));
		foreach($obj as $k => $v) $this->{$k} = $v;

		return $this->id;
	}
	
	function delete()
	{
		if ( !db_locked() ) { db_lock('{SQL_TABLE_PREFIX}groups+, {SQL_TABLE_PREFIX}group_resources+, {SQL_TABLE_PREFIX}group_members+, {SQL_TABLE_PREFIX}group_cache+'); $ll=1; }
		q("DELETE FROM {SQL_TABLE_PREFIX}group_resources WHERE group_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}group_members WHERE group_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}group_cache WHERE group_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}groups WHERE id=".$this->id);
		if ( $ll ) db_unlock();
	}
	
	function get_resources_by_rsid()
	{
		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}group_resources WHERE group_id=".$this->id);
		while ( $obj = db_rowobj($r) ) {
			$rslist[$obj->resource_type][$obj->resource_id] = $obj;
		}
		qf($r);
		
		if ( isset($rslist) ) reset($rslist);
		return $rslist;
	}

	function fetch_perms($prefix)
	{	
		$arr = $GLOBALS['__GROUPS_INC']['permlist'];
		foreach($arr as $k => $v) $this->{$k} = $GLOBALS['HTTP_POST_VARS'][$prefix.$k];
	}
	
	function get_perms()
	{
		foreach($this as $k => $v) {
			if ( substr($k, 0, 2) == 'p_' ) $arr[$k] = $v;
		}
		
		return $arr;
	}

	function add_resource_list($rslist)
	{
		if ( !db_locked() ) { db_lock('{SQL_TABLE_PREFIX}groups+, {SQL_TABLE_PREFIX}group_resources+, {SQL_TABLE_PREFIX}group_members+'); $ll=1; }
		
		$cur_rslist = $this->get_resources_by_rsid();
		
		/* add resources not present */
		foreach($rslist as $type => $idlist) { 
			foreach($idlist as $id) {
				if ( !isset($cur_rslist[$type][$id]) ) $this->add_resource($type, $id);
			}
		}
		
		/* delete 'em resources not present in the rslist */
		if( is_array($cur_rslist) ) {
			foreach($cur_rslist as $type => $idlist) {
				foreach($idlist as $id => $tmp) {
					if ( !isset($rslist[$type][$id]) ) $this->delete_resource($type, $id);
				}
			}
		}	
		
		if ( $ll ) db_unlock();
	}
	
	function add_resource($type, $id)
	{
		if ( !db_locked() ) {
			$ll = 1;
			db_lock("{SQL_TABLE_PREFIX}group_resources+");
		}
		$r = q("INSERT INTO {SQL_TABLE_PREFIX}group_resources ( group_id, resource_type, resource_id ) VALUES(".$this->id.", '$type', $id)");
		$res_id = db_lastid("{SQL_TABLE_PREFIX}group_resources", $r);
		if ( $ll ) db_unlock();
		return $res_id;
	}
	
	function delete_resource($type, $id, $db_id=NULL)
	{
		if ( !$db_id ) 
			q("DELETE FROM {SQL_TABLE_PREFIX}group_resources WHERE group_id=".$this->id." AND resource_type='$type' AND resource_id=$id");
		else 
			q("DELETE FROM {SQL_TABLE_PREFIX}group_resources WHERE id=$db_id");
		
		return true;
	}
	
	function is_resource($type, $id)
	{
		return q_singleval("SELECT 1 FROM {SQL_TABLE_PREFIX}group_resources WHERE group_id=".$this->id." AND resource_type='$type' AND resource_id=$id");
	}
	
	function add_leader($user_id)
	{
		if ( !$user_id || $user_id == '2147483647' ) return;
		
		if( !($id=q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}group_members WHERE group_id=".$this->id." AND user_id=".$user_id)) ) 
			q("INSERT INTO {SQL_TABLE_PREFIX}group_members(group_id, user_id, group_leader, approved) VALUES(".$this->id.", ".$user_id.", 'Y', 'Y')");
		else {
			q("UPDATE {SQL_TABLE_PREFIX}group_members SET group_leader='Y', approved='Y' WHERE id=".$id);		
		}
	}
	
	
	
	function update_member($usr_id, $perms_arr)
	{
		$str = mk_perm_update_qry($perms_arr, 'up_');
		q("UPDATE {SQL_TABLE_PREFIX}group_members SET $str WHERE group_id=".$this->id." AND user_id=".$usr_id);
	}
	
	function add_member($usr_id, $perms_arr) {
		$ret = mk_perm_insert_qry($perms_arr, 'up_');
		q("INSERT INTO 
			{SQL_TABLE_PREFIX}group_members(
				group_id, 
				user_id, 
				group_leader, 
				approved, 
				".$ret['fields']."
			) 
			VALUES(
				".$this->id.", 
				".$usr_id.", 
				'N', 
				'Y', 
				".$ret['vals'].")"
			);
	}
	
	function get_leader_list()
	{
		$r = q("SELECT 
				{SQL_TABLE_PREFIX}group_members.*, 
				{SQL_TABLE_PREFIX}users.alias, 
				{SQL_TABLE_PREFIX}groups.name
			FROM 
				{SQL_TABLE_PREFIX}group_members 
				LEFT JOIN {SQL_TABLE_PREFIX}users 
					ON {SQL_TABLE_PREFIX}group_members.user_id={SQL_TABLE_PREFIX}users.id 
				LEFT JOIN {SQL_TABLE_PREFIX}groups
					ON {SQL_TABLE_PREFIX}group_members.group_id={SQL_TABLE_PREFIX}groups.id 
				WHERE 
					{SQL_TABLE_PREFIX}group_members.group_id=".$this->id." 
					AND {SQL_TABLE_PREFIX}group_members.group_leader='Y'"
		);
		while ( $obj = db_rowobj($r) ) $llist[$obj->id] = $obj;
		qf($r);
		
		if ( isset($llist) ) reset($llist);
		return $llist;
	}
	
	
	
	function check_member_conflicts($usr_id, $clr_conflicts=NULL)
	{
		$rslist = $this->get_resources_by_rsid();
		$rs_str = '';
		foreach($rslist as $k => $v) $rs_str .= "'".$k."', ";
		
		if ( empty($rs_str) ) return;

		$rs_str = substr($rs_str, 0, -2);
		$r=q("SELECT {SQL_TABLE_PREFIX}group_members.group_id, {SQL_TABLE_PREFIX}group_members.user_id, {SQL_TABLE_PREFIX}groups.name, {SQL_TABLE_PREFIX}group_resources.resource_id, {SQL_TABLE_PREFIX}users.alias AS login FROM 
				{SQL_TABLE_PREFIX}group_members
				INNER JOIN {SQL_TABLE_PREFIX}group_resources 
					ON {SQL_TABLE_PREFIX}group_members.group_id={SQL_TABLE_PREFIX}group_resources.group_id 
				INNER JOIN {SQL_TABLE_PREFIX}groups
					ON {SQL_TABLE_PREFIX}group_resources.group_id={SQL_TABLE_PREFIX}groups.id
				INNER JOIN {SQL_TABLE_PREFIX}users
					ON {SQL_TABLE_PREFIX}group_members.user_id={SQL_TABLE_PREFIX}users.id
				WHERE 
					{SQL_TABLE_PREFIX}group_members.user_id=".$usr_id."
					AND {SQL_TABLE_PREFIX}group_resources.resource_id IN (".$rs_str.")
		");
		
		while ( $obj = db_rowobj($r) ) {
			if ( $clr_conflicts ) {
				q("DELETE FROM {SQL_TABLE_PREFIX}group_members WHERE user_id=".$obj->user_id." AND group_id=".$obj->group_id);
			}
			$conflist[$obj->resource_id] = $obj->name;
		}
		qf($r);
		
		if ( isset($conflist) ) reset($conflist);
		
		return $conflist;
	}
	
	function check_circular_inh($inh_id)
	{
		$inherit_id = $inh_id;
		$inh_list = array();
		while ( $inherit_id ) {
			if ( isset($inh_list[$inherit_id]) ) return FALSE;
			$obj = db_singleobj(q("SELECT id, inherit_id FROM {SQL_TABLE_PREFIX}groups WHERE id=".$inherit_id));
			$inh_list[$obj->id] = 1;
			$inherit_id=$obj->inherit_id;
		}
		
		return TRUE;
	}
	
	function resolve_perms()
	{
		$permlist = $GLOBALS['__GROUPS_INC']['permlist'];
		$perms = 0;
		$parr = NULL;
		/* this can be done faster, so, if you feel like contributing faster code, plz do so */
		foreach( $permlist as $k => $v ) {
			$inherit_id = $this->id;
			$inh_list = array();
			$pval = '';
			$pkey = $k;
			while ( $inherit_id ) {
				if ( isset($inh_list[$inherit_id]) ) return;
				$obj = db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id=".$inherit_id));
				if ( $obj->{$k} != 'I' ) {
					$ret['perm_groups'][$k] = $obj->name;
					
					$pval = $obj->{$k};
					break;
				}
				$inh_list[$inherit_id] = 1;
				$inherit_id = $obj->inherit_id;
			}
			
			if ( !empty($pval) ) 
				$parr[$pkey] = $pval;
			else
				$parr[$pkey] = 'N';
		}
		
		$obj = db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}groups WHERE id=".$this->id));
		foreach($obj as $k => $v) { 
			if ( substr($k, 0, 2) == 'p_' ) $ret['origperms'][$k] = $v;
		}
		$ret['perms'] = $parr;
		return $ret;
	}

	function rebuild_cache($user_id=NULL)
	{
		if ( !db_locked() ) { db_lock('{SQL_TABLE_PREFIX}groups+, {SQL_TABLE_PREFIX}group_resources+, {SQL_TABLE_PREFIX}group_members+, {SQL_TABLE_PREFIX}group_cache+'); $ll=1; }
		if ( empty($user_id) ) {
			q("DELETE FROM {SQL_TABLE_PREFIX}group_cache WHERE group_id=".$this->id);
			$r=q("SELECT {SQL_TABLE_PREFIX}group_resources.resource_type, {SQL_TABLE_PREFIX}group_resources.resource_id, {SQL_TABLE_PREFIX}groups.*, {SQL_TABLE_PREFIX}group_members.*, {SQL_TABLE_PREFIX}group_members.id AS mmid FROM {SQL_TABLE_PREFIX}group_members INNER JOIN {SQL_TABLE_PREFIX}groups ON {SQL_TABLE_PREFIX}group_members.group_id={SQL_TABLE_PREFIX}groups.id INNER JOIN {SQL_TABLE_PREFIX}group_resources ON {SQL_TABLE_PREFIX}group_resources.group_id={SQL_TABLE_PREFIX}group_members.group_id WHERE {SQL_TABLE_PREFIX}group_members.group_id=".$this->id." AND {SQL_TABLE_PREFIX}group_members.approved='Y'");
		}
		else {
			q("DELETE FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=$user_id AND group_id=$this->id");
			$r=q("SELECT {SQL_TABLE_PREFIX}group_resources.resource_type, {SQL_TABLE_PREFIX}group_resources.resource_id, {SQL_TABLE_PREFIX}groups.*, {SQL_TABLE_PREFIX}group_members.*, {SQL_TABLE_PREFIX}group_members.id AS mmid FROM {SQL_TABLE_PREFIX}group_members INNER JOIN {SQL_TABLE_PREFIX}groups ON {SQL_TABLE_PREFIX}group_members.group_id={SQL_TABLE_PREFIX}groups.id INNER JOIN {SQL_TABLE_PREFIX}group_resources ON {SQL_TABLE_PREFIX}group_resources.group_id={SQL_TABLE_PREFIX}group_members.group_id WHERE {SQL_TABLE_PREFIX}group_members.user_id=".$user_id." AND {SQL_TABLE_PREFIX}group_members.group_id=".$this->id." AND {SQL_TABLE_PREFIX}group_members.approved='Y'");
		}

		$ret = $this->resolve_perms();
		while ( $obj = db_rowobj($r) ) {
			$ins = 1;
			/* Mark for removal */
			if ( $obj->group_leader == 'Y' ) {
				/* adds permissions from multiple resources */
				$c_obj = db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}group_cache 
								WHERE user_id=".$obj->user_id." 
								AND resource_type='$obj->resource_type' 
								AND resource_id=$obj->resource_id"));
				
				foreach($ret['perms'] as $k => $v) { 
					if ( $c_obj->{$k} == 'Y' || $v == 'Y' ) 
						$obj->{$k} = 'Y';
					else
						$obj->{$k} = 'N';
				}

				if ( $c_obj->id ) {
					$str = mk_perm_update_qry($obj, 'p_');
					q("UPDATE {SQL_TABLE_PREFIX}group_cache SET $str WHERE id=".$c_obj->id);
					$ins = 0;
				}
			}
			else {
				foreach($ret['perms'] as $k => $v) {
					if ( $obj->{'u'.$k} != $v ) {
						$obj->{'u'.$k} = 'N';
						$obj->{$k} = 'N';
					}
					else if ( $obj->{'u'.$k} == 'N' )
						$obj->{$k} = 'N';
					else if ( $obj->{'u'.$k} == $v && $v == 'Y' )
						$obj->{$k} = 'Y';
				}
				q("DELETE FROM {SQL_TABLE_PREFIX}group_cache 
					WHERE 	user_id=".$obj->user_id." 
						AND resource_type='$obj->resource_type' 
						AND resource_id=$obj->resource_id");

				$str = mk_perm_update_qry($obj, 'up_');
				q("UPDATE {SQL_TABLE_PREFIX}group_members SET $str WHERE id=".$obj->mmid);
			}
			
			if ( $ins ) {
				$iq = mk_perm_insert_qry($obj, 'p_');
				q("INSERT INTO {SQL_TABLE_PREFIX}group_cache(user_id, resource_type, resource_id, group_id, ".$iq['fields'].") VALUES(".$obj->user_id.", '$obj->resource_type', $obj->resource_id, ".$this->id.", ".$iq['vals'].")");
			}
		}
		if ( $ll ) db_unlock();
	}
	
	function is_leader($usr_id)
	{
		$id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}group_members WHERE user_id=".$usr_id." AND group_id=".$this->id." AND group_leader='Y'");
		return $id;
	}
	
	function get_member($usr_id)
	{
		return db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}group_members LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}group_members.user_id={SQL_TABLE_PREFIX}users.id WHERE {SQL_TABLE_PREFIX}group_members.user_id=".$usr_id." AND {SQL_TABLE_PREFIX}group_members.group_id=".$this->id));
	}
	
	function get_member_by_ent_id($id)
	{
		return db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}group_members LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}group_members.user_id={SQL_TABLE_PREFIX}users.id WHERE {SQL_TABLE_PREFIX}group_members.id=".$id." AND {SQL_TABLE_PREFIX}group_members.group_id=".$this->id));
	}
	
	function rename($name)
	{
		q("UPDATE {SQL_TABLE_PREFIX}groups SET name='$name' WHERE id=$this->id");
	}
}

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
	foreach ($ent as $k => $v) {
		foreach ($v as $k2 => $v2) {
			unset($v2['ldr']);
			$vals = '\'' . implode('\', \'', $v2) . '\'';
			q('INSERT INTO {SQL_TABLE_PREFIX}group_cache ('.$fields.', user_id, resource_id) VALUES('.$vals.', '.$k2.', '.$k.')');
		}
	}

	if (isset($ll)) {
		db_unlock();
	}
}
?>