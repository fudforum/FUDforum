<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: users_adm.inc.t,v 1.4 2002/07/13 22:34:37 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
class fud_user_adm extends fud_user_reg
{
	var $custom_tags;

	function delete_user()
	{
		if ( !db_locked() ) { $ll=1; db_lock('{SQL_TABLE_PREFIX}forum+, {SQL_TABLE_PREFIX}poll_opt_track+, {SQL_TABLE_PREFIX}users+, {SQL_TABLE_PREFIX}pmsg+, {SQL_TABLE_PREFIX}attach+, {SQL_TABLE_PREFIX}mod+, {SQL_TABLE_PREFIX}custom_tags+, {SQL_TABLE_PREFIX}thread_notify+, {SQL_TABLE_PREFIX}forum_notify+, {SQL_TABLE_PREFIX}read+, {SQL_TABLE_PREFIX}forum_read+, {SQL_TABLE_PREFIX}thread_rate_track+, {SQL_TABLE_PREFIX}user_ignore+, {SQL_TABLE_PREFIX}buddy+'); }
		$this->de_moderate();
		$u_entry = $this->id."\n".addslashes(htmlspecialchars(trim_show_len($this->login,'LOGIN')));
		q("UPDATE {SQL_TABLE_PREFIX}forum SET moderators=TRIM(BOTH '\n\n' FROM REPLACE(moderators, '$u_entry', ''))");
		
		$tags = new fud_custom_tag;
		$tags->delete_user($this->id);
		
		q("DELETE FROM {SQL_TABLE_PREFIX}thread_notify WHERE user_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}forum_notify WHERE user_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}read WHERE user_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}forum_read WHERE user_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE user_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}user_ignore WHERE user_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}user_ignore WHERE ignore_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}buddy WHERE user_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}buddy WHERE bud_id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}poll_opt_track WHERE user_id=".$this->id);
		
		/* Delete the private messages of this user */
		
		$r = q("SELECT id FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id=".$this->id);
		while( list($pid) = db_rowarr($r) ) {
			$pmsg = new fud_pmsg;
			$pmsg->id = $pid;
			$pmsg->del_pmsg('TRASH');
		}
		
		q("DELETE FROM {SQL_TABLE_PREFIX}users WHERE id=".$this->id);

		if ( $ll ) db_unlock();
	}

	function block_user()
	{
		q("UPDATE {SQL_TABLE_PREFIX}users SET blocked='Y' WHERE id=".$this->id);
	}
	
	function unblock_user()
	{
		q("UPDATE {SQL_TABLE_PREFIX}users SET blocked='N' WHERE id=".$this->id);
	}
	
	function start_mod()
	{
		db_lock('{SQL_TABLE_PREFIX}forum+, {SQL_TABLE_PREFIX}mod+');
	}
	
	function end_mod()
	{
		db_unlock();
	}
	
	function mk_moderator($forum_id)
	{	
		if ( !bq("SELECT id FROM {SQL_TABLE_PREFIX}forum WHERE id=".$forum_id) ) {
			exit("no such forum to moderate\n");
		}
		
		q("INSERT INTO {SQL_TABLE_PREFIX}mod(user_id, forum_id) VALUES(".$this->id.", ".$forum_id.")");
	}
	
	function rm_moderator($forum_id)
	{
		q("DELETE FROM {SQL_TABLE_PREFIX}mod WHERE user_id=".$this->id." AND forum_id=".$forum_id);
	}
	
	function de_moderate()
	{
		q("DELETE FROM {SQL_TABLE_PREFIX}mod WHERE user_id=".$this->id);
	}
	
	function mk_admin()
	{
		q("UPDATE {SQL_TABLE_PREFIX}users SET is_mod='A' WHERE id=".$this->id);
	}
	
	function de_admin()
	{
		$is_mod = ( bq("SELECT id FROM {SQL_TABLE_PREFIX}mod WHERE user_id=".$this->id." LIMIT 1") ) ? 'Y' : 'N';
		q("UPDATE {SQL_TABLE_PREFIX}users SET is_mod='".$is_mod."' WHERE id=".$this->id);
	}
	
	function getmod()
	{
		$result = q("SELECT {SQL_TABLE_PREFIX}mod.id AS id, {SQL_TABLE_PREFIX}mod.user_id AS user_id, {SQL_TABLE_PREFIX}forum.id AS forum_id, {SQL_TABLE_PREFIX}forum.name AS name FROM {SQL_TABLE_PREFIX}mod, {SQL_TABLE_PREFIX}forum WHERE {SQL_TABLE_PREFIX}mod.forum_id={SQL_TABLE_PREFIX}forum.id AND {SQL_TABLE_PREFIX}mod.user_id=".$this->id);
		
		unset($this->mod_list);
		$this->mod_cur = 0;
		
		while ( $obj=db_rowobj($result) ) {
			$this->mod_list[$this->mod_cur++] = $obj;
		}
		qf($result);
		
		return $this->mod_cur;
	}
	
	function countmod()
	{
		if ( !isset($this->mod_list) ) return;
		return @count($this->mod_list); 
	}
	
	function resetmod()
	{
		$this->mod_cur = 0;
	}
	
	function nextmod()
	{
		if ( !isset($this->mod_list[$this->mod_cur]) ) return;
		
		return $this->mod_list[$this->mod_cur++];
	}
	
	function get_custom_tags()
	{
		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}custom_tags WHERE user_id=".$this->id);
		
		unset($this->custom_tags);
		$z = 0;
		while ( $obj = db_rowobj($r) ) {
			$this->custom_tags[$z++] = $obj;
		}
		qf($r);
		
		return $this->custom_tags;
	}
	
	function approve_avatar()
	{
		q("UPDATE {SQL_TABLE_PREFIX}users SET avatar_approved='Y' WHERE id=".$this->id);
		send_status_update($this, '{TEMPLATE: approved_avatar_title}', '{TEMPLATE: approved_avatar_msg}');
	}
	
	function unapprove_avatar()
	{
		q("UPDATE {SQL_TABLE_PREFIX}users SET avatar_approved='NO', avatar_loc=NULL WHERE id=".$this->id);
		send_status_update($this, '{TEMPLATE: unapproved_avatar_title}', '{TEMPLATE: unapproved_avatar_msg}');
	}
}

function fud_user_to_adm($obj)
{
	if ( !$obj ) return;
	$u = new fud_user_adm;
	user_copy_object($obj, $u);
	return $u;
}

?>