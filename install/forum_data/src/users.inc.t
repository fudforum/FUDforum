<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: users.inc.t,v 1.21 2003/03/31 11:29:59 hackie Exp $
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
	var $id, $login, $alias, $passwd, $plaintext_passwd, $name, $email, $location, $occupation, $interests, $display_email,
	    $notify, $notify_method, $email_messages, $pm_messages, $gender, $icq, $aim, $yahoo, $msnm, $jabber, $affero, $avatar,
	    $avatar_loc, $avatar_approved, $append_sig, $show_sigs, $show_avatars, $show_im, $posts_ppg, $time_zone, $invisible_mode,
	    $ignore_admin, $bday, $blocked, $home_page, $sig, $bio, $posted_msg_count, $last_visit, $last_event, $email_conf, $conf_key,
	    $coppa, $user_image, $join_date, $theme, $last_read, $default_view, $mod_list, $mod_cur, $is_mod, $level_id, $u_last_post_id,
	    $cat_collapse_status, $acc_status;
	
	function get_user_by_id($id) 
	{
		qobj('SELECT * FROM {SQL_TABLE_PREFIX}users WHERE id='.$id, $this);
		return $this->id;
	}
	
	function set_post_count($uid, $val, $mid=0)
	{
		if (!db_locked()) {
			db_lock('{SQL_TABLE_PREFIX}users WRITE, {SQL_TABLE_PREFIX}level WRITE, {SQL_TABLE_PREFIX}msg WRITE');
			$ll = 1;
		}
	
		if (empty($mid)) {
			$mid = (int) q_singleval('SELECT MAX(id) FROM {SQL_TABLE_PREFIX}msg WHERE poster_id='.$uid." AND approved='Y'");
		}
		$pcount = q_singleval('SELECT posted_msg_count FROM {SQL_TABLE_PREFIX}users WHERE id='.$uid) + $val;
		$level_id = (int) q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}level WHERE post_count <= '.$pcount.' ORDER BY post_count DESC LIMIT 1');
		
		q('UPDATE {SQL_TABLE_PREFIX}users SET u_last_post_id='.$mid.', posted_msg_count=posted_msg_count+'.$val.',level_id='.$level_id.' WHERE id='.$uid);
		
		if (isset($ll)) {
			db_unlock();
		}
	}
	
	function register_thread_view($thread_id, $tm=0, $msg_id=0)
	{
		if (!$tm) {
			$tm = __request_timestamp__;
		}
	
		if (($old_msg_id = q_singleval('SELECT msg_id FROM {SQL_TABLE_PREFIX}read WHERE thread_id='.$thread_id.' AND user_id='.$this->id))) {
			if ($old_msg_id > $msg_id) {
				$msg_id = $old_msg_id;
			}

			q('UPDATE {SQL_TABLE_PREFIX}read SET last_view='.$tm.', msg_id='.$msg_id.' WHERE thread_id='.$thread_id.' AND user_id='.$this->id);
		} else {
			q('INSERT INTO {SQL_TABLE_PREFIX}read(thread_id, user_id, msg_id, last_view) VALUES('.$thread_id.', '.$this->id.', '.$msg_id.', '.$tm.')');
		}
	}
	
	function register_forum_view($frm_id)
	{
		$id = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}forum_read WHERE forum_id='.$frm_id.' AND user_id='.$this->id);

		if ($id) {
			q('UPDATE {SQL_TABLE_PREFIX}forum_read SET last_view='.__request_timestamp__.' WHERE id='.$id);
		} else {
			q('INSERT INTO {SQL_TABLE_PREFIX}forum_read(forum_id, user_id, last_view) VALUES('.$frm_id.', '.$this->id.','.__request_timestamp__.')');
		}
	}
	
	function mark_all_read()
	{
		if (!($tm = q_singleval('SELECT MAX(post_stamp) FROM {SQL_TABLE_PREFIX}msg'))) {
			$tm = __request_timestamp__;
		}

		q('UPDATE {SQL_TABLE_PREFIX}users SET last_read='.$tm.' WHERE id='.$this->id);
		q('DELETE FROM {SQL_TABLE_PREFIX}read WHERE user_id='.$this->id);
		q('INSERT INTO {SQL_TABLE_PREFIX}read (user_id,thread_id,msg_id,last_view) SELECT '.$this->id.',id,last_post_id,'.$tm.' FROM {SQL_TABLE_PREFIX}thread');
	}
}

function user_copy_object($osrc, &$odst)
{
	foreach($osrc as $k => $v) {
		$odst->{$k} = $v;
	}
}

function init_user()
{
	$s = new fud_session;
	$u = new fud_user;
	$s->cookie_get_session();
	
	if ($s->user_id && $s->user_id < 2000000000) {
		if (!$u->get_user_by_id($s->user_id)) {
			$s->delete_session();
		}
	}
				
	if (!$u->id && !$s->id) {
		$s->save_session();
	}

	if ($u->id) {
		set_tz($u->time_zone);
		
		define('d_thread_view', (($GLOBALS['TREE_THREADS_ENABLE']=='N'||$u->default_view=='msg'||$u->default_view=='tree_msg')?'msg':'tree'));
		define('t_thread_view', (($GLOBALS['TREE_THREADS_ENABLE']=='N'||$u->default_view=='msg'||$u->default_view=='msg_tree')?'thread':'threadt'));
		
		q('UPDATE {SQL_TABLE_PREFIX}users SET last_visit='.__request_timestamp__.' WHERE id='.$u->id);
	} else {
		set_tz($GLOBALS['SERVER_TZ']);
		
		define('d_thread_view', (($GLOBALS['TREE_THREADS_ENABLE']=='N'||$GLOBALS['DEFAULT_THREAD_VIEW']=='msg'||$GLOBALS['DEFAULT_THREAD_VIEW']=='tree_msg')?'msg':'tree'));
		define('t_thread_view', (($GLOBALS['TREE_THREADS_ENABLE']=='N'||$GLOBALS['DEFAULT_THREAD_VIEW']=='msg'||$GLOBALS['DEFAULT_THREAD_VIEW']=='msg_tree')?'thread':'threadt'));
		
		if (!empty($GLOBALS['rid']) && !isset($_COOKIE['frm_referer_id'])) {
			setcookie('frm_referer_id', $GLOBALS['rid'], __request_timestamp__+31536000, $GLOBALS['COOKIE_PATH'], $GLOBALS['COOKIE_DOMAIN']);
		}
	}

	define('s', $s->ses_id);
	define('_rsid', 'rid='.$u->id.'&amp;S='.s);
	define('_rsidl', 'rid='.$u->id.'&S='.s);
	define('_hs', '<input type="hidden" name="S" value="'.s.'">');
	define('_uid', (($u->id && $u->email_conf == 'Y') ? $u->id : 0));

	return array($s, $u);
}

if (defined('admin_form')) { 
	fud_use('users_reg.inc'); 
	fud_use('users_adm.inc');
}
if (!defined('forum_debug')) {
	list($GLOBALS['ses'], $GLOBALS['usr']) = init_user();
}
?>