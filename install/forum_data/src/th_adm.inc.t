<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: th_adm.inc.t,v 1.1 2003/04/21 14:14:39 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function th_add($root, $forum_id, $last_post_date, $locked, $is_sticky, $ordertype, $orderexpiry, $replies=0, $lpi=0) 
{
	if (!$lpi) {
		$lpi = $root;
	}

	return db_qid("INSERT INTO 
		{SQL_TABLE_PREFIX}thread
			(forum_id, root_msg_id, last_post_date, replies, views, rating, last_post_id, locked, is_sticky, ordertype, orderexpiry)
		VALUES 
			(".$forum_id.", ".$root.", ".$last_post_date.", ".$replies.", 0, 0, ".$lpi.", '".$locked."', '".$is_sticky."', ".$ordertype.", ".$orderexpiry.")");
}

function th_move($id, $to_forum, $root_msg_id, $forum_id, $last_post_date, $last_post_id)
{
	if (!db_locked()) {
		db_lock('{SQL_TABLE_PREFIX}poll WRITE, {SQL_TABLE_PREFIX}thread_view WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}msg WRITE');
		$ll = 1;
	}
	$msg_count = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}thread LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id WHERE {SQL_TABLE_PREFIX}msg.approved='Y' AND {SQL_TABLE_PREFIX}thread.id=".$id);

	q('UPDATE {SQL_TABLE_PREFIX}thread SET forum_id='.$to_forum.' WHERE id='.$id);
	q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-'.$msg_count.' WHERE id='.$forum_id);
	q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count+1,post_count=post_count+'.$msg_count.' WHERE id='.$to_forum);
	if (($aff_rows = db_affected(q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE forum_id='.$to_forum.' AND root_msg_id='.$root_msg_id.' AND moved_to='.$forum_id)))) {
		q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-'.$aff_rows.' WHERE id='.$to_forum);
	}
	q('UPDATE {SQL_TABLE_PREFIX}thread SET moved_to='.$to_forum.' WHERE id!='.$id.' AND root_msg_id='.$root_msg_id);

	q('INSERT INTO {SQL_TABLE_PREFIX}thread 
		(forum_id, root_msg_id, last_post_date, last_post_id, moved_to) 
	VALUES 
		('.$forum_id.', '.$root_msg_id.', '.$last_post_date.', '.$last_post_id.', '.$to_forum.')');

	rebuild_forum_view($forum_id);
	rebuild_forum_view($to_forum);

	$c = q('SELECT poll_id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$id.' AND approved=\'Y\' AND poll_id>0');
	while ($r = db_rowarr($c)) {
		$p[] = $r[0];
	}
	qf($c);
	if (isset($p)) {
		q('UPDATE {SQL_TABLE_PREFIX}poll SET forum_id='.$to_forum.' WHERE id IN('.implode(',', $p).')');
	}

	if (isset($ll)) {
		db_unlock();
	}
}

function rebuild_forum_view($forum_id, $page=0)
{
	if (!db_locked()) {
	        db_lock('{SQL_TABLE_PREFIX}thread_view WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}msg WRITE, {SQL_TABLE_PREFIX}forum WRITE');
		$ll = 1;
	}
	
	$tm = __request_timestamp__;
	
	/* Remove expired moved thread pointers */
	if (($aff_rows = db_affected(q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE forum_id='.$forum_id.' AND last_post_date<'.($tm-86400*$GLOBALS['MOVED_THR_PTR_EXPIRY']).' AND moved_to!=0')))) {
		q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-'.$aff_rows.' WHERE id='.$forum_id);
		$page = 0;
	}
	
	/* De-announce expired announcments and sticky messages */
	$r = q("SELECT {SQL_TABLE_PREFIX}thread.id FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}thread.forum_id=".$forum_id." AND is_sticky='Y' AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry)<=".$tm);
	while ($tid = db_rowarr($r)) {
		q("UPDATE {SQL_TABLE_PREFIX}thread SET orderexpiry=0, ordertype='NONE', is_sticky='N' WHERE id=".$tid[0]);
	}
	qf($r);

	if (__dbtype__ == 'pgsql') {
		$tmp_tbl_name = "{SQL_TABLE_PREFIX}ftvt_".get_random_value();
		q("CREATE TEMP TABLE ".$tmp_tbl_name." ( forum_id INT NOT NULL, page INT NOT NULL, thread_id INT NOT NULL, pos SERIAL, tmp INT )");
		
		if ($page) {
			q("DELETE FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id=".$forum_id." AND page<".($page+1));
			q("INSERT INTO ".$tmp_tbl_name." (thread_id,forum_id,page,tmp) SELECT {SQL_TABLE_PREFIX}thread.id, {SQL_TABLE_PREFIX}thread.forum_id, 2147483647, CASE WHEN is_sticky='Y' AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry>".$tm." OR {SQL_TABLE_PREFIX}thread.orderexpiry=0) THEN 2147483647 ELSE {SQL_TABLE_PREFIX}thread.last_post_date END AS sort_order_fld  FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY sort_order_fld DESC, {SQL_TABLE_PREFIX}thread.last_post_id DESC LIMIT ".($GLOBALS['THREADS_PER_PAGE']*$page));
		} else {
			q("DELETE FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id=".$forum_id);
			q("INSERT INTO ".$tmp_tbl_name." (thread_id,forum_id,page,tmp) SELECT {SQL_TABLE_PREFIX}thread.id, {SQL_TABLE_PREFIX}thread.forum_id, 2147483647, CASE WHEN is_sticky='Y' AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry>".$tm." OR {SQL_TABLE_PREFIX}thread.orderexpiry=0) THEN 2147483647 ELSE {SQL_TABLE_PREFIX}thread.last_post_date END AS sort_order_fld  FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY sort_order_fld DESC, {SQL_TABLE_PREFIX}thread.last_post_id DESC");
		}
		
		q("INSERT INTO {SQL_TABLE_PREFIX}thread_view (thread_id,forum_id,page,pos) SELECT thread_id,forum_id,CEIL(pos/".$GLOBALS['THREADS_PER_PAGE'].".0),(pos-(CEIL(pos/".$GLOBALS['THREADS_PER_PAGE'].".0)-1)*".$GLOBALS['THREADS_PER_PAGE'].") FROM ".$tmp_tbl_name);
		q("DROP TABLE ".$tmp_tbl_name);
		return;
	} else if (__dbtype__ == 'mysql') {
		if ($page) {
			q('DELETE FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id='.$forum_id.' AND page<'.($page+1));
			q("INSERT INTO {SQL_TABLE_PREFIX}thread_view (thread_id,forum_id,page,tmp) SELECT {SQL_TABLE_PREFIX}thread.id, {SQL_TABLE_PREFIX}thread.forum_id, 4294967294, CASE WHEN is_sticky='Y' AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry>".$tm." OR {SQL_TABLE_PREFIX}thread.orderexpiry=0) THEN 4294967294 ELSE {SQL_TABLE_PREFIX}thread.last_post_date END AS sort_order_fld  FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY sort_order_fld DESC, {SQL_TABLE_PREFIX}thread.last_post_id DESC LIMIT 0, ".($GLOBALS['THREADS_PER_PAGE']*$page));
			q('UPDATE {SQL_TABLE_PREFIX}thread_view SET page=CEILING(pos/'.$GLOBALS['THREADS_PER_PAGE'].'), pos=pos-(CEILING(pos/'.$GLOBALS['THREADS_PER_PAGE'].')-1)*'.$GLOBALS['THREADS_PER_PAGE'].' WHERE forum_id='.$forum_id.' AND page=4294967294');			
		} else {
			q('DELETE FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id='.$forum_id);
			q("INSERT INTO {SQL_TABLE_PREFIX}thread_view (thread_id,forum_id,page,tmp) SELECT {SQL_TABLE_PREFIX}thread.id, {SQL_TABLE_PREFIX}thread.forum_id, 4294967294, CASE WHEN is_sticky='Y' AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry>".$tm." OR {SQL_TABLE_PREFIX}thread.orderexpiry=0) THEN 4294967294 ELSE {SQL_TABLE_PREFIX}thread.last_post_date END AS sort_order_fld  FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY sort_order_fld DESC, {SQL_TABLE_PREFIX}thread.last_post_id DESC");
			q('UPDATE {SQL_TABLE_PREFIX}thread_view SET page=CEILING(pos/'.$GLOBALS['THREADS_PER_PAGE'].'), pos=pos-(CEILING(pos/'.$GLOBALS['THREADS_PER_PAGE'].')-1)*'.$GLOBALS['THREADS_PER_PAGE'].' WHERE forum_id='.$forum_id);			
		}
	}

	if (isset($ll)) {
		db_unlock();
	}
}

?>