<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: drawpmsg.inc.t,v 1.7 2002/07/31 21:56:50 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function tmpl_drawpmsg(&$obj)
{
	$disable_avatar = ( $obj->level_pri == 'L' ) ? 1 : 0;

	if ( !$disable_avatar ) {
		if( $obj->avatar ) 
			$avatar_img = 'images/avatars/'.$obj->avatar;
		else if ( $obj->avatar_approved == 'Y' ) 
			$avatar_img = ($obj->avatar_loc) ? $obj->avatar_loc : 'images/custom_avatars/'.$obj->user_id;
		
		$avatar = ($avatar_img) ? '{TEMPLATE: dpmsg_avatar}' : '{TEMPLATE: dpmsg_no_avatar}';
	}
	
	if( ($GLOBALS['ONLINE_OFFLINE_STATUS'] == 'Y' && $obj->invisible_mode=='N') || $GLOBALS["usr"]->is_mod == 'A' ) 
		$online_indicator = (($obj->time_sec+$GLOBALS['LOGEDIN_TIMEOUT']*60) > __request_timestamp__) ? '{TEMPLATE: dpmsg_online_indicator}' : '{TEMPLATE: dpmsg_offline_indicator}';
	
	if ( $GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' && !empty($obj->host_name) ) {
		$host_name = wordwrap($obj->host_name,30,'<br>',1);
		$host_name = '{TEMPLATE: dpmsg_host_name}';
	}	

	if ( !empty($obj->location) ) {
		$location = trim_show_len($obj->location, 'LOCATION');
		$location = '{TEMPLATE: dpmsg_location}';
	}
	else $location = '{TEMPLATE: dpmsg_no_location}';

	$msg_icon = empty($obj->icon) ? '{TEMPLATE: dpmsg_no_msg_icon}' : '{TEMPLATE: dpmsg_msg_icon}';	
	
	if( $obj->length ) 
		$msg_body = read_pmsg_body($obj->foff,$obj->length);
	else
		$msg_body = '{TEMPLATE: dpmsg_no_msg_body}'; 
	
	if( empty($GLOBALS['POST_FORM']) ) {
		$custom_tag = empty($obj->custom_status) ? '{TEMPLATE: dpmsg_no_custom_tags}' : '{TEMPLATE: dpmsg_custom_tags}';
	
		if ( isset($GLOBALS['usr']) && $obj->user_id > 0 && $obj->user_id != _uid ) {
			$buddy_link = '{TEMPLATE: dpmsg_buddy_link}';
			if ( $obj->is_mod != 'A' && !bq("SELECT id FROM {SQL_TABLE_PREFIX}user_ignore WHERE user_id=".$GLOBALS['usr']->id." AND ignore_id=".$obj->user_id) ) $ignore_link = '{TEMPLATE: dpmsg_add_user_ignore_list}';
		}
	
		if ( $obj->level_pri ) {
			if( !empty($obj->level_name) ) $level_name = '{TEMPLATE: dpmsg_level_name}';
			if( !empty($obj->level_img) && strtolower($obj->level_pri)!='a' ) $level_image = '{TEMPLATE: dpmsg_level_image}';
		}	
	
		/* determine IM status */
		if ( $obj->icq ) 	$im_icq =   '{TEMPLATE: dpmsg_im_icq}';
		if ( $obj->aim ) 	{ $im_aim = urlencode($obj->aim); $im_aim = '{TEMPLATE: dpmsg_im_aim}'; }
		if ( $obj->yahoo ) 	{ $im_yahoo = urlencode($obj->yahoo); $im_yahoo = '{TEMPLATE: dpmsg_im_yahoo}'; }
		if ( $obj->msnm ) 	$im_msnm =  '{TEMPLATE: dpmsg_im_msnm}';
		if ( $obj->jabber ) 	$im_jabber =  '{TEMPLATE: dpmsg_im_jabber}';
	
		if( $obj->ouser_id != $GLOBALS["usr"]->id ) {
			$user_profile = '{TEMPLATE: dpmsg_user_profile}';
			$encoded_login = urlencode($obj->login);
			$ret_n_sid = 'returnto='.urlencode($GLOBALS["HTTP_SERVER_VARS"]["REQUEST_URI"]).'&'._rsid;
			if( $GLOBALS["ALLOW_EMAIL"] == 'Y' && $obj->email_messages == 'Y' ) $email_link = '{TEMPLATE: dpmsg_email_link}';
			if( $GLOBALS['PM_ENABLED']=='Y' ) $private_msg_link = '{TEMPLATE: dpmsg_private_msg_link}';
		}			
	
		if( $obj->folder_id=='DRAFT' ) $edit_link = '{TEMPLATE: dpmsg_edit_link}';
	
		if( $obj->folder_id=='INBOX' ) {
			$reply_link = '{TEMPLATE: dpmsg_reply_link}';
			$quote_link = '{TEMPLATE: dpmsg_quote_link}';
		}			
	
		$profile_link = '{TEMPLATE: dpmsg_profile_link}';
		$msg_toolbar = '{TEMPLATE: dpmsg_msg_toolbar}';
	
		if ( $obj->attach_cnt ) {
			$a_result = q("SELECT {SQL_TABLE_PREFIX}attach.id,original_name,dlcount,icon FROM {SQL_TABLE_PREFIX}attach LEFT JOIN {SQL_TABLE_PREFIX}mime ON {SQL_TABLE_PREFIX}attach.mime_type={SQL_TABLE_PREFIX}mime.id WHERE message_id=".$obj->id." AND private='Y'");
			if ( db_count($a_result) ) {
				$file_attachments='';
				while ( $a_obj = db_rowobj($a_result) ) {
					if( file_exists($GLOBALS["FILE_STORE"].$a_obj->id.".atch") ) {
						$sz = filesize($GLOBALS["FILE_STORE"].$a_obj->id.".atch")/1024;
						$sz = $sz<1000 ? number_format($sz,2).'KB' : number_format($sz/1024,2).'MB';
						if( empty($a_obj->icon) ) $a_obj->icon = 'unknown.gif';
						$file_attachments .= '{TEMPLATE: dpmsg_file_attachment}';	
					}					
				}
				$file_attachments = '{TEMPLATE: dpmsg_file_attachments}';
			}
			qf($a_result);	
		}
		if ( $GLOBALS["ALLOW_SIGS"] == 'Y' && $obj->show_sig == 'Y' && $GLOBALS["usr"]->show_sigs=='Y' && $obj->sig) $signature = '{TEMPLATE: dpmsg_signature}';
	}
	else
		$profile_link = '{TEMPLATE: dpmsg_profile_no_link}';
		
	return '{TEMPLATE: private_message_entry}';
}		
?>