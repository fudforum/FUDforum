<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: search.php.t,v 1.4 2002/06/19 19:00:29 hackie Exp $
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

	if( $GLOBALS['FORUM_SEARCH'] != 'Y' ) std_error('disabled');
	
	if ( empty($start) ) $start = 0;
	$ppg = ( !empty($usr->posts_ppg) )?$usr->posts_ppg:$GLOBALS['POSTS_PER_PAGE'];
	
	$field = ( !empty($field) ) ? trim($field) : 'all';
	if( empty($forum_limiter) ) $forum_limiter = NULL;

	{POST_HTML_PHP}
	$TITLE_EXTRA = ': {TEMPLATE: search_title}';
	if ( isset($ses) ) $ses->update('{TEMPLATE: search_update}');

	if ( !empty($srch) ) {
		$i = 0;
		$r = search($srch, $field, $start, $ppg, $forum_limiter);
		
		if ( !($total=db_count($r)) ) {
			$search_data = '{TEMPLATE: no_search_results}';
		}
		else {
			if( $start && $start<db_count($r) ) db_seek($r,$start);
		
			$z=$prev_thread_id=0;
			while ( ($obj = db_rowobj($r)) && $z<$ppg ) {
				if ( $obj->thread_id != $prev_thread_id ) 
			
				$body = strip_tags(read_msg_body($obj->offset, $obj->length, $obj->file_id));
				if( strlen($body) > 80 ) $body = substr($body,0,80).'...';
			
				if ( !empty($obj->poster_id) ) {
					$user_login = trim_show_len($obj->login,'LOGIN');
					$poster_info = '{TEMPLATE: registered_poster}';
				}
				else {
					$user_login = trim_show_len($GLOBALS['ANON_NICK'],'LOGIN');
					$poster_info = '{TEMPLATE: unregistered_poster}';
				}
			
				++$i;
				$search_data .= '{TEMPLATE: search_entry}';
				$z++;
			}
			un_register_fps();
			qf($r);
			
			$search_data = '{TEMPLATE: search_results}';
		}
	}
	$page_pager = tmpl_create_pager($start, $ppg, $total, '{ROOT}?t=search&btn_submit=1&srch='.urlencode($srch).'&field='.urlencode($field).'&'._rsid.'&forum_limiter='.$forum_limiter);

	if( $usr->is_mod != 'A' ) {
		$fids = get_all_perms(_uid);
		if( empty($fids) ) $fids = 0;
		
		$qry_limiter = ' WHERE {SQL_TABLE_PREFIX}forum.id IN ('.$fids.') ';
	}
	else
		$qry_limiter = '';

	$r = q("SELECT {SQL_TABLE_PREFIX}forum.id,{SQL_TABLE_PREFIX}forum.name,{SQL_TABLE_PREFIX}cat.id AS cat_id,{SQL_TABLE_PREFIX}cat.name AS cat_name FROM {SQL_TABLE_PREFIX}cat INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}cat.id={SQL_TABLE_PREFIX}forum.cat_id ".$qry_limiter." ORDER BY {SQL_TABLE_PREFIX}cat.view_order,{SQL_TABLE_PREFIX}forum.view_order");
	$old_cat = $forum_limit_data = '';
	if( db_count($r) ) {
		while( $obj = db_rowobj($r) ) {
			$selected = (('c'.$obj->cat_id)==$forum_limiter)?' selected':'';
			if( $old_cat != $obj->cat_id ) {
				$forum_limit_data .= '{TEMPLATE: forum_limit_cat_option}';
				$old_cat = $obj->cat_id;
			}			
			$selected = ($obj->id==$forum_limiter)?' selected':'';
			$forum_limit_data .= '{TEMPLATE: forum_limit_frm_option}';
		}
	}
	qf($r);	
	
	$search_options = tmpl_draw_radio_opt('field', "all\nsubject", "{TEMPLATE: search_entire_msg}\n{TEMPLATE: search_subect_only}", $field, '{TEMPLATE: radio_button}', '{TEMPLATE: radio_button_selected}', '{TEMPLATE: radio_button_separator}');
	
	$srch = stripslashes($srch);
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: SEARCH_PAGE}