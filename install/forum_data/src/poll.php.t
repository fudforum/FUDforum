<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: poll.php.t,v 1.6 2002/08/07 12:18:43 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	define('plain_form', 1);
	{PRE_HTML_PHP}
	
	if( !isset($ses) ) 
		std_error('login');
		
	$frm = new fud_forum;
	
	if ( !empty($pl_id) ) {
		if( !strlen($frm_id) ) 
			$frm_id = q_singleval("SELECT forum_id FROM {SQL_TABLE_PREFIX}poll LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}poll.id={SQL_TABLE_PREFIX}msg.poll_id LEFT JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread.id={SQL_TABLE_PREFIX}msg.thread_id WHERE {SQL_TABLE_PREFIX}poll.id=".$pl_id);
		if ( $frm_id ) {
			$frm->get($frm_id);
			$MOD=NULL;
			if ( _uid && ($frm->is_moderator(_uid) || $usr->is_mod == 'A') ) $MOD = 1;
		}
		
		/* check if owner */
		
		$poll = new fud_poll;
		$poll->get($pl_id);
		if ( $poll->owner != _uid && !$MOD ) {
			std_error('access');
			exit();
		}

		if ( !isset($pl_submit) ) {
			$pl_name = $poll->name;
			$pl_max_votes = $poll->max_votes;
			$pl_expiry_date = $poll->expiry_date;
		}
	}
	else if ( !empty($frm_id) ) {
		$frm->get($frm_id);
	}
	else {
		exit('<html><div align="center">Fatal Error<br><a href="#" onClick="javascript:window.close();">Close Window</a></div></html>');
	}	
	
	if ( isset($poll) && !empty($pl_optedit) && empty($pl_upd) ) {
		$a_poll = new fud_poll_opt;
		$a_poll->get($pl_optedit);
		$pl_option = $a_poll->name;
		$ses->putvar('pl_optedit', $a_poll->id);
	}
	else if ( empty($pl_upd) ) $ses->rmvar('pl_optedit');
	
	$ses->save_session(_uid);
	
	if ( isset($poll) && !empty($pl_id) && !empty($pl_submit) ) {
		$poll->name = htmlspecialchars($pl_name);
		$poll->max_votes = $pl_max_votes;
		$poll->expiry_date = $pl_expiry_date;
		$poll->sync();
		header("Location: {ROOT}?t=poll&pl_smiley_disabled=$pl_smiley_disabled&frm_id=".$frm_id."&"._rsidl."&pl_id=".$poll->id);
		exit();
	}
	
	/* Adding or Updating poll options */	
	
	if( isset($poll) && (!empty($pl_upd) || !empty($pl_add)) ) {
		$pl_option = stripslashes($pl_option);
		$pl_option = apply_custom_replace($pl_option);
		
		switch ( $frm->tag_style )
		{
			case 'ML':
				$pl_option = tags_to_html($pl_option, $frm->enable_images);
				break;
			case 'HTML':
				break;
			default:
				$pl_option = nl2br(htmlspecialchars($pl_option));	
		}
	
		if ( is_perms(_uid, $frm->id, 'SML') && empty($HTTP_POST_VARS["pl_smiley_disabled"]) ) $pl_option = smiley_to_post($pl_option);
	
		$pl_option = addslashes($pl_option);
	
		$a_opt = new fud_poll_opt;
	
		if ( !empty($pl_upd) ) {
			$a_opt->get($ses->getvar('pl_optedit'));
			if( strlen(trim($pl_option)) ) { 
				$a_opt->name = $pl_option;
				$a_opt->sync();
			}
			else {
				$a_opt->delete();
			}	
		}
		else if ( !empty($pl_add) ) {
			if( strlen(trim($pl_option)) ) { 
				$a_opt->name = $pl_option;
				$a_opt->poll_id = $poll->id;
				$a_opt->add();
			}	
		}
		
		header("Location: {ROOT}?t=poll&pl_smiley_disabled=$pl_smiley_disabled&frm_id=".$frm_id."&"._rsidl."&pl_id=".$poll->id);
		exit;
	}	
	
	if ( isset($poll) && !empty($del_id) && is_numeric($del_id) ) {
		$a_opt = new fud_poll_opt;
		$a_opt->get($del_id);
		$a_opt->delete();
		header("Location: {ROOT}?t=poll&pl_smiley_disabled=$pl_smiley_disabled&frm_id=".$frm_id."&"._rsidl."&pl_id=".$pl_id);
		exit();
	}
	
	if ( !empty($pl_submit) ) {
		$in_pl = new fud_poll;
		fetch_vars('pl_', $in_pl, $HTTP_POST_VARS);
		$in_pl->owner = $usr->id;
		$id = $in_pl->add();
		header("Location: {ROOT}?t=poll&pl_smiley_disabled=$pl_smiley_disabled&frm_id=".$frm_id."&"._rsidl."&pl_id=".$id);
	 	exit();
	}
	
	$TITLE_EXTRA = ': {TEMPLATE: poll_title}';
	{POST_HTML_PHP}

	$pl_expiry_date_data = tmpl_draw_select_opt("0\n3600\n21600\n43200\n86400\n259200\n604800\n2635200\n31536000", "{TEMPLATE: poll_unlimited}\n1 {TEMPLATE: poll_hour}\n6 {TEMPLATE: poll_hours}\n12 {TEMPLATE: poll_hours}\n1 {TEMPLATE: poll_day}\n3 {TEMPLATE: poll_days}\n1 {TEMPLATE: poll_week}\n1 {TEMPLATE: poll_month}\n1 {TEMPLATE: poll_year}", $pl_expiry_date, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');
	$pl_max_votes_data = tmpl_draw_select_opt("0\n10\n50\n100\n200\n500\n1000\n10000\n100000", "{TEMPLATE: poll_unlimited}\n10\n50\n100\n200\n500\n1000\n10000\n100000", $pl_max_votes, '{TEMPLATE: sel_opt}', '{TEMPLATE: sel_opt_selected}');

	if( is_perms(_uid, $frm->id, 'SML') ) {
		$checked = ($pl_smiley_disabled=='Y') ? ' checked':'';
		$pl_smiley_disabled_chk = '{TEMPLATE: pl_smiley_disabled_chk}';
	}

	$pl_submit = empty($pl_id) ? '{TEMPLATE: pl_submit_create}' : '{TEMPLATE: pl_submit_update}';

	/* 
	 * this is only available on a created poll
	 */
	if ( !empty($pl_id) ) { 
		if( empty($pl_option) ) $pl_option = '';
		$pl_option = post_to_smiley($pl_option);
		
		switch( $frm->tag_style )
	 	{
	 		case 'ML':
	 			$pl_option = html_to_tags($pl_option);
	 			break;
	 		case 'HTML':
	 			break;
	 		default:
	 			reverse_nl2br($pl_option);
	 	}
	 	
		$pl_option = apply_reverse_replace($pl_option);
		$pl_action = empty($pl_optedit) ? '{TEMPLATE: pl_add}' : '{TEMPLATE: pl_upd}';
	
		$opt = new fud_poll_opt;
		$opt->get_poll($pl_id);
		$opt->reset_opt();
		
		$poll_option_entry_data = '';
		while ( $obj=$opt->next_opt() ) {
			$poll_option_entry_data .= '{TEMPLATE: poll_option_entry}';
		}
		
		reverse_FMT($pl_name);
		
		$poll_editor = '{TEMPLATE: poll_editor}';
	} 
	/* end of poll option code */
	
	$poll_submit_btn = empty($pl_id) ? '{TEMPLATE: btn_submit}' : '{TEMPLATE: btn_update}';
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: POLL_PAGE}