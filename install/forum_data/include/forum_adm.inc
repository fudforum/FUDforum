<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: forum_adm.inc,v 1.16 2004/06/07 15:24:53 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

class fud_forum
{
	var	$id, $cat_id, $name, $descr, $post_passwd, $forum_icon, $forum_opt, $date_created,
		$view_order, $message_threshold, $max_attach_size, $max_file_attachments;

	function fud_forum()
	{
		foreach ($this as $k => $v) {
			if (isset($_POST['frm_'.$k])) {
				$this->{$k} = $_POST['frm_'.$k];
			}
		}
	}

	function add($pos)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		if ($pos == 'LAST') {
			$this->view_order = (int)q_singleval('SELECT MAX(view_order) FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum WHERE cat_id='.$this->cat_id) + 1;
		} else {
			$this->view_order = 1;
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET view_order=view_order+1 WHERE cat_id='.$this->cat_id);
		}

		$this->id = db_qid("INSERT INTO ".$tbl."forum (
			cat_id,
			name,
			descr,
			post_passwd,
			date_created,
			view_order,
			forum_icon,
			max_attach_size,
			max_file_attachments,
			message_threshold,
			forum_opt
		) VALUES (
			".$this->cat_id.",
			'".addslashes($this->name)."',
			".strnull(addslashes($this->descr)).",
			".strnull(addslashes($this->post_passwd)).",
			".__request_timestamp__.",
			".$this->view_order.",
			".strnull(addslashes($this->forum_icon)).",
			".(int)$this->max_attach_size.",
			".(int)$this->max_file_attachments.",
			".(int)$this->message_threshold.",
			".(int)$this->forum_opt."
		)");

		if (($gid = group_add($this->id, $this->name, 2))) {
			grp_rebuild_cache(array(0, 2147483647));
		}

		return $this->id;
	}

	function sync($id, $cat_id)
	{
		$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

		$gid = q_singleval('SELECT id FROM '.$tbl.'groups WHERE forum_id='.$id);

		q("UPDATE ".$tbl."forum SET
			cat_id=".$cat_id.",
			name='".addslashes($this->name)."',
			descr=".strnull(addslashes($this->descr)).",
			post_passwd=".strnull(addslashes($this->post_passwd)).",
			forum_icon=".strnull(addslashes($this->forum_icon)).",
			max_attach_size=".(int)$this->max_attach_size.",
			max_file_attachments=".(int)$this->max_file_attachments.",
			message_threshold=".(int)$this->message_threshold.",
			forum_opt=".(int)$this->forum_opt."
		WHERE id=".$id);

		q('UPDATE '.$tbl.'groups SET name=\''.addslashes($this->name).'\' WHERE id='.$gid);
	}
}

function create_cat_select($name, $def, $blocked)
{
	$ret = '';
	$c = uq('SELECT id, name FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'cat WHERE id!='.$blocked.' ORDER BY view_order');
	while ($r = db_rowarr($c)) {
		$ret .= '<option value="'.$r[0].'"'.($r[0] == $def ? ' selected' : '').'>'.$r[1].'</option>';
	}

	return ($ret ? '<select name="'.$name.'">' . $ret . '</select>' : '');
}

function frm_change_pos($oldp, $newp, $cat_id)
{
	if ($oldp == $newp || !$newp) {
		return;
	}

	db_lock($GLOBALS['DBHOST_TBL_PREFIX'].'forum WRITE');
	q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET view_order=2147483647 WHERE cat_id='.$cat_id.' AND view_order='.$oldp);
	if ($oldp < $newp) {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET view_order=view_order-1 WHERE cat_id='.$cat_id.' AND view_order<='.$newp.' AND view_order>'.$oldp);
		$maxp = q_singleval('SELECT MAX(view_order) FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum WHERE cat_id='.$cat_id.' AND view_order!=2147483647');
		if ($newp > $maxp) {
			$newp = $maxp + 1;
		}
	} else {
		q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET view_order=view_order+1 WHERE  cat_id='.$cat_id.' AND view_order<'.$oldp.' AND view_order>='.$newp);
	}
	q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET view_order='.$newp.' WHERE cat_id='.$cat_id.' AND view_order=2147483647');
	db_unlock();
}

function frm_rebuild_order($cat_id)
{
	$i = 1;
	$c = q('SELECT id, view_order FROM '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum WHERE cat_id='.$cat_id.' ORDER BY view_order');
	while ($r = db_rowarr($c)) {
		if ($i != $r[1]) {
			q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET view_order='.$i.' WHERE id='.$r[0]);
		}
		++$i;
	}
	unset($c);
}

function frm_move_forum($id, $dest_cat, $src_cat)
{
	q('UPDATE '.$GLOBALS['DBHOST_TBL_PREFIX'].'forum SET cat_id='.$dest_cat.' WHERE id='.$id);
	if (db_affected()) {
		if ($src_cat) {
			frm_rebuild_order($src_cat);
		}
		if ($dest_cat) {
			frm_rebuild_order($dest_cat);
		}
		return 1;
	}
	return;
}

function frm_delete($id)
{
	$tbl =& $GLOBALS['DBHOST_TBL_PREFIX'];

	db_lock($tbl.'forum WRITE, '.$tbl.'mod WRITE, '.$tbl.'nntp WRITE, '.$tbl.'mlist WRITE, '.$tbl.'thread WRITE, '.$tbl.'ann_forums WRITE, '.$tbl.'forum_notify WRITE, '.$tbl.'groups WRITE, '.$tbl.'group_resources WRITE, '.$tbl.'group_members WRITE, '.$tbl.'group_cache WRITE');

	q('DELETE FROM '.$tbl.'ann_forums WHERE forum_id='.$id);
	q('DELETE FROM '.$tbl.'thread WHERE forum_id='.$id);
	q('DELETE FROM '.$tbl.'mod WHERE forum_id='.$id);
	q('DELETE FROM '.$tbl.'forum_notify WHERE forum_id='.$id);
	q('DELETE FROM '.$tbl.'group_cache WHERE resource_id='.$id);
	q('DELETE FROM '.$tbl.'thread WHERE forum_id='.$id);
	q('DELETE FROM '.$tbl.'mlist WHERE forum_id='.$id);
	q('DELETE FROM '.$tbl.'nntp WHERE forum_id='.$id);
	$gid = q_singleval('SELECT id FROM '.$tbl.'groups WHERE forum_id='.$id);
	q('DELETE FROM '.$tbl.'forum WHERE id='.$id);

	db_unlock();

	if ($gid) {
		fud_use('groups_adm.inc', true);
		group_delete($gid);
	}
}
?>