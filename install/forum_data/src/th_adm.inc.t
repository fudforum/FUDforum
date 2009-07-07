<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: th_adm.inc.t,v 1.50 2009/07/07 20:28:57 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

function th_add($root, $forum_id, $last_post_date, $thread_opt, $orderexpiry, $replies=0, $views=0, $lpi=0, $descr='')
{
	if (!$lpi) {
		$lpi = $root;
	}

	return db_qid("INSERT INTO
		{SQL_TABLE_PREFIX}thread
			(forum_id, root_msg_id, last_post_date, replies, views, rating, last_post_id, thread_opt, orderexpiry, tdescr)
		VALUES
			(".$forum_id.", ".$root.", ".$last_post_date.", ".$replies.", $views, 0, ".$lpi.", ".$thread_opt.", ".$orderexpiry.","._esc($descr).")");
}

function th_move($id, $to_forum, $root_msg_id, $forum_id, $last_post_date, $last_post_id, $tdescr)
{
	if (!db_locked()) {
		if ($to_forum != $forum_id) {
			$lock = '{SQL_TABLE_PREFIX}tv_'.$to_forum.' WRITE, {SQL_TABLE_PREFIX}tv_'.$forum_id;
		} else {
			$lock = '{SQL_TABLE_PREFIX}tv_'.$to_forum;
		}
		
		db_lock('{SQL_TABLE_PREFIX}poll WRITE, '.$lock.' WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}msg WRITE');
		$ll = 1;
	}
	$msg_count = q_singleval('SELECT count(*) FROM {SQL_TABLE_PREFIX}thread LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id WHERE {SQL_TABLE_PREFIX}msg.apr=1 AND {SQL_TABLE_PREFIX}thread.id='.$id);

	q('UPDATE {SQL_TABLE_PREFIX}thread SET forum_id='.$to_forum.' WHERE id='.$id);
	q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-'.$msg_count.' WHERE id='.$forum_id);
	q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count+1,post_count=post_count+'.$msg_count.' WHERE id='.$to_forum);
	q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE forum_id='.$to_forum.' AND root_msg_id='.$root_msg_id.' AND moved_to='.$forum_id);
	if (($aff_rows = db_affected())) {
		q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-'.$aff_rows.' WHERE id='.$to_forum);
	}
	q('UPDATE {SQL_TABLE_PREFIX}thread SET moved_to='.$to_forum.' WHERE id!='.$id.' AND root_msg_id='.$root_msg_id);

	q('INSERT INTO {SQL_TABLE_PREFIX}thread
		(forum_id, root_msg_id, last_post_date, last_post_id, moved_to, tdescr)
	VALUES
		('.$forum_id.', '.$root_msg_id.', '.$last_post_date.', '.$last_post_id.', '.$to_forum.','._esc($descr).')');

	rebuild_forum_view_ttl($forum_id);
	rebuild_forum_view_ttl($to_forum);

	$p = db_all('SELECT poll_id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$id.' AND apr=1 AND poll_id>0');
	if ($p) {
		q('UPDATE {SQL_TABLE_PREFIX}poll SET forum_id='.$to_forum.' WHERE id IN('.implode(',', $p).')');
	}

	if (isset($ll)) {
		db_unlock();
	}
}

function __th_cron_emu($forum_id, $run=1)
{
	/* let's see if we have sticky threads that have expired */
	$exp = db_all('SELECT {SQL_TABLE_PREFIX}thread.id FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.'
			INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread.id={SQL_TABLE_PREFIX}tv_'.$forum_id.'.thread_id
			INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id
			WHERE {SQL_TABLE_PREFIX}tv_'.$forum_id.'.id>'.(q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' ORDER BY seq DESC LIMIT 1') - 50).' 
				AND {SQL_TABLE_PREFIX}tv_'.$forum_id.'.iss>0
				AND {SQL_TABLE_PREFIX}thread.thread_opt>=2 
				AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry)<='.__request_timestamp__);
	if ($exp) {
		q('UPDATE {SQL_TABLE_PREFIX}thread SET orderexpiry=0, thread_opt=thread_opt & ~ (2|4) WHERE id IN('.implode(',', $exp).')');
		$exp = 1;
	}

	/* Remove expired moved thread pointers */
	q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE forum_id='.$forum_id.' AND moved_to>0 AND last_post_date<'.(__request_timestamp__ - 86400 * $GLOBALS['MOVED_THR_PTR_EXPIRY']));
	if (($aff_rows = db_affected())) {
		q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-'.$aff_rows.' WHERE thread_count>0 AND id='.$forum_id);
		if (!$exp) {
			$exp = 1;
		}
	}

	if ($exp && $run) {
		rebuild_forum_view_ttl($forum_id,1);
	}

	return $exp;
}

function rebuild_forum_view_ttl($forum_id, $skip_cron=0)
{
	if (!$skip_cron) {
		__th_cron_emu($forum_id, 0);
	}

	if (!db_locked()) {
		$ll = 1;
		db_lock('{SQL_TABLE_PREFIX}tv_'.$forum_id.' WRITE, {SQL_TABLE_PREFIX}thread READ, {SQL_TABLE_PREFIX}msg READ');
	}

	if (__dbtype__ == 'mysql') {
		q('SET @seq=0');
		$val = '(@seq:=@seq+1)';
	} else if (__dbtype__ == 'pgsql') {
		$cur = q("SELECT nextval('{SQL_TABLE_PREFIX}tv_".$forum_id."_id_seq')") - 1;
		$val = '0';
	} else {
		$val = '0';
	}

	q('DELETE FROM {SQL_TABLE_PREFIX}tv_'.$forum_id); /* in sqlite, this resets row counter */
	q('INSERT INTO {SQL_TABLE_PREFIX}tv_'.$forum_id.' (thread_id,iss,seq) SELECT {SQL_TABLE_PREFIX}thread.id, (thread_opt & (2|4|8)), '.$val.' FROM {SQL_TABLE_PREFIX}thread 
		INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id 
		WHERE forum_id='.$forum_id.' AND {SQL_TABLE_PREFIX}msg.apr=1 
		ORDER BY (CASE WHEN thread_opt>=2 THEN (4294967294 + ((thread_opt & 8) * 100000000) + {SQL_TABLE_PREFIX}thread.last_post_date) ELSE {SQL_TABLE_PREFIX}thread.last_post_date END) ASC');

	if (__dbtype__ == 'pgsql') {
		q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET seq=id - '.$cur);
	} else if (__dbtype__ == 'sqlite') { /* adjust 1st value, since it can come from previous insert */
		q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET seq=id');
	} 

	if (isset($ll)) {
		db_unlock();
	}
}

function th_delete_rebuild($forum_id, $th)
{
	if (!db_locked()) {
		$ll = 1;
		db_lock('{SQL_TABLE_PREFIX}tv_'.$forum_id.' WRITE');
	}

	/* get position */
	if (($pos = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' WHERE thread_id='.$th))) {
		q('DELETE FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' WHERE thread_id='.$th);
		/* move every one down one, if placed after removed topic */
		q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET seq=seq-1 WHERE seq>'.$pos);
	}

	if (isset($ll)) {
		db_unlock();
	}
}

function th_new_rebuild($forum_id, $th, $sticky)
{
	if (__th_cron_emu($forum_id)) {
		return;
	}

	if (!db_locked()) {
		$ll = 1;
		db_lock('{SQL_TABLE_PREFIX}tv_'.$forum_id.' WRITE');
	}

	list($max,$iss) = db_saq('SELECT seq,iss FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' ORDER BY seq DESC LIMIT 1');
	if ((!$sticky && $iss) || $iss >=8) { /* sub-optimal case, non-sticky topic and thre are stickies in the forum */
		/* find oldest sticky message */
		if ($sticky && $iss >= 8) {
			$iss = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' WHERE seq>'.($max - 50).' AND iss>=8 ORDER BY seq ASC LIMIT 1');
		} else {
			$iss = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' WHERE seq>'.($max - 50).' AND iss>0 ORDER BY seq ASC LIMIT 1');
		}
		/* move all stickies up one */
		q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET seq=seq+1 WHERE seq>='.$iss);
		/* we do this, since in optimal case we just do ++max */
		$max = --$iss;
	}
	q('INSERT INTO {SQL_TABLE_PREFIX}tv_'.$forum_id.' (thread_id,iss,seq) VALUES('.$th.','.(int)$sticky.','.(++$max).')');

	if (isset($ll)) {
		db_unlock();
	}
}

function th_reply_rebuild($forum_id, $th, $sticky)
{
	if (!db_locked()) {
		$ll = 1;
		db_lock('{SQL_TABLE_PREFIX}tv_'.$forum_id.' WRITE');
	}

	list($max,$tid,$iss) = db_saq('SELECT seq,thread_id,iss FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' ORDER BY seq DESC LIMIT 1');

	if ($tid == $th) {
		/* NOOP: quick elimination, topic is already 1st */
	} else if (!$iss || ($sticky && $iss < 8)) { /* moving to the very top */
		/* get position */
		$pos = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' WHERE thread_id='.$th);
		/* move everyone ahead, 1 down */
		q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET seq=seq-1 WHERE seq>'.$pos);
		/* move to top of the stack */
		q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET seq='.$max.' WHERE thread_id='.$th);
	} else {
		/* get position */
		$pos = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' WHERE thread_id='.$th);
		/* find oldest sticky message */
		$iss = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' WHERE seq>'.($max - 50).' AND iss>'.($sticky && $iss >= 8 ? '=8' : '0').' ORDER BY seq ASC LIMIT 1');
		/* move everyone ahead, unless sticky, 1 down */
		q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET seq=seq-1 WHERE seq BETWEEN '.($pos + 1).' AND '.($iss - 1));
		/* move to top of the stack */
		q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET seq='.($iss - 1).' WHERE thread_id='.$th);
	}

	if (isset($ll)) {
		db_unlock();
	}
}
?>