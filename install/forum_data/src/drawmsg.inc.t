<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: drawmsg.inc.t,v 1.13 2002/08/01 18:37:27 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function build_ignore_list()
{
	$GLOBALS['__IGNORE_LIST__'] = array();

	$r = q("SELECT id,ignore_id FROM {SQL_TABLE_PREFIX}user_ignore WHERE user_id=".$GLOBALS["usr"]->id);
	while( list($id,$ignore_id) = db_rowarr($r) ) $GLOBALS['__IGNORE_LIST__'][$ignore_id] = $id;
	qf($r);
}

if( isset($GLOBALS['rev']) ) {
	$drawmsg_inc_tmp = explode(':', $GLOBALS['rev']);
	foreach($drawmsg_inc_tmp as $v) {
		if ( strlen($v) ) $GLOBALS['__REVEALED_POSTS__'][$v] = 1;
	}
	unset($drawmsg_inc_tmp);
}	

if( isset($GLOBALS['reveal']) ) {
	$drawmsg_inc_tmp = explode(':', $GLOBALS['reveal']);
	foreach($drawmsg_inc_tmp as $v) {	
		if ( strlen($v) ) $GLOBALS['__REVEALED_USERS__'][$v] = 1;
	}
	unset($drawmsg_inc_tmp);
}	

function register_vote($opt)
{
	$poll = new fud_poll; 
	$poll_opt = new fud_poll_opt;
	$poll_opt->get($opt);
	$poll->get($poll_opt->poll_id);
	if ( !$poll->voted(_uid) ) {
		$poll_opt->increase();
		$poll->regvote(_uid);
	}	
}
/* determine the source form */
$GLOBALS['__DRAW_MSG_SCRIPT_NAME'] = basename($GLOBALS['HTTP_SERVER_VARS']['PATH_TRANSLATED']);

$GLOBALS['__MSG_COUNT__']=-1;

