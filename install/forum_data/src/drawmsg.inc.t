<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: drawmsg.inc.t,v 1.26 2003/04/08 12:56:54 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/* Handle poll votes if any are present */
function register_vote(&$options, $poll_id, $opt_id, $mid)
{
	/* invalid option */
	if (!isset($options[$opt_id])) {
		return;
	}
	/* already voted */
	if (q_singleval('SELECT is FROM {SQL_TABLE_PREFIX}poll_opt_track WHERE poll_id='.$poll_id.' AND user_id='._uid)) {
		return;
	}

	db_lock('SQL_TABLE_PREFIX}poll_opt_track WRITE');
	q('INSERT INTO {SQL_TABLE_PREFIX}poll_opt_track(poll_id, user_id, poll_opt) VALUES('.$poll_id.', '._uid.', '.$opt_id.')');
	db_unlock();
	
	q('UPDATE {SQL_TABLE_PREFIX}poll_opt SET count=count+1 WHERE id='.$opt_id);
	poll_cache_rebuild($opt_id, $options);
	q('UPDATE {SQL_TABLE_PREFIX}msg SET poll_cache='.strnull(addslashes(@serialize($options))).' WHERE id='.$mid);

	return;
}

/* initialize buddy & ignore list for registered users */
if (_uid) {
	if ($usr->buddy_list) {
		$usr->buddy_list = @unserialize($usr->buddy_list);
	}
	if ($usr->ignore_list) {
		$usr->ignore_list = @unserialize($usr->ignore_list);
	}

	/* make an associated array of ignored users to temporarily 'unhide' */
	if (!empty($_GET['rev'])) {
		$drawmsg_inc_tmp = explode(':', $_GET['rev']);
		foreach($drawmsg_inc_tmp as $v) {
			$GLOBALS['__REVEALED_POSTS__'][$v] = 1;
		}
	} else {
		$_GET['rev'] = '';
	}

	/* make an associated array of ignored users to temporarily 'unhide' */
	if (!empty($_GET['reveal'])) {
		$drawmsg_inc_tmp = explode(':', $_GET['reveal']);
		foreach($drawmsg_inc_tmp as $v) {	
			unset($usr->ignore_list[$v]);
		}
	} else {
		$_GET['reveal'] = '';
	}
} else {
	$_GET['rev'] = $_GET['reveal'] = '';
}

/* Draws a message, needs a message object, user object, permissions array, 
 * flag indicating wether or not to show controls and a variable indicating
 * the number of the current message (needed for cross message pager)
 * last argument can be anything, allowing forms to specify various vars they
 * need to.
 */
