<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: mvthread.php.t,v 1.6 2003/03/31 13:21:21 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('plain_form', 1);
	
	{PRE_HTML_PHP}
	fud_user_to_adm($usr);
	
	if (isset($_POST['th'], $_POST['thx']) && (int)$_POST['th'] && (int)$_POST['thx']) {
		if ($usr->is_mod != 'A' && !is_moderator($_POST['thx'], _uid)) {
			std_error('access');
			exit();
		}
	
		if (isset($_POST['reason_msg'])) {
			fud_use('thrx_adm.inc', TRUE);
			if (fud_thr_exchange::add($_POST['reason_msg'], $_POST['th'], $_POST['thx'], _uid)) {
				logaction(_uid, 'THRXREQUEST', $_POST['th']);
			}
			exit('<html><script>window.close();</script></html>');
		} else {
			$thr = new fud_thread;
			$frm_dst = new fud_forum_adm;
		
			$thr->get_by_id((int)$_POST['th']);
			$frm_dst->get((int)$_POST['thx']);
			$table_data .= '{TEMPLATE: move_thread_request}';		
		}
	}
	 
	if (isset($_GET['th'], $_GET['to'])) {
		if (!db_locked()) { 
			db_lock('{SQL_TABLE_PREFIX}mod WRITE, {SQL_TABLE_PREFIX}cat WRITE, {SQL_TABLE_PREFIX}thread_view WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}msg WRITE');
			$ll = 1; 
		}

		$thr = new fud_thread;
		$thr->get_by_id((int) $_REQUEST['th']);

		/* fetch data about source thread & forum */
		$src_frm_lpi = q_singleval('SELECT last_post_id FROM {SQL_TABLE_PREFIX}forum WHERE id='.$thr->forum_id);
		/* fetch data about dest forum */
		$dst_frm_lpi = q_singleval('SELECT last_post_id FROM {SQL_TABLE_PREFIX}forum WHERE id='.(int)$_REQUEST['to']);
		
		if (!$src_frm_lpi || !$dst_frm_lpi) {
			db_unlock();
			invl_inp_err();
		}
		
		if ($usr->is_mod != 'A' && (!is_moderator($_REQUEST['to'], _uid) || !is_moderator($thr->forum_id, _uid))) {
			db_unlock();
			std_error('access');
			exit();
		}
		
		$thr->move($_REQUEST['to']);
		
		if ($src_frm_lpi == $thr->last_post_id) {
			$mid = intzero(q_singleval("SELECT MAX({SQL_TABLE_PREFIX}msg.id) FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.last_post_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$thr->forum_id." AND moved_to=0 AND approved='Y'"));
			q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$mid.' WHERE id='.$thr->forum_id);
		}
		
		if (dst_frm_lpi < $thr->last_post_id) {
			q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$thr->last_post_id.' WHERE id='.$_REQUEST['to']);
		}
		
		if (isset($ll)) {
			db_unlock();
		}
		
		logaction(_uid, 'THRMOVE', $_REQUEST['th']);
		
		exit("<html><script>window.opener.location='{ROOT}?t=".t_thread_view."&"._rsid."&frm_id=".$thr->forum_id."'; window.close();</script></html>");
	}

 	{POST_HTML_PHP}

	if (!isset($_POST['thx'])) {
		$thr = new fud_thread;
		$thr->get_by_id((int)$_REQUEST['th']);
	
		/* get permissions for all forums, so a user won't see forums they don't need to see */
		if ($usr->is_mod != 'A') {
			$perms = array();
			$r = q('SELECT p_VISIBLE,resource_id FROM mm_group_cache WHERE user_id='._uid.' AND resource_type=\'forum\'');
			while ($d = db_rowarr($r)) {
				$perms[$d[1]] = $d[0];
			}
			qf($r);
			$r = q('SELECT p_VISIBLE,resource_id FROM mm_group_cache WHERE user_id=2147483647 AND resource_type=\'forum\'');
			while ($d = db_rowarr($r)) {
				if (!isset($perms[$d[1]])) { 
					$perms[$d[1]] = $d[0];
				}
			}
			qf($r);
		}

		$r = q('SELECT {SQL_TABLE_PREFIX}forum.name, {SQL_TABLE_PREFIX}forum.id, {SQL_TABLE_PREFIX}cat.name, {SQL_TABLE_PREFIX}mod.user_id FROM {SQL_TABLE_PREFIX}cat INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}cat.id={SQL_TABLE_PREFIX}forum.cat_id LEFT JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}mod.user_id='._uid.' AND {SQL_TABLE_PREFIX}mod.forum_id={SQL_TABLE_PREFIX}forum.id WHERE {SQL_TABLE_PREFIX}cat.id!=0 AND {SQL_TABLE_PREFIX}forum.id!='.$thr->forum_id.' ORDER BY {SQL_TABLE_PREFIX}cat.view_order, {SQL_TABLE_PREFIX}forum.view_order');
		$table_data = $prev_cat = '';

		while ($ent = db_rowarr($r)) {
			/* determine whether to show the forum or not */
			if ($usr->is_mod != 'A' && !$ent[3] && $perms[$ent[1]] !== 'Y') {
				continue;
			}

			if ($ent[2] !== $prev_cat) {
				$table_data .= '{TEMPLATE: cat_entry}';
				$prev_cat = $ent[2];
			}

			if ($ent[3] || $usr->is_mod == 'A') {
				$table_data .= '{TEMPLATE: forum_entry}';
			} else {
				$table_data .= '{TEMPLATE: txc_forum_entry}';	
			}
		}
	}
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: MVTHREAD_PAGE}