<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: drawmsg.inc.t,v 1.50 2003/07/09 10:12:56 hackie Exp $
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
	/* invalid option or previously voted */
	if (!isset($options[$opt_id]) || q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}poll_opt_track WHERE poll_id='.$poll_id.' AND user_id='._uid)) {
		return;
	}

	db_lock('{SQL_TABLE_PREFIX}poll_opt_track WRITE');
	q('INSERT INTO {SQL_TABLE_PREFIX}poll_opt_track(poll_id, user_id, poll_opt) VALUES('.$poll_id.', '._uid.', '.$opt_id.')');
	db_unlock();
	
	q('UPDATE {SQL_TABLE_PREFIX}poll_opt SET count=count+1 WHERE id='.$opt_id);
	q('UPDATE {SQL_TABLE_PREFIX}poll SET total_votes=total_votes+1 WHERE id='.$poll_id);
	poll_cache_rebuild($opt_id, $options);
	q('UPDATE {SQL_TABLE_PREFIX}msg SET poll_cache='.strnull(addslashes(@serialize($options))).' WHERE id='.$mid);

	return 1;
}

$query_type = (empty($_POST['poll_opt']) || !($_POST['poll_opt'] = (int)$_POST['poll_opt']) ? 'uq' : 'q');

/* needed for message threshold & reveling messages */
if (isset($_GET['rev'])) {
	$tmp = explode(':', $_GET['rev']);
	foreach ($tmp as $v) {
		$GLOBALS['__FMDSP__'][$v] = 1;
	}
	if ($GLOBALS['USE_PATH_INFO'] != 'Y') {
		define('reveal_lnk', '&amp;rev=' . $_GET['rev']);
	} else {
		define('reveal_lnk', '/' . $_GET['rev']);
	}
} else {
	define('reveal_lnk', '');
}

/* initialize buddy & ignore list for registered users */
if (_uid) {
	if ($usr->buddy_list) {
		$usr->buddy_list = @unserialize($usr->buddy_list);
	}
	if ($usr->ignore_list) {
		$usr->ignore_list = @unserialize($usr->ignore_list);
	}

	/* handle temporarily un-hidden users */
	if (isset($_GET['reveal'])) {
		$tmp = explode(':', $_GET['reveal']);
		foreach($tmp as $v) {
			if (isset($usr->ignore_list[$v])) {
				$usr->ignore_list[$v] = 0;
			}
		}
		if ($GLOBALS['USE_PATH_INFO'] != 'Y') {
			define('unignore_tmp', '&amp;reveal='.$_GET['reveal']);
		} else {
			define('unignore_tmp', '/' . $_GET['reveal']);
		}
	} else {
		define('unignore_tmp', '');
	}
} else {
	define('unignore_tmp', '');
}

if ($GLOBALS['ENABLE_AFFERO'] == 'Y') {
	$GLOBALS['affero_domain'] = parse_url($WWW_ROOT);
	$GLOBALS['affero_domain'] = $GLOBALS['affero_domain']['host'];
}

if ($GLOBALS['USE_PATH_INFO'] != 'Y') {
	$_SERVER['QUERY_STRING_ENC'] = str_replace('&', '&amp;', $_SERVER['QUERY_STRING']);
} else {
	$_SERVER['QUERY_STRING_ENC'] = $_SERVER['QUERY_STRING'];
}

function make_tmp_unignore_lnk($id)
{
	if ($GLOBALS['USE_PATH_INFO'] != 'Y') {
		if (!isset($_GET['reveal'])) {
			return $_SERVER['QUERY_STRING_ENC'] . '&amp;reveal='.$id;
		} else {
			return str_replace('&amp;reveal='.$_GET['reveal'], unignore_tmp . ':' . $id, $_SERVER['QUERY_STRING_ENC']);
		}
	} else {
		$p = explode('/', substr($GLOBALS['QUERY_STRING_ENC'], 1, -1));
		$p[3] = $_GET['start'];
		if (empty($_GET['reveal'])) {
			if ($p[4] === 'prevloaded') {
				$p[6] = $id; 
			} else {
				$p[5] = $id;
			}
		} else {
			if ($p[4] === 'prevloaded') {
				$p[6] = unignore_tmp . ':' . $id; 
			} else {
				$p[5] = unignore_tmp . ':' . $id;
			}
		}
		return '/' . implode('/', $p) . '/';
	}
}

