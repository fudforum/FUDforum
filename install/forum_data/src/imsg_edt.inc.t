<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: imsg_edt.inc.t,v 1.32 2003/04/09 09:55:05 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/
class fud_msg_edit extends fud_msg
{
	function add_thread($forum_id, $autoapprove=TRUE)
	{
		return $this->add($forum_id, $autoapprove);
	}
	
	function add_reply($reply_to, $th_id=NULL, $sticky_perm, $lock_perm, $autoapprove=TRUE)
	{
		if ($reply_to) {
			$this->reply_to = $reply_to;
			$fd = db_saq('SELECT {SQL_TABLE_PREFIX}thread.forum_id,{SQL_TABLE_PREFIX}forum.message_threshold,{SQL_TABLE_PREFIX}forum.moderated FROM {SQL_TABLE_PREFIX}msg INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}thread.forum_id WHERE {SQL_TABLE_PREFIX}msg.id='.$reply_to);
		} else {
			$fd = db_saq('SELECT {SQL_TABLE_PREFIX}thread.forum_id,{SQL_TABLE_PREFIX}forum.message_threshold,{SQL_TABLE_PREFIX}forum.moderated FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}thread.forum_id WHERE {SQL_TABLE_PREFIX}thread.id='.$th_id);
		}
		
		return $this->add($fd[0], $fd[1], $fd[2], $sticky_perm, $lock_perm, $autoapprove);
	}
	
	function add($forum_id, $message_threshold, $moderated, $sticky_perm, $lock_perm, $autoapprove=TRUE)
	{
		if (!$this->post_stamp) {
			$this->post_stamp = __request_timestamp__;
		}

		$this->ip_addr = isset($_SERVER['REMOTE_ADDR']) ? "'".$_SERVER['REMOTE_ADDR']."'" : "'0.0.0.0'";
		$this->host_name = $GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' ? "'".get_host($this->ip_addr)."'" : 'NULL';
		$this->thread_id = isset($this->thread_id) ? $this->thread_id : 0;
		$this->reply_to = isset($this->reply_to) ? $this->reply_to : 0;

		$file_id = write_body($this->body, $length, $offset);

		/* determine if preview needs building */
		if ($message_threshold && $message_threshold < strlen($this->body)) {
			$thres_body = trim_html($this->body, $message_threshold);
			$file_id_preview = write_body($thres_body, $length_preview, $offset_preview);
		} else {
			$file_id_preview = $offset_preview = $length_preview = 0;
		}
		
		poll_cache_rebuild($this->poll_id, $poll_cache);
		$poll_cache = ($poll_cache ? @serialize($poll_cache) : NULL);

		$this->id = db_qid("INSERT INTO {SQL_TABLE_PREFIX}msg (
			thread_id, 
			poster_id, 
			reply_to, 
			ip_addr,
			host_name,
			post_stamp, 
			subject, 
			attach_cnt, 
			poll_id, 
			icon, 
			show_sig,
			smiley_disabled,
			file_id,
			foff,
			length,
			file_id_preview,
			offset_preview,
			length_preview,
			mlist_msg_id,
			poll_cache
		) VALUES(
			".$this->thread_id.",
			".$this->poster_id.",
			".intzero($this->reply_to).",
			".$this->ip_addr.",
			".$this->host_name.",
			".$this->post_stamp.",
			".strnull($this->subject).",
			".intzero($this->attach_cnt).",
			".intzero($this->poll_id).",
			".strnull($this->icon).",
			'".yn($this->show_sig)."',
			'".yn($this->smiley_disabled)."',
			".$file_id.",
			".intzero($offset).",
			".intzero($length).",
			".$file_id_preview.",
			".$offset_preview.",
			".$length_preview.",
			".strnull($this->mlist_msg_id).",
			".strnull(addslashes($poll_cache))."
		)");

		if ((!empty($GLOBALS['MOD']) || $lock_perm == 'Y') && isset($_POST['thr_locked'])) {
			$thr_locked = 'Y';
		} else {
			$thr_locked = 'N';
		}

		if (!$this->thread_id) { /* new thread */
			if ((!empty($GLOBALS['MOD']) || $sticky_perm == 'Y') && isset($_POST['thr_ordertype'], $_POST['thr_orderexpiry'])) {
				if ($_POST['thr_ordertype'] != 'NONE' && ($_POST['thr_ordertype'] == 'ANNOUNCE' || $_POST['thr_ordertype'] == 'STICKY')) {
					$is_sticky = 'Y';
					$thr_ordertype = "'".$_POST['thr_ordertype']."'";
					$thr_orderexpiry = (int) $_POST['thr_orderexpiry'];
				} else {
					$is_sticky = 'N';
					$thr_ordertype = "'NONE'";
					$thr_orderexpiry = 0;
				}
			} else {
				$is_sticky = 'N';
				$thr_ordertype = "'NONE'";
				$thr_orderexpiry = 0;
			}
			
			$this->thread_id = fud_thread::add($this->id, $forum_id, $this->post_stamp, $thr_locked, $is_sticky, $thr_ordertype, $thr_orderexpiry);
	
			q('UPDATE {SQL_TABLE_PREFIX}msg SET thread_id='.$this->thread_id.' WHERE id='.$this->id);
		} else {
			th_lock($this->thread_id, $thr_locked);
		}
		
		if ($autoapprove && $moderated == 'Y') {
			$this->approve($this->id, TRUE);
		}

		return $this->id;
	}
	
