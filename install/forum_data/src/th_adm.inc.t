<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: th_adm.inc.t,v 1.22 2005/08/12 16:26:52 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

function th_add($root, $forum_id, $last_post_date, $thread_opt, $orderexpiry, $replies=0, $lpi=0)
{
	if (!$lpi) {
		$lpi = $root;
	}

	return db_qid("INSERT INTO
		{SQL_TABLE_PREFIX}thread
			(forum_id, root_msg_id, last_post_date, replies, views, rating, last_post_id, thread_opt, orderexpiry)
		VALUES
			(".$forum_id.", ".$root.", ".$last_post_date.", ".$replies.", 0, 0, ".$lpi.", ".$thread_opt.", ".$orderexpiry.")");
}

function th_move($id, $to_forum, $root_msg_id, $forum_id, $last_post_date, $last_post_id)
{
	if (!db_locked()) {
		db_lock('{SQL_TABLE_PREFIX}poll WRITE, {SQL_TABLE_PREFIX}tv_'.$to_forum.' WRITE, {SQL_TABLE_PREFIX}tv_'.$forum_id.' WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}msg WRITE');
		$ll = 1;
	}
	$msg_count = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}thread LEFT JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id WHERE {SQL_TABLE_PREFIX}msg.apr=1 AND {SQL_TABLE_PREFIX}thread.id=".$id);

	q('UPDATE {SQL_TABLE_PREFIX}thread SET forum_id='.$to_forum.' WHERE id='.$id);
	q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-'.$msg_count.' WHERE id='.$forum_id);
	q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count+1,post_count=post_count+'.$msg_count.' WHERE id='.$to_forum);
	q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE forum_id='.$to_forum.' AND root_msg_id='.$root_msg_id.' AND moved_to='.$forum_id);
	if (($aff_rows = db_affected())) {
		q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-'.$aff_rows.' WHERE id='.$to_forum);
	}
	q('UPDATE {SQL_TABLE_PREFIX}thread SET moved_to='.$to_forum.' WHERE id!='.$id.' AND root_msg_id='.$root_msg_id);

	q('INSERT INTO {SQL_TABLE_PREFIX}thread
		(forum_id, root_msg_id, last_post_date, last_post_id, moved_to)
	VALUES
		('.$forum_id.', '.$root_msg_id.', '.$last_post_date.', '.$last_post_id.', '.$to_forum.')');

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
	$tm = __request_timestamp__;
	$reflow = 0;

	if (q_singleval("SELECT last_sticky_id FROM {SQL_TABLE_PREFIX}forum WHERE id=".$forum_id)) {
		/* De-announce expired announcments and sticky messages */
		$r = q("SELECT {SQL_TABLE_PREFIX}thread.id FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id WHERE {SQL_TABLE_PREFIX}thread.forum_id=".$forum_id." AND thread_opt>=2 AND ({SQL_TABLE_PREFIX}msg.post_stamp+{SQL_TABLE_PREFIX}thread.orderexpiry)<=".$tm);
		while ($tid = db_rowarr($r)) {
			q("UPDATE {SQL_TABLE_PREFIX}thread SET orderexpiry=0, thread_opt=thread_opt & ~ (2|4) WHERE id=".$tid[0]);
			$reflow = 1;
		}
		unset($r);
	}

	/* Remove expired moved thread pointers */
	q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE forum_id='.$forum_id.' AND last_post_date<'.($tm-86400*$GLOBALS['MOVED_THR_PTR_EXPIRY']).' AND moved_to!=0');
	if (($aff_rows = db_affected())) {
		q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-'.$aff_rows.' WHERE id='.$forum_id);
		$reflow = 1;
	}

	if ($reflow && $run) {
		rebuild_forum_view_ttl($forum_id,1);
	}

	return $reflow;
}

function rebuild_forum_view_ttl($forum_id, $skip_cron=0)
{
	if (!db_locked()) {
		$ll = 1;
		db_lock('{SQL_TABLE_PREFIX}tv_'.$forum_id.' WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}msg READ, {SQL_TABLE_PREFIX}forum WRITE');
	}

	if (!$skip_cron) {
		__th_cron_emu($forum_id, 0);
	}

	q('DELETE FROM {SQL_TABLE_PREFIX}tv_'.$forum_id);
	if (__dbtype__ == 'mysql') {
		q('ALTER TABLE {SQL_TABLE_PREFIX}tv_'.$forum_id.' AUTO_INCREMENT=1');
	} else if (__dbtype__ == 'pgsql') {
		q("ALTER SEQUENCE {SQL_TABLE_PREFIX}tv_".$forum_id."_id_seq RESTART WITH 1");
	}
	q('INSERT INTO {SQL_TABLE_PREFIX}tv_'.$forum_id.' (thread_id) SELECT {SQL_TABLE_PREFIX}thread.id FROM {SQL_TABLE_PREFIX}thread 
		INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.root_msg_id={SQL_TABLE_PREFIX}msg.id 
		WHERE forum_id='.$forum_id.' AND {SQL_TABLE_PREFIX}msg.apr=1 
		ORDER BY (CASE WHEN thread_opt>=2 THEN 4294967294 ELSE {SQL_TABLE_PREFIX}thread.last_post_date END) ASC, {SQL_TABLE_PREFIX}thread.last_post_id ASC');

	/* update last sticky id */
	$q = "SELECT {SQL_TABLE_PREFIX}tv_".$forum_id.".id FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}tv_".$forum_id." ON {SQL_TABLE_PREFIX}tv_".$forum_id.".thread_id={SQL_TABLE_PREFIX}thread.id WHERE thread_opt>=2 ORDER BY {SQL_TABLE_PREFIX}tv_".$forum_id.".id LIMIT 1";
	$q2 = "SELECT count(*) FROM {SQL_TABLE_PREFIX}tv_".$forum_id;
	if (__dbtype__ != 'mysql' || $GLOBALS['FUD_OPT_3'] & 1024) {
		q("UPDATE {SQL_TABLE_PREFIX}forum SET last_view_id=".(int)q_singleval($q2).", last_sticky_id=COALESCE((".$q."),0) WHERE id=".$forum_id);
	} else {
		q("UPDATE {SQL_TABLE_PREFIX}forum SET last_view_id=".(int)q_singleval($q2).", last_sticky_id=".(int)q_singleval($q)." WHERE id=".$forum_id);
	}
	
	if (isset($ll)) {
		db_unlock();
	}
}

function th_delete_rebuild($forum_id, $th)
{
	if (!db_locked()) {
		$ll = 1;
		db_lock('{SQL_TABLE_PREFIX}tv_'.$forum_id.' WRITE, {SQL_TABLE_PREFIX}forum WRITE');
	}

	if (($pos = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' WHERE thread_id='.$th))) {
		q('DELETE FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' WHERE id='.$pos);
		if (__dbtype__ == 'mysql') {
			q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id=id-1 WHERE id>'.$pos.' ORDER BY id');
		} else {
			q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id=(id * -1)+1 WHERE id>'.$pos);
			q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id= id * -1 WHERE id<0');
		}
		if (!q_singleval("SELECT last_sticky_id=last_view_id FROM  {SQL_TABLE_PREFIX}forum WHERE id=".$forum_id)) {
			q("UPDATE {SQL_TABLE_PREFIX}forum SET last_view_id=last_view_id-1 WHERE id=".$forum_id);
		} else {
			q("UPDATE {SQL_TABLE_PREFIX}forum SET last_sticky_id=last_sticky_id-1, last_view_id=last_view_id-1 WHERE id=".$forum_id);
		}
		if (__dbtype__ == 'pgsql') {
			q("ALTER SEQUENCE {SQL_TABLE_PREFIX}tv_".$forum_id."_id_seq RESTART WITH ".max(1,q_singleval("SELECT last_view_id FROM {SQL_TABLE_PREFIX}forum WHERE id=".$forum_id)));
		}
	}

	if (isset($ll)) {
		db_unlock();
	}
}

function th_new_rebuild($forum_id, $th, $sticky=0)
{
	if (__th_cron_emu($forum_id)) {
		return;
	}

	if (!db_locked()) {
		$ll = 1;
		db_lock('{SQL_TABLE_PREFIX}tv_'.$forum_id.' WRITE, {SQL_TABLE_PREFIX}forum READ');
	}

	$id = q_singleval("SELECT last_sticky_id FROM {SQL_TABLE_PREFIX}forum WHERE id=".$forum_id);

	if (!$id || $sticky) {
		$l = db_qid("INSERT INTO {SQL_TABLE_PREFIX}tv_".$forum_id." (thread_id) VALUES(".$th.")");
		if (isset($ll)) { db_unlock(); }
		if (!$sticky) {
			$l = 0;
		}
		q("UPDATE {SQL_TABLE_PREFIX}forum SET last_view_id=last_view_id+1, last_sticky_id=".$l." WHERE id=".$forum_id);
	} else {
		if (__dbtype__ == 'mysql') {
			q("UPDATE {SQL_TABLE_PREFIX}tv_".$forum_id." SET id=id+1 WHERE id>=".$id." ORDER BY id DESC");
		} else {
			q("UPDATE {SQL_TABLE_PREFIX}tv_".$forum_id." SET id=(id+1)*-1 WHERE id>=".$id);
			q("UPDATE {SQL_TABLE_PREFIX}tv_".$forum_id." SET id=id * -1 WHERE id < 0");
		}
		q("INSERT INTO {SQL_TABLE_PREFIX}tv_".$forum_id." (id, thread_id) VALUES(".$id.",".$th.")");
		if (isset($ll)) { db_unlock(); }
		q("UPDATE {SQL_TABLE_PREFIX}forum SET last_view_id=last_view_id+1, last_sticky_id=last_sticky_id+1 WHERE id=".$forum_id);
	}
}

function th_reply_rebuild($forum_id, $th=0, $sticky=0)
{
	if (!(__request_timestamp__ % 250) && __th_cron_emu($forum_id)) {
		return;
	}

	list($lv,$id) = db_saq("SELECT last_view_id,last_sticky_id FROM {SQL_TABLE_PREFIX}forum WHERE id=".$forum_id);

	if (!db_locked()) {
		$ll = 1;
		db_lock('{SQL_TABLE_PREFIX}tv_'.$forum_id.' WRITE');
	}

	$pos = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}tv_'.$forum_id.' WHERE thread_id='.$th);
	if ($pos) {
		q('UPDATE /* first */ {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id='.($lv+1).' WHERE id='.$pos);
		if (!$id || $sticky) {
			if (__dbtype__ == 'mysql') {
				q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id=id-1 WHERE id>='.$pos.' ORDER BY id');
			} else {
				q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id=(id * -1)+1 WHERE id>='.$pos);
				q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id= id * -1 WHERE id<0');
			}
		} else {
			if (__dbtype__ == 'mysql') {
				q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id=id-1 WHERE id>='.$pos.' AND id<'.$id.' ORDER BY id');
			} else {
				q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id=(id * -1)+1 WHERE id>='.$pos.' AND id<'.$id);
				q('UPDATE {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id= id * -1 WHERE id<0');
			}
			q('UPDATE /* last */ {SQL_TABLE_PREFIX}tv_'.$forum_id.' SET id='.($id-1).' WHERE id='.($lv+1));
		}
	}

	if (isset($ll)) {
		db_unlock();
	}
}
?>