<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: forum_adm.inc.t,v 1.7 2002/08/13 11:34:58 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_forum_adm extends fud_forum
{
	function get_max_view($cat_id)
	{
		$ret = q_singleval("SELECT MAX(view_order) FROM {SQL_TABLE_PREFIX}forum WHERE cat_id=".$cat_id);
		return ( empty($ret) ? 0 : $ret );
	}
	
	function add($pos)
	{
		if ( !db_locked() ) {
			$ll = 1;
			db_lock('
				{SQL_TABLE_PREFIX}forum+, 
				{SQL_TABLE_PREFIX}groups+, 
				{SQL_TABLE_PREFIX}group_resources+, 
				{SQL_TABLE_PREFIX}group_members+,
				{SQL_TABLE_PREFIX}group_cache+
			');
		}
		
		$max = $this->get_max_view($this->cat_id);
		if ( $max > 0 ) {
			$this->view_order = $max+1;
		}
		else $this->view_order = 1;
		
		if ( $pos == 'FIRST' ) {
			$this->move_up(1, $max, $this->cat_id);
			$this->view_order = 1;
		}
		
		$r = q("INSERT INTO {SQL_TABLE_PREFIX}forum (
			cat_id, 
			name, 
			descr, 
			passwd_posting, 
			post_passwd, 
			anon_forum, 
			date_created, 
			view_order, 
			forum_icon, 
			tag_style, 
			moderated, 
			max_attach_size,
			max_file_attachments,
			message_threshold
		) 
		VALUES(
			".$this->cat_id.",
			'".$this->name."',
			".strnull($this->descr).",
			'".$this->passwd_posting."',
			".strnull($this->post_passwd).",
			'".yn($this->anon_forum)."',
			".__request_timestamp__.",
			".$this->view_order.",
			".strnull($this->forum_icon).",
			'".$this->tag_style."',
			'".$this->moderated."',
			".intzero($this->max_attach_size).",
			".intzero($this->max_file_attachments).",
			".intzero($this->message_threshold)."
		)");
		
		$this->id = db_lastid("{SQL_TABLE_PREFIX}forum", $r);
		$grp = new fud_group;
		foreach($GLOBALS['__GROUPS_INC']['permlist'] as $k => $v) $grp->{$k} = 'Y';
		$grp->add('forum', $this->id, $this->name);
		$grp->add_resource('forum', $this->id);
		$grp->rebuild_cache();
		if ( $ll ) db_unlock();
		
		return $this->id;
	}
	
	function sync()
	{
		if ( !db_locked() ) { 
			$ll=1; 
			db_lock('
				{SQL_TABLE_PREFIX}forum+, 
				{SQL_TABLE_PREFIX}groups+
			'); 
		}
		
		q("UPDATE {SQL_TABLE_PREFIX}forum SET 
			cat_id=".$this->cat_id.",
			name='".$this->name."',
			descr=".strnull($this->descr).",
			passwd_posting='".$this->passwd_posting."',
			post_passwd=".strnull($this->post_passwd).",
			anon_forum='".yn($this->anon_forum)."',
			view_order=".$this->view_order.",
			forum_icon=".strnull($this->forum_icon).",
			tag_style='".$this->tag_style."',
			moderated='".$this->moderated."',
			max_attach_size=".$this->max_attach_size.", 
			max_file_attachments=".intzero($this->max_file_attachments).",
			message_threshold=".intzero($this->message_threshold)."
		WHERE id=".$this->id);
		
		q("UPDATE {SQL_TABLE_PREFIX}groups SET name='$this->name' WHERE res='forum' AND res_id=$this->id");
		
		if ( $ll ) db_unlock();
	}
	
	function change_pos($old, $new, $cat_id)
	{
		if ( !db_locked() ) {
			$ll = 1;
			db_lock('{SQL_TABLE_PREFIX}forum+');
		}
		
		q("UPDATE {SQL_TABLE_PREFIX}forum SET view_order=2147483647 WHERE cat_id=".$cat_id." AND view_order=".$old);
		
		if ( $old > $new ) 
			$this->move_up($new, $old, $cat_id);			
		else 
			$this->move_down($old, $new, $cat_id);
		
		q("UPDATE {SQL_TABLE_PREFIX}forum SET view_order=".$new." WHERE cat_id=".$cat_id." AND view_order=2147483647");
		if ( $ll ) db_unlock();
	}
	
	function move_up($start, $max, $cat_id)
	{
		q("UPDATE {SQL_TABLE_PREFIX}forum SET view_order=view_order+1 WHERE cat_id=".$cat_id." AND view_order>=".$start." AND view_order<=".$max);
	}
	
	function move_down($start, $max, $cat_id)
	{
		q("UPDATE {SQL_TABLE_PREFIX}forum SET view_order=view_order-1 WHERE cat_id=".$cat_id." AND view_order>=".$start." AND view_order<=".$max);
	}

	function get_cat_forums($cat_id)
	{
		$result = q("SELECT * FROM {SQL_TABLE_PREFIX}forum WHERE cat_id=".$cat_id." ORDER BY view_order");

		unset($this->forums);
		$this->cur_frm=0;
		if ( !is_result($result) ) return;
		
		while ( $obj=db_rowobj($result) ) {
			$this->forums[$this->cur_frm++] = $obj;
		}
		
		qf($result);
		
		$this->cur_frm = 0;
		return 1;
	}
	
	function get_all_forums()
	{
		$result = q("SELECT {SQL_TABLE_PREFIX}forum.* FROM {SQL_TABLE_PREFIX}forum INNER JOIN {SQL_TABLE_PREFIX}cat ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id ORDER BY {SQL_TABLE_PREFIX}cat.view_order, {SQL_TABLE_PREFIX}forum.view_order");
		
		unset($this->forums);
		$this->cur_frm=0;
		if ( !is_result($result) ) return;
		
		while ( $obj=db_rowobj($result) ) {
			$this->forums[$this->cur_frm++] = $obj;
		}
		
		qf($result);
		
		$this->cur_frm = 0;
		return 1;
	}
	
	function resetfrm()
	{
		$this->cur_frm = 0;
	}
	
	function countfrm()
	{
		if ( !isset($this->forums) ) return;
		return count($this->forums);
	}
	
	function nextfrm()
	{
		if ( !isset($this->forums[$this->cur_frm]) ) return;

		$this->id = $this->forums[$this->cur_frm]->id;
		$this->cat_id = $this->forums[$this->cur_frm]->cat_id;
		$this->name = $this->forums[$this->cur_frm]->name;
		$this->descr = $this->forums[$this->cur_frm]->descr;
		$this->passwd_posting = $this->forums[$this->cur_frm]->passwd_posting;
		$this->post_passwd = $this->forums[$this->cur_frm]->post_passwd;
		$this->anon_forum = $this->forums[$this->cur_frm]->anon_forum;
		$this->date_created = $this->forums[$this->cur_frm]->date_created;
		$this->thread_count = $this->forums[$this->cur_frm]->thread_count;
		$this->post_count = $this->forums[$this->cur_frm]->post_count;
		$this->view_order = $this->forums[$this->cur_frm]->view_order;
		$this->forum_icon = $this->forums[$this->cur_frm]->forum_icon;
		$this->tag_style = $this->forums[$this->cur_frm]->tag_style;
		$this->moderated = $this->forums[$this->cur_frm]->moderated;
		$this->max_attach_size = $this->forums[$this->cur_frm]->max_attach_size;
		$this->max_file_attachments = $this->forums[$this->cur_frm]->max_file_attachments;
		$this->message_threshold = $this->forums[$this->cur_frm]->message_threshold;
		$this->cur_frm++;
		
		return 1;
	}
	
	function delete($id) {
		db_lock('{SQL_TABLE_PREFIX}forum+, 
			{SQL_TABLE_PREFIX}mod+, 
			{SQL_TABLE_PREFIX}thread+, 
			{SQL_TABLE_PREFIX}msg+, 
			{SQL_TABLE_PREFIX}ann_forums+, 
			{SQL_TABLE_PREFIX}forum_notify+, 
			{SQL_TABLE_PREFIX}thread_notify+, 
			{SQL_TABLE_PREFIX}read+, 
			{SQL_TABLE_PREFIX}cat+, 
			{SQL_TABLE_PREFIX}attach+, 
			{SQL_TABLE_PREFIX}poll+, 
			{SQL_TABLE_PREFIX}poll_opt+, 
			{SQL_TABLE_PREFIX}poll_opt_track+, 
			{SQL_TABLE_PREFIX}users+, 
			{SQL_TABLE_PREFIX}msg_report+, 
			{SQL_TABLE_PREFIX}level+,
			{SQL_TABLE_PREFIX}thread_view+,
			{SQL_TABLE_PREFIX}thread_rate_track+,
			{SQL_TABLE_PREFIX}groups+, 
			{SQL_TABLE_PREFIX}group_resources+, 
			{SQL_TABLE_PREFIX}group_members+,
			{SQL_TABLE_PREFIX}group_cache+
			');
			
		$result = q("SELECT cat_id, view_order FROM {SQL_TABLE_PREFIX}forum WHERE id=".$id);
		if ( !is_result($result) ) exit("no such forum\n");
		
		list($cat_id, $view_order) = db_singlearr($result);
		
		$max = $this->get_max_view($cat_id);
		
		$thr = q("SELECT root_msg_id FROM {SQL_TABLE_PREFIX}thread WHERE forum_id=".$id);
		while ( $obj = db_rowobj($thr) ) {
			$msg = new fud_msg_edit;
			$msg->get_by_id($obj->root_msg_id);
			$msg->delete(false);
			unset($msg);
		}
		qf($thr);
		
		q("DELETE FROM {SQL_TABLE_PREFIX}ann_forums WHERE forum_id=".$id);
		q("DELETE FROM {SQL_TABLE_PREFIX}thread WHERE forum_id=".$id);
		q("DELETE FROM {SQL_TABLE_PREFIX}mod WHERE forum_id=".$id);
		q("DELETE FROM {SQL_TABLE_PREFIX}forum_notify WHERE forum_id=".$id);
		if ( $group_id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}groups WHERE res='forum' AND res_id=$id") ) {
			$grp = new fud_group;
			$grp->get($group_id);
			$grp->delete();
		}
		q("DELETE FROM {SQL_TABLE_PREFIX}forum WHERE id=".$id);
		$this->move_down($view_order, $max, $cat_id); 
		
		$r = q("select {SQL_TABLE_PREFIX}users.id FROM {SQL_TABLE_PREFIX}users LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}users.u_last_post_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}msg.id IS NULL");
		while( list($uid) = db_rowarr($r) ) {
			$u = new fud_user_reg;
			$u->id = $uid;
			$u->set_post_count(0);
		}
		qf($r);
		
		db_unlock();
	}
		
	function chcat($id, $new_cat)
	{
		db_lock('{SQL_TABLE_PREFIX}forum+');
		$result = q("SELECT cat_id, view_order FROM {SQL_TABLE_PREFIX}forum WHERE id=".$id);
		
		if ( !is_result($result) ) exit("no such forum");		
		
		list($old_cat_id, $view_order) = db_singlearr($result);
		$old_max = $this->get_max_view($old_cat_id);
		
		$new_n = $this->get_max_view($new_cat);
		if ( !$new_n ) {
			$new_n = 1;
		}
		else $new_n += 1;
		
		q("UPDATE {SQL_TABLE_PREFIX}forum SET cat_id=".$new_cat.", view_order=".$new_n." WHERE id=".$id);
		$this->move_down($view_order, $old_max, $old_cat_id); 
		db_unlock();
	}
}
?>