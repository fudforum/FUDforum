<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: users.inc.t,v 1.18 2003/03/05 13:46:36 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/


class fud_user
{
	var $id=NULL;
	var $login=NULL;
	var $alias=NULL;
	
	var $passwd=NULL;
	var $plaintext_passwd=NULL;
	
	var $name=NULL;
	
	var $email=NULL;
	var $location=NULL;
	var $occupation=NULL;
	var $interests=NULL;
	var $display_email=NULL;
	var $notify=NULL;
	var $notify_method=NULL;
	var $email_messages=NULL;
	var $pm_messages=NULL;
	var $gender=NULL;
	
	var $icq=NULL;
	var $aim=NULL;
	var $yahoo=NULL;
	var $msnm=NULL;
	var $jabber=NULL;
	var $affero=NULL;
	
	var $avatar=NULL;
	var $avatar_loc=NULL;
	var $avatar_approved=NULL;
	
	var $append_sig=NULL;
	var $show_sigs=NULL;
	var $show_avatars=NULL;
	var $show_im=NULL;
	var $posts_ppg=NULL;
	var $time_zone=NULL;
	var $invisible_mode=NULL;
	var $ignore_admin=NULL;
	var $bday=NULL;
	var $blocked=NULL;
	
	var $home_page=NULL;
	var $sig=NULL;
	var $bio=NULL;
	
	var $posted_msg_count=NULL;
	var $last_visit=NULL;
	var $last_event=NULL;
	var $email_conf=NULL;
	var $conf_key=NULL;
	var $coppa=NULL;
	var $user_image=NULL;
	var $join_date=NULL;
	var $theme=NULL;
	var $last_read=NULL;
	var $default_view=NULL;
	var $mod_list=NULL;
	var $mod_cur=NULL;
	var $is_mod=NULL;
	var $level_id=NULL;
	var $u_last_post_id=NULL;
	var $cat_collapse_status=NULL;
	var $acc_status=NULL;
	
	function get_user_by_id($id) 
	{
		qobj("SELECT * FROM {SQL_TABLE_PREFIX}users WHERE id=".$id, $this);
		if( empty($this->id) ) return;
		return $this->id;
	}
	
	function set_post_count($val, $mid='')
	{
		if( !db_locked() ) {
			db_lock('{SQL_TABLE_PREFIX}users+, {SQL_TABLE_PREFIX}level+, {SQL_TABLE_PREFIX}msg+');
			$local_lock=1;
		}
	
		if( empty($mid) ) $mid = q_singleval("SELECT MAX(id) FROM {SQL_TABLE_PREFIX}msg WHERE poster_id=".$this->id." AND approved='Y'");
		$pcount = q_singleval("SELECT posted_msg_count FROM {SQL_TABLE_PREFIX}users WHERE id=".$this->id)+$val;
		$level_id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}level WHERE post_count<=".$pcount." ORDER BY post_count DESC LIMIT 1");
		
		q("UPDATE {SQL_TABLE_PREFIX}users SET u_last_post_id=".intzero($mid).", posted_msg_count=posted_msg_count+".intzero($val).",level_id=".intzero($level_id)." WHERE id=".$this->id);
		
