<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: polllist.php.t,v 1.1 2002/07/21 22:13:20 hackie Exp $
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
	
	if ( isset($ses) ) $ses->update('{TEMPLATE: polllist_update}');
	
	{POST_HTML_PHP}
	
	$poll_vote_counts = array();
	$r = q("select poll_id,sum(count) FROM {SQL_TABLE_PREFIX}poll_opt GROUP BY poll_id");	
	while( list($pid,$sum) = db_rowarr($r) ) $poll_vote_counts[$pid] = intzero($sum);
	qf($r);
	
	if( $HTTP_GET_VARS['oby'] == 'ASC' ) {
		$oby = 'ASC';
		$oby_rev_val = 'DESC';
	}
	else {
                $oby = 'DESC';
		$oby_rev_val = 'ASC';	
	}
	
	$allowed_forums = ( $usr->is_mod != 'A' ) ? " AND {SQL_TABLE_PREFIX}thread.forum_id IN(".get_all_perms(_uid).")" : "";
	
	$uid_limit = intval($HTTP_GET_VARS['uid']) ? " AND {SQL_TABLE_PREFIX}poll.owner=$uid " : "";
	
	$ttl = intzero(q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}poll INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}poll.id={SQL_TABLE_PREFIX}msg.poll_id INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id WHERE {SQL_TABLE_PREFIX}msg.approved='Y'".$allowed_forums.$uid_limit));
				    
	if( !is_numeric($start) || $start > $ttl ) $start = 0;
	
	$r = q("SELECT 
			{SQL_TABLE_PREFIX}poll.*,
			{SQL_TABLE_PREFIX}poll_opt_track.user_id,
			{SQL_TABLE_PREFIX}users.alias AS login,
			{SQL_TABLE_PREFIX}users.last_visit,
			{SQL_TABLE_PREFIX}users.invisible_mode,
			{SQL_TABLE_PREFIX}msg.id AS msg_id,
			{SQL_TABLE_PREFIX}msg.approved,
			{SQL_TABLE_PREFIX}thread.locked
		FROM {SQL_TABLE_PREFIX}poll 
		LEFT JOIN {SQL_TABLE_PREFIX}poll_opt_track ON 
			{SQL_TABLE_PREFIX}poll.id={SQL_TABLE_PREFIX}poll_opt_track.poll_id AND {SQL_TABLE_PREFIX}poll_opt_track.user_id="._uid."
		INNER JOIN {SQL_TABLE_PREFIX}msg ON
			{SQL_TABLE_PREFIX}poll.id={SQL_TABLE_PREFIX}msg.poll_id	
		INNER JOIN {SQL_TABLE_PREFIX}thread ON
			{SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id
		LEFT JOIN {SQL_TABLE_PREFIX}users ON
			{SQL_TABLE_PREFIX}poll.owner={SQL_TABLE_PREFIX}users.id
		WHERE
			{SQL_TABLE_PREFIX}msg.approved='Y'
			".$allowed_forums.$uid_limit."
		ORDER BY {SQL_TABLE_PREFIX}poll.creation_date ".$oby." LIMIT ".qry_limit($GLOBALS['POLLS_PER_PAGE'], $start));
		
	$poll_entries = '';	
	while( $obj = db_rowobj($r) ) {
		if( 	_uid && 
			!$obj->user_id && 
			!($obj->max_votes && $poll_vote_counts[$obj->id] >= $obj->max_votes) && 
			!($obj->expiry_date && ($obj->creation_date+$obj->expiry_date) > __request_timestamp__) &&
			$obj->locked == 'N'
		) 
			$vote_lnk = '{TEMPLATE: vote_lnk}';
		else
			$vote_lnk = '';
			
		if( $obj->owner && ($obj->invisible_mode=='N' || $usr->is_mod == 'A') && $GLOBALS['ONLINE_OFFLINE_STATUS'] == 'Y' ) {
			$user_login = htmlspecialchars($obj->login);
		
			if( ($obj->last_visit + $GLOBALS['LOGEDIN_TIMEOUT']*60) > __request_timestamp__ ) 
				$online_indicator = '{TEMPLATE: polllist_online_indicator}';
			else
				$online_indicator = '{TEMPLATE: polllist_offline_indicator}';	
		}
		else
			$online_indicator = '';	
			
		$poll_entries .= '{TEMPLATE: poll_entry}';
	}
	qf($r);
	
	$pager = tmpl_create_pager($start, $GLOBALS['POLLS_PER_PAGE'], $ttl, '{ROOT}?t=polllist&oby='.$oby);
	
	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: POLLLIST_PAGE}