<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: groups_adm.inc,v 1.1 2003/04/24 16:48:41 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_group
{
	var $id, $name, $permissions, $inherit_id, $joinmode, $res_id, $res;
	
	function add($res_id, $name, $add_gl=0, $perms='', $mk_res='', $inherit_id=0, $joinmode='NONE')
	{
		if (!$add_gl && !$perms) {
			return;
		}
	
		if (!db_locked()) {
			$ll = 1;
			db_lock('{SQL_TABLE_PREFIX}groups WRITE, {SQL_TABLE_PREFIX}group_resources WRITE, {SQL_TABLE_PREFIX}group_members WRITE, {SQL_TABLE_PREFIX}group_cache WRITE');
		}

		/* XXX: This can be done better, unfortunately MySQL does not support SELECT INSERT where the source &
		 * destination tables are the same. So we are stuck doing all the work in PHP, because there is little to 
		 * gain from writing a custom solution for each database type.
		 */
		
		/* make group based on 'primary' 1st group */
		$fld_lst = 'p_' . implode(',p_', $GLOBALS['__GROUPS_INC']['permlist']);
		$r = db_arr_assoc('SELECT '.$fld_lst.' FROM '.$tbl.'groups WHERE id=2');
		if ($perms) {
			$r = array_merge($r, $perms);
		}
		$vals = "'" . implode("', '", $r) . "'";

		$gid = db_qid('INSERT INTO '.$tbl.'groups (name, inherit_id, joinmode, '.$fld_lst.') VALUES(\''.addslashes($name).'\', '.$inherit_id.', \''.$joinmode.'\', '.$vals.');
		$rid = db_qid('INSERT INTO '.$tbl.'group_resources (group_id, resource_id) VALUES ('.$gid.','.$res_id.')');
		if ($add_gl) {
			$vals = "'" . implode("', '", db_arr_assoc('SELECT '.$fld_lst.' FROM '.$tbl.'groups WHERE id=1')) . "'";
			q('INSERT INTO '.$tbl.'group_members (group_id, user_id, '.str_replace('p_', 'up_', $fld_lst).') VALUES ('.$gid.', 0, '.$vals.')');
			
			$vals = "'" . implode("', '", db_arr_assoc('SELECT '.$fld_lst.' FROM '.$tbl.'groups WHERE id=2')) . "'";
			q('INSERT INTO '.$tbl.'group_members (group_id, user_id, '.str_replace('p_', 'up_', $fld_lst).') VALUES ('.$gid.', 2147483647, '.$vals.')');
		}

		if (isset($ll)) {
			db_unlock();
		}

		$this->id = $gid;

		return $gid;
	}

	function sync($id, $perms)
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
?>