	function sync($id, $frm_id, $message_threshold, $sticky_perm, $lock_perm)
	{
		if (!db_locked()) {
			db_lock('{SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}msg WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}thread_view WRITE');
			$ll=1;
		}
		$file_id = write_body($this->body, $length, $offset);

		/* determine if preview needs building */
		if ($message_threshold && $message_threshold < strlen($this->body)) {
			$thres_body = trim_html($this->body, $message_threshold);
			$file_id_preview = write_body($thres_body, $length_preview, $offset_preview);
		} else {
			$file_id_preview = $offset_preview = $length_preview = 0;
		}
	
		poll_cache_rebuild($this->poll_id, $poll_cache);
		$poll_cache = ($poll_cache ? @serialize($poll_cache) : NULL);

		q("UPDATE {SQL_TABLE_PREFIX}msg SET 
			file_id=".$file_id.", 
			foff=".intzero($offset).", 
			length=".intzero($length).",
			mlist_msg_id=".strnull($this->mlist_msg_id).",
			file_id_preview=".$file_id_preview.",
			offset_preview=".$offset_preview.",
			length_preview=".$length_preview.",
			smiley_disabled='".yn($this->smiley_disabled)."', 
			updated_by=".$id.",
			show_sig='".yn($this->show_sig)."', 
			subject='".$this->subject."', 
			attach_cnt=".intzero($this->attach_cnt).", 
			poll_id=".intzero($this->poll_id).", 
			update_stamp=".__request_timestamp__.", 
			icon=".strnull($this->icon)." ,
			poll_cache=".strnull(addslashes($poll_cache))."
		WHERE id=".$this->id);
		
		/* determine wether or not we should deal with locked & sticky stuff 
		 * current approach may seem a little redundant, but for (most) users who 
		 * do not have access to locking & sticky this eliminated a query.
		 */
		if (isset($_POST['thr_ordertype'], $_POST['thr_orderexpiry']) || isset($_POST['thr_locked'])) {
			$th_data = db_saq('SELECT ordertype, orderexpiry, locked, root_msg_id FROM {SQL_TABLE_PREFIX}thread WHERE id='.$this->thread_id);

			if (isset($_POST['thr_locked']) && $_POST['thr_locked'] != $th_data[2]) {
				/* confirm that user has ability to change lock status of the thread */
				if (!empty($GLOBALS['MOD']) || $lock_perm == 'Y') {
					$thr_locked = $_POST['thr_locked'];
				}	
			}

			if ($th_data[3] == $this->id && isset($_POST['thr_ordertype'], $_POST['thr_orderexpiry']) && ($_POST['thr_ordertype'] != $th_data[0] || $_POST['thr_orderexpiry'] != $th_data[1])) {
				/* confirm that user has ability to change sticky status of the thread */
				if (!empty($GLOBALS['MOD']) || $sticky_perm == 'Y') {
					$is_sticky = $_POST['thr_ordertype'] == 'NONE' ? 'N' : 'Y';
					$ordertype = $_POST['thr_ordertype'];
					$orderexpiry = $_POST['thr_orderexpiry'];
				}
			}

			/* Determine if any work needs to be done */
			if (isset($thr_locked, $is_sticky)) {
				q("UPDATE SQL_TABLE_PREFIX}thread SET is_sticky='".yn($this->is_sticky)."', ordertype=".ifnull($this->ordertype, "'NONE'").", orderexpiry=".intzero($this->orderexpiry).", locked='".yn($this->locked)."' WHERE id=".$this->id);
				rebuild_forum_view($frm_id);
			} else if (isset($thr_locked)) {
				q("UPDATE SQL_TABLE_PREFIX}thread SET locked='".yn($this->locked)."' WHERE id=".$this->id);
			} else {
				q("UPDATE SQL_TABLE_PREFIX}thread SET is_sticky='".yn($this->is_sticky)."', ordertype=".ifnull($this->ordertype, "'NONE'").", orderexpiry=".intzero($this->orderexpiry)." WHERE id=".$this->id);
				rebuild_forum_view($frm_id);
			}
		}

		if (isset($ll)) {
			db_unlock();
		}

		if ($GLOBALS['FORUM_SEARCH'] == 'Y') {
			delete_msg_index($this->id);
			index_text((preg_match('!^Re: !i', $this->subject) ? '': $this->subject), $this->body, $this->id);
		}
	}
	
	function fetch_vars($array, $prefix)
	{
		$this->subject = $array[$prefix.'subject'];
		$this->body = $array[$prefix.'body'];
		$this->icon = isset($array[$prefix.'icon'])?$array[$prefix.'icon']:'';
		$this->show_sig = isset($array[$prefix.'show_sig'])?$array[$prefix.'show_sig']:'';
	}
	
	function delete($rebuild_view=TRUE, $mid=0, $th_rm=0)
	{
		if (!db_locked()) {
			db_lock('{SQL_TABLE_PREFIX}thr_exchange WRITE, {SQL_TABLE_PREFIX}thread_view WRITE, {SQL_TABLE_PREFIX}level WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}forum_read WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}msg WRITE, {SQL_TABLE_PREFIX}attach WRITE, {SQL_TABLE_PREFIX}poll WRITE, {SQL_TABLE_PREFIX}poll_opt WRITE, {SQL_TABLE_PREFIX}poll_opt_track WRITE, {SQL_TABLE_PREFIX}users WRITE, {SQL_TABLE_PREFIX}thread_notify WRITE, {SQL_TABLE_PREFIX}msg_report WRITE, {SQL_TABLE_PREFIX}thread_rate_track WRITE');
			$ll = 1;
		}
		if (!$mid) {
			$mid = $this->id;
		}
		
		if (!($del = db_sab('SELECT {SQL_TABLE_PREFIX}msg.*, {SQL_TABLE_PREFIX}thread.replies, {SQL_TABLE_PREFIX}thread.root_msg_id AS root_msg_id, {SQL_TABLE_PREFIX}thread.last_post_id AS thread_lip, {SQL_TABLE_PREFIX}forum.last_post_id AS forum_lip, {SQL_TABLE_PREFIX}thread.forum_id FROM {SQL_TABLE_PREFIX}msg LEFT JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id LEFT JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id WHERE {SQL_TABLE_PREFIX}msg.id='.$mid))) {
			exit('no such message');
		}
		
		/* attachments */
		if ($del->attach_cnt) {
			$res = q('SELECT location FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$mid." AND private='N'");
			if (db_count($res)) {
				while ($loc = db_rowarr($res)) {
					@unlink($loc[0]);
				}
			}
			qf($res);
			q('DELETE FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$mid." AND private='N'");
		}
		
		q('DELETE FROM {SQL_TABLE_PREFIX}msg_report WHERE msg_id='.$mid);
		
		if ($del->poll_id) {
			fud_poll::delete($del->poll_id);
		}
		
		/* check if thread */
		if ($del->root_msg_id == $del->id) {
			$rmsg = q('SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$del->thread_id.' AND id != '.$del->id);
			while ($dim = db_rowarr($rmsg)) {
				fud_msg_edit::delete(FALSE, $dim[0], 1);
			}
			qf($rmsg);
			q('DELETE FROM {SQL_TABLE_PREFIX}thread_notify WHERE thread_id='.$del->thread_id);
			q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE root_msg_id='.$del->root_msg_id);
			q('DELETE FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id='.$del->thread_id);
			q('DELETE FROM {SQL_TABLE_PREFIX}thr_exchange WHERE th='.$del->thread_id);
		} else if (!$th_rm  && $del->approved == 'Y') {
			q('UPDATE {SQL_TABLE_PREFIX}thread SET replies=replies-1 WHERE id='.$del->thread_id);
			q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-1 WHERE id='.$del->forum_id);
			q('UPDATE {SQL_TABLE_PREFIX}msg SET reply_to='.$del->reply_to.' WHERE thread_id='.$del->thread_id.' AND reply_to='.$mid);
		}

		q('DELETE FROM {SQL_TABLE_PREFIX}msg WHERE id='.$mid);

		if ($del->poster_id && $del->approved == 'Y') {
			fud_user::set_post_count($del->poster_id, -1);
		}

		if (!$th_rm && $del->root_msg_id != $mid && $del->thread_lip == $mid) {
			$mid = (int) q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$del->thread_id." AND approved='Y' ORDER BY post_stamp DESC LIMIT 1");
			q('UPDATE {SQL_TABLE_PREFIX}thread SET last_post_id='.$mid.' WHERE id='.$del->thread_id);
		}

		if ($del->root_msg_id == $del->id && $del->approved == 'Y') {
			$mid = (int) q_singleval("SELECT last_post_id FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.last_post_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$del->forum_id." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY last_post_id DESC LIMIT 1");
			q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$mid.',thread_count=thread_count-1, post_count=post_count-'.$del->replies.'-1 WHERE id='.$del->forum_id);

			if ($rebuild_view) {
				rebuild_forum_view($del->forum_id);
				$r = q('SELECT forum_id FROM {SQL_TABLE_PREFIX}thread WHERE root_msg_id='.$del->root_msg_id);
				if (($res = @db_rowarr($r))) {
					do {
						rebuild_forum_view($res[0]);
					} while (($res = @db_rowarr($r)));
				}
			} else {
				q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE root_msg_id='.$del->root_msg_id);
			}
		}

		if (isset($ll)) {
			db_unlock();
		}
	}	
	
	function approve($id, $unlock_safe=FALSE)
	{	
		/* fetch info about the message, poll (if one exists), thread & forum */
		$mtf = db_sab('SELECT
					m.id, m.poster_id, m.approved, m.subject, m.foff, m.length, m.file_id, m.thread_id, m.poll_id, m.attach_cnt,
					m.post_stamp, m.show_sig, m.reply_to,
					t.forum_id, t.last_post_id, t.root_msg_id, t.last_post_date,
					m2.post_stamp AS frm_last_post_date,
					f.name AS frm_name,
					u.alias, u.email, u.sig
				FROM {SQL_TABLE_PREFIX}msg m 
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
				INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
				LEFT JOIN {SQL_TABLE_PREFIX}msg m2 ON f.last_post_id=m2.id
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
				WHERE m.id='.$id.' AND m.approved=\'N\'');

		if (!db_locked()) {
			db_lock('{SQL_TABLE_PREFIX}thread_view WRITE, {SQL_TABLE_PREFIX}level WRITE, {SQL_TABLE_PREFIX}users WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}msg WRITE');
			$ll = 1;
		}

		/* nothing to do or bad message id */
		if (!$mtf) {
			return;
		}
		if ($mtf->alias) {
			reverse_FMT($mtf->alias);
		} else {
			$mtf->alias = $GLOBALS['ANON_NICK'];
		}

		q("UPDATE {SQL_TABLE_PREFIX}msg SET approved='Y' WHERE id=".$mtf->id);
			
		if ($mtf->poster_id) {
			user_set_post_count($mtf->poster_id);
		}
		
		$last_post_id = $mtf->post_stamp > $mtf->frm_last_post_date ? $mtf->id : 0;

		if ($mtf->root_msg_id == $mtf->id) {	/* new thread */
			rebuild_forum_view($mtf->forum_id);
			$threads = 1;
		} else {				/* reply to thread */
			if ($mtf->post_stamp > $mtf->last_post_date) {
				th_inc_post_count($mtf->thread_id, 1, $mtf->id, $mtf->post_stamp);
			} else {
				th_inc_post_count($mtf->thread_id, 1);
			}
			rebuild_forum_view($mtf->forum_id, q_singleval('SELECT page FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id='.$mtf->forum_id.' AND thread_id='.$mtf->thread_id));
			$threads = 0;
		}	
				
		/* update forum thread & post count as well as last_post_id field */
		frm_updt_counts($mtf->forum_id, 1, $threads, $last_post_id);

		if ($unlock_safe || isset($ll)) {
			db_unlock();
		}

		$mtf->body = read_msg_body($mtf->foff, $mtf->length, $mtf->file_id);

		if ($GLOBALS['FORUM_SEARCH'] == 'Y') {
			index_text((preg_match('!Re: !i', $mtf->subject) ? '': $mtf->subject), $mtf->body, $mtf->id);
		}

		/* handle notifications */
		if ($mtf->root_msg_id == $mtf->id) {
			/* send new thread notifications to forum subscribers */
			$c = uq('SELECT u.email, u.icq, u.notify_method
					FROM {SQL_TABLE_PREFIX}forum_notify fn
					INNER JOIN {SQL_TABLE_PREFIX}users u ON fn.user_id=u.id 
					LEFT JOIN {SQL_TABLE_PREFIX}forum_read r ON r.forum_id=fn.forum_id AND r.user_id=fn.user_id
					INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id='.$mtf->forum_id.'
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id=fn.user_id AND g2.resource_id='.$mtf->forum_id.' 
				WHERE 
					fn.forum_id='.$mtf->thread_id.' AND fn.user_id!='.intzero($mtf->poster_id).' 
					AND (CASE WHEN r.last_view IS NULL || r.last_view > '.$mtf->frm_last_post_date.' THEN 1 ELSE 0 END)=1
					AND (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)=\'Y\'');
			$notify_type = 'frm';
		} else {
			/* send new reply notifications to thread subscribers */
			$c = uq('SELECT u.email, u.icq, u.notify_method
					FROM {SQL_TABLE_PREFIX}thread_notify tn
					INNER JOIN {SQL_TABLE_PREFIX}users u ON tn.user_id=u.id 
					LEFT JOIN {SQL_TABLE_PREFIX}read r ON r.thread_id=tn.thread_id AND r.user_id=tn.user_id
					INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id='.$mtf->forum_id.'
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id=tn.user_id AND g2.resource_id='.$mtf->forum_id.' 
				WHERE 
					tn.thread_id='.$mtf->thread_id.' AND tn.user_id!='.intzero($mtf->poster_id).' 
					AND r.msg_id='.$mtf->last_post_id.' 
					AND (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)=\'Y\'');
			$notify_type = 'thr';
		}
		while ($r = db_rowarr($c)) {
			$to[$r[2]][] = $r[2] == 'EMAIL' ? $r[0] : $r[1].'@pager.icq.com';
		}
		qf($c);
		if (isset($to)) {
			send_notifications($to, $mtf->id, $mtf->subject, $mtf->alias, $notify_type, $mtf->thread_id, $mtf->frm_name);
		}

		// Handle Mailing List and/or Newsgroup syncronization.
		if (!$mtf->mlist_msg_id) {
			if ((list($mlist_id,$addr) = db_saq("SELECT id FROM {SQL_TABLE_PREFIX}mlist WHERE forum_id=".$mtf->forum_id." AND allow_frm_post='Y'"))) {
				fud_use('email_msg_format.inc', true);
				fud_use('mlist_post.inc', true);
				
				$GLOBALS['CHARSET'] = '{TEMPLATE: imsg_CHARSET}';

				$from = $mtf->poster_id ? $mtf->alias.' <'.$mtf->email.'>' : $GLOBALS['ANON_NICK'].' <'.$GLOBALS['NOTIFY_FROM'].'>';

				$body = $mtf->body . (($mtf->show_sig == 'Y' && $mtf->sig) ? "\n--\n" . $mtf->sig : '');
				plain_text($body);
				
				if ($mtf->reply_to) {
					$replyto_id = q_singleval('SELECT mlist_msg_id FROM {SQL_TABLE_PREFIX}msg WHERE id='.$mtf->reply_to);
				} else {
					$replyto_id = 0;
				}
				
				if ($mtf->attach_cnt) {
					$r = q("SELECT {SQL_TABLE_PREFIX}attach.id, {SQL_TABLE_PREFIX}attach.original_name, {SQL_TABLE_PREFIX}mime.mime_hdr FROM {SQL_TABLE_PREFIX}attach INNER JOIN {SQL_TABLE_PREFIX}mime ON {SQL_TABLE_PREFIX}attach.mime_type={SQL_TABLE_PREFIX}mime.id WHERE message_id=".$mtf->id." AND private='N'");
					while ($ent = db_rowarr($r)) {
						$attach[$ent[1]][] = file_get_contents($GLOBALS['FILE_STORE'].$ent[0].'.atch');
						$attach[$ent[1]][] = $ent[2];
					}
					qf($r);
				} else {
					$attach = null;
				}
				
				mail_list_post($addr, $from, $mtf->subject, $body, $mtf->id, $replyto_id, $attach, '');
			} else if (($nntp_id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}nntp WHERE forum_id=".$mtf->forum_id." AND allow_frm_post='Y'"))) {
				fud_use('nntp.inc', true);
				fud_use('nntp_adm.inc', true);
				fud_use('email_msg_format.inc', true);
				
				$nntp_adm = new fud_nntp_adm;
				$nntp_adm->get($nntp_id);
				$nntp = new fud_nntp;
				
				$nntp->server = $nntp_adm->server;
				$nntp->newsgroup = $nntp_adm->newsgroup;
				$nntp->port = $nntp_adm->port;
				$nntp->timeout = $nntp_adm->timeout;
				$nntp->auth = $nntp_adm->auth;
				$nntp->login = $nntp_adm->login;
				$nntp->pass = $nntp_adm->pass;

				$from = $mtf->poster_id ? $mtf->alias.' <'.$mtf->email.'>' : $GLOBALS['ANON_NICK'].' <'.$GLOBALS['NOTIFY_FROM'].'>';
				$body = $mtf->body . (($mtf->show_sig == 'Y' && $mtf->sig) ? "\n--\n" . $mtf->sig : '');
				
				if ($mtf->reply_to) {
					$replyto_id = q_singleval('SELECT mlist_msg_id FROM {SQL_TABLE_PREFIX}msg WHERE id='.$mtf->reply_to);
				} else {
					$replyto_id = 0;
				}
				
				if ($mtf->attach_cnt) {
					$r = q("SELECT id, original_name FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$mtf->id." AND private='N'");
					while ($ent = db_rowarr($r)) {
						$attach[$ent[1]][] = file_get_contents($GLOBALS['FILE_STORE'].$ent[0].'.atch');
					}
					qf($r);
				} else {
					$attach = null;
				}
				
				$lock = $nntp->get_lock();
				$nntp->post_message($mtf->subject, $body, $from, $mtf->id, $replyto_id, $attach);
				$nntp->close_connection();
				$nntp->release_lock($lock);
			}
		}
	}
}

function flood_check()
{
	$check_time = __request_timestamp__-$GLOBALS['FLOOD_CHECK_TIME'];
	
	if (($v = q_singleval("SELECT post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE ip_addr='".$_SERVER['REMOTE_ADDR']."' AND poster_id="._uid." AND post_stamp>".$check_time." ORDER BY post_stamp DESC LIMIT 1"))) {
		$v += $GLOBALS['FLOOD_CHECK_TIME']-__request_timestamp__;
		if ($v < 1) {
			$v = 1;
		}
		return $v;
	}
	
	return;		
}

function write_body($data, &$len, &$offset)
{
	$MAX_FILE_SIZE = 2147483647;

	$len = strlen($data);
	$i = 1;
	while ($i < 100) {
		$fp = fopen($GLOBALS['MSG_STORE_DIR'].'msg_'.$i, 'ab');
		if (!($off = ftell($fp))) {
			$off = __ffilesize($fp);
		}
		if (!$off || ($off + $len) < $MAX_FILE_SIZE) {
			break;
		}
		fclose($fp);
		$i++;
	}
	
	$len = fwrite($fp, $data);
	fclose($fp);
	
	if (!$off) {
		@chmod('msg_'.$i, ($GLOBALS['FILE_LOCK'] == 'Y' ? 0600 : 0666));
	}
	
	if ($len == -1) {
		exit("FATAL ERROR: system has ran out of disk space<br>\n");
	}
	$offset = $off;
	
	return $i;
}

function trim_html($str, $maxlen)
{
	$n = strlen($str);
	$ln = 0;
	for ($i = 0; $i < $n; $i++) {
		if ($str[$i] != '<') {
			$ln++;
			if ($ln > $maxlen) {
				break;
			}
			continue;
		}
		
		if (($p = strpos($str, '>', $i)) === FALSE) {
			break;
		}
		
		for ($k = $i; $k < $p; $k++) {
			switch ($str[$k]) {
				case ' ':
				case "\r":
				case "\n":
				case "\t":
				case ">":
					break 2;
			}
		}
		
		if ($str[$i+1] == '/') {
			$tagname = strtolower(substr($str, $i+2, $k-$i-2));	
			if (@end($tagindex[$tagname])) {
				$k = key($tagindex[$tagname]);
				unset($tagindex[$tagname][$k], $tree[$k]);
			}	
		} else {
			$tagname = strtolower(substr($str, $i+1, $k-$i-1));
			switch ($tagname) {
				case 'br':
				case 'img':
				case 'meta':
					break;
				default:
					$tree[] = $tagname;
					end($tree);
					$tagindex[$tagname][key($tree)] = 1;
			}
		}
		$i = $p;
	}
	
	$data = substr($str, 0, $i);
	if (is_array($tree)) {
		$tree = array_reverse($tree);
		foreach ($tree as $v) {
			$data .= '</'.$v.'>';
		}
	}

	return $data;
}
?>