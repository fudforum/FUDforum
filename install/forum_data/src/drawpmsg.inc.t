<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: drawpmsg.inc.t,v 1.15 2003/04/18 12:22:06 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

$GLOBALS['affero_domain'] = parse_url($GLOBALS['WWW_ROOT']);

function tmpl_drawpmsg(&$obj, &$usr, $mini)
{
	if (!$mini) {
		if ($obj->avatar_loc && $obj->avatar_approved == 'Y' && $usr->show_avatars == 'Y' && $GLOBALS['CUSTOM_AVATARS'] != 'OFF') {
			$avatar = '{TEMPLATE: dpmsg_avatar}';
		} else {
			$avatar = '{TEMPLATE: dpmsg_no_avatar}';
		}
		if (($GLOBALS['ONLINE_OFFLINE_STATUS'] == 'Y' && $obj->invisible_mode == 'N') || $usr->is_mod == 'A') {
			$obj->login = $obj->alias;
			$online_indicator = (($obj->last_visit + $GLOBALS['LOGEDIN_TIMEOUT'] * 60) > __request_timestamp__) ? '{TEMPLATE: dpmsg_online_indicator}' : '{TEMPLATE: dpmsg_offline_indicator}';
		} else {
			$online_indicator = '';
		}
		if ($GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' && $obj->host_name) {
			if (strlen($host_name) > 30) {
				$host_name = wordwrap($obj->host_name, 30, '<br>', 1);
			}
			$host_name = '{TEMPLATE: dpmsg_host_name}';
		} else {
			$host_name = '';
		}
		if ($obj->location) {
			if (strlen($obj->location) > $GLOBALS['MAX_LOCATION_SHOW']) {
				$location = substr($obj->location, 0, $GLOBALS['MAX_LOCATION_SHOW']) . '...';
			} else {
				$location = $obj->location;
			}
			$location = '{TEMPLATE: dpmsg_location}';
		} else {
			$location = '{TEMPLATE: dpmsg_no_location}';
		}
		$msg_icon = !$obj->icon ? '{TEMPLATE: dpmsg_no_msg_icon}' : '{TEMPLATE: dpmsg_msg_icon}';
		$custom_tag = !$obj->custom_status ? '{TEMPLATE: dpmsg_no_custom_tags}' : '{TEMPLATE: dpmsg_custom_tags}';
		$usr->buddy_list = @unserialize($usr->buddy_list);
		if ($obj->user_id != _uid && $obj->user_id > 0) {
			$buddy_link = !isset($usr->buddy_list[$obj->user_id]) ? '{TEMPLATE: dpmsg_buddy_link}' : '{TEMPLATE: dpmsg_buddy_link_remove}';
		} else {
			$buddy_link = '';
		}
		if ($obj->level_pri) {
			$level_name = $obj->level_name ? '{TEMPLATE: dpmsg_level_name}' : '';
			$level_image = ($obj->level_pri != 'a' && $obj->level_img) ? '{TEMPLATE: dpmsg_level_image}' : '';
		} else {
			$level_name = $level_image = '';
		}
		/* show im buttons if need be */
		if ($usr->show_im == 'Y') {
			$im_icq		= $obj->icq ? '{TEMPLATE: dpmsg_im_icq}' : '';
			$im_aim		= $obj->aim ? '{TEMPLATE: dpmsg_im_aim}' : '';
			$im_yahoo	= $obj->yahoo ? '{TEMPLATE: dpmsg_im_yahoo}' : '';
			$im_msnm	= $obj->msnm ? '{TEMPLATE: dpmsg_im_msnm}' : '';
			$im_jabber	= $obj->jabber ? '{TEMPLATE: dpmsg_im_jabber}' : '';
			if ($GLOBALS['ENABLE_AFFERO'] == 'Y') { 
				$im_affero = $obj->affero ? '{TEMPLATE: drawpmsg_affero_reg}' : '{TEMPLATE: drawpmsg_affero_noreg}';
			} else {
				$im_affero = '';
			}	
		} else {
			$im_icq = $im_aim = $im_yahoo = $im_msnm = $im_jabber = $im_affero = '';
		}
		if ($obj->ouser_id != _uid) {
			$user_profile = '{TEMPLATE: dpmsg_user_profile}';
			$email_link = ($GLOBALS['ALLOW_EMAIL'] == 'Y' && $obj->email_messages == 'Y') ? '{TEMPLATE: dpmsg_email_link}' : '';
			$private_msg_link = '{TEMPLATE: dpmsg_private_msg_link}';
		} else {
			$user_profile = $email_link = $private_msg_link = '';
		}
		$edit_link = $obj->folder_id == 'DRAFT' ? '{TEMPLATE: dpmsg_edit_link}' : '';
		if ($obj->folder_id == 'INBOX') {
			$reply_link = '{TEMPLATE: dpmsg_reply_link}';
			$quote_link = '{TEMPLATE: dpmsg_quote_link}';
		} else {
			$reply_link = $quote_link = '';
		}
		$profile_link = '{TEMPLATE: dpmsg_profile_link}';
		$msg_toolbar = '{TEMPLATE: dpmsg_msg_toolbar}';
	} else {
		$user_profile = $msg_toolbar = $level_name = $level_image = $im_icq = $im_aim = $im_yahoo = $im_msnm = $im_jabber = $im_affero = $buddy_link = $custom_tag = $avatar = $online_indicator = $host_name = $location = $msg_icon = '';
	}
	$msg_body = $obj->length ? read_pmsg_body($obj->foff, $obj->length) : '{TEMPLATE: dpmsg_no_msg_body}';
	
	$file_attachments = '';
	if ($obj->attach_cnt) {
		$c = uq('SELECT a.id, a.original_name, a.dlcount, m.icon, a.fsize FROM {SQL_TABLE_PREFIX}attach a LEFT JOIN {SQL_TABLE_PREFIX}mime m ON a.mime_type=m.id WHERE a.message_id='.$obj->id.' AND private=\'Y\'');
		while ($r = db_rowobj($c)) {
			$sz = $r->fsize/1024;
			$sz = $sz<1000 ? number_format($sz, 2).'KB' : number_format($sz / 1024 ,2).'MB';
			if(!$r->icon) {
				$r->icon = 'unknown.gif';
			}
			$file_attachments .= '{TEMPLATE: dpmsg_file_attachment}';
		}
		qf($c);
		if ($file_attachments) {
			$file_attachments = '{TEMPLATE: dpmsg_file_attachments}';
		}
	}

	if ($GLOBALS['ALLOW_SIGS'] == 'Y' && $obj->show_sig == 'Y' && $usr->show_sigs == 'Y' && $obj->sig) {
		$signature = '{TEMPLATE: dpmsg_signature}';
	} else {
		$signature = '';
	}

	return '{TEMPLATE: private_message_entry}';
}		
?>