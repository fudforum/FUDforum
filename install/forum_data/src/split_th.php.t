<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: split_th.php.t,v 1.4 2002/07/08 23:15:19 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	include_once "GLOBALS.php";

	{PRE_HTML_PHP}

	{POST_HTML_PHP}
	
	if( $usr->is_mod == 'A' || ($usr->is_mod == 'Y' && bq("SELECT {SQL_TABLE_PREFIX}mod.user_id FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}mod.forum_id AND {SQL_TABLE_PREFIX}mod.user_id=".$usr->id)) ) 
		$MOD = 1;
	else {
		$GLOBALS['__RESOURCE_ID'] = q_singleval("SELECT forum_id FROM {SQL_TABLE_PREFIX}thread WHERE id=".$th);
		if( !is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'SPLIT') )
			error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
	}
	
	if( !empty($HTTP_POST_VARS['new_title']) && ($mc=count($HTTP_POST_VARS['sel_th'])) ) {
		sort($HTTP_POST_VARS['sel_th']);
		reset($HTTP_POST_VARS['sel_th']);
		
		$mids = '';
		while( list(,$mid) = each($HTTP_POST_VARS['sel_th']) ) $mids .= $mid.',';
		$mids = substr($mids, 0, -1);	
		
		$src_frm = new fud_forum;
		$dst_frm = new fud_forum;
		$old_thr = new fud_thread;
		
		$old_thr->get_by_id($th);
		$src_frm->get($old_thr->forum_id);
		$dst_frm->get($forum);
		
		if( $mc != ($old_thr->replies+1) ) {
			if ( defined('db_locked') && constant('db_locked') ) {
				db_lock('{SQL_TABLE_PREFIX}thread_view+, {SQL_TABLE_PREFIX}thread+, {SQL_TABLE_PREFIX}forum+, {SQL_TABLE_PREFIX}msg+');
				$local_lock = 1;
			}
			
			$thr = new fud_thread;
			$thr->add($HTTP_POST_VARS['sel_th'][0], $dst_frm->id);
		
			/* Deal with the new thread */
			q("UPDATE {SQL_TABLE_PREFIX}msg SET thread_id=".$thr->id." WHERE id IN (".$mids.")");
			q("UPDATE {SQL_TABLE_PREFIX}msg SET reply_to=".$HTTP_POST_VARS['sel_th'][0]." WHERE thread_id=".$thr->id." AND reply_to NOT IN (".$mids.")");
			q("UPDATE {SQL_TABLE_PREFIX}msg SET reply_to=0, subject='".htmlspecialchars($HTTP_POST_VARS['new_title'])."' WHERE id=".$HTTP_POST_VARS['sel_th'][0]);
			list($lpi, $lpd) = db_singlearr(q("SELECT id,post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE id=".$HTTP_POST_VARS['sel_th'][$mc-1]));
			q("UPDATE {SQL_TABLE_PREFIX}thread SET replies=".($mc-1).", last_post_date=".$lpd.", last_post_id=".$lpi." WHERE id=".$thr->id);
		
			/* Deal with the old thread */
			list($lpi, $lpd) = db_singlearr(q("SELECT id,post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$old_thr->id." AND approved='Y' ORDER BY post_stamp DESC LIMIT 1"));
			$old_root_msg_id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$old_thr->id." AND approved='Y' ORDER BY post_stamp ASC LIMIT 1");
			q("UPDATE {SQL_TABLE_PREFIX}msg SET reply_to=".$old_root_msg_id." WHERE thread_id=".$old_thr->id." AND reply_to IN(".$mids.")");
			q("UPDATE {SQL_TABLE_PREFIX}msg SET reply_to=0 WHERE id=".$old_root_msg_id);
			q("UPDATE {SQL_TABLE_PREFIX}thread SET root_msg_id=".$old_root_msg_id.", replies=replies-".$mc.", last_post_date=".$lpd.", last_post_id=".$lpi." WHERE id=".$old_thr->id);
		
			if( $src_frm->id != $dst_frm->id ) {
				$old_frm_lpi = q_singleval("SELECT last_post_id FROM {SQL_TABLE_PREFIX}thread WHERE forum_id=".$src_frm->id);
				$new_frm_lpi = q_singleval("SELECT last_post_id FROM {SQL_TABLE_PREFIX}thread WHERE forum_id=".$dst_frm->id);
		
				q("UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id=".$old_frm_lpi.", post_count=post_count-".$mc.", thread_count=thread_count-1 WHERE id=".$src_frm->id);
				q("UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id=".$new_frm_lpi.", post_count=post_count+".$mc.", thread_count=thread_count+1 WHERE id=".$dst_frm->id);
		
				rebuild_forum_view($src_frm->id, q_singleval("SELECT page FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id=".$src_frm->id." AND thread_id=".$th));
			}
			else {
				q("UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count+1 WHERE id=".$src_frm->id);
			}
			rebuild_forum_view($dst_frm->id);
			
			if ( !empty($local_lock) ) db_unlock();
			$th_id = $thr->id;
			logaction($usr->id, 'THRSPLIT', $thr->id);
		}
		else {
			q("UPDATE {SQL_TABLE_PREFIX}msg SET subject='".htmlspecialchars($HTTP_POST_VARS['new_title'])."' WHERE id=".$old_thr->root_msg_id);
			if( $src_frm->id != $dst_frm->id ) { 			
				if ( $src_frm->last_post_id == $old_thr->last_post_id ) {
					$mid = q_singleval("SELECT last_post_id FROM {SQL_TABLE_PREFIX}thread WHERE forum_id=".$src_frm->id." AND last_post_date=".q_singleval("select MAX(last_post_date) FROM {SQL_TABLE_PREFIX}thread WHERE forum_id=".$src_frm->id));
					if( empty($mid) ) $mid = 0;
					q("UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id=".$mid." WHERE id=".$src_frm->id);
				}
		
				$old_thr->move($forum);
		
				if( $dst_frm->last_post_id < $old_thr->last_post_id ) q("UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id=".$old_thr->last_post_id." WHERE id=".$dst_frm->id);
			}
			
			$th_id = $old_thr->id;
		}
		header("Location: {ROOT}?t=msg&th=".$th_id."&"._rsid);
		exit;
	}
	
	$r = q("SELECT 
			{SQL_TABLE_PREFIX}msg.*,
			{SQL_TABLE_PREFIX}users.alias AS login 
		FROM 
			{SQL_TABLE_PREFIX}msg 
		LEFT JOIN {SQL_TABLE_PREFIX}users
			ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id
		WHERE
			thread_id=".$th." AND approved='Y'
		ORDER BY 
			post_stamp ASC
		");
	
	if ( $usr->is_mod == 'A' ) {
		$fr = q("SELECT {SQL_TABLE_PREFIX}forum.id, {SQL_TABLE_PREFIX}forum.name FROM {SQL_TABLE_PREFIX}forum INNER JOIN {SQL_TABLE_PREFIX}cat ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id ORDER BY {SQL_TABLE_PREFIX}cat.view_order, {SQL_TABLE_PREFIX}forum.view_order");
		while ( $obj = db_rowobj($fr) ) $fl[] = $obj;
	}
	else {
		$fr = q("SELECT {SQL_TABLE_PREFIX}forum.id, {SQL_TABLE_PREFIX}forum.name FROM {SQL_TABLE_PREFIX}group_cache INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}group_cache.resource_id={SQL_TABLE_PREFIX}forum.id AND {SQL_TABLE_PREFIX}group_cache.resource_type='forum' WHERE user_id="._uid." AND p_SPLIT='Y'");
		while ( $obj = db_rowobj($fr) ) $fl[] = $obj;
		
		if( $usr->is_mod == 'Y' ) {
			$fr2 = q("SELECT {SQL_TABLE_PREFIX}forum.id, {SQL_TABLE_PREFIX}forum.name FROM {SQL_TABLE_PREFIX}mod INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}mod.forum_id={SQL_TABLE_PREFIX}forum.id INNER JOIN {SQL_TABLE_PREFIX}cat ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id WHERE {SQL_TABLE_PREFIX}mod.user_id=".$usr->id." ORDER BY {SQL_TABLE_PREFIX}cat.view_order, {SQL_TABLE_PREFIX}forum.view_order");
			while ( $obj = db_rowobj($fr2) ) $fl[] = $obj;
			qf($fr2);
		}		
	}
	qf($fr);	

	reset($fl);
	$vl = $kl = '';
	while( list(,$obj) = each($fl) ) {
		$vl .= $obj->id."\n";
		$kl .= $obj->name."\n";
	}
	
	$vl = substr($vl, 0, -1);
	$kl = substr($kl, 0, -1);
		
	if( empty($forum) ) 
		$forum = q_singleval("SELECT {SQL_TABLE_PREFIX}forum.id FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id WHERE {SQL_TABLE_PREFIX}thread.id=".$th);
	
	$forum_sel = tmpl_draw_select_opt($vl, $kl, $forum, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	
	$msg_entry = '';
	
	while( $obj = db_rowobj($r) ) {
		$msg_body = read_msg_body($obj->foff,$obj->length, $obj->file_id);
		$msg_entry .= '{TEMPLATE: msg_entry}';
	}
	un_register_fps();
	qf($r);	
	
	{POST_PAGE_PHP_CODE}	
?>
{TEMPLATE: SPLIT_TH_PAGE}