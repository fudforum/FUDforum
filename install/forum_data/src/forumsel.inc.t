<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: forumsel.inc.t,v 1.5 2003/03/30 12:44:52 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function tmpl_create_forum_select($frm_id)
{
	if (_uid) {
		$frm_sel = ',{SQL_TABLE_PREFIX}forum_read.last_view ';
		$frm_join = ' LEFT JOIN {SQL_TABLE_PREFIX}forum_read ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}forum_read.forum_id AND {SQL_TABLE_PREFIX}forum_read.user_id='._uid;
	} else {
		$frm_sel = $frm_join = '';
	}
		
	if ($GLOBALS['usr']->is_mod != 'A') {
		$qry_limit = ' WHERE {SQL_TABLE_PREFIX}forum.id IN ('.intzero(get_all_perms(_uid)).') ';
	} else {
		$qry_limit = '';
	}
		
	$frmres = uq('SELECT {SQL_TABLE_PREFIX}forum.id, {SQL_TABLE_PREFIX}forum.name, {SQL_TABLE_PREFIX}cat.name, {SQL_TABLE_PREFIX}cat.id, {SQL_TABLE_PREFIX}msg.post_stamp '.$frm_sel.' FROM  {SQL_TABLE_PREFIX}cat INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}cat.id={SQL_TABLE_PREFIX}forum.cat_id LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}forum.last_post_id={SQL_TABLE_PREFIX}msg.id '.$frm_join.$qry_limit.' ORDER BY {SQL_TABLE_PREFIX}cat.view_order, {SQL_TABLE_PREFIX}forum.view_order');
	
	$prev_cat_id = 0;
	$selection_options = '';
	if (($r = db_rowarr($frmres))) {
		do {
			if ($prev_cat_id != $r[3]) {
				$prev_cat_id = $r[3];
				$selection_options .= '{TEMPLATE: category_option}';
			}
			$selected = $frm_id == $r[0] ? ' selected' : '';
			$selection_options .= (_uid && $r[5] < $r[4]) ? '{TEMPLATE: unread_forum_option}' : '{TEMPLATE: forum_option}';
		
		} while (($r = db_rowarr($frmres)));
		$selection_options = '{TEMPLATE: forum_select}';
	}

	qf($frmres);
	return $selection_options;
}

if (isset($_POST['dst_frm_id'])) {
	header("Location: {ROOT}?t=".t_thread_view."&frm_id=".$_POST['dst_frm_id']."&"._rsidl);
	exit();
}

	$forum_select = tmpl_create_forum_select($frm->id);
?>