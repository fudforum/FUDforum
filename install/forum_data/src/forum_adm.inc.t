<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: forum_adm.inc.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
		$ret = Q_SINGLEVAL("SELECT MAX(view_order) FROM {SQL_TABLE_PREFIX}forum WHERE cat_id=".$cat_id);
		return ( empty($ret) ? 0 : $ret );
	}
	
	function add($pos)
	{
		DB_LOCK('
			{SQL_TABLE_PREFIX}forum+, 
			{SQL_TABLE_PREFIX}groups+, 
			{SQL_TABLE_PREFIX}group_resources+, 
			{SQL_TABLE_PREFIX}group_members+,
			{SQL_TABLE_PREFIX}group_cache+
		');
		$max = $this->get_max_view($this->cat_id);
		if ( $max > 0 ) {
			$this->view_order = $max+1;
		}
		else $this->view_order = 1;
		
		if ( $pos == 'FIRST' ) {
			$this->move_up(1, $max, $this->cat_id);
			$this->view_order = 1;
		}
		
		Q("INSERT INTO {SQL_TABLE_PREFIX}forum (
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
			".STRNULL($this->descr).",
			'".$this->passwd_posting."',
			".STRNULL($this->post_passwd).",
			'".YN($this->anon_forum)."',
			".__request_timestamp__.",
			".$this->view_order.",
			".STRNULL($this->forum_icon).",
			'".$this->tag_style."',
			'".$this->moderated."',
			".INTZERO($this->max_attach_size).",
			".INTZERO($this->max_file_attachments).",
			".INTZERO($this->message_threshold)."
		)");
		
		$this->id = DB_LASTID();
		$grp = new fud_group;
		reset($GLOBALS['__GROUPS_INC']['permlist']);
		while ( list($k,$v) = each($GLOBALS['__GROUPS_INC']['permlist']) ) {
			$grp->{$k} = 'Y';
		}
		$grp->add('forum', $this->id, $this->name);
		$grp->add_resource('forum', $this->id);
		$grp->rebuild_cache();
		DB_UNLOCK();
		
		return $this->id;
	}
	
	function sync()
	{
		Q("UPDATE {SQL_TABLE_PREFIX}forum SET 
			cat_id=".$this->cat_id.",
			name='".$this->name."',
			descr=".STRNULL($this->descr).",
			passwd_posting='".$this->passwd_posting."',
			post_passwd=".STRNULL($this->post_passwd).",
			anon_forum='".YN($this->anon_forum)."',
			view_order=".$this->view_order.",
			forum_icon=".STRNULL($this->forum_icon).",
			tag_style='".$this->tag_style."',
			moderated='".$this->moderated."',
			max_attach_size=".$this->max_attach_size.", 
			max_file_attachments=".INTZERO($this->max_file_attachments).",
			message_threshold=".INTZERO($this->message_threshold)."
		WHERE id=".$this->id);

		if ( $this->allow_user_vote == 'Y' ) {
			$r = Q("SELECT {SQL_TABLE_PREFIX}thread.id, ROUND(AVG({SQL_TABLE_PREFIX}thread_rate_track.rating)) as avg_rating FROM {SQL_TABLE_PREFIX}thread LEFT JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id AND {SQL_TABLE_PREFIX}forum.allow_user_vote='Y' LEFT JOIN {SQL_TABLE_PREFIX}thread_rate_track ON {SQL_TABLE_PREFIX}thread_rate_track.thread_id={SQL_TABLE_PREFIX}thread.id AND {SQL_TABLE_PREFIX}forum.id=".$this->id." GROUP BY {SQL_TABLE_PREFIX}thread_rate_track.thread_id");
			while ( $obj = DB_ROWOBJ($r) ) {
				if ( !strlen($obj->avg_rating) ) $obj->avg_rating = 0;
				Q("UPDATE {SQL_TABLE_PREFIX}thread SET rating=".$obj->avg_rating." WHERE id=".$obj->id);
			}
			QF($r);
		}
	}
	
	function change_pos($old, $new, $cat_id)
	{
		DB_LOCK('{SQL_TABLE_PREFIX}forum+');
		Q("UPDATE {SQL_TABLE_PREFIX}forum SET view_order=42000000 WHERE cat_id=".$cat_id." AND view_order=".$old);
		
		if ( $old > $new ) 
			$this->move_up($new, $old, $cat_id);			
		else 
			$this->move_down($old, $new, $cat_id);
		
		Q("UPDATE {SQL_TABLE_PREFIX}forum SET view_order=".$new." WHERE cat_id=".$cat_id." AND view_order=42000000");
		DB_UNLOCK();
	}
	
	function move_up($start, $max, $cat_id)
	{
		Q("UPDATE {SQL_TABLE_PREFIX}forum SET view_order=view_order+1 WHERE cat_id=".$cat_id." AND view_order>=".$start." AND view_order<=".$max);
	}
	
	function move_down($start, $max, $cat_id)
	{
		Q("UPDATE {SQL_TABLE_PREFIX}forum SET view_order=view_order-1 WHERE cat_id=".$cat_id." AND view_order>=".$start." AND view_order<=".$max);
	}

	function get_cat_forums($cat_id)
	{
		$result = Q("SELECT * FROM {SQL_TABLE_PREFIX}forum WHERE cat_id=".$cat_id." ORDER BY view_order");

		unset($this->forums);
		$this->cur_frm=0;
		if ( !IS_RESULT($result) ) return;
		
		while ( $obj=DB_ROWOBJ($result) ) {
			$this->forums[$this->cur_frm++] = $obj;
		}
		
		QF($result);
		
		$this->cur_frm = 0;
		return 1;
	}
	
	function get_all_forums()
	{
		$result = Q("SELECT {SQL_TABLE_PREFIX}forum.* FROM {SQL_TABLE_PREFIX}forum INNER JOIN {SQL_TABLE_PREFIX}cat ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id ORDER BY {SQL_TABLE_PREFIX}cat.view_order, {SQL_TABLE_PREFIX}forum.view_order");
		
		unset($this->forums);
		$this->cur_frm=0;
		if ( !IS_RESULT($result) ) return;
		
		while ( $obj=DB_ROWOBJ($result) ) {
			$this->forums[$this->cur_frm++] = $obj;
		}
		
		QF($result);
		
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
		DB_LOCK('{SQL_TABLE_PREFIX}forum+, 
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
			
		$result = Q("SELECT cat_id, view_order FROM {SQL_TABLE_PREFIX}forum WHERE id=".$id);
		if ( !IS_RESULT($result) ) exit("no such forum\n");
		
		list($cat_id, $view_order) = DB_SINGLEARR($result);
		
		$max = $this->get_max_view($cat_id);
		
		$thr = Q("SELECT root_msg_id FROM {SQL_TABLE_PREFIX}thread WHERE forum_id=".$id);
		while ( $obj = DB_ROWOBJ($thr) ) {
			$msg = new fud_msg_edit;
			$msg->get_by_id($obj->root_msg_id);
			$msg->delete();
			unset($msg);
		}
		QF($thr);
		
		
		Q("DELETE FROM {SQL_TABLE_PREFIX}ann_forums WHERE forum_id=".$id);
		Q("DELETE FROM {SQL_TABLE_PREFIX}thread WHERE forum_id=".$id);
		Q("DELETE FROM {SQL_TABLE_PREFIX}mod WHERE forum_id=".$id);
		Q("DELETE FROM {SQL_TABLE_PREFIX}forum_notify WHERE forum_id=".$id);
		if ( $group_id = Q_SINGLEVAL("SELECT id FROM {SQL_TABLE_PREFIX}groups WHERE res='forum' AND res_id=$id") ) {
			$grp = new fud_group;
			$grp->get($group_id);
			$grp->delete();
		}
		Q("DELETE FROM {SQL_TABLE_PREFIX}forum WHERE id=".$id);
		$this->move_down($view_order, $max, $cat_id); 
		DB_UNLOCK();
	}
		
	function chcat($id, $new_cat)
	{
		DB_LOCK('{SQL_TABLE_PREFIX}forum+');
		$result = Q("SELECT cat_id, view_order FROM {SQL_TABLE_PREFIX}forum WHERE id=".$id);
		
		if ( !IS_RESULT($result) ) exit("no such forum");		
		
		list($old_cat_id, $view_order) = DB_SINGLEARR($result);
		$old_max = $this->get_max_view($old_cat_id);
		
		$new_n = $this->get_max_view($new_cat);
		if ( !$new_n ) {
			$new_n = 1;
		}
		else $new_n += 1;
		
		Q("UPDATE {SQL_TABLE_PREFIX}forum SET cat_id=".$new_cat.", view_order=".$new_n." WHERE id=".$id);
		$this->move_down($view_order, $old_max, $old_cat_id); 
		DB_UNLOCK();
	}
}
?>