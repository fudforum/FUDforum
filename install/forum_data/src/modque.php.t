<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: modque.php.t,v 1.11 2002/08/07 12:18:43 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('msg_edit', 1); define("_imsg_edit_inc_", 1);
	{PRE_HTML_PHP}
	
	if ( isset($ses) ) $ses->update();
	
	if ( !isset($usr) ) { 
		header("Location: {ROOT}?t=login&"._rsidl."&returnto=".urlencode('{ROOT}?t=modque&'._rsidl)); 
		exit(); 
	}
	
	if( $usr->is_mod != 'A' && $usr->is_mod != 'Y' )
		error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');

	if( is_numeric($appr) ) 
		$act_id = $appr;
	else if ( is_numeric($del) )
		$act_id = $del;	
	else
		$act_id = NULL;	

	if( $usr->is_mod != 'A' && $act_id ) {
		if( !bq("SELECT {SQL_TABLE_PREFIX}msg.id FROM {SQL_TABLE_PREFIX}msg INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id INNER JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}mod.forum_id AND {SQL_TABLE_PREFIX}mod.user_id="._uid." WHERE {SQL_TABLE_PREFIX}msg.id=".$act_id) ) 
			error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
	}
	
	if ( is_numeric($appr) ) {
		$msg = new fud_msg_edit;
		$msg->get_by_id($appr);
		$msg->subject = addslashes($msg->subject);
		$msg->body = addslashes($msg->body);
		$msg->approve();
		header("Location: {ROOT}?t=modque&"._rsidl.'&rand='.get_random_value());
		exit();
	}
	else if( is_numeric($del) ) {
		$msg = new fud_msg_edit;
		$msg->get_by_id($del);
		logaction($usr->id, 'DELMSG', $msg->id);
		$msg->delete();
		header("Location: {ROOT}?t=modque&"._rsidl.'&rand='.get_random_value());
		exit();
	}
	
	$r = q("SELECT 
		{SQL_TABLE_PREFIX}msg.*,
		{SQL_TABLE_PREFIX}thread.locked,
		{SQL_TABLE_PREFIX}thread.forum_id,
		{SQL_TABLE_PREFIX}avatar.img AS avatar, 
		{SQL_TABLE_PREFIX}users.id AS user_id, 
		{SQL_TABLE_PREFIX}users.alias AS login, 
		{SQL_TABLE_PREFIX}users.custom_status,
		{SQL_TABLE_PREFIX}users.display_email, 
		{SQL_TABLE_PREFIX}users.email, 
		{SQL_TABLE_PREFIX}users.posted_msg_count, 
		{SQL_TABLE_PREFIX}users.join_date, 
		{SQL_TABLE_PREFIX}users.location,
		{SQL_TABLE_PREFIX}users.sig,
		{SQL_TABLE_PREFIX}users.is_mod,
		{SQL_TABLE_PREFIX}users.icq,
		{SQL_TABLE_PREFIX}users.jabber,
		{SQL_TABLE_PREFIX}users.aim,
		{SQL_TABLE_PREFIX}users.msnm,
		{SQL_TABLE_PREFIX}users.yahoo,
		{SQL_TABLE_PREFIX}users.last_visit AS time_sec,
		{SQL_TABLE_PREFIX}users.invisible_mode,
		{SQL_TABLE_PREFIX}users.avatar_loc,
		{SQL_TABLE_PREFIX}users.avatar_approved,
		{SQL_TABLE_PREFIX}users.email_messages,
		{SQL_TABLE_PREFIX}cat.name AS cat_name, 
		{SQL_TABLE_PREFIX}level.name AS level_name,
		{SQL_TABLE_PREFIX}level.pri AS level_pri,
		{SQL_TABLE_PREFIX}level.img AS level_img,
		{SQL_TABLE_PREFIX}forum.name AS frm_name
		
	FROM
		{SQL_TABLE_PREFIX}msg 
	INNER JOIN {SQL_TABLE_PREFIX}thread 
		ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id 
	INNER JOIN {SQL_TABLE_PREFIX}forum 
		ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id 
	".($usr->is_mod != 'A' ? "INNER JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}mod.forum_id AND {SQL_TABLE_PREFIX}mod.user_id="._uid : '')."
	INNER JOIN {SQL_TABLE_PREFIX}cat
		ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id	
	LEFT JOIN {SQL_TABLE_PREFIX}users
		ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id
	LEFT JOIN {SQL_TABLE_PREFIX}avatar
		ON {SQL_TABLE_PREFIX}users.avatar={SQL_TABLE_PREFIX}avatar.id
	LEFT JOIN {SQL_TABLE_PREFIX}level
			ON {SQL_TABLE_PREFIX}users.level_id={SQL_TABLE_PREFIX}level.id
	WHERE 
		{SQL_TABLE_PREFIX}forum.moderated='Y' 
		AND {SQL_TABLE_PREFIX}msg.approved='N'
	ORDER BY 
		{SQL_TABLE_PREFIX}forum.id, 
		{SQL_TABLE_PREFIX}msg.id DESC");
	
	{POST_HTML_PHP}
	
	$md = new fud_modque;
	$thr = new fud_thread;
	
	$MOD = 1;
	
	$modque_message='';	
	while ( $obj = db_rowobj($r) ) {
		$GLOBALS["returnto"] = 'returnto='.urlencode($GLOBALS["HTTP_SERVER_VARS"]["REQUEST_URI"]);
		if ( empty($prev_thread_id) || $prev_thread_id != $obj->thread_id ) {
			$prev_thread_id = $obj->thread_id;
			
		}	
		$message = tmpl_drawmsg($obj);
		$modque_message .= '{TEMPLATE: modque_message}';
	}
	if ( !db_count($r) ) $modque_message = '{TEMPLATE: no_modque_msg}';
	qf($r);
	un_register_fps();
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: MODQUE_PAGE}