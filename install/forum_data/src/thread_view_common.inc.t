<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: thread_view_common.inc.t,v 1.16 2003/06/02 17:19:47 hackie Exp $
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

if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
	$start = 0;
}

/* This query creates frm object that contains info about the current 
 * forum, category & user's subscription status & permissions to the
 * forum.
 */

make_perms_query($fields, $join, $frm_id);

$frm = db_sab('SELECT 
			f.id, f.name, f.thread_count,
			c.name AS cat_name,
			fn.forum_id AS subscribed,
			m.forum_id AS mod,
			a.id AS is_ann,
			'.$fields.'
		FROM {SQL_TABLE_PREFIX}forum f
		INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
		LEFT JOIN {SQL_TABLE_PREFIX}forum_notify fn ON fn.user_id='._uid.' AND fn.forum_id='.$frm_id.'
		LEFT JOIN {SQL_TABLE_PREFIX}mod m ON m.user_id='._uid.' AND m.forum_id='.$frm_id.'
		'.$join.'
		LEFT JOIN {SQL_TABLE_PREFIX}ann_forums a ON a.forum_id='.$frm_id.'
		WHERE f.id='.$frm_id.' LIMIT 1');

if (!$frm) {
	invl_inp_err();
}

/* check that the user has permissions to access this forum */
if ($frm->p_read != 'Y' && !$frm->mod && $usr->is_mod != 'A') {
	if (!isset($_GET['logoff'])) {
		std_error('perms');
	} else {
		if ($GLOBALS['USE_PATH_INFO'] == 'N') {
			header('Location: {ROOT}?' . _rsidl);
		} else {
			header('Location: {ROOT}/i/' . _rsidl);
		}
		exit;
	}
}

$MOD = ($usr->is_mod == 'A' || $frm->mod) ? 1 : 0;

if ($_GET['t'] == 'threadt') {
	$ann_cols = '5';
	$cur_frm_page = $start + 1;
} else {
	$ann_cols = ($ENABLE_THREAD_RATING == 'Y' ? 9 : 8) + $MOD;
	$cur_frm_page = floor($start / $THREADS_PER_PAGE) + 1;
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
	$mark_all_read = '{TEMPLATE: thread_mark_all_read}';
} else {
	$subscribe = '';
	$mark_all_read = '{TEMPLATE: thread_pdf_rdf}';
}

$ppg = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;
?>