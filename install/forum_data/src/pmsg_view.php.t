<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: pmsg_view.php.t,v 1.4 2002/07/30 14:34:37 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	{PRE_HTML_PHP}

	if( $GLOBALS['PM_ENABLED']=='N' ) {
		error_dialog('{TEMPLATE: pm_err_nopm_title}', '{TEMPLATE: pm_err_nopm_msg}', '{ROOT}?t=index', '');
		exit;		
	}
	
	if ( !isset($usr) ) std_error('login');

	if( empty($id) || !is_numeric($id) ) {
		header("Location: {ROOT}?t=pmsg&"._rsid);
		exit;
	}
	
	$folders = array('INBOX'=>'{TEMPLATE: inbox}', 'DRAFT'=>'{TEMPLATE: draft}', 'SENT'=>'{TEMPLATE: sent}', 'TRASH'=>'{TEMPLATE: trash}');
	
	$msg = new fud_pmsg;
	$msg->get($id,1);
	
	if( empty($msg->id) ) invl_inp_err();
	
	if ( isset($ses) ) $ses->update('{TEMPLATE: pm_update}');
	
	{POST_HTML_PHP}
	
	$cur_ppage = tmpl_cur_ppage($msg->folder_id, $msg->subject);

	$r = q("SELECT 
		{SQL_TABLE_PREFIX}pmsg.*,
		{SQL_TABLE_PREFIX}avatar.img AS avatar,
		{SQL_TABLE_PREFIX}users.id AS user_id,
		{SQL_TABLE_PREFIX}users.alias AS login,
		{SQL_TABLE_PREFIX}users.display_email,
		{SQL_TABLE_PREFIX}users.avatar_approved,
		{SQL_TABLE_PREFIX}users.avatar_loc,
		{SQL_TABLE_PREFIX}users.email,
		{SQL_TABLE_PREFIX}users.posted_msg_count,
		{SQL_TABLE_PREFIX}users.join_date,
		{SQL_TABLE_PREFIX}users.location,
		{SQL_TABLE_PREFIX}users.sig,
		{SQL_TABLE_PREFIX}users.icq,
		{SQL_TABLE_PREFIX}users.is_mod,
		{SQL_TABLE_PREFIX}users.aim,
		{SQL_TABLE_PREFIX}users.msnm,
		{SQL_TABLE_PREFIX}users.yahoo,
		{SQL_TABLE_PREFIX}users.jabber,
		{SQL_TABLE_PREFIX}users.invisible_mode,
		{SQL_TABLE_PREFIX}users.email_messages,
		{SQL_TABLE_PREFIX}users.custom_status,
		{SQL_TABLE_PREFIX}level.name AS level_name,
		{SQL_TABLE_PREFIX}level.pri AS level_pri,
		{SQL_TABLE_PREFIX}level.img AS level_img,
		{SQL_TABLE_PREFIX}ses.time_sec
	FROM 
		{SQL_TABLE_PREFIX}pmsg 
		INNER JOIN {SQL_TABLE_PREFIX}users 
			ON {SQL_TABLE_PREFIX}pmsg.ouser_id={SQL_TABLE_PREFIX}users.id 
		LEFT JOIN {SQL_TABLE_PREFIX}avatar 
			ON {SQL_TABLE_PREFIX}users.avatar={SQL_TABLE_PREFIX}avatar.id 	
		LEFT JOIN {SQL_TABLE_PREFIX}level
			ON {SQL_TABLE_PREFIX}users.level_id={SQL_TABLE_PREFIX}level.id	
		LEFT JOIN {SQL_TABLE_PREFIX}ses
			ON {SQL_TABLE_PREFIX}users.id={SQL_TABLE_PREFIX}ses.user_id	
	WHERE 
		duser_id=".$usr->id." AND 
		{SQL_TABLE_PREFIX}pmsg.id='".$id."'
	");	
	$obj = db_singleobj($r);
		
	/* Next Msg */
	if( ($nid =  q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id=".$usr->id." AND folder_id='".$obj->folder_id."' AND id>".$id." ORDER BY id DESC LIMIT 1")) ) {
		$dpmsg_next_message = '{TEMPLATE: dpmsg_next_message}';		
	}	
	
	/* Prev Msg */
	if( ($pid = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id=".$usr->id." AND folder_id='".$obj->folder_id."' AND id<".$id." ORDER BY id ASC LIMIT 1")) ) {
		$dpmsg_prev_message = '{TEMPLATE: dpmsg_prev_message}';
	}	
		
	$private_message_entry = tmpl_drawpmsg($obj);

	if( !$msg->read_stamp && $msg->mailed=='Y' ) $msg->mark_read();
	if( $msg->ouser_id != $usr->id && $msg->mailed=='Y' && $msg->track=='Y' && empty($dr) ) $msg->send_notify_msg();

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: PMSG_PAGE}	