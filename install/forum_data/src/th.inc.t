<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: th.inc.t,v 1.12 2002/07/16 23:56:30 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
	
class fud_thread
{
	var $id=NULL;
	var $forum_id=NULL;
	var $root_msg_id=NULL;
	var $last_post_date=NULL;
	var $replies=NULL;
	var $views=NULL;
	var $rating=NULL;
	var $last_post_id=NULL;
	var $locked=NULL;
	var $is_sticky=NULL;
	var $ordertype=NULL;
	var $orderexpiry=NULL;
	
	var $subject=NULL;
	var $db_thread=NULL;
	
	function add($root, $forum_id, $locked=NULL, $is_sticky=NULL, $ordertype=NULL, $orderexpiry=NULL) 
	{
		$r = q("INSERT INTO 
			{SQL_TABLE_PREFIX}thread(
				forum_id, 
				root_msg_id, 
				last_post_date, 
				replies, 
				views, 
				rating, 
				last_post_id,
				locked, 
				is_sticky,
				ordertype,
				orderexpiry
			)
			VALUES
			(
				".$forum_id.",
				".$root.",
				".__request_timestamp__.",
				0,
				0,
				0,
				".$root.",
				'".yn($locked)."',
				'".yn($is_sticky)."',
				".ifnull($ordertype, "'NONE'").",
				".intzero($orderexpiry)."
			)");
		
		$this->id = db_lastid("{SQL_TABLE_PREFIX}thread", $r);
		
		return $this->id;
	}
	
	function sync()
	{
		list($old_sticky,$forum_id) = db_singlearr(q("SELECT is_sticky,forum_id FROM {SQL_TABLE_PREFIX}thread WHERE id=".$this->id));
	
		q("UPDATE {SQL_TABLE_PREFIX}thread SET 
			is_sticky='".yn($this->is_sticky)."',
			ordertype=".ifnull($this->ordertype, "'NONE'").",
			orderexpiry=".intnull($this->orderexpiry).",
			locked='".yn($this->locked)."'
		WHERE id=".$this->id);
		
		if( $old_sticky != yn($this->is_sticky) ) rebuild_forum_view($forum_id);
	}
	
	function get_by_id($id)
	{
		$result = qobj("SELECT 
				{SQL_TABLE_PREFIX}thread.*,
				{SQL_TABLE_PREFIX}msg.subject
			FROM 
				{SQL_TABLE_PREFIX}thread 
				LEFT JOIN {SQL_TABLE_PREFIX}msg 
					ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id 
				LEFT JOIN {SQL_TABLE_PREFIX}forum 
					ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id 
				LEFT JOIN {SQL_TABLE_PREFIX}cat 
					ON {SQL_TABLE_PREFIX}forum.cat_id={SQL_TABLE_PREFIX}cat.id 
			WHERE 
				{SQL_TABLE_PREFIX}thread.id=".$id, $this);
		if ( empty($this->id) ) error_dialog('{TEMPLATE: th_err_invid_title}','{TEMPLATE: th_err_invid_msg}','', 'FATAL');
	}

	function delete($rebuild_view=TRUE)
	{
		$msg = new fud_msg_edit;
		qobj("SELECT * FROM {SQL_TABLE_PREFIX}msg WHERE id=".$this->root_msg_id, $msg);
		if( !empty($msg->id) )
			$msg->delete(FALSE);
		else {
			q("DELETE FROM {SQL_TABLE_PREFIX}thread WHERE id=".$this->id);
			$tc = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$this->forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y'");
			$mc = q_singleval("SELECT SUM(replies) FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$this->forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y'");
			$mc += $tc;
			$lpi = q_singleval("SELECT MAX(last_post_id) FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$this->forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y'");
			q("UPDATE {SQL_TABLE_PREFIX}forum SET post_count=".intzero($mc).", last_post_id=".intzero($lpi).", thread_count=".intzero($tc)." WHERE id=".$this->forum_id);
		}	
		
		if( $rebuild_view ) rebuild_forum_view($this->forum_id);
	}
	
	function lock()
	{
		q("UPDATE {SQL_TABLE_PREFIX}thread SET locked='Y' WHERE id=".$this->id);
	}
	
	function unlock()
	{
		q("UPDATE {SQL_TABLE_PREFIX}thread SET locked='N' WHERE id=".$this->id);
	}
	
	function move($to_forum)
	{
		if ( !db_locked() ) {
			db_lock('{SQL_TABLE_PREFIX}thread_view+, {SQL_TABLE_PREFIX}thread+, {SQL_TABLE_PREFIX}forum+, {SQL_TABLE_PREFIX}msg+');
			$local_lock = 1;
		}
		$msg_count = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}thread LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id WHERE {SQL_TABLE_PREFIX}msg.approved='Y' AND {SQL_TABLE_PREFIX}thread.id=".$this->id);
		
		q("UPDATE {SQL_TABLE_PREFIX}thread SET forum_id=".$to_forum." WHERE id=".$this->id);
		q("UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-".$msg_count." WHERE id=".$this->forum_id);
		q("UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count+1,post_count=post_count+".$msg_count." WHERE id=".$to_forum);
		$r=q("DELETE FROM {SQL_TABLE_PREFIX}thread WHERE forum_id=".$to_forum." AND root_msg_id=".$this->root_msg_id." AND moved_to=".$this->forum_id);
		if( $aff_rows=db_affected($r) ) q("UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-".$aff_rows." WHERE id=".$to_forum);
		q("UPDATE {SQL_TABLE_PREFIX}thread SET moved_to=".$to_forum." WHERE id!=".$this->id." AND root_msg_id=".$this->root_msg_id);
		
		q("INSERT INTO {SQL_TABLE_PREFIX}thread(
			forum_id,
			root_msg_id,
			last_post_date,
			last_post_id,
			moved_to
		)
		VALUES(
			".$this->forum_id.",
			".$this->root_msg_id.",
			".$this->last_post_date.",
			".$this->last_post_id.",
			".$to_forum."
		)");
		
		rebuild_forum_view($this->forum_id);
		rebuild_forum_view($to_forum);
		
		if ( !empty($local_lock) ) db_unlock();
	}
	
	function inc_post_count($val)
	{
		q("UPDATE {SQL_TABLE_PREFIX}thread SET replies=replies+".$val." WHERE id=".$this->id);
	}
	
	function inc_view_count()
	{
		q("UPDATE {SQL_TABLE_PREFIX}thread SET views=views+1 WHERE id=".$this->id);
	}
	
	function dec_view_count($val)
	{
		q("UPDATE {SQL_TABLE_PREFIX}thread SET views=views-1 WHERE id=".$this->id);
	}
	
	function get_notify_list($user_id)
	{
		$ctm = __request_timestamp__;
		$tm = $ctm-604800;
	
		$r = q("SELECT 
				{SQL_TABLE_PREFIX}users.id AS user_id,
				{SQL_TABLE_PREFIX}users.email,
				{SQL_TABLE_PREFIX}users.icq,
				{SQL_TABLE_PREFIX}read.last_view,
				{SQL_TABLE_PREFIX}read.id,
				{SQL_TABLE_PREFIX}users.notify_method 
			FROM 
				{SQL_TABLE_PREFIX}thread_notify
				INNER JOIN {SQL_TABLE_PREFIX}users 
					ON {SQL_TABLE_PREFIX}thread_notify.user_id={SQL_TABLE_PREFIX}users.id 
				LEFT JOIN {SQL_TABLE_PREFIX}read 
					ON {SQL_TABLE_PREFIX}read.user_id={SQL_TABLE_PREFIX}thread_notify.user_id 
					AND {SQL_TABLE_PREFIX}read.thread_id={SQL_TABLE_PREFIX}thread_notify.thread_id 
			WHERE 
				( {SQL_TABLE_PREFIX}read.msg_id=".$this->last_post_id." OR {SQL_TABLE_PREFIX}read.last_view < ".$tm." )
				AND {SQL_TABLE_PREFIX}thread_notify.thread_id=".$this->id." 
				AND {SQL_TABLE_PREFIX}thread_notify.user_id!=".intzero($user_id));
		
		$p = forum_perm_array($this->forum_id);
		$to=NULL;
		while ( $obj = db_rowobj($r) ) {
			if ( !is_allowed($obj->user_id, $p) ) continue;
			
			$to[$obj->notify_method][] = ( $obj->notify_method == 'EMAIL' ) ? $obj->email : $obj->icq.'@pager.icq.com';
			if( $obj->last_view < $tm ) q("UPDATE {SQL_TABLE_PREFIX}read SET last_view=".$ctm." WHERE id=".$obj->id);
		}
		qf($r);
		
		return $to;
	}
	
	function adm_set_rating($value)
	{
		q("UPDATE {SQL_TABLE_PREFIX}thread SET rating=".intnull($value)." WHERE id=".$this->id);
	}

	function has_thread_vote($user_id, $thread_id='')
	{
		if ( !$thread_id ) $thread_id = $this->id;
		
		if( ($rating=q_singleval("SELECT rating FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id=".$thread_id." AND user_id=".$user_id)) ) {
			return $rating;	
		}
		return -1;
	}
	
	function update_vote_count()
	{
		$rating = q_singleval("SELECT ROUND(AVG(rating)) FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id=".$this->id);
		q("UPDATE {SQL_TABLE_PREFIX}thread SET rating=".$rating." WHERE id=".$this->id);
	}
	
	function vote_count($thread_id='')
	{
		if ( !$thread_id ) $thread_id = $this->id;
		
		return q_singleval("SELECT COUNT(*) FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id=".$thread_id);
	}
	
	function register_thread_vote($user_id, $rating, $thread_id='')
	{
		if ( !$thread_id ) $thread_id = $this->id;
		
		if( !db_locked() ) {
			db_lock('{SQL_TABLE_PREFIX}thread_rate_track+, {SQL_TABLE_PREFIX}thread+');
			$local_lock = 1;
		}
		
		if( $this->has_thread_vote($user_id, $thread_id) != -1 ) {
			if ( $local_lock ) db_unlock();
			return;
		}	
		
		q("INSERT INTO {SQL_TABLE_PREFIX}thread_rate_track (
			thread_id,
			user_id,
			stamp,
			rating
			)
		VALUES(
			".$thread_id.",
			".$user_id.",
			".__request_timestamp__.",
			".$rating."
		)");
		
		$rating = q_singleval("SELECT ROUND(AVG(rating)) FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id=".$thread_id);
		
		q("UPDATE {SQL_TABLE_PREFIX}thread SET rating=".$rating." WHERE id=".$thread_id);
		
		if ( $local_lock ) db_unlock();
	}
}

function rebuild_forum_view($forum_id, $page=0)
{
	if( !db_locked() ) {
	        db_lock('{SQL_TABLE_PREFIX}thread_view+, {SQL_TABLE_PREFIX}thread+, {SQL_TABLE_PREFIX}msg+, {SQL_TABLE_PREFIX}forum+');
		$local_lock=1;
	}
	
	$tm = __request_timestamp__;
	
	/* Remove expired moved thread pointers */
	$r=q("DELETE FROM {SQL_TABLE_PREFIX}thread WHERE forum_id=".$forum_id." AND last_post_date<".($tm-86400*$GLOBALS['MOVED_THR_PTR_EXPIRY'])." AND moved_to!=0");
	if( $aff_rows = db_affected($r) ) {
		q("UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-".$aff_rows." WHERE id=".$forum_id);
		$page = 0;
	}
	
	/* De-announce expired announcments and sticky messages */
	$r = q("SELECT {SQL_TABLE_PREFIX}thread.id FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.thread_id WHERE {SQL_TABLE_PREFIX}thread.forum_id=".$forum_id." AND is_sticky='Y' AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry)<".$tm);
	while( list($tid) = db_rowarr($r) ) 
		q("UPDATE {SQL_TABLE_PREFIX}thread SET ordertype='NONE', is_sticky='N' WHERE id=".$tid);
	qf($r);

	if ( __dbtype__ == 'pgsql' ) {
		$tmp_tbl_name = "{SQL_TABLE_PREFIX}ftvt_".get_random_value();
		q("CREATE TEMP TABLE ".$tmp_tbl_name." ( forum_id INT NOT NULL, page INT NOT NULL, thread_id INT NOT NULL, pos SERIAL, tmp INT )");
		
		if( $page ) {
			q("DELETE FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id=".$forum_id." AND page<".($page+1));
			q("INSERT INTO ".$tmp_tbl_name." (thread_id,forum_id,page,tmp) SELECT {SQL_TABLE_PREFIX}thread.id, {SQL_TABLE_PREFIX}thread.forum_id, 2147483647, CASE WHEN is_sticky='Y' AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry>".$tm." OR {SQL_TABLE_PREFIX}thread.orderexpiry=0) THEN 2147483647 ELSE {SQL_TABLE_PREFIX}thread.last_post_date END AS sort_order_fld  FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY sort_order_fld DESC, {SQL_TABLE_PREFIX}thread.last_post_id DESC LIMIT ".($GLOBALS['THREADS_PER_PAGE']*$page).", 0");
		}
		else {
			q("DELETE FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id=".$forum_id);
			q("INSERT INTO ".$tmp_tbl_name." (thread_id,forum_id,page,tmp) SELECT {SQL_TABLE_PREFIX}thread.id, {SQL_TABLE_PREFIX}thread.forum_id, 2147483647, CASE WHEN is_sticky='Y' AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry>".$tm." OR {SQL_TABLE_PREFIX}thread.orderexpiry=0) THEN 2147483647 ELSE {SQL_TABLE_PREFIX}thread.last_post_date END AS sort_order_fld  FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY sort_order_fld DESC, {SQL_TABLE_PREFIX}thread.last_post_id DESC");
		}
		
		q("INSERT INTO {SQL_TABLE_PREFIX}thread_view (thread_id,forum_id,page,pos) SELECT thread_id,forum_id,CEIL(pos/40.0),(pos-(CEIL(pos/40)-1)*40) FROM ".$tmp_tbl_name);
		q("DROP SEQUENCE ".$tmp_tbl_name."_pos_seq");
		q("DROP TABLE ".$tmp_tbl_name);
		return;
	}
	else if ( __dbtype__ == 'mysql' ) {
		if( $page ) {
			q("DELETE FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id=".$forum_id." AND page<".($page+1));
			q("INSERT INTO {SQL_TABLE_PREFIX}thread_view (thread_id,forum_id,page,tmp) SELECT {SQL_TABLE_PREFIX}thread.id, {SQL_TABLE_PREFIX}thread.forum_id, 4294967294, CASE WHEN is_sticky='Y' AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry>".$tm." OR {SQL_TABLE_PREFIX}thread.orderexpiry=0) THEN 4294967294 ELSE {SQL_TABLE_PREFIX}thread.last_post_date END AS sort_order_fld  FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY sort_order_fld DESC, {SQL_TABLE_PREFIX}thread.last_post_id DESC LIMIT 0, ".($GLOBALS['THREADS_PER_PAGE']*$page));
			q("UPDATE {SQL_TABLE_PREFIX}thread_view SET page=CEILING(pos/".$GLOBALS['THREADS_PER_PAGE']."), pos=pos-(CEILING(pos/".$GLOBALS['THREADS_PER_PAGE'].")-1)*".$GLOBALS['THREADS_PER_PAGE']." WHERE forum_id=".$forum_id." AND page=4294967294");			
		}
		else {
			q("DELETE FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id=".$forum_id);
			q("INSERT INTO {SQL_TABLE_PREFIX}thread_view (thread_id,forum_id,page,tmp) SELECT {SQL_TABLE_PREFIX}thread.id, {SQL_TABLE_PREFIX}thread.forum_id, 4294967294, CASE WHEN is_sticky='Y' AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry>".$tm." OR {SQL_TABLE_PREFIX}thread.orderexpiry=0) THEN 4294967294 ELSE {SQL_TABLE_PREFIX}thread.last_post_date END AS sort_order_fld  FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY sort_order_fld DESC, {SQL_TABLE_PREFIX}thread.last_post_id DESC");
			q("UPDATE {SQL_TABLE_PREFIX}thread_view SET page=CEILING(pos/".$GLOBALS['THREADS_PER_PAGE']."), pos=pos-(CEILING(pos/".$GLOBALS['THREADS_PER_PAGE'].")-1)*".$GLOBALS['THREADS_PER_PAGE']." WHERE forum_id=".$forum_id);			
		}
	}
	if( $local_lock ) db_unlock();
}
?>