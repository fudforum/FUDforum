<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: pmsg.php.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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

	if( $GLOBALS['PM_ENABLED']=='N' ) {
		error_dialog('{TEMPLATE: pm_err_nopm_title}', '{TEMPLATE: pm_err_nopm_msg}', $WWW_ROOT, '');
		exit;		
	}

	if ( !isset($usr) ) std_error('login');

	if ( (!empty($btn_move) && !empty($moveto)) || !empty($btn_delete) ) {
		$msg = new fud_pmsg;
		
		if( is_array($HTTP_POST_VARS['sel']) ) {
			while ( list(,$msg->id) = each($HTTP_POST_VARS['sel']) ) {
				if( !is_numeric($msg->id) ) continue;
				
				if ( !empty($btn_delete) ) 
					$msg->del_pmsg();
				else if ( !empty($moveto) ) 
					$msg->move_folder($moveto);
			}
		}
		else if( is_numeric($HTTP_GET_VARS['sel']) ) {
			$msg->id = $HTTP_GET_VARS['sel'];
			if ( !empty($btn_delete) ) 
				$msg->del_pmsg();
			else if ( !empty($moveto) ) 
				$msg->move_folder($moveto);
		}
		
		$folder_id = !empty($btn_delete) ? 'TRASH' : $moveto;
			
		header("Location: {ROOT}?t=pmsg&"._rsid."&folder_id=".$folder_id."&rand=".get_random_value());
		exit();
	}
		
	if ( empty($folder_id) ) $folder_id = "INBOX";
	
	$r = Q("SELECT {SQL_TABLE_PREFIX}pmsg.*,{SQL_TABLE_PREFIX}users.invisible_mode,{SQL_TABLE_PREFIX}users.login,{SQL_TABLE_PREFIX}ses.time_sec FROM {SQL_TABLE_PREFIX}pmsg INNER JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}pmsg.ouser_id={SQL_TABLE_PREFIX}users.id LEFT JOIN {SQL_TABLE_PREFIX}ses ON {SQL_TABLE_PREFIX}users.id={SQL_TABLE_PREFIX}ses.user_id WHERE duser_id=".$usr->id." AND folder_id='".$folder_id."' ORDER BY post_stamp DESC");

	if ( isset($ses) && empty($post_form) ) $ses->update('{TEMPLATE: pm_update}');
	
	$folders = array('INBOX'=>'{TEMPLATE: inbox}', 'DRAFT'=>'{TEMPLATE: draft}', 'SENT'=>'{TEMPLATE: sent}', 'TRASH'=>'{TEMPLATE: trash}');
	
	{POST_HTML_PHP}

	$cur_ppage = tmpl_cur_ppage($folder_id);

	if ( $folder_id == 'DRAFT' ) $lnk = '{ROOT}?t=pmsg&msg_id';
	
	$select_options_cur_folder = tmpl_draw_select_opt("INBOX\nDRAFT\nSENT\nTRASH", "{TEMPLATE: inbox}\n{TEMPLATE: draft}\n{TEMPLATE: sent}\n{TEMPLATE: trash}", $folder_id, '{TEMPLATE: cur_folder_opt}', '{TEMPLATE: cur_folder_opt_selected}');
	
	$disk_usage = Q_SINGLEVAL("SELECT SUM(length) FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id=".$usr->id);
	$percent_full = ceil($disk_usage/$MAX_PMSG_FLDR_SIZE*100);
	$full_indicator = ceil($percent_full*1.69);

	if( $percent_full < 90 )
		$full_indicator = '{TEMPLATE: normal_full_indicator}';
	else if( $percent_full>=90 && $percent_full<100 )
		$full_indicator = '{TEMPLATE: alert_full_indicator}';	
	else
		$full_indicator = '{TEMPLATE: full_full_indicator}';	
	
	if( empty($all) ) {
		$all_v = 1;
		$desc = '{TEMPLATE: pmsg_all}';
	}
	else {
		$all_v = 0;
		$desc = '{TEMPLATE: pmsg_none}';
	}
	
	$private_msg_entry = '';
	while ( $obj = DB_ROWOBJ($r) ) {
		switch ( $obj->folder_id ) 
		{
			case 'INBOX':
				$action = '{TEMPLATE: action_buttons_inbox}';
				break;
			case 'SENT':
			case 'TRASH':
				$action = '{TEMPLATE: action_buttons_sent_trash}';
				break;
			case 'DRAFT':
				$action = '{TEMPLATE: action_buttons_draft}';
				break;
		}
		
		$goto = ( $folder_id != 'DRAFT' ) ? '{ROOT}?t=pmsg_view&'._rsid.'&id='.$obj->id : '{ROOT}?t=ppost&'._rsid.'&msg_id='.$obj->id;
		$pmsg_status = ( $obj->read_stamp ) ? '{TEMPLATE: pmsg_unread}' : '{TEMPLATE: pmsg_read}';
		if( $obj->track=='Y' && $obj->mailed=='Y' && $obj->duser_id==$usr->id && $obj->ouser_id!=$usr->id ) $deny_recipt = '{TEMPLATE: deny_recipt}'; else $deny_recipt = '';
		
		if( $GLOBALS['ONLINE_OFFLINE_STATUS'] == 'Y' && $obj->invisible_mode=='N' ) {
			if( ($obj->time_sec+$GLOBALS['LOGEDIN_TIMEOUT']*60) > __request_timestamp__ ) 
				$online_indicator = '{TEMPLATE: pmsg_online_indicator}';
			else 
				$online_indicator = '{TEMPLATE: pmsg_offline_indicator}';
		}
		
		if ( $obj->nrf_status == 'R' )
			$msg_type ='{TEMPLATE: replied_msg}';
		else if ( $obj->nrf_status == 'F' )
			$msg_type ='{TEMPLATE: forwarded_msg}';
		else
			$msg_type = '{TEMPLATE: normal_msg}';
		
		$user_login = htmlspecialchars($obj->login);
		$checked = !empty($all)?' checked':'';
		
		$private_msg_entry .= '{TEMPLATE: private_msg_entry}';
	}
	QF($r);
	
	$btn_action = ( $folder_id == 'TRASH' ) ? '{TEMPLATE: restore_to}' : '{TEMPLATE: move_to}';
	while( list($k, $v) = each($folders) ) {
		if( $k == $folder_id ) continue;
		$values .= $k."\n";
		$names .= $v."\n";
	}
	$moveto_list = tmpl_draw_select_opt(trim($values), trim($names), '', '{TEMPLATE: move_to_opt}', '{TEMPLATE: move_to_opt_selected}');
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: PMSG_PAGE}