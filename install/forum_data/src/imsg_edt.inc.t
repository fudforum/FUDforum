<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: imsg_edt.inc.t,v 1.62 2003/06/05 22:42:14 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

class fud_msg
{
	var $id, $thread_id, $poster_id, $reply_to, $ip_addr, $host_name, $post_stamp, $subject, $attach_cnt, $poll_id, 
	    $update_stamp, $icon, $approved, $show_sig, $updated_by, $smiley_disabled, $login, $length, $foff, $file_id,
	    $file_id_preview, $length_preview, $offset_preview, $body, $mlist_msg_id;
}

class fud_msg_edit extends fud_msg
{
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

		if (!isset($this->ip_addr)) {
			$this->ip_addr = get_ip();
		}
		$this->host_name = $GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' ? "'".addslashes(get_host($this->ip_addr))."'" : 'NULL';
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
			'".$this->ip_addr."',
			".$this->host_name.",
			".$this->post_stamp.",
			".strnull(addslashes(htmlspecialchars($this->subject))).",
			".intzero($this->attach_cnt).",
			".intzero($this->poll_id).",
			".strnull(addslashes($this->icon)).",
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
			
			$this->thread_id = th_add($this->id, $forum_id, $this->post_stamp, $thr_locked, $is_sticky, $thr_ordertype, $thr_orderexpiry);
	
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
			db_lock('{SQL_TABLE_PREFIX}poll_opt WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}msg WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}thread_view WRITE');
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
			mlist_msg_id=".strnull(addslashes($this->mlist_msg_id)).",
			file_id_preview=".$file_id_preview.",
			offset_preview=".$offset_preview.",
			length_preview=".$length_preview.",
			smiley_disabled='".yn($this->smiley_disabled)."', 
			updated_by=".$id.",
			show_sig='".yn($this->show_sig)."', 
			subject='".addslashes(htmlspecialchars($this->subject))."', 
			attach_cnt=".intzero($this->attach_cnt).", 
			poll_id=".intzero($this->poll_id).", 
			update_stamp=".__request_timestamp__.", 
			icon=".strnull(addslashes($this->icon))." ,
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
				q("UPDATE {SQL_TABLE_PREFIX}thread SET is_sticky='".yn($is_sticky)."', ordertype=".ifnull($ordertype, "'NONE'").", orderexpiry=".intzero($this->orderexpiry).", locked='".yn($thr_locked)."' WHERE id=".$this->thread_id);
				rebuild_forum_view($frm_id);
			} else if (isset($thr_locked)) {
				q("UPDATE {SQL_TABLE_PREFIX}thread SET locked='".yn($thr_locked)."' WHERE id=".$this->thread_id);
			} else {
				q("UPDATE {SQL_TABLE_PREFIX}thread SET is_sticky='".yn($is_sticky)."', ordertype=".ifnull($ordertype, "'NONE'").", orderexpiry=".intzero($orderexpiry)." WHERE id=".$this->thread_id);
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
	
	function delete($rebuild_view=TRUE, $mid=0, $th_rm=0)
	{
		if (!db_locked()) {
			db_lock('{SQL_TABLE_PREFIX}thr_exchange WRITE, {SQL_TABLE_PREFIX}thread_view WRITE, {SQL_TABLE_PREFIX}level WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}forum_read WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}msg WRITE, {SQL_TABLE_PREFIX}attach WRITE, {SQL_TABLE_PREFIX}poll WRITE, {SQL_TABLE_PREFIX}poll_opt WRITE, {SQL_TABLE_PREFIX}poll_opt_track WRITE, {SQL_TABLE_PREFIX}users WRITE, {SQL_TABLE_PREFIX}thread_notify WRITE, {SQL_TABLE_PREFIX}msg_report WRITE, {SQL_TABLE_PREFIX}thread_rate_track WRITE');
			$ll = 1;
		}
		if (!$mid) {
			$mid = $this->id;
		}
		
		if (!($del = db_sab('SELECT 
				{SQL_TABLE_PREFIX}msg.id, {SQL_TABLE_PREFIX}msg.attach_cnt, {SQL_TABLE_PREFIX}msg.poll_id, {SQL_TABLE_PREFIX}msg.thread_id, {SQL_TABLE_PREFIX}msg.reply_to, {SQL_TABLE_PREFIX}msg.approved, {SQL_TABLE_PREFIX}msg.poster_id,
				{SQL_TABLE_PREFIX}thread.replies, {SQL_TABLE_PREFIX}thread.root_msg_id AS root_msg_id, {SQL_TABLE_PREFIX}thread.last_post_id AS thread_lip, {SQL_TABLE_PREFIX}thread.forum_id,
				{SQL_TABLE_PREFIX}forum.last_post_id AS forum_lip FROM {SQL_TABLE_PREFIX}msg LEFT JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id LEFT JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}thread.forum_id={SQL_TABLE_PREFIX}forum.id WHERE {SQL_TABLE_PREFIX}msg.id='.$mid))) {
			if (isset($ll)) {
				db_unlock();
			}
			return;
		}
		
		/* attachments */
		if ($del->attach_cnt) {
			$res = q('SELECT location FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$mid." AND private='N'");
			while ($loc = db_rowarr($res)) {
				@unlink($loc[0]);
			}
			qf($res);
			q('DELETE FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$mid." AND private='N'");
		}
		
		q('DELETE FROM {SQL_TABLE_PREFIX}msg_report WHERE msg_id='.$mid);
		
		if ($del->poll_id) {
			poll_delete($del->poll_id);
		}
		
		/* check if thread */
		if ($del->root_msg_id == $del->id) {
			$th_rm = 1;
			/* delete all messages in the thread if there is more then 1 message */
			if ($del->replies) {
				$rmsg = q('SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$del->thread_id.' AND id != '.$del->id);
				while ($dim = db_rowarr($rmsg)) {
					fud_msg_edit::delete(FALSE, $dim[0], 1);
				}
				qf($rmsg);
			}
			
			q('DELETE FROM {SQL_TABLE_PREFIX}thread_notify WHERE thread_id='.$del->thread_id);
			q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE id='.$del->thread_id);
			q('DELETE FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id='.$del->thread_id);
			q('DELETE FROM {SQL_TABLE_PREFIX}thr_exchange WHERE th='.$del->thread_id);
			
			if ($del->approved == 'Y') {
				/* we need to determine the last post id for the forum, it can be null */
				$lpi = (int) q_singleval('SELECT {SQL_TABLE_PREFIX}thread.last_post_id FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.last_post_id={SQL_TABLE_PREFIX}msg.id AND {SQL_TABLE_PREFIX}msg.approved=\'Y\' WHERE forum_id='.$del->forum_id.' AND moved_to=0 ORDER BY {SQL_TABLE_PREFIX}msg.post_stamp DESC LIMIT 1');
				q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$lpi.', thread_count=thread_count-1, post_count=post_count-'.$del->replies.'-1 WHERE id='.$del->forum_id);
			}
		} else if (!$th_rm  && $del->approved == 'Y') {
			q('UPDATE {SQL_TABLE_PREFIX}msg SET reply_to='.$del->reply_to.' WHERE thread_id='.$del->thread_id.' AND reply_to='.$mid);
			
			/* check if the message is the last in thread */
			if ($del->thread_lip == $del->id) {
				list($lpi, $lpd) = db_saq('SELECT id, post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$del->thread_id.' AND approved=\'Y\' AND id!='.$del->id.' ORDER BY post_stamp DESC LIMIT 1');
				q('UPDATE {SQL_TABLE_PREFIX}thread SET last_post_id='.$lpi.', last_post_date='.$lpd.', replies=replies-1 WHERE id='.$del->thread_id);
			} else {
				q('UPDATE {SQL_TABLE_PREFIX}thread SET replies=replies-1 WHERE id='.$del->thread_id);
			}

			/* check if the message is the last in the forum */
			if ($del->forum_lip == $del->id) {
				$lp = db_saq('SELECT {SQL_TABLE_PREFIX}thread.last_post_id, {SQL_TABLE_PREFIX}thread.last_post_date FROM {SQL_TABLE_PREFIX}thread_view INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}thread_view.forum_id={SQL_TABLE_PREFIX}thread.forum_id AND {SQL_TABLE_PREFIX}thread_view.thread_id={SQL_TABLE_PREFIX}thread.id WHERE {SQL_TABLE_PREFIX}thread_view.forum_id='.$del->forum_id.' AND {SQL_TABLE_PREFIX}thread_view.page=1 AND {SQL_TABLE_PREFIX}thread.moved_to=0 ORDER BY {SQL_TABLE_PREFIX}thread.last_post_date DESC LIMIT 1');
				if (!isset($lpd) || $lp[1] > $lpd) {
					$lpi = $lp[0];
				}
				q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-1, last_post_id='.$lpi.' WHERE id='.$del->forum_id);
			} else {
				q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-1 WHERE id='.$del->forum_id);
			}
		}

		q('DELETE FROM {SQL_TABLE_PREFIX}msg WHERE id='.$mid);

		if ($del->approved == 'Y') {
			if ($del->poster_id) {
				user_set_post_count($del->poster_id);
			}

			if ($rebuild_view) {
				rebuild_forum_view($del->forum_id);
				
				/* needed for moved thread pointers */
				$r = q('SELECT forum_id, id FROM {SQL_TABLE_PREFIX}thread WHERE root_msg_id='.$del->root_msg_id);
				while (($res = db_rowarr($r))) {
					if ($th_rm) {
						q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE id='.$res[1]);
					}
					rebuild_forum_view($res[0]);
				}
				qf($r);
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
					m.post_stamp, m.show_sig, m.reply_to, m.mlist_msg_id,
					t.forum_id, t.last_post_id, t.root_msg_id, t.last_post_date,
					m2.post_stamp AS frm_last_post_date,
					f.name AS frm_name,
					u.alias, u.email, u.sig,
					n.id AS nntp_id, ml.id AS mlist_id
				FROM {SQL_TABLE_PREFIX}msg m 
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
				INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
				LEFT JOIN {SQL_TABLE_PREFIX}msg m2 ON f.last_post_id=m2.id
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
				LEFT JOIN {SQL_TABLE_PREFIX}mlist ml ON ml.forum_id=f.id
				LEFT JOIN {SQL_TABLE_PREFIX}nntp n ON n.forum_id=f.id
				WHERE m.id='.$id.' AND m.approved=\'N\'');

		/* nothing to do or bad message id */
		if (!$mtf) {
			return;
		}

		if (!db_locked()) {
			db_lock('{SQL_TABLE_PREFIX}thread_view WRITE, {SQL_TABLE_PREFIX}level WRITE, {SQL_TABLE_PREFIX}users WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}msg WRITE');
			$ll = 1;
		}

		if ($mtf->alias) {
			reverse_fmt($mtf->alias);
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

		if ($mtf->poll_id) {
			poll_activate($mtf->poll_id, $mtf->forum_id);
		}

		$mtf->body = read_msg_body($mtf->foff, $mtf->length, $mtf->file_id);

		if ($GLOBALS['FORUM_SEARCH'] == 'Y') {
			index_text((preg_match('!Re: !i', $mtf->subject) ? '': $mtf->subject), $mtf->body, $mtf->id);
		}

		/* handle notifications */
		if ($mtf->root_msg_id == $mtf->id) {
			if (empty($mtf->frm_last_post_date)) {
				$mtf->frm_last_post_date = 0;
			}
		
			/* send new thread notifications to forum subscribers */
			$c = uq('SELECT u.email, u.icq, u.notify_method
					FROM {SQL_TABLE_PREFIX}forum_notify fn
					INNER JOIN {SQL_TABLE_PREFIX}users u ON fn.user_id=u.id 
					LEFT JOIN {SQL_TABLE_PREFIX}forum_read r ON r.forum_id=fn.forum_id AND r.user_id=fn.user_id
					INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id='.$mtf->forum_id.'
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id=fn.user_id AND g2.resource_id='.$mtf->forum_id.' 
				WHERE 
					fn.forum_id='.$mtf->forum_id.' AND fn.user_id!='.intzero($mtf->poster_id).' 
					AND (CASE WHEN (r.last_view IS NULL AND (u.last_read=0 OR u.last_read >= '.$mtf->frm_last_post_date.')) OR r.last_view > '.$mtf->frm_last_post_date.' THEN 1 ELSE 0 END)=1
					AND (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)=\'Y\'');
			$notify_type = 'frm';
		} else {
			/* send new reply notifications to thread subscribers */
			$c = uq('SELECT u.email, u.icq, u.notify_method, r.msg_id, u.id
					FROM {SQL_TABLE_PREFIX}thread_notify tn
					INNER JOIN {SQL_TABLE_PREFIX}users u ON tn.user_id=u.id 
					LEFT JOIN {SQL_TABLE_PREFIX}read r ON r.thread_id=tn.thread_id AND r.user_id=tn.user_id
					INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id='.$mtf->forum_id.'
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id=tn.user_id AND g2.resource_id='.$mtf->forum_id.' 
				WHERE 
					tn.thread_id='.$mtf->thread_id.' AND tn.user_id!='.intzero($mtf->poster_id).' 
					AND (r.msg_id='.$mtf->last_post_id.' OR (r.msg_id IS NULL AND '.$mtf->post_stamp.' > u.last_read))
					AND (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)=\'Y\'');
			$notify_type = 'thr';
		}
		while ($r = db_rowarr($c)) {
			$to[$r[2]][] = $r[2] == 'EMAIL' ? $r[0] : $r[1].'@pager.icq.com';
			if (isset($r[4]) && is_null($r[3])) {
				$tl[] = $r[4];
			}
		}
		qf($c);
		if (isset($tl)) {
			/* this allows us to mark the message we are sending notification about as read, so that we do not re-notify the user
			 * until this message is read.
			 */
			q('INSERT INTO {SQL_TABLE_PREFIX}read (thread_id, msg_id, last_view, user_id) SELECT '.$mtf->thread_id.', 0, 0, id FROM {SQL_TABLE_PREFIX}users WHERE id IN('.implode(',', $tl).')');
		}
		if (isset($to)) {
			send_notifications($to, $mtf->id, $mtf->subject, $mtf->alias, $notify_type, ($notify_type == 'thr' ? $mtf->thread_id : $mtf->forum_id), $mtf->frm_name, $mtf->forum_id);
		}

		// Handle Mailing List and/or Newsgroup syncronization.
		if (($mtf->nntp_id || $mtf->mlist_id) && !$mtf->mlist_msg_id) {
			fud_use('email_msg_format.inc', true);

			reverse_fmt($mtf->alias);
			$from = $mtf->poster_id ? $mtf->alias.' <'.$mtf->email.'>' : $GLOBALS['ANON_NICK'].' <'.$GLOBALS['NOTIFY_FROM'].'>';
			$body = $mtf->body . (($mtf->show_sig == 'Y' && $mtf->sig) ? "\n--\n" . $mtf->sig : '');
			plain_text($body);
			plain_text($subject);

			if ($mtf->reply_to) {
				$replyto_id = q_singleval('SELECT mlist_msg_id FROM {SQL_TABLE_PREFIX}msg WHERE id='.$mtf->reply_to);
			} else {
				$replyto_id = 0;
			}

			if ($mtf->attach_cnt) {
				$r = uq("SELECT a.id, a.original_name, 
						CASE WHEN m.mime_hdr IS NULL THEN 'application/octet-stream' ELSE m.mime_hdr END
						FROM {SQL_TABLE_PREFIX}attach a 
						LEFT JOIN {SQL_TABLE_PREFIX}mime m ON a.mime_type=m.id
						WHERE a.message_id=".$mtf->id." AND a.private='N'");
				while ($ent = db_rowarr($r)) {
					$attach[$ent[1]] = file_get_contents($GLOBALS['FILE_STORE'].$ent[0].'.atch');
					if ($mtf->mlist_id) {
						$attach_mime[$ent[1]] = $ent[2];
					}
				}
				qf($r);
			} else {
				$attach_mime = $attach = null;
			}

			if ($mtf->nntp_id) {
				fud_use('nntp.inc', true);
				
				$nntp_adm = db_sab('SELECT * FROM {SQL_TABLE_PREFIX}nntp WHERE id='.$mtf->nntp_id);
				$nntp = new fud_nntp;
				
				$nntp->server = $nntp_adm->server;
				$nntp->newsgroup = $nntp_adm->newsgroup;
				$nntp->port = $nntp_adm->port;
				$nntp->timeout = $nntp_adm->timeout;
				$nntp->auth = $nntp_adm->auth;
				$nntp->login = $nntp_adm->login;
				$nntp->pass = $nntp_adm->pass;

				define('sql_p', '{SQL_TABLE_PREFIX}');

				$lock = $nntp->get_lock();
				$nntp->post_message($mtf->subject, $body, $from, $mtf->id, $replyto_id, $attach);
				$nntp->close_connection();
				$nntp->release_lock($lock);
			} else {
				fud_use('mlist_post.inc', true);
				$GLOBALS['CHARSET'] = '{TEMPLATE: imsg_CHARSET}';
				$r = db_saq('SELECT name, additional_headers FROM {SQL_TABLE_PREFIX}mlist WHERE id='.$mtf->mlist_id);
				mail_list_post($r[0], $from, $mtf->subject, $body, $mtf->id, $replyto_id, $attach, $attach_mime, $r[1]);
			}
		}
	}
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

	if (fwrite($fp, $data) !== $len) {
		exit("FATAL ERROR: system has ran out of disk space<br>\n");
	}
	fclose($fp);

	if (!$off) {
		@chmod('msg_'.$i, ($GLOBALS['FILE_LOCK'] == 'Y' ? 0600 : 0666));
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
	if (isset($tree) && is_array($tree)) {
		$tree = array_reverse($tree);
		foreach ($tree as $v) {
			$data .= '</'.$v.'>';
		}
	}

	return $data;
}

function make_email_message(&$body, &$obj, $iemail_unsub)
{
	$TITLE_EXTRA = $iemail_poll = $iemail_attach = '';
	if ($obj->poll_cache) {
		$pl = @unserialize($obj->poll_cache);
		if (is_array($pl) && count($pl)) {
			foreach ($pl as $k => $v) {
				$length = ($v[1] && $obj->total_votes) ? round($v[1] / $obj->total_votes * 100) : 0;
				$iemail_poll .= '{TEMPLATE: iemail_poll_result}';	
			}
			$iemail_poll = '{TEMPLATE: iemail_poll_tbl}';
		}
	}
	if ($obj->attach_cnt && $obj->attach_cache) {
		$atch = @unserialize($obj->attach_cache);
		if (is_array($atch) && count($atch)) {
			foreach ($atch as $v) {
				$sz = $v[2] / 1024;
				$sz = $sz < 1000 ? number_format($sz, 2).'KB' : number_format($sz/1024, 2).'MB';
				$iemail_attach .= '{TEMPLATE: iemail_attach_entry}';
			}
			$iemail_attach = '{TEMPLATE: iemail_attach}';
		}
	}

	if ($GLOBALS['USE_PATH_INFO'] == 'Y') {
		$pfx = str_repeat('/', substr_count(_rsid, '/'));
	}

	return '{TEMPLATE: iemail_body}';
}

function send_notifications($to, $msg_id, $thr_subject, $poster_login, $id_type, $id, $frm_name, $frm_id)
{
	if (isset($to['EMAIL']) && (is_string($to['EMAIL']) || (is_array($to['EMAIL']) && count($to['EMAIL'])))) {
		$do_email = 1;
		$goto_url['email'] = '{ROOT}?t=rview&goto='.$msg_id;
		if ($GLOBALS['NOTIFY_WITH_BODY'] == 'Y') {
			
			$obj = db_sab("SELECT p.total_votes, p.name AS poll_name, m.reply_to, m.subject, m.id, m.post_stamp, m.poster_id, m.foff, m.length, m.file_id, u.alias, m.attach_cnt, m.attach_cache, m.poll_cache FROM {SQL_TABLE_PREFIX}msg m LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id WHERE m.id=".$msg_id." AND m.approved='Y'");

			$headers  = "MIME-Version: 1.0\r\n";
			if ($obj->reply_to) {
				$headers .= "In-Reply-To: ".$obj->reply_to."\r\n";
			}
			$headers .= "List-Id: ".$frm_id.".".$_SERVER['SERVER_NAME']."\r\n";
			$split = get_random_value(128)                                                                            ;
			$headers .= "Content-Type: multipart/alternative; boundary=\"------------" . $split . "\"\r\n";
			$boundry = "\r\n--------------" . $split . "\r\n";
		
			$CHARSET = '{TEMPLATE: CHARSET}';
		
			$plain_text = read_msg_body($obj->foff, $obj->length, $obj->file_id);
			$iemail_unsub = $id_type == 'thr' ? '{TEMPLATE: iemail_thread_unsub}' : '{TEMPLATE: iemail_forum_unsub}';
		
			$body_email = $boundry . "Content-Type: text/plain; charset=" . $CHARSET . "; format=flowed\r\nContent-Transfer-Encoding: 7bit\r\n\r\n" . strip_tags($plain_text) . "\r\n\r\n" . '{TEMPLATE: iemail_participate}' . ' ' . '{ROOT}?t=rview&th=' . $id . "&notify=1&opt=off\r\n" . 
			$boundry . "Content-Type: text/html; charset=" . $CHARSET . "\r\nContent-Transfer-Encoding: 7bit\r\n\r\n" . make_email_message($plain_text, $obj, $iemail_unsub) . "\r\n" . substr($boundry, 0, -2) . "--\r\n";
		}
	}
	if (isset($to['ICQ']) && (is_string($to['ICQ']) || (is_array($to['ICQ']) && count($to['ICQ'])))) {
		$do_icq = 1;
		$icq = str_replace('http://', "http'+'://'+'", $GLOBALS['WWW_ROOT']);
		$icq = str_replace('www.', "www'+'.", $icq);
		$goto_url['icq'] = "javascript:window.location='".$icq."{ROOT}?t=rview&goto=".$msg_id."';";
	} else if (!isset($do_email)) {
		/* nothing to do */
		return;
	}

	reverse_fmt($thr_subject);
	reverse_fmt($poster_login);
	
	if ($id_type == 'thr') {
		$subj = '{TEMPLATE: iemail_thr_subject}';
		
		if (!isset($body_email)) {
			$unsub_url['email'] = '{ROOT}?t=rview&th='.$id.'&notify=1&opt=off';
			$body_email = '{TEMPLATE: iemail_thr_bodyemail}';
		}	
		
		if (isset($do_icq)) {
			$body_icq = '{TEMPLATE: iemail_thr_bodyicq}';
			$unsub_url['icq'] = "javascript:window.location='".$icq."{ROOT}?t=rview&th=".$id."&notify=1&opt=off';";
		}
	} else if ($id_type == 'frm') {
		reverse_fmt($frm_name);

		$subj = '{TEMPLATE: iemail_frm_subject}';

		if (isset($do_icq)) {
			$unsub_url['icq'] = "javascript:window.location='".$icq."{ROOT}?t=rview&unsub=1&frm_id=".$id."';";
			$body_icq = '{TEMPLATE: iemail_frm_bodyicq}';
		}
		if (!isset($body_email)) {
			$unsub_url['email'] = '{ROOT}?t=rview&unsub=1&frm_id='.$id;
			$body_email = '{TEMPLATE: iemail_frm_bodyemail}';
		}	
	}	

	if (isset($do_email)) {
		send_email($GLOBALS['NOTIFY_FROM'], $to['EMAIL'], $subj, $body_email, (isset($headers) ? $headers : ''));
	}
	if (isset($do_icq)) {
		send_email($GLOBALS['NOTIFY_FROM'], $to['ICQ'], $subj, $body_icq);
	}
}
?>