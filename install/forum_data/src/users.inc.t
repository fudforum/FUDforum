<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: users.inc.t,v 1.43 2003/05/12 16:49:55 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function init_user()
{
	/* fetch an object with the user's session, profile & theme info */
	if (!($u = ses_get())) {
		/* new anon user */
		$u = ses_anon_make();
	} else if ($u->id != 1) { /* store the last visit date for registered user */
		q('UPDATE {SQL_TABLE_PREFIX}users SET last_visit='.__request_timestamp__.' WHERE id='.$u->id);
	}
	if ($u->data) {
		$u->data = @unserialize($u->data);
	}

	/* set timezone */
	@putenv('TZ=' . $u->time_zone);
	/* set locale */
	setlocale(LC_ALL, $u->locale);

	/* view format for threads & messages */
	define('d_thread_view', (($GLOBALS['TREE_THREADS_ENABLE']=='N'||$u->default_view=='msg'||$u->default_view=='tree_msg')?'msg':'tree'));
	define('t_thread_view', (($GLOBALS['TREE_THREADS_ENABLE']=='N'||$u->default_view=='msg'||$u->default_view=='msg_tree')?'thread':'threadt'));

	/* theme path */
	define('fud_theme', 'theme/' . $u->theme_name . '/');
		
	/* define _uid, which, will tell us if this is a 'real' user or not */
	define('_uid', (($u->email_conf == 'Y' && $u->acc_status == 'A') ? $u->id : 0));
	define('__fud_real_user__', ($u->id != 1 ? $u->id : 0));

	/* define constants used to track URL sessions & referrals */
	if ($GLOBALS['SESSION_USE_URL'] == 'Y') {
		define('s', $u->ses_id); define('_hs', '<input type="hidden" name="S" value="'.s.'">');
		if ($GLOBALS['TRACK_REFERRALS'] == 'Y' && _uid) { 
			define('_rsid', 'rid='._uid.'&amp;S='.s); define('_rsidl', 'rid='._uid.'&S='.s);
		} else {
			define('_rsid',  'S='.s); define('_rsidl', _rsid);
		}
	} else {
		define('s', ''); define('_hs', '');
		if ($GLOBALS['TRACK_REFERRALS'] == 'Y' && _uid) { 
			define('_rsid',  'rid='._uid); define('_rsidl', _rsid);
		} else {
			define('_rsid', ''); define('_rsidl', ''); 
		}
	}

	return $u;
}

function user_alias_by_id($id)
{
	return q_singleval('SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id='.$id);
}

function user_register_forum_view($frm_id)
{
	q('UPDATE {SQL_TABLE_PREFIX}forum_read SET last_view='.__request_timestamp__.' WHERE forum_id='.$frm_id.' AND user_id='._uid);
	if (!db_affected()) {
		q('INSERT INTO {SQL_TABLE_PREFIX}forum_read(forum_id, user_id, last_view) VALUES('.$frm_id.', '._uid.','.__request_timestamp__.')');
	}
}

function user_register_thread_view($thread_id, $tm=0, $msg_id=0)
{
	if (!$tm) {
		$tm = __request_timestamp__;
	}
	q('UPDATE {SQL_TABLE_PREFIX}read SET last_view='.$tm.', msg_id='.$msg_id.' WHERE thread_id='.$thread_id.' AND user_id='._uid);
	if (!db_affected()) {
		q('INSERT INTO {SQL_TABLE_PREFIX}read(thread_id, user_id, msg_id, last_view) VALUES('.$thread_id.', '._uid.', '.$msg_id.', '.$tm.')');	
	}
}

function user_set_post_count($uid)
{
	$pd = db_saq("SELECT MAX(id),count(*) FROM {SQL_TABLE_PREFIX}msg WHERE poster_id=".$uid." AND approved='Y'");
	$pd[0] = (int) $pd[0]; $pd[1] = (int) $pd[1];
	$level_id = (int) q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}level WHERE post_count <= '.$pd[1].' ORDER BY post_count DESC LIMIT 1');
	q('UPDATE {SQL_TABLE_PREFIX}users SET u_last_post_id='.$pd[0].', posted_msg_count='.$pd[1].', level_id='.$level_id.' WHERE id='.$uid);
}

function user_mark_all_read($id)
{
	if (!($tm = q_singleval('SELECT MAX(post_stamp) FROM {SQL_TABLE_PREFIX}msg'))) {
		$tm = __request_timestamp__;
	}
	q('UPDATE {SQL_TABLE_PREFIX}users SET last_read='.$tm.' WHERE id='.$id);
	q('DELETE FROM {SQL_TABLE_PREFIX}read WHERE user_id='.$id);
	q('DELETE FROM {SQL_TABLE_PREFIX}forum_read WHERE user_id='.$id);
}

function user_mark_forum_read($id, $fid, $last_view)
{
	if (!($tm = q_singleval('SELECT t.last_post_date FROM {SQL_TABLE_PREFIX}thread_view tv INNER JOIN {SQL_TABLE_PREFIX}thread t ON t.forum_id=tv.forum_id AND t.id=tv.thread_id WHERE tv.forum_id='.$fid.' AND tv.page=1 AND tv.pos=1'))) {
		$tm = __request_timestamp__;
	}
	$c = q('SELECT r.id, t.last_post_id FROM {SQL_TABLE_PREFIX}read r INNER JOIN {SQL_TABLE_PREFIX}thread t ON r.thread_id=t.id AND r.last_view < t.last_post_date WHERE r.user_id='.$id); 
	while ($r = db_rowarr($c)) {
		q('UPDATE {SQL_TABLE_PREFIX}read SET last_view='.$tm.', msg_id='.$r[1].' WHERE id='.$r[0]);
	}
	qf($c);

	db_lock('{SQL_TABLE_PREFIX}read WRITE, {SQL_TABLE_PREFIX}thread WRITE');
	q('INSERT INTO {SQL_TABLE_PREFIX}read (user_id, thread_id, msg_id, last_view) SELECT '.$id.', id, last_post_id, '.$tm.' FROM {SQL_TABLE_PREFIX}thread WHERE forum_id='.$fid.' AND last_post_date > '.$last_view);
	db_unlock();
}

if (!defined('forum_debug')) {
	$GLOBALS['usr'] =& init_user();
}
?>