<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ppost.php.t,v 1.2 2002/06/18 18:26:09 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	include_once "GLOBALS.php";	
	{PRE_HTML_PHP}
	
	if( !_uid ) {
		error_dialog('{TEMPLATE: permission_denied_title}', '{TEMPLATE: permission_denied_msg}', '');
		exit;
	}
			
	if( $GLOBALS['PM_ENABLED']=='N' ) {
		error_dialog('{TEMPLATE: pm_err_nopm_title}', '{TEMPLATE: pm_err_nopm_msg}', $WWW_ROOT, '');
		exit;		
	}

	if( ($fldr_size = q_singleval("SELECT SUM(length) FROM {SQL_TABLE_PREFIX}pmsg WHERE duser_id=".$usr->id)) > $MAX_PMSG_FLDR_SIZE ) {
		error_dialog('{TEMPLATE: pm_no_space_title}', '{TEMPLATE: pm_no_space_msg}', '{ROOT}?t=pmsg&'._rsid, '');
		exit;
	}

	$smiley_www = 'images/smiley_icons/';
	$icon_path = 'images/message_icons/';
	$icon_path_www = 'images/message_icons/';
	$returnto = urlencode("{ROOT}?t=ppost&"._rsid);
	$returnto_d = $returnto;
	$attach_control_error=NULL;

	/* INITIAL SECURITY CHECKS */
	
	$flt = new fud_ip_filter;
	if ( isset($GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']) && $flt->is_blocked($GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR']) ) {
		error_dialog('{TEMPLATE: post_err_notallowed_title}', '{TEMPLATE: post_err_notallowed_msg}', $returnto_d);
		exit();
	}
	unset($flt);

	if ( !isset($usr) ) std_error('login');
	is_allowed_user();

	/* remove any slashes from msg_to_list passed by get */
	if( isset($HTTP_GET_VARS['msg_to_list']) ) $HTTP_GET_VARS['msg_to_list'] = $GLOBALS['msg_to_list'] = stripslashes($HTTP_GET_VARS['msg_to_list']);

	if( empty($prev_loaded) ) {
		$msg_r = new fud_pmsg;
		if( !empty($msg_id) && is_numeric($msg_id) ) {
			$msg_r->get($msg_id);
			if( !empty($msg_r->id) ) {
				export_vars('msg_', $msg_r);

				reverse_FMT($msg_subject);
				$msg_subject = apply_reverse_replace($msg_subject);
			
				$msg_body = post_to_smiley($msg_body);
				switch ( $GLOBALS['PRIVATE_TAGS'] )
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
			}	
		}
		else if( is_numeric($quote) || is_numeric($forward) ) {
			$reply_to = $quote;
		
			$msg_r->get((empty($quote)?$forward:$quote));
			if( !empty($msg_r->id) ) {
				export_vars('msg_', $msg_r);
				$msg_id=$msg_to_list=$msg_duser_id='';
				$msg_body = post_to_smiley($msg_body);
				switch ( $GLOBALS['PRIVATE_TAGS'] )
				{
					case 'ML':
						$msg_body = html_to_tags($msg_body);
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
			 	$msg_body = apply_reverse_replace($msg_body)."\n";	
		 	
			 	reverse_FMT($msg_subject);
				$msg_subject = apply_reverse_replace($msg_subject);
			
				if( !empty($quote) && !preg_match("!^Re: !", $msg_subject) ) 
					$msg_subject = 'Re: '.$msg_subject;
			
				if( !empty($forward) && !preg_match("!^Fwd: !", $msg_subject) )
					$msg_subject = 'Fwd: '.$msg_subject;	
			
				if( !empty($quote) ) $msg_to_list = q_singleval("SELECT login FROM {SQL_TABLE_PREFIX}users WHERE id=".$msg_r->ouser_id);
			}	
		}
		else if( !empty($reply) && is_numeric($reply) ) {
			$reply_to = $reply;
		
			$msg_r->get($reply,1);
			if( !empty($msg_r->id) ) {
				$msg_subject = $msg_r->subject;
			
				$msg_to_list = q_singleval("SELECT login FROM {SQL_TABLE_PREFIX}users WHERE id=".$msg_r->ouser_id);
			
				unset($msg_r);
			
				reverse_FMT($msg_subject);
				$msg_subject = apply_reverse_replace($msg_subject);
			
				if( !empty($reply) && !preg_match("!^Re:!", $msg_subject) ) 
					$msg_subject = 'Re: '.$msg_subject;
			}	
		}
		
		if ( !empty($msg_r->attach_cnt) && ( !empty($msg_id) || !empty($forward) ) ) {
	 		$r = q("SELECT * FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$msg_r->id." AND private='Y'");
	 		$attach_count=0;
	 		while ( $obj = db_rowobj($r) ) {
	 			$afile['name'] = $obj->original_name;
	 			$afile['db_id'] = $obj->id;
	 			$afile['size'] = filesize($obj->location);
	 			$afile['tmp'] = $afile['delque'] = NULL;
	 			$attach_list[$obj->id] = $afile;
	 			$attach_count++;
	 		}
	 		qf($r);
		 }
		 
		 if( !empty($reply_to) ) 
		 	$msg_ref_msg_id = 'R'.$reply_to;
		 else if( !empty($forward) ) 
		 	$msg_ref_msg_id = 'F'.$forward;
	}
	else {
		if( empty($preview) && empty($spell) && !empty($btn_action) ) {
			if( $btn_action == 'draft' )
				$HTTP_POST_VARS['btn_draft'] = 1;
			else
				$HTTP_POST_VARS["btn_submit"] = 1;
		}
		
		/* remove slashes */
		while( list($k,) = each($HTTP_POST_VARS) ) {
			if( !empty($HTTP_POST_VARS[$k]) ) $HTTP_POST_VARS[$k] = $GLOBALS[$k] = stripslashes($HTTP_POST_VARS[$k]);
		}
	}

	$MAX_F_SIZE = round($PRIVATE_ATTACH_SIZE/1024);
	
	if( $PRIVATE_ATTACHMENTS > 0 ) {
		/* restore the attachment array */
		$attach_count=0;
		if ( !empty($file_array) ) {
			$file_array = base64_decode(stripslashes($HTTP_POST_VARS['file_array']));
			if ( strlen($file_array) ) {
				$arr = explode("\n", $file_array);
				reset($arr);
				while ( list($k, $v) = each($arr) ) {
					if ( $v ) {
						list($afile['tmp'], $afile['name'], $afile['size'], $afile['delque'], $afile['db_id']) = explode("\r", $v);
						if ( strlen($afile['db_id']) ) 
							$k = $afile['db_id'];
						else
							$k = $afile['tmp'];
							
						$attach_list[$k] = $afile;
						if( $afile['delque']<1 ) $attach_count++;
					}
				}
			}
				
			if ( $file_del_opt && isset($attach_list[$file_del_opt]) ) {
				$attach_list[$file_del_opt]['delque'] = 1;
				$attach_count--;
			}
		}

		if ( !empty($attach_control_size) ) {
			if( $attach_control_size>$PRIVATE_ATTACH_SIZE ) {
				$attach_control_error = '{TEMPLATE: post_err_attach_size}';
			}
			else {
				if( filter_ext($attach_control_name) ) {
					$attach_control_error = '{TEMPLATE: post_err_attach_ext}';
				}
				else {
					if( ($attach_count+1) <= $PRIVATE_ATTACHMENTS ) { 
						unset($afile);
						$afile['tmp'] = safe_tmp_copy($attach_control);
						$afile['name'] = htmlspecialchars(stripslashes($attach_control_name));
						$afile['size'] = $attach_control_size;
						$afile['db_id'] = $afile['delque'] = NULL;
						$attach_list[$afile['tmp']] = $afile;
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

	if( !empty($HTTP_POST_VARS["btn_submit"]) || !empty($HTTP_POST_VARS['btn_draft']) ) {
		$msg_p = new fud_pmsg;
		fetch_vars('msg_', $msg_p, $HTTP_POST_VARS);
		$msg_p->smiley_disabled = yn($msg_smiley_disabled);
		$msg_p->attach_cnt = isset($attach_cnt)?$attach_cnt:0;
		$msg_p->body = $HTTP_POST_VARS['msg_body'];
		$msg_p->folder_id = isset($HTTP_POST_VARS["btn_submit"])?'SENT':'DRAFT';
		$msg_p->to_list = addslashes($msg_p->to_list);
		
		$msg_p->body = apply_custom_replace($msg_p->body);
		switch ( $GLOBALS['PRIVATE_TAGS'] )
		{
			case 'ML':
				$msg_p->body = tags_to_html($msg_p->body, strtoupper($GLOBALS['PRIVATE_IMAGES']));
				break;
			case 'HTML':
				break;
			default:
				$msg_p->body = nl2br(htmlspecialchars($msg_p->body));	
		}
		
		if( $msg_p->smiley_disabled!='Y' ) $msg_p->body = smiley_to_post($msg_p->body);
		fud_wordwrap($msg_p->body);
		
		$msg_p->attach_cnt = empty($attach_cnt)?0:$attach_cnt;
		$msg_p->ouser_id = $usr->id;
		
		$msg_p->subject = apply_custom_replace($msg_p->subject);
		$msg_p->subject = htmlspecialchars($msg_p->subject);
		$msg_p->subject = addslashes($msg_p->subject);
	
		if( !empty($HTTP_POST_VARS['btn_draft']) || !check_ppost_form() ) {
			if( empty($msg_id) ) {
				if( $reply_to ) 
					$msg_p->ref_msg_id = 'R'.$reply_to;
				else if( $forward ) 
					$msg_p->ref_msg_id = 'F'.$forward;
				else
					$msg_p->ref_msg_id = NULL;	

				$msg_p->add();
			}	
			else
				$msg_p->sync();	
				
			if( empty($HTTP_POST_VARS['btn_draft'])	&& !empty($msg_p->ref_msg_id) )
				set_nrf(substr($msg_p->ref_msg_id, 0, 1), substr($msg_p->ref_msg_id, 1));
		}
		
		if( $PRIVATE_ATTACHMENTS>0 && isset($attach_list) ) {
			reset($attach_list);
			while ( list($k, $v) = each($attach_list) ) {
				if( empty($v) ) continue;
				if ( strlen($v['delque']) ) {
					if ( $v['tmp'] ) {
						if( file_exists($GLOBALS['TMP'].$v['tmp']) ) 
							unlink($GLOBALS['TMP'].$v['tmp']);
					}		
					else {
						unset($at_obj);
						$at_obj = new fud_attach();
						if( is_numeric($v['db_id']) ) {
							$at_obj->get($v['db_id'], 'Y');
							if( $at_obj->owner == _uid ) $at_obj->delete();
						}	
					}
				}
				else if( empty($v['db_id']) ) {
					unset($at_obj);
					$at_obj = new fud_attach();
					$at_obj->add($usr->id, $msg_p->id, addslashes($v['name']), $GLOBALS['TMP'].$v['tmp'], 'Y');
					
					if( count($GLOBALS["send_to_array"]) ) {
						reset($GLOBALS["send_to_array"]);
						while( list(,$va) = each($GLOBALS["send_to_array"]) ) {
							unset($at_obj);
							$at_obj = new fud_attach();
							$at_obj->add($va[0], $va[1], addslashes($v['name']), $GLOBALS['TMP'].$v['tmp'], 'Y');
						}
					}
					
					if( file_exists($GLOBALS['TMP'].$v['tmp']) )
						unlink($GLOBALS['TMP'].$v['tmp']);	
				}
				else if( !empty($v['db_id']) && count($GLOBALS["send_to_array"]) ) {
					if( $forward && !bq("SELECT id FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$msg_p->id." AND id=".$v['db_id']) ) {
						unset($at_obj);
						$at_obj = new fud_attach();
						$at_obj->add($usr->id, $msg_p->id, addslashes($v['name']), $GLOBALS['FILE_STORE'].$v['db_id'].'.atch', 'Y');
					}
				
					reset($GLOBALS["send_to_array"]);
					while( list(,$va) = each($GLOBALS["send_to_array"]) ) {
						unset($at_obj);
						$at_obj = new fud_attach();
						$at_obj->add($va[0], $va[1], addslashes($v['name']), $GLOBALS['FILE_STORE'].$v['db_id'].'.atch', 'Y');
					}
				}
			}
		}
		
		if( empty($GLOBALS['__error__']) ) {
			header("Location: {ROOT}?t=pmsg&"._rsid."&folder_id=INBOX");
			exit;
		}	
	}
		
	if ( !empty($reply_to) && $old_subject == $msg_subject ) $no_spell_subject = 1;
	
	if( !empty($HTTP_POST_VARS["btn_spell"]) ) {
		$text = apply_custom_replace($HTTP_POST_VARS["msg_body"]);
		$text_s = apply_custom_replace($HTTP_POST_VARS["msg_subject"]);
		
		switch ( $GLOBALS['PRIVATE_TAGS'] )
		{
			case 'ML':
				$text = tags_to_html($text, strtoupper($GLOBALS['PRIVATE_IMAGES']));
				break;
			case 'HTML':
				break;
			default:
				$text = htmlspecialchars($text);	
		}
		
		if( empty($msg_smiley_disabled) ) $text = smiley_to_post($text);

	 	if( strlen($text) ) {	
			$wa = tokenize_string($text);
			$text = spell_replace($wa,'body');
			
			if( empty($msg_smiley_disabled) ) $msg_body = post_to_smiley($text);
			
			switch ( $GLOBALS['PRIVATE_TAGS'] ) 
			{
				case 'ML':
					$msg_body = html_to_tags($msg_body);
					break;
				case 'HTML':
					break;
				default:
					reverse_FMT($msg_body);		
			}
			
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

	/* form start */	 
	$ses->update('{TEMPLATE: pm_update}');
	
	{POST_HTML_PHP}
	
	$cur_ppage = tmpl_cur_ppage('','');

if ( !empty($preview) || !empty($spell) ) {
	$text = apply_custom_replace($HTTP_POST_VARS['msg_body']);
	$text_s = apply_custom_replace($HTTP_POST_VARS['msg_subject']);

	switch ( $GLOBALS['PRIVATE_TAGS'] ) 
	{
		case 'ML':
			$text = tags_to_html($text, strtoupper($GLOBALS['PRIVATE_IMAGES']));
			break;
		case 'HTML':
			break;
		default:
			$text = nl2br(htmlspecialchars($text));
	}
	
	if ( $PRIVATE_MSG_SMILEY == 'Y' && empty($HTTP_POST_VARS["msg_smiley_disabled"]) ) $text = smiley_to_post($text);
	
	$text_s = htmlspecialchars($text_s);
	
	if( !function_exists('pspell_config_create') ) $spell=0;
	
	if ( !empty($spell) && strlen($text) ) $text = check_data_spell($text,'body');
	
	fud_wordwrap($text);

	$sig=$subj='';
	if ( $text_s ) {
		if ( !empty($spell) && empty($no_spell_subject) && strlen($text_s) )
			$subj .= check_data_spell($text_s,'subject');
		else
			$subj .= $text_s;
	}
	
	if ( $GLOBALS['ALLOW_SIGS']=='Y' && !empty($msg_show_sig) && $msg_show_sig == 'Y' && $usr->sig ) $signature = '{TEMPLATE: signature}';
	if ( !empty($spell) ) $apply_spell_changes = '{TEMPLATE: apply_spell_changes}';
	
	$preview_message = '{TEMPLATE: preview_message}';
}

if ( is_post_error() ) $post_error = '{TEMPLATE: post_error}';

	/*
	 * form begins here
	 */
	$to_err = get_err('msg_to_list');
	$msg_subect_err = get_err('msg_subject');
	 
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
	 
	if( strtolower($GLOBALS['PRIVATE_MSG_SMILEY']) == 'y' ) {
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

	if( strtolower($GLOBALS['PRIVATE_TAGS']) == 'ml' ) $fud_code_icons = '{TEMPLATE: fud_code_icons}';
	
	$post_options = tmpl_post_options('private');
	$message_err = get_err('msg_body',1);
	$msg_body = str_replace("\r", "", $msg_body);
	
	if ( $PRIVATE_ATTACHMENTS > 0 ) {	
		/* check if there are any attached files, if so draw a table */
		if ( isset($attach_list) ) {
			reset($attach_list);
			$file_array=NULL;
			$attached_files='';
			while ( list($k, $v) = each($attach_list) ) {
				$file_array .= $v['tmp']."\r".$v['name']."\r".$v['size']."\r".$v['delque']."\r".$v['db_id']."\n";
				if ( empty($v['delque']) ) {
					if( $v['size'] < 100000 )
						$sz = number_format($v['size']/1024,2).'KB';
					else 
						$sz = number_format($v['size']/1048576,2).'MB';
						
					$attached_files .= '{TEMPLATE: attached_file}';
				}
			}
			$file_array_be64 = base64_encode($file_array);
			if( !empty($attached_files) ) $attachment_list = '{TEMPLATE: attachment_list}';
		}
		if( empty($attach_count) ) 
			$attach_count=0;
		else
			$attached_status = '{TEMPLATE: attached_status}';
		
		if( ($attach_count+1) <= $PRIVATE_ATTACHMENTS ) $upload_file = '{TEMPLATE: upload_file}';
		$allowed_extensions = tmpl_list_ext();
		
		$file_attachments = '{TEMPLATE: file_attachments}';
	}

	$msg_track_check = ( $msg_track == 'Y' ) ? ' checked' : '';
	
	if( !$HTTP_POST_VARS['prev_loaded'] ) $msg_show_sig = $usr->append_sig;
	$msg_show_sig_check = ( $msg_show_sig == 'Y' ) ? ' checked' : '';

	if( strtolower($GLOBALS['PRIVATE_MSG_SMILEY']) == 'y' ) {
		$msg_smiley_disabled_check = ($msg_smiley_disabled=='Y' ? ' checked' : '');
		$disable_smileys = '{TEMPLATE: disable_smileys}';
	}
	
	if( $GLOBALS["SPELL_CHECK_ENABLED"]=='Y' && function_exists('pspell_config_create') ) $spell_check_button = '{TEMPLATE: spell_check_button}';
	
	if( !empty($msg_ref_msg_id) ) {
		$ref_id = substr($msg_ref_msg_id,1);
		$POST_FORM = 1;
		$r = q("SELECT 
			{SQL_TABLE_PREFIX}pmsg.*,
			{SQL_TABLE_PREFIX}users.id AS user_id,
			{SQL_TABLE_PREFIX}users.login,
			{SQL_TABLE_PREFIX}users.invisible_mode,
			{SQL_TABLE_PREFIX}users.posted_msg_count,
			{SQL_TABLE_PREFIX}users.join_date,
			{SQL_TABLE_PREFIX}ses.time_sec
		FROM 
			{SQL_TABLE_PREFIX}pmsg
			INNER JOIN {SQL_TABLE_PREFIX}users 
				ON {SQL_TABLE_PREFIX}pmsg.ouser_id={SQL_TABLE_PREFIX}users.id 
			LEFT JOIN {SQL_TABLE_PREFIX}ses
				ON {SQL_TABLE_PREFIX}users.id={SQL_TABLE_PREFIX}ses.user_id	
		WHERE 
			duser_id=".$usr->id." AND 
			{SQL_TABLE_PREFIX}pmsg.id='".$ref_id."'
		");
		fud_use('drawpmsg.inc');	
		$reference_msg = tmpl_drawpmsg(db_singleobj($r));
		$reference_msg = '{TEMPLATE: reference_msg}';
	}
	
	$ret = create_return();
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: PPOST_PAGE}