<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: reported.php.t,v 1.3 2002/06/18 18:26:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*#? Message Report  Page */
	include_once "GLOBALS.php";
	{PRE_HTML_PHP}
	
	if ( !empty($del) && is_numeric($del) ) {
		$obj = get_report($del, $usr->id);
		if ( !delete_msg_report($del, $usr->id) ) { std_error('access'); exit(); }
		logaction($usr->id, 'DELREPORT', $obj->id);
		header("Location: {ROOT}?t=reported&"._rsid.'rand='.get_random_value());
		exit();
	}
	
	{POST_HTML_PHP}
	
	$mod_limiter = ( $usr->is_mod != 'A' ) ? 'INNER JOIN {SQL_TABLE_PREFIX}mod ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}mod.forum_id AND {SQL_TABLE_PREFIX}mod.user_id='._uid : '';
	
	$r=q("SELECT 
			{SQL_TABLE_PREFIX}msg.*,
			fud_msg_2.subject AS thread_subject,
			{SQL_TABLE_PREFIX}thread.root_msg_id,
			{SQL_TABLE_PREFIX}thread.locked,
			{SQL_TABLE_PREFIX}thread.forum_id,
			{SQL_TABLE_PREFIX}avatar.img AS avatar, 
			{SQL_TABLE_PREFIX}users.id AS user_id, 
			{SQL_TABLE_PREFIX}users.login, 
			{SQL_TABLE_PREFIX}users.custom_status,
			{SQL_TABLE_PREFIX}users.display_email, 
			{SQL_TABLE_PREFIX}users.email, 
			{SQL_TABLE_PREFIX}users.posted_msg_count, 
			{SQL_TABLE_PREFIX}users.join_date, 
			{SQL_TABLE_PREFIX}users.location,
			{SQL_TABLE_PREFIX}users.sig,
			{SQL_TABLE_PREFIX}users.icq,
			{SQL_TABLE_PREFIX}users.aim,
			{SQL_TABLE_PREFIX}users.jabber,
			{SQL_TABLE_PREFIX}users.msnm,
			{SQL_TABLE_PREFIX}users.yahoo,
			{SQL_TABLE_PREFIX}users.is_mod,
			{SQL_TABLE_PREFIX}users.avatar_loc,
			{SQL_TABLE_PREFIX}users.avatar_approved,
			{SQL_TABLE_PREFIX}users.invisible_mode,
			{SQL_TABLE_PREFIX}users.email_messages,
			{SQL_TABLE_PREFIX}msg_report.id AS report_id,
			{SQL_TABLE_PREFIX}msg_report.stamp AS report_stamp,
			{SQL_TABLE_PREFIX}msg_report.reason AS report_reason,
			fud_users_r.id AS report_user_id,
			fud_users_r.login AS report_user_login,
			{SQL_TABLE_PREFIX}ses.time_sec AS time_sec,
			{SQL_TABLE_PREFIX}level.name AS level_name,
			{SQL_TABLE_PREFIX}level.pri AS level_pri,
			{SQL_TABLE_PREFIX}level.img AS level_img,
			{SQL_TABLE_PREFIX}forum.name AS frm_name,
			fud_ses_r.time_sec AS time_sec_r
		FROM 
			{SQL_TABLE_PREFIX}msg_report 
			LEFT JOIN {SQL_TABLE_PREFIX}msg 
				ON {SQL_TABLE_PREFIX}msg_report.msg_id={SQL_TABLE_PREFIX}msg.id 
			LEFT JOIN {SQL_TABLE_PREFIX}users
				ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id
			LEFT JOIN {SQL_TABLE_PREFIX}avatar
				ON {SQL_TABLE_PREFIX}users.avatar={SQL_TABLE_PREFIX}avatar.id
			LEFT JOIN {SQL_TABLE_PREFIX}thread 
				ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id 
			LEFT JOIN {SQL_TABLE_PREFIX}msg AS fud_msg_2
				ON fud_msg_2.id={SQL_TABLE_PREFIX}thread.root_msg_id
			LEFT JOIN {SQL_TABLE_PREFIX}forum 
				ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id 
			".$mod_limiter."
			LEFT JOIN {SQL_TABLE_PREFIX}users AS fud_users_r
				ON {SQL_TABLE_PREFIX}msg_report.user_id=fud_users_r.id
			LEFT JOIN {SQL_TABLE_PREFIX}ses
				ON {SQL_TABLE_PREFIX}ses.user_id={SQL_TABLE_PREFIX}msg.poster_id
			LEFT JOIN {SQL_TABLE_PREFIX}ses AS fud_ses_r
				ON {SQL_TABLE_PREFIX}msg_report.user_id=fud_ses_r.user_id
			LEFT JOIN {SQL_TABLE_PREFIX}level
				ON {SQL_TABLE_PREFIX}users.level_id={SQL_TABLE_PREFIX}level.id
		ORDER BY 
			{SQL_TABLE_PREFIX}msg_report.id");
	
	$MOD = 1;
	$reported_message='';
	while ( $obj = db_rowobj($r) ) {
		if( !empty($obj->report_user_id) ) {
			$user_login = htmlspecialchars($obj->report_user_login);
			$user_login = '{TEMPLATE: reported_reg_user_link}';
		}
		else {
			$user_login = htmlspecialchars($GLOBALS['ANON_NICK']);
			$user_login = '{TEMPLATE: reported_anon_user}';
		}
	
		$GLOBALS["returnto"] = 'returnto='.urlencode($GLOBALS["HTTP_SERVER_VARS"]["REQUEST_URI"]);
		if ( empty($prev_thread_id) || $prev_thread_id != $obj->thread_id ) {
			$prev_thread_id = $obj->thread_id;
			
		}	
		$message = tmpl_drawmsg($obj);
		
		$reported_message .= '{TEMPLATE: reported_message}';
	}
	qf($r);
	un_register_fps();
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: REPORTED_PAGE}