function tmpl_drawmsg(&$obj, &$usr, &$perms, $hide_controls, &$m_num, $misc)
{
	/* draw next/prev message controls */
	if (!$hide_controls) {
		/* tree view is a special condition, we only show 1 message per page */
		if ($_REQUEST['t'] != 'tree') {
			$prev_message = $misc[0] ? '{TEMPLATE: dmsg_tree_prev_message_prev_page}' : '';
			$next_message = $misc[1] ? '{TEMPLATE: dmsg_tree_next_message_next_page}' : '';
		} else {
			/* handle previous link */
			if (!$m_num && $obj->id > $obj->root_msg_id) { /* prev link on different page */
				$msg_start = $misc[0] - $misc[1];
				$prev_message = '{TEMPLATE: dmsg_prev_message_prev_page}';
			} else if ($m_num) { /* inline link, same page */
				$msg_num = $m_num - 1;
				$prev_message = '{TEMPLATE: dmsg_prev_message}';
			} else {
				$prev_message = '';
			}

			/* handle next link */
			if ($obj->id < $obj->last_post_id) {
				if ($m_num && !($misc[1] - $n_num - 1)) { /* next page link */
					$msg_start = $misc[0] + $misc[1];
					$next_message = '{TEMPLATE: dmsg_next_message_next_page}';
				} else {
					$msg_num = $m_num + 1;
					$next_message = '{TEMPLATE: dmsg_next_message}';
				}
			} else {
				$next_message = '';
			}
		}
	} else {
		$next_message = $prev_message = '';
	}	

	$msg_bg_color_alt = '{TEMPLATE: msg_bg_color_alt}';

	if (!$obj->user_id) {
		$user_login = $GLOBALS['ANON_NICK'];
		$user_login_td = '{TEMPLATE: dmsg_ignored_user_message_anon}';
	} else {
		$user_login = $obj->login;
		$user_login_td = '{TEMPLATE: dmsg_ignored_user_message_regged}';
	}

	/* check if the message should be ignored and it is not temporarily revelead */
	if (isset($usr->ignore_list[$obj->poster_id]) && !isset($GLOBALS['__REVEALED_POSTS__'][$obj->id])) {
		return !$hide_controls ? '{TEMPLATE: dmsg_ignored_user_message}' : '{TEMPLATE: dmsg_ignored_user_message_static}';
	}
	
	if ($obj->user_id) {
		if ($obj->avatar_loc && $obj->avatar_approved == 'Y' && $usr->show_avatars == 'Y' && $GLOBALS['CUSTOM_AVATARS'] != 'OFF') {
			$avatar = $obj->avatar_loc;
		} else {
			$avatar = '';
		}

		if (($GLOBALS['ONLINE_OFFLINE_STATUS'] == 'Y' && $obj->invisible_mode == 'N') || $GLOBALS["usr"]->is_mod == 'A') {
			$online_indicator = (($obj->time_sec + $GLOBALS['LOGEDIN_TIMEOUT'] * 60) > __request_timestamp__) ? '{TEMPLATE: dmsg_online_indicator}' : '{TEMPLATE: dmsg_offline_indicator}';
		} else {
			$online_indicator = '';
		}

		$user_link = $hide_controls ? '{TEMPLATE: dmsg_reg_user_link}' : '{TEMPLATE: dmsg_reg_user_no_link}';
		
		$user_posts = '{TEMPLATE: dmsg_user_posts}';
		$user_reg_date = '{TEMPLATE: dmsg_user_reg_date}';

		if ($obj->location) {
			if (strlen($obj->location) > $GLOBALS['MAX_LOCATION_SHOW']) {
				$location = substr($obj->location, 0, $GLOBALS['MAX_LOCATION_SHOW']) . '...';
			}
			$location = '{TEMPLATE: dmsg_location}';
		} else {
			$location = '{TEMPLATE: dmsg_no_location}';
		}

		$custom_tag = $obj->custom_status ? '{TEMPLATE: dmsg_no_custom_tags}' : '{TEMPLATE: dmsg_custom_tags}';
	} else {
		$user_link = '{TEMPLATE: dmsg_anon_user}';
	}

	if ($usr->is_mod == 'A' || $GLOBALS['DISPLAY_IP'] == 'Y') {
		$ip_address = '{TEMPLATE: dmsg_ip_address}';
	} else {
		$ip_address = '';
	}

	if ($GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' && $obj->host_name) {
		if (strlen($obj->host_name) > 30) {
			$host_name = wordwrap($obj->host_name, 30, '<br>', 1);
		}
		$host_name = '{TEMPLATE: dmsg_host_name}';
	} else {
		$host_name = '';
	}
	
	$msg_icon = $obj->icon ? '{TEMPLATE: dmsg_no_msg_icon}' : '{TEMPLATE: dmsg_msg_icon}';
	
	if (_uid && _uid != $obj->user_id) {
		$buddy_link	= !isset($usr->buddy_list[$obj->user_id]) ? '' : '';
		$ignore_link	= !isset($usr->ignore_list[$obj->user_id]) ? '{TEMPLATE: dmsg_add_user_ignore_list}' : '{TEMPLATE: dmsg_remove_user_ignore_list}';
	} else {
		$buddy_link = $ignore_link = '';
	}

	if ($obj->level_pri) {
		$level_name = $obj->level_name ? '{TEMPLATE: dmsg_level_name}' : '';
		$level_image = $obj->level_pri != 'a' ? '{TEMPLATE: dmsg_level_image}' : '';
	} else {
			$level_name = $level_image = '';
	}

	/* show im buttons if need be */
	if ($hide_controls && (!_uid || $usr->show_im == 'Y')) {
		$im_icq		= $obj->icq ? '{TEMPLATE: dmsg_im_icq}' : '';
		$im_aim		= $obj->aim ? '{TEMPLATE: dmsg_im_aim}' : '';
		$im_yahoo	= $obj->yahoo ? '{TEMPLATE: dmsg_im_yahoo}' : '';
		$im_msnm	= $obj->msnm ? '{TEMPLATE: dmsg_im_msnm}' : '';
		$im_jabber	= $obj->jabber ? '{TEMPLATE: dmsg_im_jabber}' : '';
		if ($GLOBALS['ENABLE_AFFERO'] == 'Y') { 
			$im_affero = $obj->affero ? '{TEMPLATE: drawmsg_affero_reg}' : '{TEMPLATE: drawmsg_affero_noreg}';
		} else {
			$im_affero = '';
		}
	} else {
		$im_icq = $im_aim = $im_yahoo = $im_msnm = $im_jabber = $im_affero = '';
	}
	
	/* Display message body
	 * If we have message threshold & the entirity of the post has been revelead show a preview
	 * otherwise if the message body exists show an actual body
	 * if there is no body show a 'no-body' message
	 */
	if ($obj->message_threshold && $obj->length_preview && empty($GLOBALS['__REVEALED_POSTS__'][$obj->id]) && $obj->length > $obj->message_threshold) {
		$msg_body = read_msg_body($obj->offset_preview, $obj->length_preview, $obj->file_id_preview);
		$msg_body = '{TEMPLATE: dmsg_short_message_body}';
	} else if ($obj->length) {
		$msg_body = read_msg_body($obj->foff,$obj->length, $obj->file_id);
		$msg_body = '{TEMPLATE: dmsg_normal_message_body}';
	} else {
		$msg_body = '{TEMPLATE: dmsg_no_msg_body}';
	}
	
	/* handle poll votes */
	if (!empty($_GET['poll_opt']) && ($_GET['poll_opt'] = (int)$_GET['poll_opt']) && $obj->locked != 'Y' && $perms['vote'] == 'Y') {
		register_vote($_GET['poll_opt']);
		unset($_GET['poll_opt']);
	}
	
	/* display poll if there is one */
	if ($obj->poll_id) {
		
	
		$show_res = 1;
		
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
			$poll = '{TEMPLATE: mini_dmsg_poll}';
		}
		
		$poll = empty($hide_controls) ? '{TEMPLATE: dmsg_poll}' : '{TEMPLATE: mini_dmsg_poll}';
	}

	if ( $obj->attach_cnt ) {
		$a_result = q("SELECT {SQL_TABLE_PREFIX}attach.id,original_name,dlcount,icon,fsize FROM {SQL_TABLE_PREFIX}attach LEFT JOIN {SQL_TABLE_PREFIX}mime ON {SQL_TABLE_PREFIX}attach.mime_type={SQL_TABLE_PREFIX}mime.id WHERE message_id=".$obj->id." AND private='N'");
		if ( db_count($a_result) ) {
			$drawmsg_file_attachments='';
			while ( $a_obj = db_rowobj($a_result) ) {
				if( file_exists($GLOBALS["FILE_STORE"].$a_obj->id.".atch") ) {
					$sz = $a_obj->fsize/1024;
					$sz = $sz<1000 ? number_format($sz,2).'KB' : number_format($sz/1024,2).'MB';
					if( empty($a_obj->icon) ) $a_obj->icon = 'unknown.gif';
					$drawmsg_file_attachments .= '{TEMPLATE: dmsg_drawmsg_file_attachment}';	
				}					
			}
			$drawmsg_file_attachments = '{TEMPLATE: dmsg_drawmsg_file_attachments}';
		}
		qf($a_result);	
	}
		
	/* Determine if the message was updated and if this needs to be shown */
	if ($obj->update_stamp) {
		if ($obj->updated_by != $obj->poster_id && $GLOBALS['EDITED_BY_MOD'] == 'Y') {
			$modified_message = '{TEMPLATE: dmsg_modified_message_mod}';
		} else if ($obj->updated_by == $obj->poster_id && $GLOBALS['SHOW_EDITED_BY'] == 'Y') {
			$modified_message = '{TEMPLATE: dmsg_modified_message}';
		} else {
			$modified_message = '';
		}
	} else {
		$modified_message = '';
	}
	
	if ($hide_controls) {
		if ($obj->sig && $GLOBALS["ALLOW_SIGS"] == 'Y' && $obj->show_sig == 'Y' && $usr->show_sigs=='Y') {
			$signature = '{TEMPLATE: dmsg_signature}';
		} else {
			$signature = '';
		}
	
		$report_to_mod_link = '{TEMPLATE: dmsg_report_to_mod_link}';
	
		if ($obj->user_id) {
			$user_profile = '{TEMPLATE: dmsg_user_profile}';
			$encoded_login = urlencode($obj->login);
			if( $GLOBALS["ALLOW_EMAIL"] == 'Y' && $obj->email_messages == 'Y' ) $email_link = '{TEMPLATE: dmsg_email_link}';
			if( $GLOBALS['PM_ENABLED']=='Y' ) $private_msg_link = '{TEMPLATE: dmsg_private_msg_link}';
		}
		
		if ($msg_count && $pager && ($GLOBALS['__MSG_COUNT__']-1) == $msg_count && $obj->id!=$obj->last_post_id) {
			$page_num = $start+$count;
			$next_page = '{TEMPLATE: dmsg_next_msg_page}';
		} else {
			$next_page = '{TEMPLATE: dmsg_no_next_msg_page}';
		}
		
		if ( $GLOBALS["MOD"] || is_perms(_uid, $__RESOURCE_ID, 'DEL') ) $delete_link = '{TEMPLATE: dmsg_delete_link}';
		if ( isset($GLOBALS["usr"]) && (_uid == $obj->poster_id || $GLOBALS["MOD"] || is_perms(_uid, $__RESOURCE_ID, 'EDIT')) ) {
			if ( $GLOBALS["MOD"] || is_perms(_uid, $__RESOURCE_ID, 'EDIT') || !$GLOBALS['EDIT_TIME_LIMIT'] || (__request_timestamp__-$obj->post_stamp < $GLOBALS['EDIT_TIME_LIMIT']*60) ) $edit_link = '{TEMPLATE: dmsg_edit_link}';
		}
		
		if( $GLOBALS["MOD"] || $obj->locked=='N' ) {
			$reply_link = '{TEMPLATE: dmsg_reply_link}';
			$quote_link = '{TEMPLATE: dmsg_quote_link}';
		}	
		
		$message_toolbar = '{TEMPLATE: dmsg_message_toolbar}';
	} else {
		$signature = $report_to_mod_link = $next_page = $message_toolbar = '';
	}
	return '{TEMPLATE: message_entry}';
}		
?>