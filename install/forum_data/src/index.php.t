<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: index.php.t,v 1.12 2002/07/31 21:56:50 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	{PRE_HTML_PHP}

function set_collapse($id, $val)
{
	if ( isset($GLOBALS['collapse'][$id]) ) return;
	$GLOBALS['collapse'][$id] = $val;
}

function reload_collapse($str)
{
	$arr = explode('_', $str);	
	foreach( $arr as $line ) {
		list($key, $val) = explode(':', $line);
		if ( empty($key) ) continue;
		$GLOBALS['collapse'][$key] = $val;
	}
}

function url_tog_collapse($id)
{
	if ( !isset($GLOBALS['collapse'][$id]) ) return;

	if( empty($GLOBALS['HTTP_GET_VARS']['c']) ) 
		$str = $id.':'.(empty($GLOBALS['collapse'][$id]) ? '1' : '0');
	else {
		if( preg_match('!(^|_)('.$id.':)(0|1)!e', $GLOBALS['HTTP_GET_VARS']['c'], $matched) ) {
			$val = ($matched[3]=='1')?'0':'1';		
			$str = preg_replace('!(^|_)'.$id.':'.$matched[3].'!', '\1'.$id.':'.$val, $GLOBALS['HTTP_GET_VARS']['c']);
		}
		else
			$str .= $GLOBALS['HTTP_GET_VARS']['c'].'_'.$id.':'.(empty($GLOBALS['collapse'][$id]) ? '1' : '0');
	}	
	return $str;	
}

function iscollapsed($id)
{
	if ( !isset($GLOBALS['collapse'][$id]) ) return;
	return $GLOBALS['collapse'][$id];
}