function tmpl_drawmsg(&$obj, $msg_count=NULL, $pager=NULL, $_rsid=_rsid)
{
	global $rev;
	global $reveal;
	global $count;
	global $start;

	if ( empty($obj->user_id) ) $obj->user_id=$obj->poster_id=0;

	if ( !empty($GLOBALS['DRAWMSG_OPTS']['NO_MSG_CONTROLS']) ) $hide_controls = 1;
	if( isset($GLOBALS['usr']) && !isset($GLOBALS['__IGNORE_LIST__']) ) build_ignore_list();
	$ret_n_sid = 'returnto='.urlencode($GLOBALS["HTTP_SERVER_VARS"]["REQUEST_URI"].'#msg_'.$obj->id).'&'.$_rsid;
	
	$GLOBALS['__MSG_COUNT__']++;
	
	if( $msg_count && empty($hide_controls) ) { 
		if( $GLOBALS['__MSG_COUNT__'] ) {
			$msg_num = $GLOBALS['__MSG_COUNT__']-1;
			$prev_message = '{TEMPLATE: dmsg_prev_message}';
		}
		else if ( $pager && $obj->id!=$obj->root_msg_id ) {
			$msg_start = $GLOBALS['start']-$GLOBALS['count'];
			$prev_message = '{TEMPLATE: dmsg_prev_message_prev_page}';
		}	
			
		if( $GLOBALS['__MSG_COUNT__'] < $msg_count ) {
			$msg_num = $GLOBALS['__MSG_COUNT__']+1;
			$next_message = '{TEMPLATE: dmsg_next_message}';
		}
		else if ( $pager && $obj->id!=$obj->last_post_id ) {
			$msg_start = $GLOBALS['start']+$GLOBALS['count'];
			$next_message = '{TEMPLATE: dmsg_next_message_next_page}';
		}			
	}	
	
	if( $GLOBALS['t'] == 'tree' && empty($hide_controls) ) {
		if( $pager[0] ) 
			$prev_message = '{TEMPLATE: dmsg_tree_prev_message_prev_page}';
		if( $pager[1] )	
			$next_message = '{TEMPLATE: dmsg_tree_next_message_next_page}';
	}
	
	$msg_bg_color_alt = '{TEMPLATE: msg_bg_color_alt}';
	
	if ( empty($obj->user_id) ) {
		$user_login = $GLOBALS['ANON_NICK'];
		$user_login_td = '{TEMPLATE: dmsg_ignored_user_message_anon}';
	}
	else {
		$user_login = $obj->login;
		$user_login_td = '{TEMPLATE: dmsg_ignored_user_message_regged}';
	}
	
	$link_args = '&mid='.$GLOBALS['mid'].'&'.$_rsid.'&frm_id='.$GLOBALS['frm_id'].'&th='.$GLOBALS['th'].'&start='.$GLOBALS['start'].'&count='.$GLOBALS['count'].'&unread='.$GLOBALS['unread'].'&reply_count='.$GLOBALS['reply_count'].'&date='.$GLOBALS['date'].'#msg_'.$obj->id;
	if ( !empty($GLOBALS['__IGNORE_LIST__'][$obj->poster_id]) && empty($GLOBALS['__REVEALED_POSTS__'][$obj->id]) && empty($GLOBALS['__REVEALED_USERS__'][$obj->poster_id]) ) {
		if ( empty($hide_controls) )	
			return '{TEMPLATE: dmsg_ignored_user_message}';
		else
			return '{TEMPLATE: dmsg_ignored_user_message_static}';
	}

	if( !empty($obj->user_id) ) {
		$disable_avatar = ( $obj->level_pri == 'L' || (_uid && $GLOBALS["usr"]->show_avatars=='N') ) ? 1 : 0;
		
		if( ($GLOBALS['ONLINE_OFFLINE_STATUS'] == 'Y' && $obj->invisible_mode=='N' ) || $GLOBALS["usr"]->is_mod == 'A' ) 
			$online_indicator = (($obj->time_sec+$GLOBALS['LOGEDIN_TIMEOUT']*60) > __request_timestamp__) ? '{TEMPLATE: dmsg_online_indicator}' : '{TEMPLATE: dmsg_offline_indicator}';
		
		if ( !$disable_avatar ) {
			if( $obj->avatar ) 
				$avatar_img = 'images/avatars/'.$obj->avatar;
			else if ( $obj->avatar_approved == 'Y' ) 
				$avatar_img = ($obj->avatar_loc) ? $obj->avatar_loc : 'images/custom_avatars/'.$obj->user_id;
		
			$avatar = ($avatar_img) ? '{TEMPLATE: dmsg_avatar}' : '{TEMPLATE: dmsg_no_avatar}';
		}
		
		$user_link = empty($hide_controls) ? '{TEMPLATE: dmsg_reg_user_link}' : '{TEMPLATE: dmsg_reg_user_no_link}';
		
		$user_posts = '{TEMPLATE: dmsg_user_posts}';
		$user_reg_date = '{TEMPLATE: dmsg_user_reg_date}';

		if ( !empty($obj->location) ) {
			$location = trim_show_len($obj->location);
			$location = '{TEMPLATE: dmsg_location}';
		}
		else $location = '{TEMPLATE: dmsg_no_location}';

		$custom_tag = empty($obj->custom_status) ? '{TEMPLATE: dmsg_no_custom_tags}' : '{TEMPLATE: dmsg_custom_tags}';
	}
	else 
		$user_link = '{TEMPLATE: dmsg_anon_user}';
		
	if ( $GLOBALS["MOD"] || $GLOBALS["DISPLAY_IP"] == 'Y' ) $ip_address = '{TEMPLATE: dmsg_ip_address}';

	if ( $GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' && !empty($obj->host_name) ) {
		$host_name = wordwrap($obj->host_name,30,'<br>',1);
		$host_name = '{TEMPLATE: dmsg_host_name}';
	}
	
	$msg_icon = empty($obj->icon) ? '{TEMPLATE: dmsg_no_msg_icon}' : '{TEMPLATE: dmsg_msg_icon}';
	
	$buddy_link=$ignore_link='';
	if ( isset($GLOBALS['usr']) && $GLOBALS['usr']->id != $obj->user_id && empty($hide_controls) ) {
		if ( $obj->user_id > 0 ) 
			$buddy_link = '{TEMPLATE: dmsg_buddy_link}';
		else 
			$obj->user_id = 0;
		
		if ( $obj->is_mod != 'A' ) {
			if ( !empty($GLOBALS['__IGNORE_LIST__'][$obj->poster_id]) )
				$ignore_link = '{TEMPLATE: dmsg_remove_user_ignore_list}';
			else 
				$ignore_link = '{TEMPLATE: dmsg_add_user_ignore_list}';
		}
	}
	
	if ( $obj->level_pri ) {
		if( !empty($obj->level_name) ) $level_name = '{TEMPLATE: dmsg_level_name}';
		if( !empty($obj->level_img) && strtolower($obj->level_pri)!='a' ) $level_image = '{TEMPLATE: dmsg_level_image}';
	}	

	if ( empty($hide_controls) ) {
		/* determine IM status */
		if ( $obj->icq ) 	$im_icq =   '{TEMPLATE: dmsg_im_icq}';
		if ( $obj->aim ) 	{ $im_aim = urlencode($obj->aim); $im_aim = '{TEMPLATE: dmsg_im_aim}'; }
		if ( $obj->yahoo ) 	{ $im_yahoo = urlencode($obj->yahoo); $im_yahoo = '{TEMPLATE: dmsg_im_yahoo}'; }
		if ( $obj->msnm ) 	$im_msnm =  '{TEMPLATE: dmsg_im_msnm}';
		if ( $obj->jabber ) 	$im_jabber =  '{TEMPLATE: dmsg_im_jabber}';
	}
	
	
	if( $obj->message_threshold && $obj->length_preview && empty($GLOBALS['__REVEALED_POSTS__'][$obj->id]) && $obj->length > $obj->message_threshold ) {
		$msg_body = read_msg_body($obj->offset_preview, $obj->length_preview, $obj->file_id_preview);
		$msg_body = '{TEMPLATE: dmsg_short_message_body}';
	}
	else if ( $obj->length ) {
		$msg_body = read_msg_body($obj->foff,$obj->length, $obj->file_id);
		$msg_body = '{TEMPLATE: dmsg_normal_message_body}';
	}
	else
		$msg_body = '{TEMPLATE: dmsg_no_msg_body}';
	
	if( !empty($GLOBALS['opt']) && $obj->locked != 'Y' ) {
		register_vote($GLOBALS['opt']);
		$GLOBALS['opt']=NULL;
	}	
	
	$__RESOURCE_ID = $obj->forum_id;
	if ( $obj->poll_id ) {
		$show_res=1;
		
		$poll_obj = db_singleobj(q("SELECT * FROM {SQL_TABLE_PREFIX}poll WHERE id=".$obj->poll_id));

		if( _uid && $GLOBALS["pl_view"] != $obj->poll_id && $obj->locked == 'N' && is_perms(_uid, $__RESOURCE_ID, 'VOTE') && !bq("SELECT id FROM {SQL_TABLE_PREFIX}poll_opt_track WHERE poll_id=".$poll_obj->id." AND user_id="._uid) ) $show_res=0;
		
		/* determine if poll is expired or reach max count */
			
		$res = q("SELECT * FROM {SQL_TABLE_PREFIX}poll_opt WHERE poll_id=".$poll_obj->id);
		$total_votes = q_singleval("SELECT sum(count) FROM {SQL_TABLE_PREFIX}poll_opt WHERE poll_id=".$poll_obj->id." GROUP BY poll_id");
		
		$i=0;
		if ( !$show_res && $poll_obj->max_votes && $total_votes >= $poll_obj->max_votes ) $show_res = 1;
		if ( !$show_res && $poll_obj->expiry_date && ($poll_obj->creation_date + $poll_obj->expiry_date) <= __request_timestamp__ ) $show_res = 1;
		
		$poll_data='';
		while ( $opt = db_rowobj($res) ) {
			$i++;
			if ( $show_res || !empty($hide_controls) ) {
				$length = ( $opt->count ) ? round($opt->count/$total_votes*100) : 0;
				$poll_data .= '{TEMPLATE: dmsg_poll_result}';
			}
			else 
				$poll_data .= '{TEMPLATE: dmsg_poll_option}';
		}
			
		qf($res);
			
		if ( !$show_res && empty($hide_controls) ) {
			if( $total_votes ) $view_poll_results_button = '{TEMPLATE: dmsg_view_poll_results_button}';
			$poll_buttons = '{TEMPLATE: dmsg_poll_buttons}';
		}
		
		$poll = '{TEMPLATE: dmsg_poll}'; 
	}

	if ( $obj->attach_cnt ) {
		$a_result = q("SELECT {SQL_TABLE_PREFIX}attach.id,original_name,dlcount,icon FROM {SQL_TABLE_PREFIX}attach LEFT JOIN {SQL_TABLE_PREFIX}mime ON {SQL_TABLE_PREFIX}attach.mime_type={SQL_TABLE_PREFIX}mime.id WHERE message_id=".$obj->id." AND private='N'");
		if ( db_count($a_result) ) {
			$drawmsg_file_attachments='';
			while ( $a_obj = db_rowobj($a_result) ) {
				if( file_exists($GLOBALS["FILE_STORE"].$a_obj->id.".atch") ) {
					$sz = filesize($GLOBALS["FILE_STORE"].$a_obj->id.".atch")/1024;
					$sz = $sz<1000 ? number_format($sz,2).'KB' : number_format($sz/1024,2).'MB';
					if( empty($a_obj->icon) ) $a_obj->icon = 'unknown.gif';
					$drawmsg_file_attachments .= '{TEMPLATE: dmsg_drawmsg_file_attachment}';	
				}					
			}
			$drawmsg_file_attachments = '{TEMPLATE: dmsg_drawmsg_file_attachments}';
		}
		qf($a_result);	
	}
		
	if ( $obj->update_stamp ) {
		if( $obj->updated_by != $obj->poster_id && $GLOBALS['EDITED_BY_MOD'] == 'Y' ) 
			$modified_message = '{TEMPLATE: dmsg_modified_message_mod}';
		else if ( $obj->updated_by == $obj->poster_id && $GLOBALS['SHOW_EDITED_BY'] == 'Y' ) 
			$modified_message = '{TEMPLATE: dmsg_modified_message}';
	}		
	
	if( empty($hide_controls) ) {
		if ( $GLOBALS["ALLOW_SIGS"] == 'Y' && $obj->show_sig == 'Y' && $GLOBALS["usr"]->show_sigs=='Y' && $obj->sig ) $signature = '{TEMPLATE: dmsg_signature}';
	
		$report_to_mod_link = '{TEMPLATE: dmsg_report_to_mod_link}';
	
		if( $obj->user_id ) {
			$user_profile = '{TEMPLATE: dmsg_user_profile}';
			$encoded_login = urlencode($obj->login);
			if( $GLOBALS["ALLOW_EMAIL"] == 'Y' && $obj->email_messages == 'Y' ) $email_link = '{TEMPLATE: dmsg_email_link}';
			if( $GLOBALS['PM_ENABLED']=='Y' ) $private_msg_link = '{TEMPLATE: dmsg_private_msg_link}';
		}
		
		if( $msg_count && $pager && ($GLOBALS['__MSG_COUNT__']-1) == $msg_count && $obj->id!=$obj->last_post_id ) {
			$page_num = $start+$count;
			$next_page = '{TEMPLATE: dmsg_next_msg_page}';
		}
		else
			$next_page = '{TEMPLATE: dmsg_no_next_msg_page}';
		
		if ( $GLOBALS["MOD"] || is_perms(_uid, $__RESOURCE_ID, 'DEL') ) $delete_link = '{TEMPLATE: dmsg_delete_link}';
		if ( isset($GLOBALS["usr"]) && (_uid == $obj->poster_id || $GLOBALS["MOD"] || is_perms(_uid, $__RESOURCE_ID, 'EDIT')) ) {
			if ( $GLOBALS["MOD"] || is_perms(_uid, $__RESOURCE_ID, 'EDIT') || !$GLOBALS['EDIT_TIME_LIMIT'] || (__request_timestamp__-$obj->post_stamp < $GLOBALS['EDIT_TIME_LIMIT']*60) ) $edit_link = '{TEMPLATE: dmsg_edit_link}';
		}
		
		if( $GLOBALS["MOD"] || $obj->locked=='N' ) {
			$reply_link = '{TEMPLATE: dmsg_reply_link}';
			$quote_link = '{TEMPLATE: dmsg_quote_link}';
		}	
		
		$message_toolbar = '{TEMPLATE: dmsg_message_toolbar}';
	}
	return '{TEMPLATE: message_entry}';
}		
?>