		if( $local_lock ) db_unlock();
	}
	
	function register_thread_view($thread_id, $tm='', $msg_id='')
	{
		if( !$tm ) $tm = __request_timestamp__;
	
		if( ($old_msg_id = q_singleval("SELECT msg_id FROM {SQL_TABLE_PREFIX}read WHERE thread_id=".$thread_id." AND user_id=".$this->id)) ) {
			if( $old_msg_id > $msg_id ) $msg_id = $old_msg_id;
			
			q("UPDATE {SQL_TABLE_PREFIX}read SET last_view=".$tm.", msg_id=".intzero($msg_id)." WHERE thread_id=".$thread_id." AND user_id=".$this->id);
		}
		else {
			q("INSERT INTO {SQL_TABLE_PREFIX}read(thread_id, user_id, msg_id, last_view) VALUES(".$thread_id.", ".$this->id.", ".intzero($msg_id).", ".$tm.")");		}
	}
	
	function register_forum_view($frm_id)
	{
		$id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}forum_read WHERE forum_id=".$frm_id." AND user_id=".$this->id);

		if ( $id ) 
			q("UPDATE {SQL_TABLE_PREFIX}forum_read SET last_view=".__request_timestamp__." WHERE id=".$id);
		else 
			q("INSERT INTO {SQL_TABLE_PREFIX}forum_read(forum_id, user_id, last_view) VALUES(".$frm_id.", ".$this->id.",".__request_timestamp__.")");
	}
	
	function mark_all_read()
	{
		if( !($tm = q_singleval("SELECT MAX(post_stamp) FROM {SQL_TABLE_PREFIX}msg")) ) 
			$tm = __request_timestamp__;
	
		q("UPDATE {SQL_TABLE_PREFIX}users SET last_read=".$tm." WHERE id=".$this->id);
		q("DELETE FROM {SQL_TABLE_PREFIX}read WHERE user_id=".$this->id);
		q("INSERT INTO {SQL_TABLE_PREFIX}read (user_id,thread_id,msg_id,last_view) SELECT ".$this->id.",id,last_post_id,".$tm." FROM {SQL_TABLE_PREFIX}thread");
	}
}

function user_copy_object($osrc, &$odst)
{
	foreach($osrc as $k => $v) $odst->{$k} = $v;
}

function init_user()
{
	$s = new fud_session;
	
	$u = new fud_user;
	
	$s->cookie_get_session();
	if ( $s->user_id && $s->user_id<2000000000 ) {
		if ( !$u->get_user_by_id($s->user_id) ) {
			$u=NULL;
			$s->delete_session();
		}
		/* else NOP */
	}
	else $u = NULL;
				
	if ( empty($u) && empty($s->id) ) $s->save_session();

	$rv[0] = $s;

	if( !empty($u) ) {
		set_tz($u->time_zone);
		
		define('d_thread_view', (($GLOBALS['TREE_THREADS_ENABLE']=='N'||$u->default_view=='msg'||$u->default_view=='tree_msg')?'msg':'tree'));
		define('t_thread_view', (($GLOBALS['TREE_THREADS_ENABLE']=='N'||$u->default_view=='msg'||$u->default_view=='msg_tree')?'thread':'threadt'));
		
		q("UPDATE {SQL_TABLE_PREFIX}users SET last_visit=".__request_timestamp__." WHERE id=".$u->id);
		$rv[1] = $u;
	}
	else {
		set_tz($GLOBALS["SERVER_TZ"]);
		
		define('d_thread_view', (($GLOBALS['TREE_THREADS_ENABLE']=='N'||$GLOBALS['DEFAULT_THREAD_VIEW']=='msg'||$GLOBALS['DEFAULT_THREAD_VIEW']=='tree_msg')?'msg':'tree'));
		define('t_thread_view', (($GLOBALS['TREE_THREADS_ENABLE']=='N'||$GLOBALS['DEFAULT_THREAD_VIEW']=='msg'||$GLOBALS['DEFAULT_THREAD_VIEW']=='msg_tree')?'thread':'threadt'));
		
		$rv[1] = NULL;
		if( !empty($GLOBALS["rid"]) && empty($GLOBALS["HTTP_COOKIE_VARS"]["frm_referer_id"]) ) set_referer_cookie($GLOBALS["rid"]);
	}

	define('s', $s->ses_id);
	define('_rsid', 'rid='.$u->id.'&amp;S='.s);
	define('_rsidl', 'rid='.$u->id.'&S='.s);
	define('_hs', '<input type="hidden" name="S" value="'.s.'">');
	define('_uid', (($u->email_conf == 'Y')?$u->id:0));

	return $rv;
}

if (  defined('admin_form')  ) { fud_use('users_reg.inc'); fud_use('users_adm.inc'); }
if ( !defined('forum_debug') ) list($GLOBALS['ses'], $GLOBALS['usr']) = init_user();
?>