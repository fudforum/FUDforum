<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: showposts.php.t,v 1.1.1.1 2002/06/17 23:00:09 hackie Exp $
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
	
	
	if( !is_numeric($id) ) invl_inp_err();
	
	$u = new fud_user();
	$u->get_user_by_id($id);
	
	if( empty($u->id) ) invl_inp_err();
	
	{POST_HTML_PHP}
	$TITLE_EXTRA = ': {TEMPLATE: show_posts_by}';
	
	if ( isset($ses) ) $ses->update('{TEMPLATE: showposts_update}');

	if ( empty($start) ) $start = 0;
	if ( empty($count) ) $count = $THREADS_PER_PAGE;
	
	$fids = get_all_perms(_uid);
	
	if( !empty($fids) || $usr->mod=='A' ) {
		$qry_limit = ( $usr->mod != 'A' ) ? "{SQL_TABLE_PREFIX}forum.id IN (".$fids.") AND " : '';
	
		$total = Q_SINGLEVAL("SELECT 
				count(*) 
			FROM 
				{SQL_TABLE_PREFIX}msg 
			LEFT JOIN {SQL_TABLE_PREFIX}thread 
				ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id 
			LEFT JOIN {SQL_TABLE_PREFIX}forum 
				ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id 
			WHERE
				".$qry_limit."
				{SQL_TABLE_PREFIX}msg.approved='Y' AND 
				{SQL_TABLE_PREFIX}msg.poster_id=".$id." 
			ORDER BY {SQL_TABLE_PREFIX}msg.id");	
		
		$r = Q("SELECT 
				{SQL_TABLE_PREFIX}thread.id AS th_id, 
				{SQL_TABLE_PREFIX}forum.name AS forum_name, 
				{SQL_TABLE_PREFIX}forum.id as fid, 
				{SQL_TABLE_PREFIX}msg.subject AS subject, 
				{SQL_TABLE_PREFIX}msg.id AS id, 
				{SQL_TABLE_PREFIX}msg.post_stamp AS post_stamp 
			FROM 
				{SQL_TABLE_PREFIX}msg 
			LEFT JOIN {SQL_TABLE_PREFIX}thread 
				ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id 
			LEFT JOIN {SQL_TABLE_PREFIX}forum 
				ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id 
			WHERE 
				".$qry_limit."
				{SQL_TABLE_PREFIX}msg.approved='Y' AND 
				{SQL_TABLE_PREFIX}msg.poster_id=".$id." 
			ORDER BY 
				{SQL_TABLE_PREFIX}msg.id DESC 
			LIMIT ".$start.",".$count);
		
		set_row_color_alt(true);
	
		$post_entry='';
		while ( $obj = DB_ROWOBJ($r) ) {
			$style = ROW_BGCOLOR();
			$post_entry .= '{TEMPLATE: post_entry}';
		}
		QF($r);
	
		$pager = tmpl_create_pager($start, $count, $total, '{ROOT}?t=showposts&id='.$id.'&start='.$start.'&'._rsid);
	}
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: SHOWPOSTS_PAGE}