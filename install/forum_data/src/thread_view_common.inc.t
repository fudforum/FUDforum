<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: thread_view_common.inc.t,v 1.5 2003/04/11 09:52:56 hackie Exp $
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
if (!isset($_GET['frm_id']) || (!($frm_id = (int)$_GET['frm_id']))) {
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

make_perms_query($fields, $join);

$frm = db_sab('SELECT 
			f.id,
			f.name,
			f.thread_count,
			c.name AS cat_name,
			fn.forum_id AS subscribed,
			m.forum_id AS mod,
			a.id AS is_ann,
			'.$fields.'
		FROM {SQL_TABLE_PREFIX}forum f
		INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
		LEFT JOIN {SQL_TABLE_PREFIX}forum_notify fn ON fn.user_id='._uid.' AND fn.forum_id='.$frm_id.'
		LEFT JOIN {SQL_TABLE_PREFIX}mod m ON m.user_id='._uid.' AND m.forum_id='.$frm_id.'
		LEFT JOIN {SQL_TABLE_PREFIX}ann_forums a ON a.forum_id=f.id
		'.$join.'
		WHERE f.id='.$frm_id.' LIMIT 1');

if (!$frm) {
	invl_inp_err();
}

/* check that the user has permissions to access this forum */
if ($frm->p_read != 'Y') {
	if (!isset($_GET['logoff'])) {
		error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}');
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