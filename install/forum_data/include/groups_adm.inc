<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: groups_adm.inc,v 1.7 2003/05/11 18:47:00 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function group_add($res_id, $name, $add_gl=0, $perms='', $inherit_id=0, $joinmode='NONE')
{
	if (!$add_gl && !$perms) {
		return;
	}

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (!db_locked()) {
		$ll = 1;
		db_lock($tbl.'groups WRITE, '.$tbl.'group_resources WRITE, '.$tbl.'group_members WRITE, '.$tbl.'group_cache WRITE');
	}

	/* XXX: This can be done better, unfortunately MySQL does not support SELECT INSERT where the source &
	 * destination tables are the same. So we are stuck doing all the work in PHP, because there is little to 
	 * gain from writing a custom solution for each database type.
	 */
		
	/* make group based on 'primary' 1st group */
	$fld_lst = implode(',', $GLOBALS['__GROUPS_INC']['permlist']);
	$r = db_arr_assoc('SELECT '.$fld_lst.' FROM '.$tbl.'groups WHERE id=2');

	if ($perms) {
		$r = array_merge($r, $perms);
	}
	$vals = "'" . implode("', '", $r) . "'";

	/* primary group */
	$pg = ($add_gl == 2) ? $res_id : 0;
	if ($pg) {
		$vals = substr(str_repeat("'Y',", count($GLOBALS['__GROUPS_INC']['permlist'])), 0, -1);
	}

	$gid = db_qid('INSERT INTO '.$tbl.'groups (name, inherit_id, joinmode, '.$fld_lst.', forum_id) VALUES(\''.addslashes($name).'\', '.$inherit_id.', \''.$joinmode.'\', '.$vals.', '.$pg.')');
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
	return $gid;
}

function group_delete($id)
{
	if ($id < 3) {
		return;
	}

	$tbl = $GLOBALS['DBHOST_TBL_PREFIX'];

	if (!db_locked()) {
		$ll = 1;
		db_lock($tbl.'groups WRITE, '.$tbl.'group_resources WRITE, '.$tbl.'group_members WRITE, '.$tbl.'group_resources gr WRITE, '.$tbl.'group_members gm WRITE, '.$tbl.'group_cache WRITE');
	} 
	q('DELETE FROM '.$tbl.'group_resources WHERE group_id='.$id);
	q('DELETE FROM '.$tbl.'group_members WHERE group_id='.$id);
	q('DELETE FROM '.$tbl.'group_cache WHERE group_id='.$id);
	/* deal with inheritence */
	if (q_singleval('SELECT id FROM '.$tbl.'groups WHERE inherit_id='.$id.' LIMIT 1')) {
		if (!($ih = q_singleval('SELECT inherit_id FROM '.$tbl.'groups WHERE id='.$id))) {
			$ih = 1;
		}
		if (!function_exists('grp_rebuild_cache')) {
			fud_use('groups.inc');
		}
		$c = q('SELECT id FROM '.$tbl.'groups WHERE inherit_id='.$id);
		q('UPDATE '.$tbl.'groups SET inherit_id='.$ih.' WHERE inherit_id='.$id);
		while ($r = db_rowarr($c)) {
			grp_rebuild_cache($r[0]);		
		}
		qf($c);
		
	}
	q('DELETE FROM '.$tbl.'groups WHERE id='.$id);

	if (isset($ll)) {
		db_unlock();
	}
}

function group_check_inheritence($id)
{
	if (!$id) {
		return;
	}
	$c = uq('SELECT id, inherit_id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'groups WHERE id>2 AND inherit_id>0');
	while ($r = db_rowarr($c)) {
		$il[$r[0]] = $r[1];
	}
	qf($c);

	while (isset($il[$id])) {
		if (isset($il[$id]) && !$il[$id]) {
			return 1;
		}
		$t = $il[$id];
		$il[$id] = 0;
		$id = $t;
	}
	return;
}

function group_sync($id, $name, $inherit, $perms)
{
	$ap = array('I'=>1, 'Y'=>1, 'N'=>1);

	$pq = '';
	foreach ($perms as $k => $v) {
		$pq .= $k . "='".(isset($ap[$v]) ? $v : 'N')."',";
	}
	$name = is_null($name) ? 'name' : "'".addslashes($name)."'";

	q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'groups SET '.$pq.' name='.$name.', inherit_id='.$inherit.' WHERE id='.$id);
}

function draw_perm_table($obj)
{
	$arr =& $GLOBALS['__GROUPS_INC']['permlist'];
	$str = '';
	foreach($arr as $k => $v) {
		switch ($obj->{$k}) {
			case 'Y':
				$str .= '<td><font size="-1" color="#FF0000"><b>(Yes)</b></font></td>';
				break;
			case 'N':
				$str .= '<td><font size="-1" color="#0000FF"><b>(No)</b></font></td>';
				break;
			default:
				$str .= '<td><font size="-1" color="#00AA00">Inherit</font></td>';
		}
	}

	return $str;
}

function rebuild_group_ldr_cache($id)
{
	$c = uq('SELECT group_id FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'group_members WHERE user_id='.$id.' AND group_leader=\'Y\'');
	while ($r = db_rowarr($c)) {
		$g[] = $r[0];
	}
	qf($c);
	if (isset($g)) {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET group_leader_list='.strnull(addslashes(@serialize($g))).' WHERE id='.$id);
	} else {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'users SET group_leader_list=NULL WHERE id='.$id);
	}
}
?>