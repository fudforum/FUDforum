<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: drawpmsg.inc.t,v 1.42 2005/03/16 21:12:42 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

$GLOBALS['affero_domain'] = parse_url($GLOBALS['WWW_ROOT']);

function tmpl_drawpmsg($obj, $usr, $mini)
{
	$o1 =& $GLOBALS['FUD_OPT_1'];
	$o2 =& $GLOBALS['FUD_OPT_2'];
	$a = (int) $obj->users_opt;
	$b =& $usr->users_opt;

	if (!$mini) {
		$custom_tag = $obj->custom_status ? '{TEMPLATE: dmsg_custom_tags}' : '{TEMPLATE: dmsg_no_custom_tags}';
		$c = (int) $obj->level_opt;

		if ($obj->avatar_loc && $a & 8388608 && $b & 8192 && $o1 & 28 && !($c & 2)) {
			if (!($c & 1)) {
				$level_name =& $obj->level_name;
				$level_image = $obj->level_img ? '{TEMPLATE: dmsg_level_image}' : '';
			} else {
				$level_name = $level_image = '';
			}
		} else {
			$level_image = $obj->level_img ? '{TEMPLATE: dmsg_level_image}' : '';
			$obj->avatar_loc = '';
			$level_name =& $obj->level_name;
		}
		$avatar = ($obj->avatar_loc || $level_image) ? '{TEMPLATE: dmsg_avatar}' : '';
		$dmsg_tags = ($custom_tag || $level_name) ? '{TEMPLATE: dmsg_tags}' : '';

		if (($o2 & 32 && !($a & 32768)) || $b & 1048576) {
			$obj->login = $obj->alias;
			$online_indicator = (($obj->last_visit + $GLOBALS['LOGEDIN_TIMEOUT'] * 60) > __request_timestamp__) ? '{TEMPLATE: dpmsg_online_indicator}' : '{TEMPLATE: dpmsg_offline_indicator}';
		} else {
			$online_indicator = '';
		}

		$host_name = ($obj->host_name && $o1 & 268435456) ? '{TEMPLATE: dpmsg_host_name}' : '';
		$ip_address = '';

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
		$usr->buddy_list = $usr->buddy_list ? unserialize($usr->buddy_list) : array();
		if ($obj->user_id != _uid && $obj->user_id > 0) {
			$buddy_link = !isset($usr->buddy_list[$obj->user_id]) ? '{TEMPLATE: dpmsg_buddy_link}' : '{TEMPLATE: dpmsg_buddy_link_remove}';
		} else {
			$buddy_link = '';
		}
		/* show im buttons if need be */
		if ($b & 16384) {
			$im_icq		= $obj->icq ? '{TEMPLATE: dpmsg_im_icq}' : '';
			$im_aim		= $obj->aim ? '{TEMPLATE: dpmsg_im_aim}' : '';
			$im_yahoo	= $obj->yahoo ? '{TEMPLATE: dpmsg_im_yahoo}' : '';
			$im_msnm	= $obj->msnm ? '{TEMPLATE: dpmsg_im_msnm}' : '';
			$im_jabber	= $obj->jabber ? '{TEMPLATE: dpmsg_im_jabber}' : '';
			if ($o2 & 2048) {
				$im_affero = $obj->affero ? '{TEMPLATE: drawpmsg_affero_reg}' : '{TEMPLATE: drawpmsg_affero_noreg}';
			} else {
				$im_affero = '';
			}
			$dmsg_im_row = ($im_icq || $im_aim || $im_yahoo || $im_msnm || $im_jabber || $im_affero) ? '{TEMPLATE: dmsg_im_row}' : '';
		} else {
			$dmsg_im_row = '';
		}
		if ($obj->ouser_id != _uid) {
			$user_profile = '{TEMPLATE: dpmsg_user_profile}';
			$email_link = ($o1 & 4194304 && $a & 16) ? '{TEMPLATE: dpmsg_email_link}' : '';
			$private_msg_link = '{TEMPLATE: dpmsg_private_msg_link}';
		} else {
			$user_profile = $email_link = $private_msg_link = '';
		}
		$edit_link = $obj->fldr == 4 ? '{TEMPLATE: dpmsg_edit_link}' : '';
		if ($obj->fldr == 1) {
			$reply_link = '{TEMPLATE: dpmsg_reply_link}';
			$quote_link = '{TEMPLATE: dpmsg_quote_link}';
		} else {
			$reply_link = $quote_link = '';
		}
		$profile_link = '{TEMPLATE: dpmsg_profile_link}';
		$msg_toolbar = '{TEMPLATE: dpmsg_msg_toolbar}';
	} else {
		$dmsg_tags = $dmsg_im_row = $user_profile = $msg_toolbar = $buddy_link = $avatar = $online_indicator = $host_name = $location = $msg_icon = '';
		$profile_link = '{TEMPLATE: dpmsg_profile_no_link}';
	}
	$msg_body = $obj->length ? read_pmsg_body($obj->foff, $obj->length) : '{TEMPLATE: dpmsg_no_msg_body}';

	$file_attachments = '';
	if ($obj->attach_cnt) {
		$c = uq('SELECT a.id, a.original_name, a.dlcount, m.icon, a.fsize FROM {SQL_TABLE_PREFIX}attach a LEFT JOIN {SQL_TABLE_PREFIX}mime m ON a.mime_type=m.id WHERE a.message_id='.$obj->id.' AND attach_opt=1');
		while ($r = db_rowobj($c)) {
			$sz = $r->fsize/1024;
			$sz = $sz<1000 ? number_format($sz, 2).'KB' : number_format($sz / 1024 ,2).'MB';
			if(!$r->icon) {
				$r->icon = 'unknown.gif';
			}
			$file_attachments .= '{TEMPLATE: dpmsg_file_attachment}';
		}
		if ($file_attachments) {
			$file_attachments = '{TEMPLATE: dpmsg_file_attachments}';
			/* append session to getfile */
			if ($o1 & 128 && !isset($_COOKIE[$GLOBALS['COOKIE_NAME']])) {
				$msg_body = str_replace('<img src="index.php?t=getfile', '<img src="index.php?t=getfile&amp;S='.s, $msg_body);
				$tap = 1;
			}
			if ($o2 & 32768 && (isset($tap) || $o2 & 8192)) {
				$pos = 0;
				while (($pos = strpos($msg_body, '<img src="index.php/fa/', $pos)) !== false) {
					$pos = strpos($msg_body, '"', $pos + 11);
					$msg_body = substr_replace($msg_body, _rsid, $pos, 0);
				}
			}
		}
	}

	$signature = ($obj->sig && $o1 & 32768 && $obj->pmsg_opt & 1 && $b & 4096) ? '{TEMPLATE: dpmsg_signature}' : '';

	return '{TEMPLATE: private_message_entry}';
}
?>