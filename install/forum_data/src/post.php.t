<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: post.php.t,v 1.19 2002/11/18 16:04:19 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*#? Post Editor Page */
	define('msg_edit', 1); define("_imsg_edit_inc_", 1);
	{PRE_HTML_PHP}
	
	$smiley_www = 'images/smiley_icons/';
	$icon_path = 'images/message_icons/';
	$icon_path_www = 'images/message_icons/';
	$returnto_d = (!empty($returnto)?$returnto:NULL);
	$attach_control_error=NULL;

	if( isset($HTTP_POST_VARS['moderated_redr']) ) check_return();

	/* INITIAL SECURITY CHECKS */
	$flt = new fud_ip_filter;
	if ( isset($GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']) && $flt->is_blocked($GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']) ) {
		error_dialog('{TEMPLATE: post_err_notallowed_title}', '{TEMPLATE: post_err_notallowed_msg}', $returnto_d);
		exit();
	}
	$flt=NULL;

	if( $reply_to || $msg_id ) {
		$mid = ($reply_to)?$reply_to:$msg_id;
		if( !is_numeric($mid) ) invl_inp_err();
		$msg = new fud_msg_edit;
		$msg->get_by_id($mid);
	 	$th_id = $msg->thread_id;
	}

	$frm = new fud_forum;
	if( !empty($th_id) ) {
		if( !is_numeric($th_id) ) invl_inp_err();
		$thr = new fud_thread;
		$thr->get_by_id($th_id);	
		$frm->get($thr->forum_id);
	}
	else if( !empty($frm_id) ) {
		$frm->get($frm_id);
		$th_id = NULL;
	}	
	else {
		std_error('systemerr');
		exit;
	}
	
	$MAX_F_SIZE = $frm->max_attach_size;
	
	/* More Security */
	if( isset($thr) && $usr->is_mod != 'A' && $thr->locked=='Y' ) {
		error_dialog('{TEMPLATE: post_err_lockedthread_title}', '{TEMPLATE: post_err_lockedthread_msg}', $returnto_d);
		exit();
	}
	$__RESOURCE_ID = $frm->id;
	
	if( isset($usr) ) {
		/* check if moderator */
		if ( $frm->is_moderator($usr->id) || $usr->is_mod == 'A' ) { $MOD = 1; } else { $MOD=NULL; }

		is_allowed_user();
		
		if( empty($reply_to) && empty($MOD) && empty($msg_id) && !is_perms(_uid,$__RESOURCE_ID ,'POST') )
			error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
		else if( (!empty($th_id) || !empty($reply_to)) && empty($MOD) && !is_perms(_uid,$__RESOURCE_ID ,'REPLY') ) 
			error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
		else if( $msg_id && empty($MOD) && $msg->poster_id != $usr->id && !is_perms(_uid, $__RESOURCE_ID ,'EDIT')	)
			error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
		else if( $msg_id && empty($MOD) && $EDIT_TIME_LIMIT && ($msg->post_stamp+$EDIT_TIME_LIMIT*60<__request_timestamp__) ) {
			error_dialog('{TEMPLATE: post_err_edttimelimit_title}', '{TEMPLATE: post_err_edttimelimit_msg}', $returnto_d); 
			exit();
		}
	}
	else {
		if( empty($th_id) && !is_perms(_uid, $__RESOURCE_ID, 'POST') ) {
			error_dialog('{TEMPLATE: post_err_noannontopics_title}', '{TEMPLATE: post_err_noannontopics_msg}', $returnto_d); 
			exit(); 
		}
		else if ( !is_perms(_uid, $__RESOURCE_ID, 'REPLY') ) {
			error_dialog('{TEMPLATE: post_err_noannonposts_title}', '{TEMPLATE: post_err_noannonposts_msg}', $returnto_d); 
			exit(); 
		}
	}

	/* Retrieve Message */
	if( empty($HTTP_POST_VARS['prev_loaded']) ) { 
		if( isset($usr) ) {
			$msg_show_sig = $usr->append_sig;
			$msg_poster_notif = $usr->notify;
		}
		
		if( $msg_id ) {
			$msg->export_vars('msg_');
			
			$msg_body = post_to_smiley($msg_body);
	 		
	 		switch( $frm->tag_style )
	 		{
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
	 		
	 		reverse_FMT($msg_subject);
			$msg_subject = apply_reverse_replace($msg_subject);
	 		
	 		$msg_smiley_disabled = $msg->smiley_disabled;
	 	
	 		$msg_poster_notif = ( is_notified($usr->id, $msg->thread_id) ) ? 'Y' : 'N';
	 			
	 		if ( $msg->attach_cnt ) {
	 			$r = q("SELECT id FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$msg->id." AND private='N'");
	 			while ( list($fa_id) = db_rowarr($r) ) $attach_list[$fa_id] = $fa_id;
	 			qf($r);
	 			$attach_count = count($attach_list);
		 	}
		 	$pl_id = $msg->poll_id;	
		}
		else if( $reply_to || $th_id ) {
			$subj = ( $reply_to ) ? $msg->subject : $thr->subject;
			reverse_FMT($subj);
			$subj = apply_reverse_replace($subj);
		
			$reply_prefix = preg_quote(strtolower('{TEMPLATE: reply_prefix}'));
			$msg_subject = ( !preg_match('/^{TEMPLATE: reply_prefix}/i', $subj) ) ? '{TEMPLATE: reply_prefix}'.$subj : $subj;
			$old_subject = $msg_subject;

			if( isset($quote) ) {
				$msg_body = apply_reverse_replace($msg->body);
				$msg_body = post_to_smiley(str_replace("\r", '', $msg_body));
				
				if( !strlen($msg->login) ) $msg->login = $GLOBALS['ANON_NICK'];
				reverse_FMT($msg->login);
				
				switch ( $frm->tag_style )
				{
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
	}	
	else { /* $HTTP_POST_VARS['prev_loaded'] */
		/* remove slashes */
		foreach($HTTP_POST_VARS as $k => $v) { 
			if( !empty($HTTP_POST_VARS[$k]) ) $HTTP_POST_VARS[$k] = $GLOBALS[$k] = stripslashes($HTTP_POST_VARS[$k]);
		}

		if ( $FLOOD_CHECK_TIME && !$MOD && !$msg_id ) {
			if ( ($tm=flood_check()) ) {
				error_dialog('{TEMPLATE: post_err_floodtrig_title}', '{TEMPLATE: post_err_floodtrig_msg}', $returnto_d);
				exit();
			}
		}
		
		if( is_perms(_uid, $__RESOURCE_ID, 'FILE') ) {
			$attach_count = 0;
			
			/* restore the attachment array */
			if( !empty($HTTP_POST_VARS['file_array']) ) {
				$file_array = explode("\n", base64_decode(stripslashes($HTTP_POST_VARS['file_array'])));
				
				foreach( $file_array as $val ) {
					list($fa_id,$fa_id2) = explode(":", $val);
					$attach_list[$fa_id] = $fa_id2;
					if( !empty($fa_id2) ) $attach_count++;
				}
			}
			
			if( !empty($HTTP_POST_VARS['file_del_opt']) ) {
				$attach_list[$HTTP_POST_VARS['file_del_opt']] = 0;
				/* Remove any reference to the image from the body to prevent broken images */
				if ( strpos($msg_body, '[img]{ROOT}?t=getfile&id='.$HTTP_POST_VARS['file_del_opt'].'[/img]') ) 
					$msg_body = str_replace('[img]{ROOT}?t=getfile&id='.$HTTP_POST_VARS['file_del_opt'].'[/img]', '', $msg_body);
					
				$attach_count--;
			}	
			
			if ( !empty($attach_control_size) ) {
				if( $attach_control_size>($MAX_F_SIZE*1024) ) {
					$attach_control_error = '{TEMPLATE: post_err_attach_size}';
				}
				else {
					if( filter_ext($attach_control_name) ) {
						$attach_control_error = '{TEMPLATE: post_err_attach_ext}';
					}
					else {
						if( ($attach_count+1) <= $frm->max_file_attachments ) {
						
							$at_obj = new fud_attach();
							
							$at_obj->original_name = $attach_control_name;
							$at_obj->fsize = $attach_control_size;
							$at_obj->owner = _uid;
							$val = $at_obj->add($attach_control);

							$attach_list[$val] = $val;
							$attach_count++;
						}
						else {
							$attach_control_error = '{TEMPLATE: post_err_attach_filelimit}';
						}	
					}	
				}	
			}
			$attach_cnt = $attach_count;
		}
		
		if( !empty($HTTP_POST_VARS["pl_del"]) && is_numeric($pl_id) ) {
			$poll = new fud_poll;
			$poll->get($pl_id);
			if ( $MOD || is_perms(_uid, $__RESOURCE_ID, 'EDIT') || $poll->owner==_uid ) $poll->delete();
			$pl_id = 0;
			unset($poll);
		}
		
		if ( $reply_to && $old_subject == $msg_subject )
				$no_spell_subject = 1;
				
		if( !empty($HTTP_POST_VARS["btn_spell"]) ) {
			$GLOBALS['MINIMSG_OPT']['DISABLED'] = 1;
			$text = apply_custom_replace($HTTP_POST_VARS["msg_body"]);
			$text_s = apply_custom_replace($HTTP_POST_VARS["msg_subject"]);
		
			switch( $frm->tag_style )
			{
				case 'ML':
					$text = tags_to_html($text, (is_perms(_uid, $__RESOURCE_ID, 'IMG')?'Y':'N'));
					break;
				case 'HTML':
					break;
				default:
					$text = htmlspecialchars($text);
			}
		
			if ( is_perms(_uid, $__RESOURCE_ID, 'SML') && empty($HTTP_POST_VARS["msg_smiley_disabled"]) ) $text = smiley_to_post($text);

	 		if( strlen($text) ) {	
				$wa = tokenize_string($text);
				$msg_body = spell_replace($wa,'body');
				
				if ( is_perms(_uid, $__RESOURCE_ID, 'SML') && empty($HTTP_POST_VARS["msg_smiley_disabled"]) ) $msg_body = post_to_smiley($msg_body);
				if($frm->tag_style == 'ML' ) $msg_body = html_to_tags($msg_body);
				else if ( $frm->tag_style !='HTML' )  reverse_FMT($msg_body);
				
				$msg_body = apply_reverse_replace($msg_body);
			}	
			$wa='';
			
			if( strlen($HTTP_POST_VARS["msg_subject"]) && empty($no_spell_subject) ) {
				$text_s = htmlspecialchars($text_s);
				$wa = tokenize_string($text_s);
				$text_s = spell_replace($wa,'subject');
				reverse_FMT($text_s);
				$msg_subject = apply_reverse_replace($text_s);
			}
		}
		
		if( empty($frm_passwd) ) $frm_passwd = '';	
		 	
		if( empty($spell) && empty($preview) && !empty($submitted) ) $HTTP_POST_VARS["btn_submit"] = 1;
		
		if ( !empty($HTTP_POST_VARS["btn_submit"]) && $frm->passwd_posting == 'Y' && $frm->post_passwd != $frm_passwd ) {
			set_err('password', '{TEMPLATE: post_err_passwd}');
		}
		
		/* submit processing */
		if( !empty($HTTP_POST_VARS["btn_submit"]) && !check_post_form() ) {
			$msg_post = new fud_msg_edit;
			
			/* Process Message Data */
			$msg_post->poster_id = (isset($usr))?$usr->id:0;
			$msg_post->poll_id = $pl_id;
			$msg_post->fetch_vars($HTTP_POST_VARS, 'msg_');
		 	$msg_post->smiley_disabled = yn($msg_smiley_disabled);
		 	$msg_post->attach_cnt = intval($attach_cnt);
			$msg_post->body = apply_custom_replace($msg_post->body);
			
			switch ( $frm->tag_style )
			{
				case 'ML':
					$msg_post->body = tags_to_html($msg_post->body, (is_perms(_uid, $__RESOURCE_ID, 'IMG')?'Y':'N'));
					break;
				case 'HTML':
					break;
				default:
					$msg_post->body = nl2br(htmlspecialchars($msg_post->body));
			}
			
	 		if( is_perms(_uid, $__RESOURCE_ID, 'SML') && $msg_post->smiley_disabled!='Y' ) 
	 			$msg_post->body = smiley_to_post($msg_post->body);
	 			
			fud_wordwrap($msg_post->body);
			
			$msg_post->subject = apply_custom_replace($msg_post->subject);
			$msg_post->subject = htmlspecialchars($msg_post->subject);
			$msg_post->subject = addslashes($msg_post->subject);
		
		 	/* chose to create thread OR add message OR update message */
		 	
		 	if( !$th_id ) {
		 		$create_thread=1;
		 		$msg_post->add_thread($frm->id, FALSE);
		 		$thr = new fud_thread;
		 		$thr->get_by_id($msg_post->thread_id);
		 	}
			else if( $th_id && !$msg_id ) {
				$msg_post->thread_id = $th_id;
		 		$msg_post->add_reply($reply_to, $th_id, FALSE);
			}
			else if( $msg_id ) {
				$msg_post->id = $msg_id;
				$msg_post->thread_id = $th_id;
				$msg_post->post_stamp = $msg->post_stamp;
				$msg_post->sync($usr->id);
				/* log moderator edit */
			 	if ( _uid && _uid != $msg->poster_id ) 
			 		logaction($usr->id, 'MSGEDIT', $msg_post->id);
			}
			else {
				std_error('systemerr');
				exit();
			}

			/* write file attachments */
			if (is_perms(_uid, $__RESOURCE_ID, 'FILE') && isset($attach_list)) {
				fud_attach::finalize($attach_list, $msg_post->id);
			}	
			
			if (empty($msg_id) && ($frm->moderated == 'N' || $MOD)) {
				$msg_post->approve(NULL, TRUE);
			}	
	
			/* deal with notifications */
			$th_not = new fud_thread_notify;
			if ( isset($usr) ) {
	 			if ( $HTTP_POST_VARS["msg_poster_notif"]=='Y' ) 
	 				$th_not->add($usr->id, $msg_post->thread_id);
	 			else if ( !empty($GLOBALS["HTTP_POST_VARS"]["msg_id"]) )
	 				$th_not->delete($usr->id, $msg_post->thread_id);
			}
			
			/* register a view, so the forum marked as read */
			if ( isset($frm) && isset($usr) ) $usr->register_forum_view($frm->id);
			
			/* where to redirect, to the treeview or the flat view 
			 * and consider what to do for a moderated forum
			 */
			$msg_url = ( strstr($returnto_d, 't=tree') ) ? 'tree' : 'msg';
			if( $frm->moderated == 'N' || $MOD )
				if( !strstr($returnto_d, 't=selmsg') )
					$returnto = '{ROOT}?t='.$msg_url.'&goto='.$msg_post->id.'&'._rsid;
				else {
					if( ($pos = strpos($returnto_d, '#')) ) $returnto_d = substr($returnto_d, 0, $pos);
					$returnto = $returnto_d.'#msg_'.$msg_post->id;
				}	
			else {
				if( !strstr($returnto_d, 't=selmsg') )
					$returnto = ( $th_id ) ? '{ROOT}?t='.$msg_url.'&'._rsid.'&th='.$th_id : '{ROOT}?t='.t_thread_view.'&'._rsid.'&frm_id='.$frm_id;
				else 
					$returnto = $returnto_d;
		 	}
		 	
			if( $frm->moderated == 'Y' && !$MOD ) {
				$ret = create_return();
				$fp = fopen($GLOBALS['INCLUDE'].'theme/'.$GLOBALS['FUD_THEME']->theme.'/usercp.inc', "rb");
				$data = fread($fp, __ffilesize($fp));
				fclose($fp);
				$s = strpos($data, '<?php')+5;
				eval(substr($data, $s, (strrpos($data, '?>')-$s)));
				?>
				{TEMPLATE: moderated_forum_post}
				<?php
				exit;
			}
			else
				check_return();
					
		} /* Form submitted and user redirected to own message */
	} /* $prevloaded is SET, this form has been submitted */
	
	/* form start */	 
	if ( isset($ses) ) {
		if ( $reply_to || $th_id && !$msg_id )
			$ses->update('{TEMPLATE: post_reply_update}', $__RESOURCE_ID);
		else if ( $msg_id ) 
			$ses->update('{TEMPLATE: post_reply_update}', $__RESOURCE_ID);
		else 
			$ses->update('{TEMPLATE: post_topic_update}', $__RESOURCE_ID);
	}
	
	if ( isset($thr) ) $th=$thr->id;
	if ( !empty($spell) ) $GLOBALS['MINIMSG_OPT']['DISABLED'] = TRUE;
	{POST_HTML_PHP}

	if ( empty($th_id) ) 
		$label = '{TEMPLATE: create_thread}';
	else
		$label = '{TEMPLATE: submit_reply}';
	
if ( !empty($preview) || !empty($spell) ) {
	$text = apply_custom_replace($HTTP_POST_VARS['msg_body']);
	$text_s = apply_custom_replace($HTTP_POST_VARS['msg_subject']);

	switch ( $frm->tag_style )
	{
		case 'ML':
			$text = tags_to_html($text, (is_perms(_uid, $__RESOURCE_ID, 'IMG')?'Y':'N'));
			break;
		case 'HTML':
			break;
		default:
			$text = nl2br(htmlspecialchars($text));
	}
			
	if ( is_perms(_uid, $__RESOURCE_ID, 'SML') && empty($HTTP_POST_VARS["msg_smiley_disabled"]) ) $text = smiley_to_post($text);
	
	$text_s = htmlspecialchars($text_s);
		
	if( !function_exists('pspell_config_create') || !$GLOBALS['FUD_THEME']->pspell_lang ) $spell=0;
	
	if ( !empty($spell) && !empty($text) ) $text = check_data_spell($text,'body');
	fud_wordwrap($text);

	$sig=$subj='';
	if ( $text_s ) {
		if ( !empty($spell) && empty($no_spell_subject) && strlen($text_s) )
			$subj .= check_data_spell($text_s,'subject');
		else
			$subj .= $text_s;
	}
	if( $GLOBALS['ALLOW_SIGS']=='Y' && $msg_show_sig == 'Y' ) {
		if ( isset($HTTP_POST_VARS['msg_id']) && !empty($msg->poster_id) && $msg->poster_id != _uid && empty($HTTP_POST_VARS['reply_to']) ) 
			$sig = q_singleval("SELECT sig FROM {SQL_TABLE_PREFIX}users WHERE id=".$msg->poster_id);
		else
			$sig = $usr->sig;	
		
		$signature = $sig ? '{TEMPLATE: signature}' : '';
	}
	
	if ( !empty($spell) ) $apply_spell_changes = '{TEMPLATE: apply_spell_changes}'; 
	
	$preview_message = '{TEMPLATE: preview_message}';
}

if ( is_post_error() ) $post_error = '{TEMPLATE: post_error}';

	if ( isset($usr) ) $loged_in_user = '{TEMPLATE: loged_in_user}';

	/*
	 * form begins here
	 */
		
	if ( $frm->passwd_posting == 'Y' ) {
		$pass_err = get_err('password');
		$post_password = '{TEMPLATE: post_password}';
	}
	
	$msg_subect_err = get_err('msg_subject');
	
	if ( $MOD || is_perms(_uid, $__RESOURCE_ID,'POLL') ) {
		if ( empty($pl_id) ) {
			$poll = '{TEMPLATE: create_poll}';
		}
		else if ( is_numeric($pl_id) ) {
			$poll = new fud_poll;
			$poll->get($pl_id);
			$poll = '{TEMPLATE: edit_poll}';
		}
	}
	
	if ( isset($MOD) || is_perms(_uid, $__RESOURCE_ID, 'STICKY') ) {
		if ( empty($thr) || ($thr->root_msg_id==$msg->id && empty($reply_to)) ) {
			if ( !$prev_loaded ) {
				$thr_ordertype = $thr->ordertype;
				$thr_orderexpiry = $thr->orderexpiry;
			}

			$thread_type_select = tmpl_draw_select_opt("NONE\nSTICKY\nANNOUNCE", "{TEMPLATE: post_normal}\n{TEMPLATE: post_sticky}\n{TEMPLATE: post_annoncement}", $thr_ordertype, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
			$thread_expiry_select = tmpl_draw_select_opt("1000000000\n3600\n7200\n14400\n28800\n57600\n86400\n172800\n345600\n604800\n1209600\n2635200\n5270400\n10540800\n938131200", "{TEMPLATE: th_expr_never}\n{TEMPLATE: th_expr_one_hr}\n{TEMPLATE: th_expr_three_hr}\n{TEMPLATE: th_expr_four_hr}\n{TEMPLATE: th_expr_eight_hr}\n{TEMPLATE: th_expr_sixteen_hr}\n{TEMPLATE: th_expr_one_day}\n{TEMPLATE: th_expr_two_day}\n{TEMPLATE: th_expr_four_day}\n{TEMPLATE: th_expr_one_week}\n{TEMPLATE: th_expr_two_week}\n{TEMPLATE: th_expr_one_month}\n{TEMPLATE: th_expr_two_month}\n{TEMPLATE: th_expr_four_month}\n{TEMPLATE: th_expr_one_year}", $thr_orderexpiry, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
		
			$admin_options = '{TEMPLATE: admin_options}';
		}
		
		if ( !$prev_loaded )
			$thr_locked = (isset($thr)&&isset($thr->locked))?$thr->locked:'';

		$thr_locked_checked = ($thr_locked=='Y')? ' checked' : '';
		$mod_post_opts = '{TEMPLATE: mod_post_opts}';
	}
	
	/* 
	 * draw the icon select here
	 */
	 

	if ( $dp = opendir($icon_path) ) {
		$none_checked = empty($msg_icon) ? ' checked' : '';
	 	$col_pos = 0;
	 	$col_count = 9;
	 	$post_icons = '';
	 	$post_icon_entry = '';
	 	while ( $de = readdir($dp) ) {
	 		if ( $de == '.' || $de == '..' ) continue;
			if ( strlen($de) < 4 ) continue;
			$ext = strtolower(substr($de, -4));
			
			if ( $ext != '.gif' && $ext != '.jpg' && $ext != '.png' ) continue;
			if ( ++$col_pos > $col_count ) { $post_icons_rows .= '{TEMPLATE: post_icon_row}'; $post_icon_entry = ''; $col_pos = 0; }
			
			$checked = ($de==$msg_icon)?' checked':'';
			$post_icon_entry .= '{TEMPLATE: post_icon_entry}';
	 	}
	 	closedir($dp);
	 	if ( $col_pos ) { $post_icons_rows .= '{TEMPLATE: post_icon_row}'; $post_icon_entry = ''; $col_pos = 0; }
	 	
	 	if ( !empty($post_icons_rows) ) $post_icons = '{TEMPLATE: post_icons}';
	}
	 
	if( is_perms(_uid, $__RESOURCE_ID, 'SML') ) {
		$smileys = new fud_smiley;
		$smileys->getall();
		$smileys->resets();
		if ( $smileys->counts() ) {
			$col_count = 25;
			$col_pos = 0;
			
			$post_smiley_entry = $post_smiley_row = '';
			$i=0;
			while ( ($obj = $smileys->eachs()) && ($i++ < $GLOBALS['MAX_SMILIES_SHOWN']) ) {
				if ( ++$col_pos > $col_count ) { $post_smiley_row .= '{TEMPLATE: post_smiley_row}'; $post_smiley_entry=''; $col_pos = 0; }
				$obj->code = ($a=strpos($obj->code, '~')) ? substr($obj->code,0,$a) : $obj->code;
				$post_smiley_entry .= '{TEMPLATE: post_smiley_entry}';
			}
			if ( $col_pos ) $post_smiley_row .= '{TEMPLATE: post_smiley_row}';
			
			$post_smilies = '{TEMPLATE: post_smilies}';
		}
	}
	
	if( $frm->tag_style == 'ML' ) $fud_code_icons = '{TEMPLATE: fud_code_icons}';
	
	$post_options = tmpl_post_options($frm);
	$message_err = get_err('msg_body',1);
	$msg_body = str_replace("\r", "", $msg_body);
	
	if ( is_perms(_uid, $__RESOURCE_ID, 'FILE') ) {	
		/* check if there are any attached files, if so draw a table */
		if ( isset($attach_list) ) {
			$id_list = $file_array = $attached_files = $attachment_list = $attached_status = '';
			foreach($attach_list as $k => $v) {
				if( !empty($v) ) {
					$id_list .= intval($v).',';
				}
				$file_array .= $k.':'.$v."\n";
			}
			
			$file_array_be64 = base64_encode($file_array);
		
			if( !empty($id_list) ) {
				$r = q("SELECT {SQL_TABLE_PREFIX}attach.id,{SQL_TABLE_PREFIX}attach.fsize,{SQL_TABLE_PREFIX}attach.original_name,{SQL_TABLE_PREFIX}mime.mime_hdr FROM {SQL_TABLE_PREFIX}attach LEFT JOIN {SQL_TABLE_PREFIX}mime ON {SQL_TABLE_PREFIX}attach.mime_type={SQL_TABLE_PREFIX}mime.id WHERE {SQL_TABLE_PREFIX}attach.id IN(".substr($id_list,0,-1).")");
				while( $obj = db_rowobj($r) ) {
					$sz = ( $obj->fsize < 100000 ) ? number_format($obj->fsize/1024,2).'KB' : number_format($obj->fsize/1048576,2).'MB';
					
					$insert_uploaded_image = strncasecmp('image/', $obj->mime_hdr, 6) ? '' : '{TEMPLATE: insert_uploaded_image}';
					
					$attached_files .= '{TEMPLATE: attached_file}';
				}
				qf($r);
			}
			
			$attachment_list = '{TEMPLATE: attachment_list}';
			$attached_status = '{TEMPLATE: attached_status}';
		}
		
		if( ($attach_count+1) <= $frm->max_file_attachments ) $upload_file = '{TEMPLATE: upload_file}';
		$allowed_extensions = tmpl_list_ext();
		
		$file_attachments = '{TEMPLATE: file_attachments}';
	}
	else
		$file_attachments = '';

	if( isset($usr) ) {
		$msg_poster_notif_check = $msg_poster_notif=='Y'?' checked':'';
		$msg_show_sig_check = $msg_show_sig=='Y'?' checked':'';
		$reg_user_options = '{TEMPLATE: reg_user_options}';
	}
	
	if( is_perms(_uid, $__RESOURCE_ID, 'SML') ) {
		$msg_smiley_disabled_check = ($msg_smiley_disabled=='Y' ? ' checked' : '');
		$disable_smileys = '{TEMPLATE: disable_smileys}';
	}	
	
	
	if ( $msg_id ) $label = '{TEMPLATE: edit_message}';
	
	if( $GLOBALS["SPELL_CHECK_ENABLED"]=='Y' && function_exists('pspell_config_create') && $GLOBALS['FUD_THEME']->pspell_lang ) $spell_check_button = '{TEMPLATE: spell_check_button}';

	$ret = create_return();

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: POST_PAGE}