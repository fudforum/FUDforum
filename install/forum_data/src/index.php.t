<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: index.php.t,v 1.22 2003/03/31 13:21:21 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	$frm->id = '';
	{PRE_HTML_PHP}

function set_collapse($id, $val)
{
	if (isset($GLOBALS['collapse'][$id])) {
		return;
	}
	$GLOBALS['collapse'][$id] = $val;
}

function reload_collapse($str)
{
	$tok = strtok($str, '_');
	do {
		list($key, $val) = explode(':', $tok);
		if ((int) $key) {
			$GLOBALS['collapse'][(int) $key] = (int) $val;
		}
	} while (($tok = strtok('_')));
}

function url_tog_collapse($id)
{
	if (!isset($GLOBALS['collapse'][$id])) {
		return;
	}

	if (empty($_GET['c'])) {
		$str = $id.':'.(empty($GLOBALS['collapse'][$id]) ? '1' : '0');
	} else {
		if (preg_match('!(^|_)('.$id.':)(0|1)!e', $_GET['c'], $matched)) {
			$val = ($matched[3]=='1')?'0':'1';		
			$str = preg_replace('!(^|_)'.$id.':'.$matched[3].'!', '\1'.$id.':'.$val, $_GET['c']);
		} else {
			$str = $_GET['c'].'_'.$id.':'.(empty($GLOBALS['collapse'][$id]) ? '1' : '0');
		}
	}	
	return $str;	
}

function iscollapsed($id)
{
	if (!isset($GLOBALS['collapse'][$id])) {
		return;
	}
	return $GLOBALS['collapse'][$id];
}

