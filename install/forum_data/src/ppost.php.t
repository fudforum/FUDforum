<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: ppost.php.t,v 1.73 2004/12/09 18:43:02 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

/*{PRE_HTML_PHP}*/

function export_msg_data($m, &$msg_subject, &$msg_body, &$msg_icon, &$msg_smiley_disabled, &$msg_show_sig, &$msg_track, &$msg_to_list, $repl=0)
{
	$msg_subject = $m->subject;
	$msg_body = read_pmsg_body($m->foff, $m->length);
	$msg_icon = $m->icon;
	$msg_smiley_disabled = $m->pmsg_opt & 2 ? '2' : '';
	$msg_show_sig = $m->pmsg_opt & 1 ? '1' : '';
	$msg_track = $m->pmsg_opt & 4 ? '4' : '';
	$msg_to_list = $m->to_list;

	reverse_fmt($msg_subject);
	/* we do not revert replacment for forward/quote */
	if ($repl) {
		$msg_subject = apply_reverse_replace($msg_subject);
		$msg_body = apply_reverse_replace($msg_body);
	}
	if (!$msg_smiley_disabled) {
		$msg_body = post_to_smiley($msg_body);
	}
	if ($GLOBALS['FUD_OPT_1'] & 4096) {
		$msg_body = html_to_tags($msg_body);
	} else if ($GLOBALS['FUD_OPT_1'] & 2048) {
		reverse_fmt($msg_body);
		reverse_nl2br($msg_body);
	}
}

	if (__fud_real_user__) {
		is_allowed_user($usr);
	} else {
		std_error('login');
	}

	if (!($FUD_OPT_1 & 1024)) {
		error_dialog('{TEMPLATE: pm_err_nopm_title}', '{TEMPLATE: pm_err_nopm_msg}');
	}
	if (!($usr->users_opt & 32)) {
		error_dialog('{TEMPLATE: pm_err_disabled_title}', '{TEMPLATE: pm_err_disabled_msg}');
	}
	if (($fldr_size = q_singleval('SELECT SUM(length) FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id='._uid)) > $MAX_PMSG_FLDR_SIZE) {
		error_dialog('{TEMPLATE: pm_no_space_title}', '{TEMPLATE: pm_no_space_msg}');
	}

	$attach_control_error = '';
	$pm_find_user = $FUD_OPT_1 & (8388608|4194304) ? '{TEMPLATE: pm_find_user}' : '';

	$attach_count = 0;
	$attach_list = array();

	if (!isset($_POST['prev_loaded'])) {
		/* setup some default values */
		$msg_subject = $msg_body = $msg_icon = $old_subject = $msg_ref_msg_id = '';
		$msg_track = '';
		$msg_show_sig = $usr->users_opt & 2048 ? '1' : '';
		$msg_smiley_disabled = $FUD_OPT_1 & 8192 ? '' : '2';
		$reply = $forward = $msg_id = 0;

		/* deal with users passed via GET */
		if (isset($_GET['toi']) && ($toi = (int)$_GET['toi'])) {
			$msg_to_list = q_singleval('SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id='.$toi.' AND id>1');
		} else {
			$msg_to_list = '';
		}

		if (isset($_GET['msg_id']) && ($msg_id = (int)$_GET['msg_id'])) { /* editing a message */
			if (($msg_r = db_sab('SELECT id, subject, length, foff, to_list, icon, attach_cnt, pmsg_opt, ref_msg_id FROM {SQL_TABLE_PREFIX}pmsg WHERE id='.$msg_id.' AND duser_id='._uid))) {
				export_msg_data($msg_r, $msg_subject, $msg_body, $msg_icon, $msg_smiley_disabled, $msg_show_sig, $msg_track, $msg_to_list, 1);
			}
		} else if (isset($_GET['quote']) || isset($_GET['forward'])) { /* quote or forward message */
			if (($msg_r = db_sab('SELECT id, post_stamp, ouser_id, subject, length, foff, to_list, icon, attach_cnt, pmsg_opt, ref_msg_id '.(isset($_GET['quote']) ? ', to_list' : '').' FROM {SQL_TABLE_PREFIX}pmsg WHERE id='.(int)(isset($_GET['quote']) ? $_GET['quote'] : $_GET['forward']).' AND duser_id='._uid))) {
				$reply = $quote = isset($_GET['quote']) ? (int)$_GET['quote'] : 0;
				$forward = isset($_GET['forward']) ? (int)$_GET['forward'] : 0;

				export_msg_data($msg_r, $msg_subject, $msg_body, $msg_icon, $msg_smiley_disabled, $msg_show_sig, $msg_track, $msg_to_list);
				$msg_id = $msg_to_list = '';

				if ($quote) {
					$msg_to_list = q_singleval('SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE id='.$msg_r->ouser_id);
				}

				if ($quote) {
					if ($FUD_OPT_1 & 4096) {
						$msg_body = '{TEMPLATE: fud_quote}';
					} else if ($FUD_OPT_1 & 2048) {
						$msg_body = str_replace('<br>', "\n", '{TEMPLATE: plain_quote}');
					} else {
						$msg_body = '{TEMPLATE: html_quote}';
					}

					if (strncmp($msg_subject, 'Re: ', 4)) {
						$old_subject = $msg_subject = 'Re: ' . $msg_subject;
					}
					$msg_ref_msg_id = 'R'.$reply;
					unset($msg_r);
				} else if ($forward && strncmp($msg_subject, 'Fwd: ', 5)) {
					$old_subject = $msg_subject = 'Fwd: ' . $msg_subject;
					$msg_ref_msg_id = 'F'.$forward;
				}
			}
		} else if (isset($_GET['reply']) && ($reply = (int)$_GET['reply'])) {
			if (($msg_r = db_saq('SELECT p.subject, u.alias FROM {SQL_TABLE_PREFIX}pmsg p INNER JOIN {SQL_TABLE_PREFIX}users u ON p.ouser_id=u.id WHERE p.id='.$reply.' AND p.duser_id='._uid))) {
				$msg_subject = $msg_r[0];
				$msg_to_list = $msg_r[1];

				if (strncmp($msg_subject, 'Re: ', 4)) {
					$old_subject = $msg_subject = 'Re: ' . $msg_subject;
				}
				reverse_fmt($msg_subject);
				unset($msg_r);
				$msg_ref_msg_id = 'R'.$reply;
			}
		}

		/* restore file attachments */
		if (!empty($msg_r->attach_cnt) && $PRIVATE_ATTACHMENTS > 0) {
			$c = uq('SELECT id FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$msg_r->id.' AND attach_opt=1');
	 		while ($r = db_rowarr($c)) {
	 			$attach_list[$r[0]] = $r[0];
	 		}
		}
	} else {
		if (isset($_POST['btn_action'])) {
			if ($_POST['btn_action'] == 'draft') {
				$_POST['btn_draft'] = 1;
			} else if ($_POST['btn_action'] == 'send') {
				$_POST['btn_submit'] = 1;
			}
		}

		$msg_to_list = htmlspecialchars($_POST['msg_to_list']);
		$msg_subject = $_POST['msg_subject'];
		$old_subject = $_POST['old_subject'];
		$msg_body = $_POST['msg_body'];
		$msg_icon = (isset($_POST['msg_icon']) && basename($_POST['msg_icon']) == $_POST['msg_icon'] && @file_exists($WWW_ROOT_DISK.'images/message_icons/'.$_POST['msg_icon'])) ? $_POST['msg_icon'] : '';
		$msg_track = isset($_POST['msg_track']) ? '4' : '';
		$msg_smiley_disabled = isset($_POST['msg_smiley_disabled']) ? '2' : '';
		$msg_show_sig = isset($_POST['msg_show_sig']) ? '1' : '';

		$reply = isset($_POST['quote']) ? (int)$_POST['quote'] : 0;
		$forward = isset($_POST['forward']) ? (int)$_POST['forward'] : 0;
		$msg_id = isset($_POST['msg_id']) ? (int)$_POST['msg_id'] : 0;
		$msg_ref_msg_id = isset($_POST['msg_ref_msg_id']) ? (int)$_POST['msg_ref_msg_id'] : '';

		/* restore file attachments */
		if (!empty($_POST['file_array']) && $PRIVATE_ATTACHMENTS > 0 && $usr->data === md5($_POST['file_array'])) {
			$attach_list = unserialize(base64_decode($_POST['file_array']));
		}
	}

	if ($attach_list) {
		$enc = base64_encode(serialize($attach_list));
		foreach ($attach_list as $v) {
			if ($v) {
				$attach_count++;
			}
		}
		/* remove file attachment */
		if (isset($_POST['file_del_opt'], $attach_list[$_POST['file_del_opt']])) {
			if ($attach_list[$_POST['file_del_opt']]) {
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
		}
	}

	/* deal with newly uploaded files */
	if ($PRIVATE_ATTACHMENTS > 0 && isset($_FILES['attach_control']) && $_FILES['attach_control']['size'] > 0) {
		if ($_FILES['attach_control']['size'] > $PRIVATE_ATTACH_SIZE) {
			$MAX_F_SIZE = $PRIVATE_ATTACH_SIZE;
			$attach_control_error = '{TEMPLATE: post_err_attach_size}';
		} else {
			if (filter_ext($_FILES['attach_control']['name'])) {
				$attach_control_error = '{TEMPLATE: post_err_attach_ext}';
			} else {
				if (($attach_count+1) <= $PRIVATE_ATTACHMENTS) {
					$val = attach_add($_FILES['attach_control'], _uid, 1);
					$attach_list[$val] = $val;
					$attach_count++;
				} else {
					$attach_control_error = '{TEMPLATE: post_err_attach_filelimit}';
				}
			}
		}
	}

	if ((isset($_POST['btn_submit']) && !check_ppost_form($_POST['msg_subject'])) || isset($_POST['btn_draft'])) {
		$msg_p = new fud_pmsg;
		$msg_p->pmsg_opt = (int) $msg_smiley_disabled | (int) $msg_show_sig | (int) $msg_track;
		$msg_p->attach_cnt = $attach_count;
		$msg_p->icon = $msg_icon;
		$msg_p->body = $msg_body;
		$msg_p->subject = $msg_subject;
		$msg_p->fldr = isset($_POST['btn_submit']) ? 3 : 4;
		$msg_p->to_list = $_POST['msg_to_list'];

		$msg_p->body = apply_custom_replace($msg_p->body);
		if ($FUD_OPT_1 & 4096) {
			$msg_p->body = tags_to_html($msg_p->body, $FUD_OPT_1 & 16384);
		} else if ($FUD_OPT_1 & 2048) {
			$msg_p->body = nl2br(htmlspecialchars($msg_p->body));
		}

		if ($FUD_OPT_1 & 6144) {
			char_fix($msg_p->body);
		}

		if (!($msg_p->pmsg_opt & 2)) {
			$msg_p->body = smiley_to_post($msg_p->body);
		}
		fud_wordwrap($msg_p->body);

		$msg_p->ouser_id = _uid;

		$msg_p->subject = apply_custom_replace($msg_p->subject);
		$msg_p->subject = htmlspecialchars($msg_p->subject);

		char_fix($msg_p->subject);

		if (empty($_POST['msg_id'])) {
			$msg_p->pmsg_opt = $msg_p->pmsg_opt &~ 96;
			if ($_POST['reply']) {
				$msg_p->ref_msg_id = 'R'.$_POST['reply'];
				$msg_p->pmsg_opt |= 64;
			} else if ($_POST['forward']) {
				$msg_p->ref_msg_id = 'F'.$_POST['forward'];
			} else {
				$msg_p->ref_msg_id = null;
				$msg_p->pmsg_opt |= 32;
			}

			$msg_p->add();
		} else {
			$msg_p->id = (int) $_POST['msg_id'];
			$msg_p->sync();
		}

		if ($attach_list) {
			attach_finalize($attach_list, $msg_p->id, 1);

			/* we need to add attachments to all copies of the message */
			if (!isset($_POST['btn_draft'])) {
				$atl = array();
				$c = uq('SELECT id, original_name, mime_type, fsize FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$msg_p->id.' AND attach_opt=1');
				while ($r = db_rowarr($c)) {
					$atl[$r[0]] = "'".addslashes($r[1])."', ".$r[2].", ".$r[3];
				}
				if ($atl) {
					$aidl = array();

					foreach ($GLOBALS['send_to_array'] as $mid) {
						foreach ($atl as $k => $v) {
							$aid = db_qid('INSERT INTO {SQL_TABLE_PREFIX}attach (owner, attach_opt, message_id, original_name, mime_type, fsize, location) VALUES(' . $mid[0] . ', 1, ' . $mid[1] . ', ' . $v .', \'\')');
							$aidl[] = $aid;
							copy($FILE_STORE . $k . '.atch', $FILE_STORE . $aid . '.atch');
							@chmod($FILE_STORE . $aid . '.atch', ($FUD_OPT_2 & 8388608 ? 0600 : 0666));
						}
					}
					$cc = __FUD_SQL_CONCAT__.'('.__FUD_SQL_CONCAT__."('".$FILE_STORE."', id), '.atch')";
					q('UPDATE {SQL_TABLE_PREFIX}attach SET location='.$cc.' WHERE id IN('.implode(',', $aidl).')');
				}
			}
		}

		if ($FUD_OPT_2 & 32768) {
			header('Location: {FULL_ROOT}{ROOT}/pdm/1/'._rsidl);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?t=pmsg&'._rsidl.'&fldr=1');
		}
		exit;
	}

	$no_spell_subject = ($reply && $old_subject == $msg_subject) ? 1 : 0;

	if (isset($_POST['btn_spell'])) {
		$text = apply_custom_replace($_POST['msg_body']);
		$text_s = apply_custom_replace($_POST['msg_subject']);

		if ($FUD_OPT_1 & 4096) {
			$text = tags_to_html($text, $FUD_OPT_1 & 16384);
		} else if ($FUD_OPT_1 & 2048) {
			$text = htmlspecialchars($text);
		}

		if ($FUD_OPT_1 & 6144) {
			char_fix($text);
		}

		if ($FUD_OPT_1 & 8192 && !$msg_smiley_disabled) {
			$text = smiley_to_post($text);
		}

	 	if ($text) {
			$text = spell_replace(tokenize_string($text), 'body');

			if ($FUD_OPT_1 & 8192 && !$msg_smiley_disabled) {
				$msg_body = post_to_smiley($text);
			}

			if ($FUD_OPT_1 & 4096) {
				$msg_body = html_to_tags($msg_body);
			} else if ($FUD_OPT_1 & 2048) {
				reverse_fmt($msg_body);
			}
			$msg_body = apply_reverse_replace($msg_body);
		}

		if ($text_s && !$no_spell_subject) {
			$text_s = htmlspecialchars($text_s);
			char_fix($text_s);
			$text_s = spell_replace(tokenize_string($text_s), 'subject');
			reverse_fmt($text_s);
			$msg_subject = apply_reverse_replace($text_s);
		}
	}

	ses_update_status($usr->sid, '{TEMPLATE: pm_update}');

/*{POST_HTML_PHP}*/

	$cur_ppage = tmpl_cur_ppage('', $folders);

	$spell_check_button = ($FUD_OPT_1 & 2097152 && extension_loaded('pspell') && $usr->pspell_lang) ? '{TEMPLATE: spell_check_button}' : '';

	if (isset($_POST['preview']) || isset($_POST['spell'])) {
		$text = apply_custom_replace($_POST['msg_body']);
		$text_s = apply_custom_replace($_POST['msg_subject']);

		if ($FUD_OPT_1 & 4096) {
			$text = tags_to_html($text, $FUD_OPT_1 & 16384);
		} else if ($FUD_OPT_1 & 2048) {
			$text = nl2br(htmlspecialchars($text));
		}

		if ($FUD_OPT_1 & 6144) {
			char_fix($text);
		}

		if ($FUD_OPT_1 & 8192 && !$msg_smiley_disabled) {
			$text = smiley_to_post($text);
		}
		$text_s = htmlspecialchars($text_s);
		char_fix($text_s);

		$spell = $spell_check_button && isset($_POST['spell']);

		if ($spell && strlen($text)) {
			$text = check_data_spell($text, 'body', $usr->pspell_lang);
		}
		fud_wordwrap($text);

		$subj = ($spell && !$no_spell_subject && $text_s) ? check_data_spell($text_s, 'subject', $usr->pspell_lang) : $text_s;

		$signature = ($FUD_OPT_1 & 32768 && $usr->sig && $msg_show_sig) ? '{TEMPLATE: signature}' : '';
		$apply_spell_changes = $spell ? '{TEMPLATE: apply_spell_changes}' : '';
		$preview_message = '{TEMPLATE: preview_message}';
	} else {
		$preview_message = '';
	}

	$post_error = is_post_error() ? '{TEMPLATE: post_error}' : '';
	$session_error = get_err('msg_session');
	if ($session_error) {
		$post_error = $session_error;
	}

	$to_err = get_err('msg_to_list');
	$msg_subect_err = get_err('msg_subject');
	$message_err = get_err('msg_body',1);

	$post_smilies = $FUD_OPT_1 & 8192 ? draw_post_smiley_cntrl() : '';
	$post_icons = draw_post_icons($msg_icon);
	$fud_code_icons = $FUD_OPT_1 & 4096? '{TEMPLATE: fud_code_icons}' : '';

	$post_options = tmpl_post_options('private');

	if ($FUD_OPT_2 & 32768) {
		$private = '1';
	} else {
		$private = '&amp;private=1';
	}

	if ($PRIVATE_ATTACHMENTS > 0) {
		$file_attachments = draw_post_attachments($attach_list, round($PRIVATE_ATTACH_SIZE / 1024), $PRIVATE_ATTACHMENTS, $attach_control_error, $private, $msg_id ? $msg_id : (isset($_GET['forward']) ? (int)$_GET['forward'] : 0));
	} else {
		$file_attachments = '';
	}

	$msg_track_check = $msg_track ? ' checked' : '';
	$msg_show_sig_check = $msg_show_sig ? ' checked' : '';

	if ($FUD_OPT_1 & 8192) {
		$msg_smiley_disabled_check = $msg_smiley_disabled ? ' checked' : '';
		$disable_smileys = '{TEMPLATE: disable_smileys}';
	} else {
		$disable_smileys = '';
	}

	if ($reply && ($mm = db_sab('SELECT p.*, u.sig, u.alias, u.users_opt, u.posted_msg_count, u.join_date, u.last_visit FROM {SQL_TABLE_PREFIX}pmsg p INNER JOIN {SQL_TABLE_PREFIX}users u ON p.ouser_id=u.id WHERE p.duser_id='._uid.' AND p.id='.$reply))) {
		fud_use('drawpmsg.inc');
		$dpmsg_prev_message = $dpmsg_next_message = '';
		$reference_msg = tmpl_drawpmsg($mm, $usr, true);
		$reference_msg = '{TEMPLATE: reference_msg}';
	} else {
		$reference_msg = '';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PPOST_PAGE}