function index_view_perms($usr_id)
{
	$GLOBALS['NO_VIEW_PERMS'] = array();

	if( empty($usr_id) ) 
		$usr_id = $usr_str = 0;
	else 
		$usr_str = $usr_id.',2147483647';

	$fl = '';
	$tmp_arr = array();
	$r = q("SELECT user_id,resource_id,p_READ AS p_read,p_VISIBLE AS p_visible FROM {SQL_TABLE_PREFIX}group_cache WHERE user_id IN(".$usr_str.") AND resource_type='forum' ORDER BY user_id");
	while( $obj = db_rowobj($r) ) {
		if( $obj->p_visible == 'N' ) {
			$tmp_arr[$obj->resource_id] = 1;
			continue;
		}
		
		if( $obj->user_id == $usr_id ) {
			if( $obj->p_read == 'N' ) 
				$GLOBALS['NO_VIEW_PERMS'][$obj->resource_id] = $obj->resource_id;
			
			$fl .= $obj->resource_id.',';
				
			$tmp_arr[$obj->resource_id] = 1;
		}
		else if( empty($tmp_arr[$obj->resource_id]) ) {
			if( $obj->p_visible == 'N' ) continue;
			
			if( $obj->p_read == 'N' ) 
				$GLOBALS['NO_VIEW_PERMS'][$obj->resource_id] = $obj->resource_id;

			$fl .= $obj->resource_id.',';	
		}	
	}	
	qf($r);
	unset($tmp_arr);
	
	if( !empty($fl) ) $fl = substr($fl, 0, -1);
	
	return $fl;
}

	/*----------------- END FORM FUNCTIONS --------------------*/

	if ( !empty($c) ) reload_collapse($c);
		
	if ( isset($ses) ) $ses->update('{TEMPLATE: index_update}');
	
	{POST_HTML_PHP}
	$TITLE_EXTRA = ': {TEMPLATE: index_title}';

	if ( isset($usr) ) {
		$last_login = $usr->last_visit;
		$welcome_message = '{TEMPLATE: welcome_message}';
		$frm_sel = '{SQL_TABLE_PREFIX}forum_read.last_view,';
		$frm_join = 'LEFT JOIN {SQL_TABLE_PREFIX}forum_read ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}forum_read.forum_id AND {SQL_TABLE_PREFIX}forum_read.user_id='.$usr->id;
	}
	else $frm_sel=$frm_join='';
	
	$forum_list_table_data = '';
	
	if( $usr->is_mod != 'A' ) {
		$lmt = index_view_perms(_uid);
		if( !$lmt ) $lmt = 0;
		$qry_limit = " WHERE {SQL_TABLE_PREFIX}forum.id IN (".$lmt.")";
	}	
	
	$frmres = q("SELECT {SQL_TABLE_PREFIX}cat.description, {SQL_TABLE_PREFIX}cat.name AS cat_name, {SQL_TABLE_PREFIX}cat.default_view, {SQL_TABLE_PREFIX}cat.allow_collapse, {SQL_TABLE_PREFIX}forum.*, ".$frm_sel." {SQL_TABLE_PREFIX}msg.id AS msg_id, {SQL_TABLE_PREFIX}msg.post_stamp AS msg_post_stamp, {SQL_TABLE_PREFIX}users.id AS user_id, {SQL_TABLE_PREFIX}users.alias AS user_login
		FROM 
			{SQL_TABLE_PREFIX}cat
			INNER JOIN {SQL_TABLE_PREFIX}forum 
				ON {SQL_TABLE_PREFIX}cat.id={SQL_TABLE_PREFIX}forum.cat_id
			LEFT JOIN {SQL_TABLE_PREFIX}msg 
				ON {SQL_TABLE_PREFIX}forum.last_post_id={SQL_TABLE_PREFIX}msg.id 
			LEFT JOIN {SQL_TABLE_PREFIX}users 
				ON {SQL_TABLE_PREFIX}msg.poster_id={SQL_TABLE_PREFIX}users.id
			".$frm_join.$qry_limit."
			ORDER BY 
				{SQL_TABLE_PREFIX}cat.view_order,
				{SQL_TABLE_PREFIX}forum.view_order
		");
	$cat=0;	
	
	while ( $data = db_rowobj($frmres) ) {
		if( $cat != $data->cat_id ) {
			if ( $data->allow_collapse == 'Y' ) {
				set_collapse($data->cat_id, (($data->default_view=='COLLAPSED')?'1':'0'));
				
				if( iscollapsed($data->cat_id) ) {
					$collapse_status = '{TEMPLATE: maximize_category}';
					$collapse_indicator = '{TEMPLATE: collapse_indicator_MAX}';
				}
				else {
					$collapse_status = '{TEMPLATE: minimize_category}';
					$collapse_indicator = '{TEMPLATE: collapse_indicator_MIN}';
				}
				
				$collapse_url = '{ROOT}?t=index&c='.url_tog_collapse($data->cat_id).'&'._rsid;
				
				$forum_list_table_data .= '{TEMPLATE: index_category_allow_collapse_Y}';
			}
			else {
				$forum_list_table_data .= '{TEMPLATE: index_category_allow_collapse_N}';
			}
			$cat = $data->cat_id;
		}
		
		if( iscollapsed($data->cat_id) ) continue;

		
		if ( $data->forum_icon ) 
			$forum_icon = '{TEMPLATE: forum_icon}';
		else
			$forum_icon = '{TEMPLATE: no_forum_icon}';
		
		$forum_link = '{ROOT}?t=thread&frm_id='.$data->id.'&'._rsid;

		if( isset($GLOBALS['NO_VIEW_PERMS'][$data->id]) ) {
			
			$forum_list_table_data .= '{TEMPLATE: forum_with_no_view_perms}';
			continue;
		}
	
		if ( isset($usr) && $data->last_view < $data->msg_post_stamp && $usr->last_read < $data->msg_post_stamp )
			$forum_read_indicator = '{TEMPLATE: forum_unread}';
		else if( isset($usr) ) 
			$forum_read_indicator = '{TEMPLATE: forum_read}';
		else
			$forum_read_indicator = '{TEMPLATE: forum_no_indicator}';			

		if( $data->last_post_id ) {
			if( !empty($data->user_id) ) {
				$profile_link = '{ROOT}?t=usrinfo&id='.$data->user_id.'&'._rsid;
				$last_poster_profile = '{TEMPLATE: profile_link_user}';
			}	
			else
				$last_poster_profile = '{TEMPLATE: profile_link_anon}';
				
			$last_post_link	= '{ROOT}?t='.d_thread_view.'&goto='.$data->last_post_id.'&'._rsid;
			$last_post_link = '{TEMPLATE: last_post_link}';
			
			$last_post = '{TEMPLATE: last_post}';
		}
		else {
			$last_post = '{TEMPLATE: na}';
		}
		
		if( $data->moderators ) {
			$ma = explode("\n\n", $data->moderators);
			$moderators = '';
			foreach($ma as $v) { 
				list($mod_id,$mod_name) = explode("\n", $v);
				$mod_link = '{ROOT}?t=usrinfo&id='.$mod_id.'&'._rsid;
				$moderators .= '{TEMPLATE: profile_link_mod}';
			}
		}
		else $moderators = '{TEMPLATE: no_mod}';
		
		$forum_list_table_data .= '{TEMPLATE: index_forum_entry}';
	}
	
	qf($frmres);

	if( isset($usr) ) {
		$mark_read_link = '{ROOT}?t=markread&'._rsid.'&returnto='.urlencode('{ROOT}?t=index&'._rsid.'&c='.(isset($c)?$c:''));
		$mark_all_read = '{TEMPLATE: mark_all_read}';
	}
	else $mark_all_read = '';		
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: INDEX_PAGE}