function index_view_perms()
{
	$GLOBALS['NO_VIEW_PERMS'] = array();

	$fl = '';
	$tmp_arr = array();
	$r = uq('SELECT user_id, resource_id, p_READ, p_VISIBLE FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id IN('.(_uid ? _uid.',2147483647' : 0).') AND resource_type=\'forum\' ORDER BY user_id');
	while ($d = db_rowarr($r)) {
		if ($d[3] == 'N') {
			$tmp_arr[$d[1]] = 1;
			continue;
		}
		
		if ($d[0] == _uid) {
			if ($d[2] == 'N') {
				$GLOBALS['NO_VIEW_PERMS'][$d[1]] = $d[1];
			}
			
			$fl .= $d[1].',';
				
			$tmp_arr[$d[1]] = 1;
		} else if (empty($tmp_arr[$d[1]])) {
			if ($d[3] == 'N') {
				continue;
			}
			
			if ($d[2] == 'N') {
				$GLOBALS['NO_VIEW_PERMS'][$d[1]] = $d[1];
			}

			$fl .= $d[1].',';	
		}	
	}	
	qf($r);
	
	if (!empty($fl)) {
		$fl = substr($fl, 0, -1);
	}
	
	return $fl;
}

	if (isset($_GET['c'])) {
		$c = $_GET['c'];
		if (_uid && $c != $usr->cat_collapse_status) {
			q("UPDATE {SQL_TABLE_PREFIX}users SET cat_collapse_status='".$c."' WHERE id="._uid);
		}
		reload_collapse($c);
	} else if (_uid && $usr->cat_collapse_status) {
		$c = $usr->cat_collapse_status;
		reload_collapse($c);
	} else {
		$c = '';
	}

	if (!_uid) {
		$mark_all_read = $welcome_message = $frm_sel = $frm_join = '';
	} else {
		$welcome_message = '{TEMPLATE: welcome_message}';
		$frm_sel = ',{SQL_TABLE_PREFIX}forum_read.last_view ';
		$frm_join = 'LEFT JOIN {SQL_TABLE_PREFIX}forum_read ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}forum_read.forum_id AND {SQL_TABLE_PREFIX}forum_read.user_id='._uid;
		if ($usr->is_mod != 'A') {
			$frm_join .= ' WHERE {SQL_TABLE_PREFIX}forum.id IN ('.intzero(index_view_perms()).')';
		}
		$ses->update('{TEMPLATE: index_update}');
		$returnto = '{ROOT}?t=index&amp;'._rsid.'&amp;c='.$c;
		$mark_all_read = '{TEMPLATE: mark_all_read}';
	}

	{POST_HTML_PHP}
	$TITLE_EXTRA = ': {TEMPLATE: index_title}';

	$forum_list_table_data = '';
	
	/* List of fetched fields & their ids
	  0	msg.subject, 
	  1	msg.id AS msg_id, 
	  2	msg.post_stamp, 
	  3	users.id AS user_id, 
	  4	users.alias
	  5	cat.description, 
	  6	cat.name, 
	  7	cat.default_view, 
	  8	cat.allow_collapse, 
	  9	forum.cat_id,
	  10	forum.forum_icon
	  11	forum.id
	  12	forum.last_post_id
	  13	forum.moderators
	  14	forum.name
	  15	forum.descr
	  16	forum.post_count
	  17	forum.thread_count
	  18	forum_read.last_view
	*/
	$frmres = uq('SELECT {SQL_TABLE_PREFIX}msg.subject,{SQL_TABLE_PREFIX}msg.id AS msg_id,{SQL_TABLE_PREFIX}msg.post_stamp AS msg_post_stamp,{SQL_TABLE_PREFIX}users.id AS user_id,{SQL_TABLE_PREFIX}users.alias,{SQL_TABLE_PREFIX}cat.description,{SQL_TABLE_PREFIX}cat.name AS cat_name,{SQL_TABLE_PREFIX}cat.default_view,{SQL_TABLE_PREFIX}cat.allow_collapse,{SQL_TABLE_PREFIX}forum.cat_id,{SQL_TABLE_PREFIX}forum.forum_icon,{SQL_TABLE_PREFIX}forum.id,{SQL_TABLE_PREFIX}forum.last_post_id,{SQL_TABLE_PREFIX}forum.moderators,{SQL_TABLE_PREFIX}forum.name,{SQL_TABLE_PREFIX}forum.descr,{SQL_TABLE_PREFIX}forum.post_count,{SQL_TABLE_PREFIX}forum.thread_count '.$frm_sel.' FROM {SQL_TABLE_PREFIX}cat INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}cat.id={SQL_TABLE_PREFIX}forum.cat_id LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}forum.last_post_id={SQL_TABLE_PREFIX}msg.id LEFT JOIN {SQL_TABLE_PREFIX}users ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id '.$frm_join.' ORDER BY {SQL_TABLE_PREFIX}cat.view_order, {SQL_TABLE_PREFIX}forum.view_order');

	$cat = 0;	
	while ($r = db_rowarr($frmres)) {
		if ($cat != $r[9]) {
			if ($r[8] == 'Y') {
				set_collapse($r[9], ($r[7] == 'COLLAPSED' ? 1 : 0));
				
				if (iscollapsed($r[9])) {
					$collapse_status = '{TEMPLATE: maximize_category}';
					$collapse_indicator = '{TEMPLATE: collapse_indicator_MAX}';
				} else {
					$collapse_status = '{TEMPLATE: minimize_category}';
					$collapse_indicator = '{TEMPLATE: collapse_indicator_MIN}';
				}
				
				$collapse_url = '{ROOT}?t=index&amp;c='.url_tog_collapse($r[9]).'&amp;'._rsid;
				
				$forum_list_table_data .= '{TEMPLATE: index_category_allow_collapse_Y}';
			} else {
				$forum_list_table_data .= '{TEMPLATE: index_category_allow_collapse_N}';
			}
			$cat = $r[9];
		}
		
		if (iscollapsed($r[9])) {
			continue;
		}
		
		if ($r[10]) {
			$forum_icon = '{TEMPLATE: forum_icon}';
		} else {
			$forum_icon = '{TEMPLATE: no_forum_icon}';
		}
		
		$forum_link = '{ROOT}?t='.t_thread_view.'&amp;frm_id='.$r[11].'&amp;'._rsid;

		if (isset($GLOBALS['NO_VIEW_PERMS'][$r[11]])) {
			$forum_list_table_data .= '{TEMPLATE: forum_with_no_view_perms}';
			continue;
		}
	
		if (_uid && $r[18] < $r[2] && $usr->last_read < $r[2]) {
			$forum_read_indicator = '{TEMPLATE: forum_unread}';
		} else if (_uid) {
			$forum_read_indicator = '{TEMPLATE: forum_read}';
		} else {
			$forum_read_indicator = '{TEMPLATE: forum_no_indicator}';
		}

		if ($r[12]) {
			if ($r[3]) {
				$last_poster_profile = '{TEMPLATE: profile_link_user}';
			} else {
				$last_poster_profile = '{TEMPLATE: profile_link_anon}';
			}
			$last_post = '{TEMPLATE: last_post}';
		} else {
			$last_post = '{TEMPLATE: na}';
		}
		
		if ($r[14] && ($mods = @unserialize($r[13]))) {
			foreach($mods as $k => $v) {
				$moderators .= '{TEMPLATE: profile_link_mod}';	
			}
		} else {
			$moderators = '{TEMPLATE: no_mod}';
		}
		
		$forum_list_table_data .= '{TEMPLATE: index_forum_entry}';
	}
	
	qf($frmres);

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: INDEX_PAGE}