function make_reveal_link($id)
{
	if ($GLOBALS['USE_PATH_INFO'] != 'Y') {
		if (!isset($GLOBALS['__FMDSP__'])) {
			return $_SERVER['QUERY_STRING_ENC'] . '&amp;rev='.$id;
		} else {
			return str_replace('&amp;rev='.$_GET['rev'], reveal_lnk . ':' . $id, $_SERVER['QUERY_STRING_ENC']);
		}
	} else {
		$p = explode('/', substr($GLOBALS['QUERY_STRING_ENC'], 1, -1));
		$p[3] = $_GET['start'];

		if (!isset($GLOBALS['__FMDSP__'])) {
			if ($p[4] === 'prevloaded') {
				$p[5] = $id; 
			} else {
				$p[4] = $id;
			}
		} else {
			if ($p[4] === 'prevloaded') {
				$p[5] = reveal_lnk . $id; 
			} else {
				$p[4] = reveal_lnk . $id;
			}
		}
		return '/' . implode('/', $p) . '/';
	}
}

/* Draws a message, needs a message object, user object, permissions array, 
 * flag indicating wether or not to show controls and a variable indicating
 * the number of the current message (needed for cross message pager)
 * last argument can be anything, allowing forms to specify various vars they
 * need to.
 */
function tmpl_drawmsg($obj, $usr, $perms, $hide_controls, &$m_num, $misc)
{
	/* draw next/prev message controls */
	if (!$hide_controls && $misc) {
		/* tree view is a special condition, we only show 1 message per page */
		if ($_GET['t'] == 'tree') {
			$prev_message = $misc[0] ? '{TEMPLATE: dmsg_tree_prev_message_prev_page}' : '';
			$next_message = $misc[1] ? '{TEMPLATE: dmsg_tree_next_message_next_page}' : '';
			$next_page = '';
		} else {
			/* handle previous link */
			if (!$m_num && $obj->id > $obj->root_msg_id) { /* prev link on different page */
				$msg_start = $misc[0] - $misc[1];
				$prev_message = '{TEMPLATE: dmsg_prev_message_prev_page}';
			} else if ($m_num) { /* inline link, same page */
				$msg_num = $m_num;
				$prev_message = '{TEMPLATE: dmsg_prev_message}';
			} else {
				$prev_message = '';
			}

			/* handle next link */
			if ($obj->id < $obj->last_post_id) {
				if ($m_num && !($misc[1] - $m_num - 1)) { /* next page link */
					$msg_start = $misc[0] + $misc[1];
					$next_message = '{TEMPLATE: dmsg_next_message_next_page}';
					$next_page = '{TEMPLATE: dmsg_next_msg_page}';
				} else {
					$msg_num = $m_num + 2;
					$next_message = '{TEMPLATE: dmsg_next_message}';
					$next_page = '';
				}
			} else {
				$next_page = $next_message = '';
			}
		}
		$m_num++;
	} else {
		$next_page = $next_message = $prev_message = '';
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
	if ($usr->ignore_list && !empty($usr->ignore_list[$obj->poster_id]) && !isset($GLOBALS['__FMDSP__'][$obj->id])) {
		$rev_url = make_reveal_link($obj->id);
		$un_ignore_url = make_tmp_unignore_lnk($obj->poster_id);
		return !$hide_controls ? '{TEMPLATE: dmsg_ignored_user_message}' : '{TEMPLATE: dmsg_ignored_user_message_static}';
	}
	
	if ($obj->user_id) {
		if (!$hide_controls) {
			if ($obj->avatar_loc && $obj->avatar_approved == 'Y' && $usr->show_avatars == 'Y' && $GLOBALS['CUSTOM_AVATARS'] != 'OFF' && $obj->level_pri != 'L') {
				$avatar = '{TEMPLATE: dmsg_avatar}';
			} else {
				$avatar = '{TEMPLATE: dmsg_no_avatar}';
			}

			if (($GLOBALS['ONLINE_OFFLINE_STATUS'] == 'Y' && $obj->invisible_mode == 'N') || $usr->is_mod == 'A') {
				$online_indicator = (($obj->time_sec + $GLOBALS['LOGEDIN_TIMEOUT'] * 60) > __request_timestamp__) ? '{TEMPLATE: dmsg_online_indicator}' : '{TEMPLATE: dmsg_offline_indicator}';
			} else {
				$online_indicator = '';
			}
			$user_link = '{TEMPLATE: dmsg_reg_user_link}';
			$user_posts = '{TEMPLATE: dmsg_user_posts}';
			$user_reg_date = '{TEMPLATE: dmsg_user_reg_date}';

			if ($obj->location) {
				if (strlen($obj->location) > $GLOBALS['MAX_LOCATION_SHOW']) {
					$location = substr($obj->location, 0, $GLOBALS['MAX_LOCATION_SHOW']) . '...';
				} else {
					$location = $obj->location;
				}
				$location = '{TEMPLATE: dmsg_location}';
			} else {
				$location = '{TEMPLATE: dmsg_no_location}';
			}
			$custom_tag = $obj->custom_status ? '{TEMPLATE: dmsg_custom_tags}' : '{TEMPLATE: dmsg_no_custom_tags}';

			if (_uid && _uid != $obj->user_id) {
				$buddy_link	= !isset($usr->buddy_list[$obj->user_id]) ? '{TEMPLATE: dmsg_buddy_link_add}' : '{TEMPLATE: dmsg_buddy_link_remove}';
				$ignore_link	= !isset($usr->ignore_list[$obj->user_id]) ? '{TEMPLATE: dmsg_add_user_ignore_list}' : '{TEMPLATE: dmsg_remove_user_ignore_list}';
			} else {
				$buddy_link = $ignore_link = '';
			}
			if ($obj->level_pri) {
				$level_name = $obj->level_name ? '{TEMPLATE: dmsg_level_name}' : '';
				$level_image = ($obj->level_pri != 'A' && $obj->level_img) ? '{TEMPLATE: dmsg_level_image}' : '';
			} else {
				$level_name = $level_image = '';
			}
			/* show im buttons if need be */
			if ($usr->show_im == 'Y') {
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
		 } else {
		 	$user_link = '{TEMPLATE: dmsg_reg_user_no_link}';
		 	$im_icq = $im_aim = $im_yahoo = $im_msnm = $im_jabber = $im_affero = $level_name = $level_image = $buddy_link = $ignore_link = $location = $custom_tag = $user_reg_date = $online_indicator = $user_posts = $user_reg_date = $avatar = '';
		 }
	} else {
		$user_link = '{TEMPLATE: dmsg_anon_user}';
		$im_icq = $im_aim = $im_yahoo = $im_msnm = $im_jabber = $im_affero = $level_name = $level_image = $buddy_link = $ignore_link = $location = $custom_tag = $user_reg_date = $online_indicator = $user_posts = $user_reg_date = $avatar = '';
	}

	/* Display message body
	 * If we have message threshold & the entirity of the post has been revelead show a preview
	 * otherwise if the message body exists show an actual body
	 * if there is no body show a 'no-body' message
	 */
	if (!$hide_controls && $obj->message_threshold && $obj->length_preview && $obj->length > $obj->message_threshold && !isset($GLOBALS['__FMDSP__'][$obj->id])) {
		$rev_url = make_reveal_link($obj->id);
		$msg_body = read_msg_body($obj->offset_preview, $obj->length_preview, $obj->file_id_preview);
		$msg_body = '{TEMPLATE: dmsg_short_message_body}';
	} else if ($obj->length) {
		$msg_body = read_msg_body($obj->foff, $obj->length, $obj->file_id);
		$msg_body = '{TEMPLATE: dmsg_normal_message_body}';
	} else {
		$msg_body = '{TEMPLATE: dmsg_no_msg_body}';
	}

	if ($obj->poll_cache) {
		$obj->poll_cache = @unserialize($obj->poll_cache);
	}
	
	/* handle poll votes */
	if (!empty($_POST['poll_opt']) && ($_POST['poll_opt'] = (int)$_POST['poll_opt']) && $obj->locked == 'N' && $perms['p_vote'] == 'Y') {
		if (register_vote($obj->poll_cache, $obj->poll_id, $_POST['poll_opt'], $obj->id)) {
			$obj->total_votes += 1;
			$obj->cant_vote = 1;
		}
		unset($_GET['poll_opt']);
	}
	
	/* display poll if there is one */
	if ($obj->poll_id && $obj->poll_cache) {
		/* we need to determine if we allow the user to vote or see poll results */
		$show_res = 1;

		/* various conditions that may prevent poll voting */		
		if (!$hide_controls && !$obj->cant_vote && (!isset($_POST['pl_view']) || $_POST['pl_view'] != $obj->poll_id)) {
			if ($perms['p_vote'] == 'Y' && ($obj->locked == 'N' || $perms['p_lock'] == 'Y')) {
				if (!$obj->expiry_date || ($obj->creation_date + $obj->expiry_date) > __request_timestamp__) {
					/* check if the max # of poll votes was reached */
					if (!$obj->max_votes || $obj->total_votes < $obj->max_votes) {
						$show_res = 0;
					}
				}
			}
		}
		
		$i = 0;
		
		$poll_data = '';
		foreach ($obj->poll_cache as $k => $v) {
			$i++;
			if ($show_res) {
				$length = ($v[1] && $obj->total_votes) ? round($v[1] / $obj->total_votes * 100) : 0;
				$poll_data .= '{TEMPLATE: dmsg_poll_result}';	
			} else {
				$poll_data .= '{TEMPLATE: dmsg_poll_option}';
			}
		}
		
		if (!$show_res) {
			$view_poll_results_button = $obj->total_votes ? '{TEMPLATE: dmsg_view_poll_results_button}' : '';
			$poll_buttons = '{TEMPLATE: dmsg_poll_buttons}';
			$poll = '{TEMPLATE: dmsg_poll}';
		} else {
			$poll = '{TEMPLATE: mini_dmsg_poll}';
		}
	} else {
		$poll = '';
	}

	/* draw file attachments if there are any */
	$drawmsg_file_attachments = '';
	if ($obj->attach_cnt && !empty($obj->attach_cache)) {
		$atch = @unserialize($obj->attach_cache);
		if (is_array($atch) && count($atch)) {
			foreach ($atch as $v) {
				$sz = $v[2] / 1024;
				$sz = $sz < 1000 ? number_format($sz, 2).'KB' : number_format($sz/1024, 2).'MB';
				$drawmsg_file_attachments .= '{TEMPLATE: dmsg_drawmsg_file_attachment}';
			}
			$drawmsg_file_attachments = '{TEMPLATE: dmsg_drawmsg_file_attachments}';
		}
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
	
	if (!$hide_controls) {
		if ($usr->is_mod == 'A' || $GLOBALS['DISPLAY_IP'] == 'Y') {
			$ip_address = '{TEMPLATE: dmsg_ip_address}';
		} else {
			$ip_address = '';
		}

		if ($obj->host_name && $GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y') {
			if (strlen($obj->host_name) > 30) {
				$obj->host_name = wordwrap($obj->host_name, 30, '<br />', 1);
			}
			$host_name = '{TEMPLATE: dmsg_host_name}';
		} else {
			$host_name = '';
		}
	
		$msg_icon = !$obj->icon ? '{TEMPLATE: dmsg_no_msg_icon}' : '{TEMPLATE: dmsg_msg_icon}';

		if ($obj->sig && $GLOBALS['ALLOW_SIGS'] == 'Y' && $obj->show_sig == 'Y' && $usr->show_sigs == 'Y') {
			$signature = '{TEMPLATE: dmsg_signature}';
		} else {
			$signature = '';
		}

		$report_to_mod_link = '{TEMPLATE: dmsg_report_to_mod_link}';

		if ($obj->user_id) {
			$user_profile = '{TEMPLATE: dmsg_user_profile}';
			$email_link = ($GLOBALS['ALLOW_EMAIL'] == 'Y' && $obj->email_messages == 'Y') ? '{TEMPLATE: dmsg_email_link}' : '';
			$private_msg_link = $GLOBALS['PM_ENABLED'] == 'Y' ? '{TEMPLATE: dmsg_private_msg_link}' : '';
		} else {
			$user_profile = $email_link = $private_msg_link = '';
		}

		/* little trick, this variable will only be avaliable if we have a next link leading to another page */
		if (isset($next_page)) {
			$next_page = '{TEMPLATE: dmsg_no_next_msg_page}';
		}

		$delete_link = $perms['p_del'] == 'Y' ? '{TEMPLATE: dmsg_delete_link}' : '';

		if ($perms['p_edit'] == 'Y' || (_uid == $obj->poster_id && (!$GLOBALS['EDIT_TIME_LIMIT'] || __request_timestamp__ - $obj->post_stamp < $GLOBALS['EDIT_TIME_LIMIT'] * 60))) {
			$edit_link = '{TEMPLATE: dmsg_edit_link}';
		} else {
			$edit_link = '';
		}

		if ($obj->locked == 'N' || $perms['p_lock'] == 'Y') {
			$reply_link = '{TEMPLATE: dmsg_reply_link}';
			$quote_link = '{TEMPLATE: dmsg_quote_link}';
		} else {
			$reply_link = $quote_link = '';
		}
		
		$message_toolbar = '{TEMPLATE: dmsg_message_toolbar}';
	} else {
		$msg_icon = $ip_address = $host_name = $signature = $report_to_mod_link = $message_toolbar = '';
	}

	return '{TEMPLATE: message_entry}';
}		
?>