<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: post.php.t,v 1.58 2003/05/16 12:42:31 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function flood_check()
{
	$check_time = __request_timestamp__-$GLOBALS['FLOOD_CHECK_TIME'];
	
	if (($v = q_singleval("SELECT post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE ip_addr='".get_ip()."' AND poster_id="._uid." AND post_stamp>".$check_time." ORDER BY post_stamp DESC LIMIT 1"))) {
		return (($v + $GLOBALS['FLOOD_CHECK_TIME']) - __request_timestamp__);
	}
	
	return;		
}

/*{PRE_HTML_PHP}*/
	
	$pl_id = 0;
	$old_subject = $attach_control_error = '';

	/* redirect user where need be in moderated forums after they've seen
	 * the moderation message.
	 */
	if(isset($_POST['moderated_redr'])) {
		check_return($usr->returnto);
	}

	/* we do this because we don't want to take a chance that data is passed via cookies */
	if (isset($_GET['reply_to']) || isset($_POST['reply_to'])) {
		$reply_to = (int) $_REQUEST['reply_to'];
	} else {
		$reply_to = 0;
	}
	if (isset($_GET['msg_id']) || isset($_POST['msg_id'])) {
		$msg_id = (int) $_REQUEST['msg_id'];
	} else {
		$msg_id = 0;
	}
	if (isset($_GET['th_id']) || isset($_POST['th_id'])) {
		$th_id = (int) $_REQUEST['th_id'];
	} else {
		$th_id = 0;
	}
	if (isset($_GET['frm_id']) || isset($_POST['frm_id'])) {
		$frm_id = (int) $_REQUEST['frm_id'];
	} else {
		$frm_id = 0;
	}

	/* replying or editing a message */
	if ($reply_to || $msg_id) {
		$msg = msg_get(($reply_to ? $reply_to : $msg_id));
	 	$th_id = $msg->thread_id;
	 	$msg->login = q_singleval('SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id='.$msg->poster_id);
	}

	if ($th_id) {
		$thr = db_sab('SELECT t.forum_id, t.replies, t.locked, t.root_msg_id, t.ordertype, t.orderexpiry, m.subject FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE t.id='.$th_id);
		if (!$thr) {
			invl_inp_err();
		}
		$frm_id = $thr->forum_id;
	} else if ($frm_id) {
		$th_id = NULL;
	} else {
		std_error('systemerr');
	}
	$frm = db_sab('SELECT id, name, max_attach_size, tag_style, max_file_attachments, passwd_posting, post_passwd, message_threshold, moderated FROM {SQL_TABLE_PREFIX}forum WHERE id='.$frm_id);
	
	/* fetch permissions & moderation status */
	if (!_uid) {
		$MOD = 0;
		$perms = db_arr_assoc('SELECT p_VISIBLE as p_visible, p_READ as p_read, p_POST as p_post, p_REPLY as p_reply, p_EDIT as p_edit, p_DEL as p_del, p_STICKY as p_sticky, p_POLL as p_poll, p_FILE as p_file, p_VOTE as p_vote, p_RATE as p_rate, p_SPLIT as p_split, p_LOCK as p_lock, p_MOVE as p_move, p_SML as p_sml, p_IMG as p_img FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id=0 AND resource_id='.$frm->id);
	} else if ($usr->is_mod == 'A' || ($usr->is_mod == 'Y' && is_moderator($frm->id, _uid))) {
		$MOD = 1;
		$perms = array('p_visible'=>'Y', 'p_read'=>'Y', 'p_post'=>'Y', 'p_reply'=>'Y', 'p_edit'=>'Y', 'p_del'=>'Y', 'p_sticky'=>'Y', 'p_poll'=>'Y', 'p_file'=>'Y', 'p_vote'=>'Y', 'p_rate'=>'Y', 'p_split'=>'Y', 'p_lock'=>'Y', 'p_move'=>'Y', 'p_sml'=>'Y', 'p_img'=>'Y');
	} else {
		$MOD = 0;
		$perms = db_arr_assoc('SELECT p_VISIBLE as p_visible, p_READ as p_read, p_POST as p_post, p_REPLY as p_reply, p_EDIT as p_edit, p_DEL as p_del, p_STICKY as p_sticky, p_POLL as p_poll, p_FILE as p_file, p_VOTE as p_vote, p_RATE as p_rate, p_SPLIT as p_split, p_LOCK as p_lock, p_MOVE as p_move, p_SML as p_sml, p_IMG as p_img FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id IN('._uid.',2147483647) AND resource_id='.$frm->id.' ORDER BY user_id ASC LIMIT 1');
	}

	/* More Security */
	if (isset($thr) && $perms['p_lock'] != 'Y' && $thr->locked=='Y') {
		error_dialog('{TEMPLATE: post_err_lockedthread_title}', '{TEMPLATE: post_err_lockedthread_msg}');
	}

	if (_uid) {
		/* all sorts of user blocking filters */
		is_allowed_user($usr);
		
		/* if not moderator, validate user permissions */
		if (!$MOD) {
			if (!$reply_to && !$msg_id && $perms['p_post'] != 'Y') {
				std_error('perms');
			} else if (($th_id || $reply_to) && $perms['p_reply'] != 'Y') {
				std_error('perms');
			} else if ($msg_id && $msg->poster_id != $usr->id && $perms['p_edit'] != 'Y') {
				std_error('perms');
			} else if ($msg_id && $EDIT_TIME_LIMIT && ($msg->post_stamp + $EDIT_TIME_LIMIT * 60 <__request_timestamp__)) {
				error_dialog('{TEMPLATE: post_err_edttimelimit_title}', '{TEMPLATE: post_err_edttimelimit_msg}'); 
			}
		}
	} else {
		if (!$th_id && $perms['p_post'] != 'Y') {
			error_dialog('{TEMPLATE: post_err_noannontopics_title}', '{TEMPLATE: post_err_noannontopics_msg}'); 
		} else if ($perms['p_reply'] != 'Y') {
			error_dialog('{TEMPLATE: post_err_noannonposts_title}', '{TEMPLATE: post_err_noannonposts_msg}'); 
		}
	}

	/* Retrieve Message */
	if (!isset($_POST['prev_loaded'])) { 
		if (_uid) {
			if (!$msg_id) {
				$msg_show_sig = $usr->append_sig == 'Y' ? $usr->append_sig : NULL;
			} else {
				$msg_show_sig = $msg->show_sig == 'Y' ? $msg->show_sig : NULL;
			}
			if ($msg_id || $reply_to || $th_id) {
				$msg_poster_notif = is_notified(_uid, $msg->thread_id) ? 'Y' : NULL;
			} else {
				$msg_poster_notif = $usr->notify == 'Y' ? $usr->notify : NULL;
			}
		}
		
		if ($msg_id) {
			$msg_subject = $msg->subject;
			reverse_FMT($msg_subject);
			$msg_subject = apply_reverse_replace($msg_subject);
		
			$msg_body = post_to_smiley($msg->body);
	 		switch ($frm->tag_style) {
	 			case 'ML':
	 				$msg_body = html_to_tags($msg_body);
	 				break;
	 			case 'HTML':
	 				break;
	 			default:
	 				reverse_FMT($msg_body);
	 				reverse_nl2br($msg_body);
	 		}
	 		$msg_body = apply_reverse_replace($msg_body);

	 		$msg_smiley_disabled = $msg->smiley_disabled == 'Y' ? 'Y' : NULL;
			$msg_icon = $msg->icon;

	 		if ($msg->attach_cnt) {
	 			$r = q("SELECT id FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$msg->id." AND private='N'");
	 			while ($fa_id = db_rowarr($r)) {
	 				$attach_list[$fa_id[0]] = $fa_id[0];
	 			}
	 			qf($r);
	 			$attach_count = count($attach_list);
		 	}
		 	$pl_id = (int) $msg->poll_id;	
		} else if ($reply_to || $th_id) {
			$subj = $reply_to ? $msg->subject : $thr->subject;
			reverse_FMT($subj);

			$msg_subject = strncmp('{TEMPLATE: reply_prefix}', $subj, strlen('{TEMPLATE: reply_prefix}')) ? '{TEMPLATE: reply_prefix}' . ' ' . $subj : $subj;
			$old_subject = $msg_subject;

			if (isset($_GET['quote'])) {
				$msg_body = post_to_smiley(str_replace("\r", '', $msg->body));
				
				if (!strlen($msg->login)) {
					$msg->login = $GLOBALS['ANON_NICK'];
				}
				reverse_FMT($msg->login);
				
				switch ($frm->tag_style) {
					case 'ML':
						$msg_body = html_to_tags($msg_body);
						reverse_FMT($msg_body);
				 		$msg_body = '{TEMPLATE: fud_quote}';
				 		break;
					case 'HTML':
						$msg_body = '{TEMPLATE: html_quote}';
						break;
					default:
						reverse_FMT($msg_body);
						reverse_nl2br($msg_body);
						$msg_body = str_replace('<br>', "\n", '{TEMPLATE: plain_quote}');
				}
				$msg_body .= "\n";
			}
		}
	} else { /* $_POST['prev_loaded'] */
		if ($FLOOD_CHECK_TIME && !$MOD && !$msg_id && ($tm = flood_check())) {
			error_dialog('{TEMPLATE: post_err_floodtrig_title}', '{TEMPLATE: post_err_floodtrig_msg}');
		}

		/* import message options */
		$msg_show_sig		= isset($_POST['msg_show_sig']) ? $_POST['msg_show_sig'] : NULL;
		$msg_smiley_disabled	= isset($_POST['msg_smiley_disabled']) ? $_POST['msg_smiley_disabled'] : NULL;
		$msg_poster_notif	= isset($_POST['msg_poster_notif']) ? $_POST['msg_poster_notif'] : NULL;
		$pl_id			= !empty($_POST['pl_id']) ? poll_validate((int)$_POST['pl_id'], $msg_id) : 0;
		$msg_body		= $_POST['msg_body'];
		$msg_subject		= $_POST['msg_subject'];

		if ($perms['p_file'] == 'Y') {
			$attach_count = 0;
			
			/* restore the attachment array */
			if (!empty($_POST['file_array']) ) {
				$attach_list = @unserialize(base64_decode($_POST['file_array']));
				if (($attach_count = count($attach_list))) {
					foreach ($attach_list as $v) {
						if (!$v) {
							--$attach_count;
						}
					}
				}
			}
			
			/* remove file attachment */
			if (!empty($_POST['file_del_opt']) && isset($attach_list[$_POST['file_del_opt']])) {
				$attach_list[$_POST['file_del_opt']] = 0;
				/* Remove any reference to the image from the body to prevent broken images */
				if (strpos($msg_body, '[img]{ROOT}?t=getfile&id='.$_POST['file_del_opt'].'[/img]') !== FALSE) {
					$msg_body = str_replace('[img]{ROOT}?t=getfile&id='.$_POST['file_del_opt'].'[/img]', '', $msg_body);
				}
					
				$attach_count--;
			}	
			
			$MAX_F_SIZE = $frm->max_attach_size * 1024;
			/* newly uploaded files */
			if (isset($_FILES['attach_control']) && $_FILES['attach_control']['size']) {
				if ($_FILES['attach_control']['size'] > $MAX_F_SIZE) {
					$attach_control_error = '{TEMPLATE: post_err_attach_size}';
				} else {
					if (filter_ext($_FILES['attach_control']['name'])) {
						$attach_control_error = '{TEMPLATE: post_err_attach_ext}';
					} else {
						if (($attach_count+1) <= $frm->max_file_attachments) {
							$val = attach_add($_FILES['attach_control'], _uid);
							$attach_list[$val] = $val;
							$attach_count++;
						} else {
							$attach_control_error = '{TEMPLATE: post_err_attach_filelimit}';
						}	
					}	
				}	
			}
			$attach_cnt = $attach_count;
		} else {
			$attach_cnt = 0;
		}
		
		/* removal of a poll */
		if (!empty($_POST['pl_del']) && $pl_id && $perms['p_poll'] == 'Y') {
			poll_delete($pl_id);
			$pl_id = 0;
		}
		
		if ($reply_to && $old_subject == $msg_subject) {
			$no_spell_subject = 1;
		}
				
		if (isset($_POST['btn_spell'])) {
			$GLOBALS['MINIMSG_OPT']['DISABLED'] = 1;
			$text = apply_custom_replace($_POST['msg_body']);
			$text_s = apply_custom_replace($_POST['msg_subject']);
		
			switch ($frm->tag_style) {
				case 'ML':
					$text = tags_to_html($text, $perms['p_img']);
					break;
				case 'HTML':
					break;
				default:
					$text = htmlspecialchars($text);
			}

			if ($perms['p_sml'] == 'Y' && !$msg_smiley_disabled) {
				$text = smiley_to_post($text);
			}

	 		if (strlen($text)) {	
				$wa = tokenize_string($text);
				$msg_body = spell_replace($wa, 'body');
				
				if ($perms['p_sml'] == 'Y' && !$msg_smiley_disabled) {
					$msg_body = post_to_smiley($msg_body);
				}
				if ($frm->tag_style == 'ML' ) {
					$msg_body = html_to_tags($msg_body);
				} else if ($frm->tag_style != 'HTML') {
					reverse_FMT($msg_body);
				}
				
				$msg_body = apply_reverse_replace($msg_body);
			}	
			$wa = '';
			
			if (strlen($_POST['msg_subject']) && empty($no_spell_subject)) {
				$text_s = htmlspecialchars($text_s);
				$wa = tokenize_string($text_s);
				$text_s = spell_replace($wa, 'subject');
				reverse_FMT($text_s);
				$msg_subject = apply_reverse_replace($text_s);
			}
		}
		
		if (!empty($_POST['submitted']) && !isset($_POST['spell']) && !isset($_POST['preview'])) {
			$_POST['btn_submit'] = 1;
		}
		
		if ($usr->is_mod != 'A' && isset($_POST['btn_submit']) && $frm->passwd_posting == 'Y' && (!isset($_POST['frm_passwd']) || $frm->post_passwd != $_POST['frm_passwd'])) {
			set_err('password', '{TEMPLATE: post_err_passwd}');
		}
		
		/* submit processing */
		if (isset($_POST['btn_submit']) && !check_post_form()) {
			$msg_post = new fud_msg_edit;
			
			/* Process Message Data */
			$msg_post->poster_id = _uid;
			$msg_post->poll_id = $pl_id;
			$msg_post->subject = $msg_subject;
			$msg_post->body = $msg_body;
			$msg_post->icon = isset($_POST['msg_icon']) ? $_POST['msg_icon'] : '';
		 	$msg_post->smiley_disabled = $msg_smiley_disabled ? 'Y' : 'N';
		 	$msg_post->show_sig = $msg_show_sig ? 'Y' : 'N';
		 	$msg_post->attach_cnt = (int) $attach_cnt;
			$msg_post->body = apply_custom_replace($msg_post->body);
			
			switch ($frm->tag_style) {
				case 'ML':
					$msg_post->body = tags_to_html($msg_post->body, $perms['p_img']);
					break;
				case 'HTML':
					break;
				default:
					$msg_post->body = nl2br(htmlspecialchars($msg_post->body));
			}
			
	 		if ($perms['p_sml'] == 'Y' && $msg_post->smiley_disabled != 'Y') {
	 			$msg_post->body = smiley_to_post($msg_post->body);
	 		}
	 			
			fud_wordwrap($msg_post->body);
			
			$msg_post->subject = apply_custom_replace($msg_post->subject);
		
		 	/* chose to create thread OR add message OR update message */
		 	
		 	if (!$th_id) {
		 		$create_thread = 1;
		 		$msg_post->add($frm->id, $frm->message_threshold, $frm->moderated, $perms['p_sticky'], $perms['p_lock'], FALSE);
		 	} else if ($th_id && !$msg_id) {
				$msg_post->thread_id = $th_id;
		 		$msg_post->add_reply($reply_to, $th_id, $perms['p_sticky'], $perms['p_lock'], FALSE);
			} else if ($msg_id) {
				$msg_post->id = $msg_id;
				$msg_post->thread_id = $th_id;
				$msg_post->post_stamp = $msg->post_stamp;
				$msg_post->sync(_uid, $frm->id, $frm->message_threshold, $perms['p_sticky'], $perms['p_lock']);
				/* log moderator edit */
			 	if (_uid && _uid != $msg->poster_id) {
			 		logaction($usr->id, 'MSGEDIT', $msg_post->id);
			 	}
			} else {
				std_error('systemerr');
				exit();
			}

			/* write file attachments */
			if ($perms['p_file'] == 'Y' && isset($attach_list)) {
				attach_finalize($attach_list, $msg_post->id);
			}	
			
			if (!$msg_id && ($frm->moderated == 'N' || $MOD)) {
				$msg_post->approve($msg_post->id, TRUE);
			}	
	
			/* deal with notifications */
			if (_uid) {
	 			if (isset($_POST['msg_poster_notif'])) {
	 				thread_notify_add(_uid, $msg_post->thread_id);
	 			} else {
	 				thread_notify_del(_uid, $msg_post->thread_id);
	 			}
			}
			
			/* register a view, so the forum marked as read */
			if (isset($frm) && _uid) {
				user_register_forum_view($frm->id);
			}
			
			/* where to redirect, to the treeview or the flat view 
			 * and consider what to do for a moderated forum
			 */
			if ($frm->moderated == 'Y' && !$MOD) {
				if ($GLOBALS['MODERATED_POST_NOTIFY'] == 'Y') {
					$c = uq('SELECT u.email FROM {SQL_TABLE_PREFIX}mod mm INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=mm.user_id WHERE mm.forum_id='.$frm->id);
					while ($r = db_rowarr($c)) {
						$modl[] = $r[0];
					}
					qf($c);
					if (isset($modl)) {
						send_email($GLOBALS['NOTIFY_FROM'], $modl[], '{TEMPLATE: post_mod_msg_notify_title}', '{TEMPLATE: post_mod_msg_notify_msg}', '');
					}
				}
				$data = file_get_contents($GLOBALS['INCLUDE'].'theme/'.$usr->theme_name.'/usercp.inc');
				$s = strpos($data, '<?php') + 5;
				eval(substr($data, $s, (strrpos($data, '?>') - $s)));
				?>
				{TEMPLATE: moderated_forum_post}
				<?php
				exit;
			} else {
				if ($usr->returnto) {
					if (!strncmp('t=selmsg', $usr->returnto, 8) || !strncmp('/slm/', $usr->returnto, 5)) {
						check_return($usr->returnto);
					}
					$t = ($tmp['t'] == 'tree' || $tmp['t'] == 'msg') ? $tmp['t'] : d_thread_view;
				} else {
					$t = d_thread_view;
				}
				/* redirect the user to their message */
				header('Location: {ROOT}?t='.$t.'&goto='.$msg_post->id.'&'._rsidl);
				exit;
			}
		} /* Form submitted and user redirected to own message */
	} /* $prevloaded is SET, this form has been submitted */
	
	if ($reply_to || $th_id && !$msg_id) {
		ses_update_status($usr->sid, '{TEMPLATE: post_reply_update}', $frm->id, 0);
	} else if ($msg_id) {
		ses_update_status($usr->sid, '{TEMPLATE: post_reply_update}', $frm->id, 0);
	} else  {
		ses_update_status($usr->sid, '{TEMPLATE: post_topic_update}', $frm->id, 0);
	}

	if (isset($_POST['spell'])) {
		$GLOBALS['MINIMSG_OPT']['DISABLED'] = TRUE;
	}

/*{POST_HTML_PHP}*/

	if (!$th_id) {
		$label = '{TEMPLATE: create_thread}';
	} else if ($msg_id) {
		$label = '{TEMPLATE: edit_message}';
	} else {
		$label = '{TEMPLATE: submit_reply}';
	}	

	if ($SPELL_CHECK_ENABLED == 'Y' && function_exists('pspell_config_create') && $usr->pspell_lang) {
		$spell_check_button = '{TEMPLATE: spell_check_button}';
	} else {
		$spell_check_button = '';
	}

	if (isset($_POST['preview']) || isset($_POST['spell'])) {
		$text = apply_custom_replace($_POST['msg_body']);
		$text_s = apply_custom_replace($_POST['msg_subject']);

		switch ($frm->tag_style) {
			case 'ML':
				$text = tags_to_html($text, $perms['p_img']);
				break;
			case 'HTML':
				break;
			default:
				$text = nl2br(htmlspecialchars($text));
		}
			
		if ($perms['p_sml'] == 'Y' && !$msg_smiley_disabled) {
			$text = smiley_to_post($text);
		}
	
		$text_s = htmlspecialchars($text_s);

		$spell = (isset($_POST['spell']) && function_exists('pspell_config_create') && $usr->pspell_lang) ? 1 : 0;
		
		if ($spell && $text) {
			$text = check_data_spell($text, 'body', $usr->pspell_lang);
		}
		fud_wordwrap($text);

		if ($spell && empty($no_spell_subject) && $text_s) {
			$subj = check_data_spell($text_s, 'subject', $usr->pspell_lang);
		} else {
			$subj = $text_s;
		}

		if ($GLOBALS['ALLOW_SIGS'] == 'Y' && isset($msg_show_sig)) {
			if ($msg_id && $msg->poster_id && $msg->poster_id != _uid && !reply_to) {
				$sig = q_singleval('SELECT sig FROM {SQL_TABLE_PREFIX}users WHERE id='.$msg->poster_id);
			} else {
				$sig = $usr->sig;
			}
		
			$signature = $sig ? '{TEMPLATE: signature}' : '';
		} else {
			$signature = '';
		}

		$apply_spell_changes = $spell ? '{TEMPLATE: apply_spell_changes}' : '';

		$preview_message = '{TEMPLATE: preview_message}';
	} else {
		$preview_message = '';
	}

	$post_error = is_post_error() ? '{TEMPLATE: post_error}' : '';
	$loged_in_user = _uid ? '{TEMPLATE: loged_in_user}' : '';

	/* handle password protected forums */
	if ($frm->passwd_posting == 'Y' && $usr->is_mod != 'A') {
		$pass_err = get_err('password');
		$post_password = '{TEMPLATE: post_password}';
	} else {
		$post_password = '';
	}
	
	$msg_subect_err = get_err('msg_subject');
	if (!isset($msg_subject)) {
		$msg_subject = '';
	}
	
	/* handle polls */
	$poll = '';
	if ($perms['p_poll'] == 'Y') {
		if (!$pl_id) {
			$poll = '{TEMPLATE: create_poll}';
		} else if (($poll = db_saq('SELECT id, name FROM {SQL_TABLE_PREFIX}poll WHERE id='.$pl_id))) {
			$poll = '{TEMPLATE: edit_poll}';
		}
	}

	/* sticky/announcment controls */
	if ($perms['p_sticky'] == 'Y' && (!isset($thr) || ($thr->root_msg_id == $msg->id && !$reply_to))) {
		if (!isset($_POST['prev_loaded'])) {
			if (!isset($thr)) {
				$thr_ordertype = $thr_orderexpiry = '';
			} else {
				$thr_ordertype = $thr->ordertype;
				$thr_orderexpiry = $thr->orderexpiry;
			}
		} else {
			$thr_ordertype = isset($_POST['thr_ordertype']) ? $_POST['thr_ordertype'] : '';
			$thr_orderexpiry = isset($_POST['thr_orderexpiry']) ? $_POST['thr_orderexpiry'] : '';
		}

		$thread_type_select = tmpl_draw_select_opt("NONE\nSTICKY\nANNOUNCE", "{TEMPLATE: post_normal}\n{TEMPLATE: post_sticky}\n{TEMPLATE: post_annoncement}", $thr_ordertype, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
		$thread_expiry_select = tmpl_draw_select_opt("1000000000\n3600\n7200\n14400\n28800\n57600\n86400\n172800\n345600\n604800\n1209600\n2635200\n5270400\n10540800\n938131200", "{TEMPLATE: th_expr_never}\n{TEMPLATE: th_expr_one_hr}\n{TEMPLATE: th_expr_three_hr}\n{TEMPLATE: th_expr_four_hr}\n{TEMPLATE: th_expr_eight_hr}\n{TEMPLATE: th_expr_sixteen_hr}\n{TEMPLATE: th_expr_one_day}\n{TEMPLATE: th_expr_two_day}\n{TEMPLATE: th_expr_four_day}\n{TEMPLATE: th_expr_one_week}\n{TEMPLATE: th_expr_two_week}\n{TEMPLATE: th_expr_one_month}\n{TEMPLATE: th_expr_two_month}\n{TEMPLATE: th_expr_four_month}\n{TEMPLATE: th_expr_one_year}", $thr_orderexpiry, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
		
		$admin_options = '{TEMPLATE: admin_options}';
	} else {
		$admin_options = '';
	}

	/* thread locking controls */
	if ($perms['p_lock'] == 'Y') {
		if (!isset($_POST['prev_loaded']) && isset($thr)) {
			$thr_locked_checked = $thr->locked == 'Y' ? ' checked' : '';
		} else if (isset($_POST['prev_loaded'])) {
			$thr_locked_checked = isset($_POST['thr_locked']) ? ' checked' : '';
		} else {
			$thr_locked_checked = '';
		}
		$mod_post_opts = '{TEMPLATE: mod_post_opts}';
	} else {
		$mod_post_opts = '';
	}
	
	/* message icon selection */
	$post_icons = draw_post_icons((isset($_POST['msg_icon']) ? $_POST['msg_icon'] : ''));
	
	/* tool bar icons */
	$fud_code_icons = $frm->tag_style == 'ML' ? '{TEMPLATE: fud_code_icons}' : '';
	
	$post_options = tmpl_post_options($frm);
	$message_err = get_err('msg_body', 1);
	$msg_body = isset($msg_body) ? str_replace("\r", '', $msg_body) : '';
	
	/* handle file attachments */
	if ($perms['p_file'] == 'Y') {
		$file_attachments = draw_post_attachments((isset($attach_list) ? $attach_list : ''), $frm->max_attach_size, $frm->max_file_attachments, $attach_control_error);
	} else {
		$file_attachments = '';
	}

	if (_uid) {
		$msg_poster_notif_check = isset($msg_poster_notif) ? ' checked' : '';
		$msg_show_sig_check = isset($msg_show_sig) ? ' checked' : '';
		$reg_user_options = '{TEMPLATE: reg_user_options}';
	} else {
		$reg_user_options = '';
	}
	
	/* handle smilies */
	if ($perms['p_sml'] == 'Y') {
		$msg_smiley_disabled_check = isset($msg_smiley_disabled) ? ' checked' : '';
		$disable_smileys = '{TEMPLATE: disable_smileys}';
		$post_smilies = draw_post_smiley_cntrl();
	} else {
		$post_smilies = $disable_smileys = '';
	}
	
	

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: POST_PAGE}