<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: thread_view_common.inc.t,v 1.1 2003/04/03 14:35:21 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/* make sure that we have what appears to be a valid forum id */
if (!isset($_GET['frm_id']) || (!($_GET['frm_id'] = (int)$_GET['frm_id']))) {
	invl_inp_err();
}

if (isset($_REQUEST['start'])) {
	$start = (int) $_REQUEST['start'];
} else {
	$start = 0;
}

/* This query creates frm object that contains info about the current 
 * forum, category & user's subscription status & permissions to the
 * forum.
 */

if (_uid) {
	$perm_q = "g.user_id IN("._uid.", 2147483647) AND resource_type='forum' AND resource_id=f.id";
} else {
	$perm_q = "g.user_id=0 AND resource_type='forum' AND resource_id=f.id";
}

$frm = db_sab('SELECT 
			f.id,
			f.name,
			f.thread_count,
			c.name AS cat_name,
			fn.forum_id AS subscribed,
			m.forum_id AS mod,
			g.p_VISIBLE as p_visible, g.p_READ as p_read, g.p_post as p_post, g.p_REPLY as p_reply, g.p_EDIT as p_edit, g.p_DEL as p_del, g.p_STICKY as p_sticky, g.p_POLL as p_poll, g.p_FILE as p_file, g.p_VOTE as p_vote, g.p_RATE as p_rate, g.p_SPLIT as p_split, g.p_LOCK as p_lock, g.p_MOVE as p_move, g.p_SML as p_sml, g.p_IMG as p_img
		FROM {SQL_TABLE_PREFIX}forum f
		INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
		INNER JOIN {SQL_TABLE_PREFIX}group_cache g ON '.$perm_q.'
		LEFT JOIN {SQL_TABLE_PREFIX}forum_notify fn ON fn.user_id='._uid.' AND fn.forum_id='.$_GET['frm_id'].'
		LEFT JOIN {SQL_TABLE_PREFIX}mod m ON m.user_id='._uid.' AND m.forum_id=f.id
		WHERE f.id='.$_GET['frm_id'].' ORDER BY g.id ASC LIMIT 1');

if (!$frm) {
	invl_inp_err();
}

/* check that the user has permissions to access this forum */
$perms = perms_from_obj($frm, $usr->is_mod);
if ($perms['read'] != 'Y') {
	if (!isset($_GET['logoff'])) {
		error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
	} else {
		header('Location: {ROOT}');
		exit;
	}
}

/* do various things for registered users */
if (_uid) {
	if (isset($_GET['sub'])) {
		forum_notify_add(_uid, $frm->id);
		$frm->subscribed = 1;
	} else if (isset($_GET['unsub'])) {
		forum_notify_del(_uid, $frm->id);
		$frm->subscribed = 0;
	}
	$subscribe = $frm->subscribed ? '{TEMPLATE: unsubscribe_link}' : '{TEMPLATE: subscribe_link}';
	$ppg = $usr->posts_ppg ? $usr->posts_ppg : $THREADS_PER_PAGE;
	$MOD = ($usr->is_mod == 'A' || $frm->mod) ? 1 : 0;
} else {
	$subscribe = '';
	$ppg = $THREADS_PER_PAGE;
	$MOD = 0;
}
?>