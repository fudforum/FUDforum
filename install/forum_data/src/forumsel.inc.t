<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: forumsel.inc.t,v 1.3 2002/08/07 12:18:43 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	
function tmpl_create_forum_select()
{
	global $frm_id;
	global $start;
	global $count;
	global $th_id;
	global $rid;
	global $th;

	if ( isset($GLOBALS['usr']) ) {
		$frm_sel = '{SQL_TABLE_PREFIX}forum_read.last_view,';
		$frm_join = 'LEFT JOIN {SQL_TABLE_PREFIX}forum_read ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}forum_read.forum_id AND {SQL_TABLE_PREFIX}forum_read.user_id='.$GLOBALS['usr']->id;
	}
	else $frm_sel=$frm_join='';
		
	if( $GLOBALS['usr']->is_mod != 'A' ) {
		$lmt = get_all_perms(_uid);
		if( !$lmt ) $lmt = 0;
		$qry_limit = " WHERE {SQL_TABLE_PREFIX}forum.id IN (".$lmt.") ";
	}		
		
	$frmres = q("SELECT 
		{SQL_TABLE_PREFIX}forum.id,
		{SQL_TABLE_PREFIX}forum.name,
		".$frm_sel."
		{SQL_TABLE_PREFIX}cat.name AS cat_name,
		{SQL_TABLE_PREFIX}cat.id AS cat_id,
		{SQL_TABLE_PREFIX}msg.post_stamp AS msg_post_stamp
	FROM 
		{SQL_TABLE_PREFIX}cat
		INNER JOIN {SQL_TABLE_PREFIX}forum
			ON {SQL_TABLE_PREFIX}cat.id={SQL_TABLE_PREFIX}forum.cat_id
		LEFT JOIN {SQL_TABLE_PREFIX}msg
			ON {SQL_TABLE_PREFIX}forum.last_post_id={SQL_TABLE_PREFIX}msg.id	
		".$frm_join.$qry_limit."
	ORDER BY 
		{SQL_TABLE_PREFIX}cat.view_order, {SQL_TABLE_PREFIX}forum.view_order
	");
	
	$prev_cat_id = 0;
	if ( db_count($frmres) ) {
		$selection_options = '';
		
		while ( $obj = db_rowobj($frmres) ) {
			if ( $prev_cat_id != $obj->cat_id ) {
				$prev_cat_id = $obj->cat_id;
				$selection_options .= '{TEMPLATE: category_option}';
			}
			
			$selected = ( $frm_id == $obj->id ) ? ' selected' : '';
			$selection_options .= ( isset($GLOBALS['usr']) && $obj->last_view < $obj->msg_post_stamp ) ? '{TEMPLATE: unread_forum_option}' : '{TEMPLATE: forum_option}';
		}
	}
	qf($frmres);

	return '{TEMPLATE: forum_select}';
}

if ( !empty($dst_frm_id) ) {
	header("Location: {ROOT}?t=thread&frm_id=".$dst_frm_id."&"._rsidl);
	exit();
}

	$forum_select = tmpl_create_forum_select();
?>