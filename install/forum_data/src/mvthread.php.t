<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: mvthread.php.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
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
	
	include_once "GLOBALS.php";
	{PRE_HTML_PHP}
	$usr = fud_user_to_adm($usr);
	
	if( !empty($thx) ) {
		if( isset($reason_msg) ) {
			fud_use('static/thrx_adm.inc');
			$thrx = new fud_thr_exchange;
			$thrx->reason_msg = nl2br(htmlspecialchars($HTTP_POST_VARS['reason_msg']));
			$thrx->th = $HTTP_POST_VARS['th'];
			$thrx->frm = $HTTP_POST_VARS['thx'];
			$thrx->req_by = $usr->id;
			$thrx->add();
			logaction($usr->id, 'THRXREQUEST', $thrx->th);
			exit('<html><script>window.close();</script></html>');
		}
		else {
			$thr = new fud_thread;
			$frm_dst = new fud_forum_adm;
		
			$thr->get_by_id($th);
			$frm_dst->get($thx);
			$table_data .= '{TEMPLATE: move_thread_request}';		
		}
	}
	 
	if ( !empty($th) && !empty($to) ) {
		$frm_src = new fud_forum_adm;
		$frm_dst = new fud_forum_adm;
		$thr = new fud_thread;
		
		db_lock('{SQL_TABLE_PREFIX}mod+, {SQL_TABLE_PREFIX}cat+, {SQL_TABLE_PREFIX}thread_view+, {SQL_TABLE_PREFIX}thread+, {SQL_TABLE_PREFIX}forum+, {SQL_TABLE_PREFIX}msg+');
		
		$thr->get_by_id($th);
		$frm_src->get($thr->forum_id);
		$frm_dst->get($to);
		
		if ( !$frm_src->is_moderator($usr->id) && $usr->is_mod!='A' ) {
			db_unlock();
			std_error('access');
			exit();
		}
		
		if ( !$frm_dst->is_moderator($usr->id) && $usr->is_mod!='A' ) {
			db_unlock();
			std_error('access');
			exit();
		}
		
		$thr->move($to);
		
		if ( $frm_src->last_post_id == $thr->last_post_id ) {
			$mid = intzero(q_singleval("SELECT MAX({SQL_TABLE_PREFIX}msg.id) FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.last_post_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$frm_src->id." AND moved_to=0 AND approved='Y'"));
			q("UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id=".$mid." WHERE id=".$frm_src->id);
		}
		
		if( $frm_dst->last_post_id < $thr->last_post_id ) q("UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id=".$thr->last_post_id." WHERE id=".$frm_dst->id);
		
		db_unlock();
		
		logaction($usr->id, 'THRMOVE', $thr->id);
		
		exit("<html><script>window.opener.location='{ROOT}?t=thread&"._rsid."&frm_id=".$frm_src->id."'; window.close();</script></html>");
	}

 	{POST_HTML_PHP}

	if( empty($thx) ) {
		$thr = new fud_thread;
		$thr->get_by_id($th);

		$cat = new fud_cat;
	
		$prev_cat = NULL;
		$table_data = '';
	
		$r = q("SELECT {SQL_TABLE_PREFIX}forum.*,{SQL_TABLE_PREFIX}mod.user_id AS mod_id FROM {SQL_TABLE_PREFIX}forum LEFT JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}mod.user_id=".$usr->id." AND {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}mod.forum_id WHERE {SQL_TABLE_PREFIX}forum.cat_id!=0 GROUP BY {SQL_TABLE_PREFIX}forum.id ORDER BY {SQL_TABLE_PREFIX}forum.cat_id, {SQL_TABLE_PREFIX}forum.view_order");
		while( $obj = db_rowobj($r) ) {
			if( $obj->cat_id != $prev_cat ) {
				$cat->get_cat($obj->cat_id);
				$table_data .= '{TEMPLATE: cat_entry}';
				$prev_cat = $obj->cat_id;
			}
		
			if( $obj->id != $thr->forum_id ) {
				if( $obj->mod_id == $usr->id || $usr->is_mod=='A' ) 
					$table_data .= '{TEMPLATE: forum_entry}';
				else
					$table_data .= '{TEMPLATE: txc_forum_entry}';
			}
		}	
	}
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: MVTHREAD_PAGE}