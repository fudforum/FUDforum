<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: thr_exch.php.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
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
	fud_use('static/thrx_adm.inc');
	{PRE_HTML_PHP}
	
	/* Verify that the user has the right to approve the thread */
	if( !empty($appr) || !empty($decl) ) {
		$thrx = new fud_thr_exchange;
		$thrx->get(($appr?$appr:$decl));
	
		if( !bq("SELECT id FROM {SQL_TABLE_PREFIX}mod WHERE user_id=".$usr->id." AND forum_id=".$thrx->frm) && !is_perms(_uid, $thrx->frm, 'MOVE') && $usr->is_mod!='A' ) $appr = $decl = NULL;
	}

	if( !empty($appr) ) {
		$thr = new fud_thread;
		$frm_src = new fud_forum;
		$frm_dst = new fud_forum;
		
		db_lock('{SQL_TABLE_PREFIX}mod+, {SQL_TABLE_PREFIX}cat+, {SQL_TABLE_PREFIX}thread_view+, {SQL_TABLE_PREFIX}thread+, {SQL_TABLE_PREFIX}forum+, {SQL_TABLE_PREFIX}msg+');
		
		$thr->get_by_id($thrx->th);
		
		
		$frm_src->get(q_singleval("SELECT forum_id FROM {SQL_TABLE_PREFIX}thread WHERE id=".$thrx->th));
		$frm_dst->get($thrx->frm);
		
		$thr->move($thrx->frm);
		
		if ( $frm_src->last_post_id == $thr->last_post_id ) {
			$mid = intzero(q_singleval("SELECT MAX({SQL_TABLE_PREFIX}msg.id) FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.last_post_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$frm_src->id." AND moved_to=0 AND approved='Y'"));
			q("UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id=".$mid." WHERE id=".$frm_src->id);
		}
		
		if( $frm_dst->last_post_id < $thr->last_post_id ) q("UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id=".$thr->last_post_id." WHERE id=".$frm_dst->id);
		
		db_unlock();
		
		$thrx->delete();
		logaction($usr->id, 'THRXAPPROVE', $thr->id);
		
		header("Location: {ROOT}?t=thr_exch&"._rsid);
		exit;
	}
	else if( !empty($decl) ) {
		$thr_name = q_singleval("SELECT subject FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}thread.id=".$thrx->th);
			
		$frm = new fud_forum;
		$frm->get($thrx->frm);
	
		if( isset($reason) ) {
			$dusr = new fud_user;
			$dusr->get_user_by_id($thrx->req_by);
			
			send_status_update($dusr, '{TEMPLATE: exch_decline_ttl}', stripslashes($reason));
			$thrx->delete();
			
			header("Location: {ROOT}?t=thr_exch&"._rsid);
			exit;
		}
		else {
			$thr_exch_data = '{TEMPLATE: thr_move_decline}';
		}
		
		logaction($usr->id, 'THRXDECLINE', $thrx->th);
	}
	
	{POST_HTML_PHP}
	
	if( empty($decl) ) {
		if( $usr->is_mod != 'A' ) $limit = 'INNER JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}mod.user_id='.$usr->id.' AND {SQL_TABLE_PREFIX}thr_exchange.frm={SQL_TABLE_PREFIX}mod.forum_id ';
		
		$r = q("SELECT 
				{SQL_TABLE_PREFIX}thr_exchange.*,
				{SQL_TABLE_PREFIX}msg.subject,
				{SQL_TABLE_PREFIX}forum.name,
				fud_forum2.name AS name2,
				{SQL_TABLE_PREFIX}users.login
			 FROM 
			 	{SQL_TABLE_PREFIX}thr_exchange 
			 	".$limit."
			 INNER JOIN {SQL_TABLE_PREFIX}thread
			 	ON {SQL_TABLE_PREFIX}thr_exchange.th={SQL_TABLE_PREFIX}thread.id
			 INNER JOIN {SQL_TABLE_PREFIX}msg
			 	ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id
			 INNER JOIN {SQL_TABLE_PREFIX}forum
			 	ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id
			 INNER JOIN {SQL_TABLE_PREFIX}forum AS fud_forum2
		 		ON {SQL_TABLE_PREFIX}thr_exchange.frm=fud_forum2.id	
			 INNER JOIN {SQL_TABLE_PREFIX}users
			 	ON {SQL_TABLE_PREFIX}thr_exchange.req_by={SQL_TABLE_PREFIX}users.id");
		if( db_count($r) ) {
			$thr_exch_data = '';
			while( $obj = db_rowobj($r) ) $thr_exch_data .= '{TEMPLATE: thr_exch_entry}';
		}
		else
			$thr_exch_data = '{TEMPLATE: no_thr_exch}';
			
		qf($r);	
	}		
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: THR_EXCH_PAGE}