<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: post.php.t,v 1.153 2006/09/05 13:16:49 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License.
**/

function flood_check()
{
	$check_time = __request_timestamp__-$GLOBALS['FLOOD_CHECK_TIME'];

	if (($v = q_singleval("SELECT post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE ip_addr='".get_ip()."' AND poster_id="._uid." AND post_stamp>".$check_time." ORDER BY post_stamp DESC LIMIT 1"))) {
		return ($v - $check_time);
	}

	return;
}

/*{PRE_HTML_PHP}*/

	$pl_id = 0;
	$old_subject = $attach_control_error = '';

	/* redirect user where need be in moderated forums after they've seen the moderation message. */
	if (isset($_POST['moderated_redr'])) {
		check_return($usr->returnto);
	}

	/* we do this because we don't want to take a chance that data is passed via cookies */
	$src = empty($_POST) ? '_GET' : '_POST';
	foreach (array('reply_to','msg_id','th_id','frm_id') as $v) {
		$$v = isset(${$src}[$v]) ? (int) ${$src}[$v] : 0;
	}

	/* replying or editing a message */
	if ($reply_to || $msg_id) {
		if (($msg = db_sab('SELECT * FROM {SQL_TABLE_PREFIX}msg WHERE id='.($reply_to ? $reply_to : $msg_id)))) {
			$msg->body = read_msg_body($msg->foff, $msg->length, $msg->file_id);
		} else {
			error_dialog('{TEMPLATE: imsg_err_message_title}', '{TEMPLATE: imsg_err_message_msg}');
		}
	 	$th_id = $msg->thread_id;
	 	$msg->login = q_singleval('SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id='.$msg->poster_id);
	}

	if ($th_id) {
		$thr = db_sab('SELECT t.forum_id, t.replies, t.thread_opt, t.root_msg_id, t.orderexpiry, m.subject FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id WHERE t.id='.$th_id);
		if (!$thr) {
			invl_inp_err();
		}
		$frm_id = $thr->forum_id;
	} else if ($frm_id) {
		$thr = $th_id = null;
	} else {
		std_error('systemerr');
	}
	$frm = db_sab('SELECT id, name, max_attach_size, forum_opt, max_file_attachments, post_passwd, message_threshold FROM {SQL_TABLE_PREFIX}forum WHERE id='.$frm_id);
	if (!$frm) {
		std_error('systemerr');
	}
	$frm->forum_opt = (int) $frm->forum_opt;

	/* fetch permissions & moderation status */
	$MOD = (int) ($is_a || ($usr->users_opt & 524288 && q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}mod WHERE user_id='._uid.' AND forum_id='.$frm->id)));
	$perms = perms_from_obj(db_sab('SELECT group_cache_opt, '.$MOD.' as md FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id IN('._uid.',2147483647) AND resource_id='.$frm->id.' ORDER BY user_id ASC LIMIT 1'), $is_a);

	/* More Security */
	if ($thr && !($perms & 4096) && $thr->thread_opt & 1) {
		error_dialog('{TEMPLATE: post_err_lockedthread_title}', '{TEMPLATE: post_err_lockedthread_msg}');
	}

	if (_uid) {
		/* all sorts of user blocking filters */
		is_allowed_user($usr);

		/* if not moderator, validate user permissions */
		if (!$reply_to && !$msg_id && !($perms & 4)) {
			std_error('perms');
		} else if (!$msg_id && ($th_id || $reply_to) && !($perms & 8)) {
			std_error('perms');
		} else if ($msg_id && $msg->poster_id != $usr->id && !($perms & 16)) {
			std_error('perms');
		} else if ($msg_id && $EDIT_TIME_LIMIT && !$MOD && ($msg->post_stamp + $EDIT_TIME_LIMIT * 60 <__request_timestamp__)) {
			error_dialog('{TEMPLATE: post_err_edttimelimit_title}', '{TEMPLATE: post_err_edttimelimit_msg}');
		} else if ($msg_id && !$MOD && $frm->forum_opt & 2) {
			error_dialog('{TEMPLATE: post_err_mod_forum_edit_ttl}', '{TEMPLATE: post_err_mod_forum_edit_msg}');
		}
	} else {
		if (__fud_real_user__) {
			is_allowed_user($usr);
		}

		if (!$th_id && !($perms & 4)) {
			ses_anonuser_auth($usr->sid, '{TEMPLATE: post_err_noannontopics_msg}');
		} else if ($reply_to && !($perms & 8)) {
			ses_anonuser_auth($usr->sid, '{TEMPLATE: post_err_noannonposts_msg}');
		} else if (($msg_id && !($perms & 16)) || is_ip_blocked(get_ip())) {
			invl_inp_err();
		}
	}

	if (isset($_GET['prev_loaded'])) {
		$_POST['prev_loaded'] = $_GET['prev_loaded'];
	}

	$attach_list = array();
	$msg_smiley_disabled = $msg_subject = $msg_body = '';

	/* Retrieve Message */
	if (!isset($_POST['prev_loaded'])) {
		if (_uid) {
			$msg_show_sig = !$msg_id ? ($usr->users_opt & 2048) : ($msg->msg_opt & 1);

			if ($msg_id || $reply_to) {
				$msg_poster_notif = (($usr->users_opt & 2) && !q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$msg->thread_id.' AND poster_id='._uid)) || is_notified(_uid, $msg->thread_id);
			} else {
				$msg_poster_notif = ($usr->users_opt & 2);
			}
		}

		if ($msg_id) {
			$msg_subject = apply_reverse_replace(reverse_fmt($msg->subject));

			$msg_body = post_to_smiley($msg->body);
	 		if ($frm->forum_opt & 16) {
	 			$msg_body = html_to_tags($msg_body);
	 		} else if ($frm->forum_opt & 8) {
	 			$msg_body = reverse_nl2br(reverse_fmt($msg_body));
	 		}
	 		$msg_body = apply_reverse_replace($msg_body);

	 		$msg_smiley_disabled = ($msg->msg_opt & 2);
			$_POST['msg_icon'] = $msg->icon;

	 		if ($msg->attach_cnt) {
	 			$r = q('SELECT id FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$msg->id.' AND attach_opt=0');
	 			while ($fa_id = db_rowarr($r)) {
	 				$attach_list[$fa_id[0]] = $fa_id[0];
	 			}
	 			unset($r);
	 			$attach_count = count($attach_list);
		 	}
		 	$pl_id = (int) $msg->poll_id;
		} else if ($reply_to || $th_id) {
			$subj = reverse_fmt($reply_to ? $msg->subject : $thr->subject);

			$msg_subject = strncmp('{TEMPLATE: reply_prefix}', $subj, strlen('{TEMPLATE: reply_prefix}')) ? '{TEMPLATE: reply_prefix}' . ' ' . $subj : $subj;
			$old_subject = $msg_subject;

			if (isset($_GET['quote']) && $reply_to) {
				$msg_body = post_to_smiley(str_replace("\r", '', $msg->body));

				if (!strlen($msg->login)) {
					$msg->login =& $ANON_NICK;
				}
				$msg->login = reverse_fmt($msg->login);

				if ($frm->forum_opt & 16) {
					$msg_body = html_to_tags($msg_body);
				 	$msg_body = '{TEMPLATE: fud_quote}';
				} else if ($frm->forum_opt & 8) {
					$msg_body = "> ".str_replace("\n", "\n> ", reverse_nl2br(reverse_fmt($msg_body)));
					$msg_body = str_replace('<br />', "\n", '{TEMPLATE: plain_quote}');
				} else {
					$msg_body = '{TEMPLATE: html_quote}';
				}
				$msg_body .= "\n";
			}
		}
		$GLOBALS['MINIMSG_OPT_DISABLED'] = 0;
	} else { /* $_POST['prev_loaded'] */
		if ($FLOOD_CHECK_TIME && !$MOD && !$msg_id && ($tm = flood_check())) {
			error_dialog('{TEMPLATE: post_err_floodtrig_title}', '{TEMPLATE: post_err_floodtrig_msg}');
		}

		/* import message options */
		$msg_show_sig		= isset($_POST['msg_show_sig']) ? (string)$_POST['msg_show_sig'] : '';
		$msg_smiley_disabled	= isset($_POST['msg_smiley_disabled']) ? (string)$_POST['msg_smiley_disabled'] : '';
		$msg_poster_notif	= isset($_POST['msg_poster_notif']) ? (string)$_POST['msg_poster_notif'] : '';
		$pl_id			= !empty($_POST['pl_id']) ? poll_validate((int)$_POST['pl_id'], $msg_id) : 0;
		$msg_body		= isset($_POST['msg_body']) ? (string)$_POST['msg_body'] : '';
		$msg_subject		= isset($_POST['msg_subject']) ? (string)$_POST['msg_subject'] : '';

		if ($perms & 256) {
			$attach_count = 0;

			/* restore the attachment array */
			if (!empty($_POST['file_array'])) {
				if ($usr->data === md5($_POST['file_array'])) {
					if (($attach_list = unserialize(base64_decode($_POST['file_array'])))) {
						foreach ($attach_list as $v) {
							if ($v) {
								$attach_count++;
							}
						}
					}
				} else if ($msg_id) { /* if checksum fails and we're editing a message, get attachment data from db */
					$r = q('SELECT id FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$msg_id.' AND attach_opt=0');
		 			while ($fa_id = db_rowarr($r)) {
		 				$attach_list[$fa_id[0]] = $fa_id[0];
	 				}
	 				unset($r);
	 				$attach_count = count($attach_list);
				}
			}

			/* remove file attachment */
			if (!empty($_POST['file_del_opt']) && isset($attach_list[$_POST['file_del_opt']])) {
				$attach_list[$_POST['file_del_opt']] = 0;
				/* Remove any reference to the image from the body to prevent broken images */
				if (strpos($msg_body, '[img]{ROOT}?t=getfile&id='.$_POST['file_del_opt'].'[/img]') !== false) {
					$msg_body = str_replace('[img]{ROOT}?t=getfile&id='.$_POST['file_del_opt'].'[/img]', '', $msg_body);
				}
				if (strpos($msg_body, '[img]{FULL_ROOT}{ROOT}?t=getfile&id='.$_POST['file_del_opt'].'[/img]') !== false) {
					$msg_body = str_replace('[img]{FULL_ROOT}{ROOT}?t=getfile&id='.$_POST['file_del_opt'].'[/img]', '', $msg_body);
				}
				$attach_count--;
			}

			if ($frm->forum_opt & 32 && $MOD) {
				$frm->max_attach_size = (int) ini_get('upload_max_filesize');
				$t = str_replace($frm->max_attach_size, '', ini_get('upload_max_filesize'));
				if ($t == 'M' || $t == 'm') {
					$frm->max_attach_size *= 1024;
				}
				$frm->max_file_attachments = 100;
			}
			$MAX_F_SIZE = $frm->max_attach_size * 1024;

			/* newly uploaded files */
			if (isset($_FILES['attach_control']) && $_FILES['attach_control']['size']) {
				if ($_FILES['attach_control']['size'] > $MAX_F_SIZE) {
					$attach_control_error = '{TEMPLATE: post_err_attach_size}';
				} else {
					if (!($MOD && $frm->forum_opt & 32) && filter_ext($_FILES['attach_control']['name'])) {
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
		if (!empty($_POST['pl_del']) && $pl_id && $perms & 128) {
			poll_delete($pl_id);
			$pl_id = 0;
		}

		$no_spell_subject = ($reply_to && $old_subject == $msg_subject);

		if (($GLOBALS['MINIMSG_OPT_DISABLED'] = isset($_POST['btn_spell']))) {
			$text = apply_custom_replace($msg_body);
			$text_s = apply_custom_replace($msg_subject);

			if ($frm->forum_opt & 16) {
				$text = char_fix(tags_to_html($text, $perms & 32768));
			} else if ($frm->forum_opt & 8) {
				$text = char_fix(htmlspecialchars($text));
			}

			if ($perms & 16384 && !$msg_smiley_disabled) {
				$text = smiley_to_post($text);
			}

	 		if (strlen($text)) {
				$wa = tokenize_string($text);
				$msg_body = spell_replace($wa, 'body');

				if ($perms & 16384 && !$msg_smiley_disabled) {
					$msg_body = post_to_smiley($msg_body);
				}
				if ($frm->forum_opt & 16) {
					$msg_body = html_to_tags($msg_body);
				} else if ($frm->forum_opt & 8) {
					$msg_body = reverse_fmt($msg_body);
				}

				$msg_body = apply_reverse_replace($msg_body);
			}
			$wa = '';

			if (strlen($_POST['msg_subject']) && !$no_spell_subject) {
				$text_s = char_fix(htmlspecialchars($text_s));
				$wa = tokenize_string($text_s);
				$text_s = spell_replace($wa, 'subject');
				$msg_subject = apply_reverse_replace(reverse_fmt($text_s));
			}
		} else if (isset($_POST['spell'])) {
			$GLOBALS['MINIMSG_OPT_DISABLED'] = 1;
		}

		if (!empty($_POST['submitted']) && !isset($_POST['spell']) && !isset($_POST['preview'])) {
			$_POST['btn_submit'] = 1;
		}

		if (!$is_a && isset($_POST['btn_submit']) && $frm->forum_opt & 4 && (!isset($_POST['frm_passwd']) || $frm->post_passwd != $_POST['frm_passwd'])) {
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
			$msg_post->icon = (isset($_POST['msg_icon']) && is_string($_POST['msg_icon']) && basename($_POST['msg_icon']) == $_POST['msg_icon'] && @file_exists($WWW_ROOT_DISK.'images/message_icons/'.$_POST['msg_icon'])) ? $_POST['msg_icon'] : '';
		 	$msg_post->msg_opt =  $msg_smiley_disabled ? 2 : 0;
		 	$msg_post->msg_opt |= $msg_show_sig ? 1 : 0;
		 	$msg_post->attach_cnt = (int) $attach_cnt;
			$msg_post->body = apply_custom_replace($msg_post->body);

			if ($frm->forum_opt & 16) {
				$msg_post->body = char_fix(tags_to_html($msg_post->body, $perms & 32768));
			} else if ($frm->forum_opt & 8) {
				$msg_post->body = char_fix(nl2br(htmlspecialchars($msg_post->body)));
			}

	 		if ($perms & 16384 && !($msg_post->msg_opt & 2)) {
	 			$msg_post->body = smiley_to_post($msg_post->body);
	 		}

			fud_wordwrap($msg_post->body);

			$msg_post->subject = char_fix(htmlspecialchars(apply_custom_replace($msg_post->subject)));

		 	/* chose to create thread OR add message OR update message */

		 	if (!$th_id) {
		 		$create_thread = 1;
		 		$msg_post->add($frm->id, $frm->message_threshold, $frm->forum_opt, ($perms & (64|4096)), false);
		 	} else if ($th_id && !$msg_id) {
				$msg_post->thread_id = $th_id;
		 		$msg_post->add_reply($reply_to, $th_id, ($perms & (64|4096)), false);
			} else if ($msg_id) {
				$msg_post->id = $msg_id;
				$msg_post->thread_id = $th_id;
				$msg_post->post_stamp = $msg->post_stamp;
				$msg_post->mlist_msg_id = $msg->mlist_msg_id;
				$msg_post->file_id = $msg->file_id;
				$msg_post->file_id_preview = $msg->file_id_preview;
				$msg_post->sync(_uid, $frm->id, $frm->message_threshold, ($perms & (64|4096)));
				/* log moderator edit */
			 	if (_uid && _uid != $msg->poster_id) {
			 		logaction($usr->id, 'MSGEDIT', $msg_post->id);
			 	}
			} else {
				std_error('systemerr');
			}

			/* write file attachments */
			if ($perms & 256 && $attach_list) {
				attach_finalize($attach_list, $msg_post->id);
			}

			if (!$msg_id && (!($frm->forum_opt & 2) || $MOD)) {
				$msg_post->approve($msg_post->id);
			}

			if (_uid && !$msg_id) {
				/* deal with notifications */
	 			if (isset($_POST['msg_poster_notif'])) {
	 				thread_notify_add(_uid, $msg_post->thread_id);
	 			} else {
	 				thread_notify_del(_uid, $msg_post->thread_id);
	 			}

				/* register a view, so the forum marked as read */
				user_register_forum_view($frm->id);
			}

			/* where to redirect, to the treeview or the flat view and consider what to do for a moderated forum or post-only forum */
			if (!$MOD && !($perms & 2)) {
				check_return();
			} else if ($frm->forum_opt & 2 && !$MOD) {
				if ($FUD_OPT_2 & 262144) {
					$modl = db_all('SELECT u.email FROM {SQL_TABLE_PREFIX}mod mm INNER JOIN {SQL_TABLE_PREFIX}users u ON u.id=mm.user_id WHERE mm.forum_id='.$frm->id);
					if ($modl) {
						send_email($NOTIFY_FROM, $modl, '{TEMPLATE: post_mod_msg_notify_title}', '{TEMPLATE: post_mod_msg_notify_msg}', '');
					}
				}
				$data = file_get_contents($INCLUDE.'theme/'.$usr->theme_name.'/usercp.inc');
				$s = strpos($data, '<?php') + 5;
				eval(substr($data, $s, (strrpos($data, '?>') - $s)));
				?>
				{TEMPLATE: moderated_forum_post}
				<?php
				exit;
			} else {
				$t = d_thread_view;

				if ($msg_id && ($frm->forum_opt & 2) && !q_singleval('SELECT apr FROM {SQL_TABLE_PREFIX}msg WHERE id='.$msg_id)) { /* editing unapproved message in moderated forum */
					check_return($usr->returnto);
				}

				if ($usr->returnto) {
					if (!strncmp('t=selmsg', $usr->returnto, 8) || !strncmp('/sel/', $usr->returnto, 5)) {
						check_return($usr->returnto);
					}
					if (preg_match('!t=(tree|msg)!', $usr->returnto, $tmp)) {
						$t = $tmp[1];
					}
				}
				/* redirect the user to their message */
				if ($FUD_OPT_2 & 32768) {
					header('Location: {FULL_ROOT}{ROOT}/m/'.$msg_post->id.'/'._rsidl.'#msg_'.$msg_post->id);
				} else {
					header('Location: {FULL_ROOT}{ROOT}?t='.$t.'&goto='.$msg_post->id.'&'._rsidl.'#msg_'.$msg_post->id);
				}
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

/*{POST_HTML_PHP}*/

	if (!$th_id) {
		$label = '{TEMPLATE: create_thread}';
	} else if ($msg_id) {
		$label = '{TEMPLATE: edit_message}';
	} else {
		$label = '{TEMPLATE: submit_reply}';
	}

	$spell_check_button = ($FUD_OPT_1 & 2097152 && extension_loaded('pspell') && $usr->pspell_lang) ? '{TEMPLATE: spell_check_button}' : '';

	if (isset($_POST['preview']) || isset($_POST['spell'])) {
		$text = apply_custom_replace($msg_body);
		$text_s = apply_custom_replace($msg_subject);

		if ($frm->forum_opt & 16) {
			$text = char_fix(tags_to_html($text, $perms & 32768));
		} else if ($frm->forum_opt & 8) {
			$text = char_fix(nl2br(htmlspecialchars($text)));
		}

		if ($perms & 16384 && !$msg_smiley_disabled) {
			$text = smiley_to_post($text);
		}

		$text_s = char_fix(htmlspecialchars($text_s));

		$spell = $spell_check_button && isset($_POST['spell']);

		if ($spell && $text) {
			$text = check_data_spell($text, 'body', $usr->pspell_lang);
		}
		fud_wordwrap($text);

		if ($spell && !$no_spell_subject && $text_s) {
			$subj = check_data_spell($text_s, 'subject', $usr->pspell_lang);
		} else {
			$subj = $text_s;
		}

		if ($FUD_OPT_1 & 32768 && $msg_show_sig) {
			if ($msg_id && $msg->poster_id && $msg->poster_id != _uid && !$reply_to) {
				$sig = q_singleval('SELECT sig FROM {SQL_TABLE_PREFIX}users WHERE id='.$msg->poster_id);
			} else {
				$sig = $usr->sig;
			}

			$signature = $sig ? '{TEMPLATE: signature}' : '';
		} else {
			$signature = '';
		}

		$preview_message = '{TEMPLATE: preview_message}';
	} else {
		$preview_message = '';
	}

	$post_error = get_err('msg_session');
	if (!$post_error && is_post_error()) {
		$post_error = '{TEMPLATE: post_error}';
	}

	/* handle polls */
	$poll = '';
	if ($perms & 128) {
		if (!$pl_id) {
			$poll = '{TEMPLATE: create_poll}';
		} else if (($poll = db_saq('SELECT id, name FROM {SQL_TABLE_PREFIX}poll WHERE id='.$pl_id))) {
			$poll = '{TEMPLATE: edit_poll}';
		}
	}

	/* sticky/announcment controls */
	if ($perms & 64 && (!$thr || $msg_id == $thr->root_msg_id)) {
		if (!isset($_POST['prev_loaded'])) {
			if (!$thr) {
				$thr_ordertype = $thr_orderexpiry = '';
			} else {
				$thr_ordertype = ($thr->thread_opt|1) ^ 1;
				$thr_orderexpiry = $thr->orderexpiry;
			}
		} else {
			$thr_ordertype = isset($_POST['thr_ordertype']) ? (int) $_POST['thr_ordertype'] : '';
			$thr_orderexpiry = isset($_POST['thr_orderexpiry']) ? (int) $_POST['thr_orderexpiry'] : '';
		}

		$thread_type_select = tmpl_draw_select_opt("0\n4\n2", "{TEMPLATE: post_normal}\n{TEMPLATE: post_sticky}\n{TEMPLATE: post_annoncement}", $thr_ordertype);
		$thread_expiry_select = tmpl_draw_select_opt("1000000000\n3600\n7200\n14400\n28800\n57600\n86400\n172800\n345600\n604800\n1209600\n2635200\n5270400\n10540800\n938131200", "{TEMPLATE: th_expr_never}\n{TEMPLATE: th_expr_one_hr}\n{TEMPLATE: th_expr_three_hr}\n{TEMPLATE: th_expr_four_hr}\n{TEMPLATE: th_expr_eight_hr}\n{TEMPLATE: th_expr_sixteen_hr}\n{TEMPLATE: th_expr_one_day}\n{TEMPLATE: th_expr_two_day}\n{TEMPLATE: th_expr_four_day}\n{TEMPLATE: th_expr_one_week}\n{TEMPLATE: th_expr_two_week}\n{TEMPLATE: th_expr_one_month}\n{TEMPLATE: th_expr_two_month}\n{TEMPLATE: th_expr_four_month}\n{TEMPLATE: th_expr_one_year}", $thr_orderexpiry);

		$admin_options = '{TEMPLATE: admin_options}';
	} else {
		$admin_options = '';
	}

	/* thread locking controls */
	if ($perms & 4096) {
		$thr_locked_checked = '';
		if (!isset($_POST['prev_loaded']) && $thr && $thr->thread_opt & 1) {
			$thr_locked_checked = ' checked';
		} else if (isset($_POST['prev_loaded']) && isset($_POST['thr_locked'])) {
			$thr_locked_checked = ' checked';
		}
		$mod_post_opts = '{TEMPLATE: mod_post_opts}';
	} else {
		$mod_post_opts = '';
	}

	$thr_always_on_top = '';
	if ($perms & 64) {
		if (!isset($_POST['prev_loaded']) && $thr && $thr->thread_opt & 8) {
			$thr_always_on_top = ' checked';
		} else if (isset($_POST['prev_loaded']) && isset($_POST['thr_always_on_top'])) {
			$thr_always_on_top = ' checked';
		}
	}

	$msg_body = $msg_body ? char_fix(htmlspecialchars(str_replace("\r", '', $msg_body))) : '';
	if ($msg_subject) {
		$msg_subject = char_fix(htmlspecialchars($msg_subject));
	}

	/* handle file attachments */
	if ($perms & 256) {
		if ($frm->forum_opt & 32 && $MOD) {
			$frm->max_attach_size = (int) ini_get('upload_max_filesize');
			$t = str_replace($frm->max_attach_size, '', ini_get('upload_max_filesize'));
			if ($t == 'M' || $t == 'm') {
				$frm->max_attach_size *= 1024;
			}
			$frm->max_file_attachments = 100;
		}
		$file_attachments = draw_post_attachments($attach_list, $frm->max_attach_size, $frm->max_file_attachments, $attach_control_error, 0, $msg_id);
	} else {
		$file_attachments = '';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: POST_PAGE}