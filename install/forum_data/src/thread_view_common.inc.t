<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: thread_view_common.inc.t,v 1.30 2004/01/04 16:38:27 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
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
			m.forum_id AS md,
			a.ann_id AS is_ann,
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

$MOD = ($usr->users_opt & 1048576 || $frm->md);

/* check that the user has permissions to access this forum */
if (!($frm->group_cache_opt & 2) && !$MOD) {
	if (!isset($_GET['logoff'])) {
		std_error('perms');
	} else {
		if ($FUD_OPT_2 & 32768) {
			header('Location: {ROOT}/i/' . _rsidl);
		} else {
			header('Location: {ROOT}?' . _rsidl);
		}
		exit;
	}
}

if ($_GET['t'] == 'threadt') {
	$ann_cols = '5';
	$cur_frm_page = $start + 1;
} else {
	$ann_cols = '6';
	$cur_frm_page = floor($start / $THREADS_PER_PAGE) + 1;
}

$thread_printable_pdf = $FUD_OPT_2 & 2097152 ? '{TEMPLATE: thread_printable_pdf}' : '';
$thread_syndicate = $FUD_OPT_2 & 1048576 ? '{TEMPLATE: thread_syndicate}' : '';

/* do various things for registered users */
if (_uid) {
	if (isset($_GET['sub']) && sq_check(0, $usr->sq)) {
		forum_notify_add(_uid, $frm->id);
		$frm->subscribed = 1;
	} else if (isset($_GET['unsub']) && sq_check(0, $usr->sq)) {
		forum_notify_del(_uid, $frm->id);
		$frm->subscribed = 0;
	}
	$subscribe = $frm->subscribed ? '{TEMPLATE: unsubscribe_link}' : '{TEMPLATE: subscribe_link}';
	$mark_all_read = '{TEMPLATE: thread_mark_all_read}';
	$merget = ($MOD || $frm->group_cache_opt & 2048) ? '{TEMPLATE: thread_merge_t}' : '';
} else {
	$merget = $subscribe = '';
	$mark_all_read = '{TEMPLATE: thread_pdf_rdf}';
}

$ppg = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;
?>