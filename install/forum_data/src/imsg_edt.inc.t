<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: imsg_edt.inc.t,v 1.148 2005/09/30 18:43:21 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

class fud_msg
{
	var $id, $thread_id, $poster_id, $reply_to, $ip_addr, $host_name, $post_stamp, $subject, $attach_cnt, $poll_id,
	    $update_stamp, $icon, $apr, $updated_by, $login, $length, $foff, $file_id, $msg_opt,
	    $file_id_preview, $length_preview, $offset_preview, $body, $mlist_msg_id;
}

$GLOBALS['CHARSET'] = '{TEMPLATE: imsg_CHARSET}';

class fud_msg_edit extends fud_msg
{
	function add_reply($reply_to, $th_id=null, $perm, $autoapprove=1)
	{
		if ($reply_to) {
			$this->reply_to = $reply_to;
			$fd = db_saq('SELECT t.forum_id, f.message_threshold, f.forum_opt FROM {SQL_TABLE_PREFIX}msg m INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=t.forum_id WHERE m.id='.$reply_to);
		} else {
			$fd = db_saq('SELECT t.forum_id, f.message_threshold, f.forum_opt FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=t.forum_id WHERE t.id='.$th_id);
		}

		return $this->add($fd[0], $fd[1], $fd[2], $perm, $autoapprove);
	}

	function add($forum_id, $message_threshold, $forum_opt, $perm, $autoapprove=1)
	{
		if (!$this->post_stamp) {
			$this->post_stamp = __request_timestamp__;
		}

		if (!isset($this->ip_addr)) {
			$this->ip_addr = get_ip();
		}
		$this->host_name = $GLOBALS['FUD_OPT_1'] & 268435456 ? _esc(get_host($this->ip_addr)) : 'NULL';
		$this->thread_id = isset($this->thread_id) ? $this->thread_id : 0;
		$this->reply_to = isset($this->reply_to) ? $this->reply_to : 0;

		$file_id = write_body($this->body, $length, $offset, $forum_id);

		/* determine if preview needs building */
		if ($message_threshold && $message_threshold < strlen($this->body)) {
			$thres_body = trim_html($this->body, $message_threshold);
			$file_id_preview = write_body($thres_body, $length_preview, $offset_preview, $forum_id);
		} else {
			$file_id_preview = $offset_preview = $length_preview = 0;
		}

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
			msg_opt,
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
			".(int)$this->reply_to.",
			'".$this->ip_addr."',
			".$this->host_name.",
			".$this->post_stamp.",
			".ssn($this->subject).",
			".(int)$this->attach_cnt.",
			".(int)$this->poll_id.",
			".ssn($this->icon).",
			".$this->msg_opt.",
			".$file_id.",
			".(int)$offset.",
			".(int)$length.",
			".$file_id_preview.",
			".$offset_preview.",
			".$length_preview.",
			".ssn($this->mlist_msg_id).",
			".ssn(poll_cache_rebuild($this->poll_id))."
		)");

		$thread_opt = (int) ($perm & 4096 && isset($_POST['thr_locked']));

		if (!$this->thread_id) { /* new thread */
			if ($perm & 64 && isset($_POST['thr_ordertype'], $_POST['thr_orderexpiry'])) {
				if ((int)$_POST['thr_ordertype']) {
					$thread_opt |= (int) $_POST['thr_ordertype'];
					$thr_orderexpiry = (int) $_POST['thr_orderexpiry'];
				}
			}

			$this->thread_id = th_add($this->id, $forum_id, $this->post_stamp, $thread_opt, (isset($thr_orderexpiry) ? $thr_orderexpiry : 0));

			q('UPDATE {SQL_TABLE_PREFIX}msg SET thread_id='.$this->thread_id.' WHERE id='.$this->id);
		} else {
			th_lock($this->thread_id, $thread_opt & 1);
		}

		if ($autoapprove && $forum_opt & 2) {
			$this->approve($this->id);
		}

		return $this->id;
	}

	function sync($id, $frm_id, $message_threshold, $perm)
	{
		$file_id = write_body($this->body, $length, $offset, $frm_id);

		/* determine if preview needs building */
		if ($message_threshold && $message_threshold < strlen($this->body)) {
			$thres_body = trim_html($this->body, $message_threshold);
			$file_id_preview = write_body($thres_body, $length_preview, $offset_preview, $frm_id);
		} else {
			$file_id_preview = $offset_preview = $length_preview = 0;
		}

		q("UPDATE {SQL_TABLE_PREFIX}msg SET
			file_id=".$file_id.",
			foff=".(int)$offset.",
			length=".(int)$length.",
			mlist_msg_id=".ssn($this->mlist_msg_id).",
			file_id_preview=".$file_id_preview.",
			offset_preview=".$offset_preview.",
			length_preview=".$length_preview.",
			updated_by=".$id.",
			msg_opt=".$this->msg_opt.",
			attach_cnt=".(int)$this->attach_cnt.",
			poll_id=".(int)$this->poll_id.",
			update_stamp=".__request_timestamp__.",
			icon=".ssn($this->icon)." ,
			poll_cache=".ssn(poll_cache_rebuild($this->poll_id)).",
			subject=".ssn($this->subject)."
		WHERE id=".$this->id);

		/* determine wether or not we should deal with locked & sticky stuff
		 * current approach may seem a little redundant, but for (most) users who
		 * do not have access to locking & sticky this eliminated a query.
		 */
		$th_data = db_saq('SELECT orderexpiry, thread_opt, root_msg_id FROM {SQL_TABLE_PREFIX}thread WHERE id='.$this->thread_id);
		$locked = (int) isset($_POST['thr_locked']);
		if (isset($_POST['thr_ordertype'], $_POST['thr_orderexpiry']) || (($th_data[1] ^ $locked) & 1)) {
			$thread_opt = (int) $th_data[1];
			$orderexpiry = isset($_POST['thr_orderexpiry']) ? (int) $_POST['thr_orderexpiry'] : 0;

			/* confirm that user has ability to change lock status of the thread */
			if ($perm & 4096) {
				if ($locked && !($thread_opt & $locked)) {
					$thread_opt |= 1;
				} else if (!$locked && $thread_opt & 1) {
					$thread_opt &= ~1;
				}
			}

			/* confirm that user has ability to change sticky status of the thread */
			if ($th_data[2] == $this->id && isset($_POST['thr_ordertype'], $_POST['thr_orderexpiry']) && $perm & 64) {
				if (!$_POST['thr_ordertype'] && $thread_opt>1) {
					$orderexpiry = 0;
					$thread_opt &= ~6;
				} else if ($thread_opt < 2 && (int) $_POST['thr_ordertype']) {
					$thread_opt |= $_POST['thr_ordertype'];
				} else if (!($thread_opt & (int) $_POST['thr_ordertype'])) {
					$thread_opt = $_POST['thr_ordertype'] | ($thread_opt & 1);
				}
			}

			/* Determine if any work needs to be done */
			if ($thread_opt != $th_data[1] || $orderexpiry != $th_data[0]) {
				q("UPDATE {SQL_TABLE_PREFIX}thread SET thread_opt=".$thread_opt.", orderexpiry=".$orderexpiry." WHERE id=".$this->thread_id);
				/* Avoid rebuilding the forum view whenever possible, since it's a rather slow process
				 * Only rebuild if expiry time has changed or message gained/lost sticky status
				 */
				$diff = $thread_opt ^ $th_data[1];
				if (($diff > 1 && !($diff & 6)) || $orderexpiry != $th_data[0]) {
					rebuild_forum_view_ttl($frm_id);
				}
			}
		}

		if ($GLOBALS['FUD_OPT_1'] & 16777216) {
			q('DELETE FROM {SQL_TABLE_PREFIX}index WHERE msg_id='.$this->id);
			q('DELETE FROM {SQL_TABLE_PREFIX}title_index WHERE msg_id='.$this->id);
			index_text((!strncasecmp('Re: ', $this->subject, 4) ? '' : $this->subject), $this->body, $this->id);
		}
	}

	function delete($rebuild_view=1, $mid=0, $th_rm=0)
	{
		if (!$mid) {
			$mid = $this->id;
		}

		if (!($del = db_sab('SELECT m.id, m.attach_cnt, m.poll_id, m.thread_id, m.reply_to, m.apr, m.poster_id, t.replies, t.root_msg_id AS root_msg_id, t.last_post_id AS thread_lip, t.forum_id, f.last_post_id AS forum_lip 
					FROM {SQL_TABLE_PREFIX}msg m 
					LEFT JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id 
					LEFT JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id WHERE m.id='.$mid))) {
			return;
		}

		if (!db_locked()) {
			db_lock('{SQL_TABLE_PREFIX}forum f WRITE, {SQL_TABLE_PREFIX}thr_exchange WRITE, {SQL_TABLE_PREFIX}tv_'.$del->forum_id.' WRITE, {SQL_TABLE_PREFIX}tv_'.$del->forum_id.' tv WRITE, {SQL_TABLE_PREFIX}msg m WRITE, {SQL_TABLE_PREFIX}thread t WRITE, {SQL_TABLE_PREFIX}level WRITE, {SQL_TABLE_PREFIX}forum WRITE, {SQL_TABLE_PREFIX}forum_read WRITE, {SQL_TABLE_PREFIX}thread WRITE, {SQL_TABLE_PREFIX}msg WRITE, {SQL_TABLE_PREFIX}attach WRITE, {SQL_TABLE_PREFIX}poll WRITE, {SQL_TABLE_PREFIX}poll_opt WRITE, {SQL_TABLE_PREFIX}poll_opt_track WRITE, {SQL_TABLE_PREFIX}users WRITE, {SQL_TABLE_PREFIX}thread_notify WRITE, {SQL_TABLE_PREFIX}msg_report WRITE, {SQL_TABLE_PREFIX}thread_rate_track WRITE');
			$ll = 1;
		}

		q('DELETE FROM {SQL_TABLE_PREFIX}msg WHERE id='.$mid);

		/* attachments */
		if ($del->attach_cnt) {
			$res = q('SELECT location FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$mid." AND attach_opt=0");
			while ($loc = db_rowarr($res)) {
				@unlink($loc[0]);
			}
			unset($res);
			q('DELETE FROM {SQL_TABLE_PREFIX}attach WHERE message_id='.$mid." AND attach_opt=0");
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
					fud_msg_edit::delete(0, $dim[0], 1);
				}
				unset($rmsg);
			}

			q('DELETE FROM {SQL_TABLE_PREFIX}thread_notify WHERE thread_id='.$del->thread_id);
			q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE id='.$del->thread_id);
			q('DELETE FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id='.$del->thread_id);
			q('DELETE FROM {SQL_TABLE_PREFIX}thr_exchange WHERE th='.$del->thread_id);

			if ($del->apr) {
				/* we need to determine the last post id for the forum, it can be null */
				$lpi = (int) q_singleval('SELECT t.last_post_id FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.last_post_id=m.id AND m.apr=1 WHERE t.forum_id='.$del->forum_id.' AND t.moved_to=0 ORDER BY m.post_stamp DESC LIMIT 1');
				q('UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id='.$lpi.', thread_count=thread_count-1, post_count=post_count-'.$del->replies.'-1 WHERE id='.$del->forum_id);
			}
		} else if (!$th_rm  && $del->apr) {
			q('UPDATE {SQL_TABLE_PREFIX}msg SET reply_to='.$del->reply_to.' WHERE thread_id='.$del->thread_id.' AND reply_to='.$mid);

			/* check if the message is the last in thread */
			if ($del->thread_lip == $del->id) {
				list($lpi, $lpd) = db_saq('SELECT id, post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE thread_id='.$del->thread_id.' AND apr=1 ORDER BY post_stamp DESC LIMIT 1');
				q('UPDATE {SQL_TABLE_PREFIX}thread SET last_post_id='.$lpi.', last_post_date='.$lpd.', replies=replies-1 WHERE id='.$del->thread_id);
			} else {
				q('UPDATE {SQL_TABLE_PREFIX}thread SET replies=replies-1 WHERE id='.$del->thread_id);
			}

			/* check if the message is the last in the forum */
			if ($del->forum_lip == $del->id) {
				$page = q_singleval('SELECT seq FROM {SQL_TABLE_PREFIX}tv_'.$del->forum_id.' WHERE thread_id='.$del->thread_id);
				$lp = db_saq('SELECT t.last_post_id, t.last_post_date 
					FROM {SQL_TABLE_PREFIX}tv_'.$del->forum_id.' tv
					INNER JOIN {SQL_TABLE_PREFIX}thread t ON tv.thread_id=t.id 
					WHERE tv.seq IN('.$page.','.($page - 1).') AND t.moved_to=0 ORDER BY t.last_post_date DESC LIMIT 1');
				if (!isset($lpd) || $lp[1] > $lpd) {
					$lpi = $lp[0];
				}
				q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-1, last_post_id='.$lpi.' WHERE id='.$del->forum_id);
			} else {
				q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count-1 WHERE id='.$del->forum_id);
			}
		}

		if ($del->apr) {
			if ($del->poster_id) {
				user_set_post_count($del->poster_id);
			}
			if ($rebuild_view) {
				if ($th_rm) {
					th_delete_rebuild($del->forum_id, $del->thread_id);
				} else if ($del->thread_lip == $del->id) {
					rebuild_forum_view_ttl($del->forum_id);
				}
			}
		}
		if (isset($ll)) {
			db_unlock();
		}
		
		if (!$del->apr || !$th_rm || ($del->root_msg_id != $del->id)) {
			return;
		}

		/* needed for moved thread pointers */
		$r = q('SELECT forum_id, id FROM {SQL_TABLE_PREFIX}thread WHERE root_msg_id='.$del->root_msg_id);
		while (($res = db_rowarr($r))) {
			q('DELETE FROM {SQL_TABLE_PREFIX}thread WHERE id='.$res[1]);
			q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count-1 WHERE id='.$res[0]);
			th_delete_rebuild($res[0], $res[1]);
		}
		unset($r);
	}

	function approve($id)
	{
		/* fetch info about the message, poll (if one exists), thread & forum */
		$mtf = db_sab('SELECT
					m.id, m.poster_id, m.apr, m.subject, m.foff, m.length, m.file_id, m.thread_id, m.poll_id, m.attach_cnt,
					m.post_stamp, m.reply_to, m.mlist_msg_id, m.msg_opt,
					t.forum_id, t.last_post_id, t.root_msg_id, t.last_post_date, t.thread_opt,
					m2.post_stamp AS frm_last_post_date,
					f.name AS frm_name,
					u.alias, u.email, u.sig, u.name as real_name,
					n.id AS nntp_id, ml.id AS mlist_id
				FROM {SQL_TABLE_PREFIX}msg m
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
				INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
				LEFT JOIN {SQL_TABLE_PREFIX}msg m2 ON f.last_post_id=m2.id
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id
				LEFT JOIN {SQL_TABLE_PREFIX}mlist ml ON ml.forum_id=f.id AND (ml.mlist_opt & 2) > 0
				LEFT JOIN {SQL_TABLE_PREFIX}nntp n ON n.forum_id=f.id AND (n.nntp_opt & 2) > 0
				WHERE m.id='.$id.' AND m.apr=0');

		/* nothing to do or bad message id */
		if (!$mtf) {
			return;
		}

		if ($mtf->alias) {
			$mtf->alias = reverse_fmt($mtf->alias);
		} else {
			$mtf->alias = $GLOBALS['ANON_NICK'];
		}

		q("UPDATE {SQL_TABLE_PREFIX}msg SET apr=1 WHERE id=".$mtf->id);

		if ($mtf->poster_id) {
			user_set_post_count($mtf->poster_id);
		}

		if ($mtf->post_stamp > $mtf->frm_last_post_date) {
			$mtf->last_post_id = $mtf->id;
		}		

		if ($mtf->root_msg_id == $mtf->id) {	/* new thread */
			th_new_rebuild($mtf->forum_id, $mtf->thread_id, $mtf->thread_opt>=2);
			$threads = 1;
		} else {				/* reply to thread */
			if ($mtf->post_stamp > $mtf->last_post_date) {
				th_inc_post_count($mtf->thread_id, 1, $mtf->id, $mtf->post_stamp);
			} else {
				th_inc_post_count($mtf->thread_id, 1);
			}
			th_reply_rebuild($mtf->forum_id, $mtf->thread_id, $mtf->thread_opt>=2);
			$threads = 0;
		}

		/* update forum thread & post count as well as last_post_id field */
		q('UPDATE {SQL_TABLE_PREFIX}forum SET post_count=post_count+1, thread_count=thread_count+'.$threads.', last_post_id='.$mtf->last_post_id.' WHERE id='.$mtf->forum_id);

		if ($mtf->poll_id) {
			poll_activate($mtf->poll_id, $mtf->forum_id);
		}

		$mtf->body = read_msg_body($mtf->foff, $mtf->length, $mtf->file_id);

		if ($GLOBALS['FUD_OPT_1'] & 16777216) {
			index_text((strncasecmp($mtf->subject, 'Re: ', 4) ? $mtf->subject : ''), $mtf->body, $mtf->id);
		}

		/* handle notifications */
		if ($mtf->root_msg_id == $mtf->id || $GLOBALS['FUD_OPT_3'] & 16384) {
			if (empty($mtf->frm_last_post_date)) {
				$mtf->frm_last_post_date = 0;
			}

			/* send new thread notifications to forum subscribers */
			$to = db_all('SELECT u.email
					FROM {SQL_TABLE_PREFIX}forum_notify fn
					INNER JOIN {SQL_TABLE_PREFIX}users u ON fn.user_id=u.id AND (u.users_opt & 134217728) = 0
					INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id='.$mtf->forum_id.'
					LEFT JOIN {SQL_TABLE_PREFIX}forum_read r ON r.forum_id=fn.forum_id AND r.user_id=fn.user_id
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id=fn.user_id AND g2.resource_id='.$mtf->forum_id.'
					LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id='.$mtf->forum_id.' AND mm.user_id=u.id
				WHERE
					fn.forum_id='.$mtf->forum_id.' AND fn.user_id!='.(int)$mtf->poster_id.'
					'.($GLOBALS['FUD_OPT_3'] & 64 ? 'AND (CASE WHEN (r.last_view IS NULL AND (u.last_read=0 OR u.last_read >= '.$mtf->frm_last_post_date.')) OR r.last_view > '.$mtf->frm_last_post_date.' THEN 1 ELSE 0 END)=1' : '').'
					AND ((COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 2) > 0 OR (u.users_opt & 1048576) > 0 OR mm.id IS NOT NULL)');
			if ($GLOBALS['FUD_OPT_3'] & 16384) {
				$notify_type = 'thr';
			} else {
				$notify_type = 'frm';
			}
		} else {
			$to = array();
		}
		if ($mtf->root_msg_id != $mtf->id) {
			/* send new reply notifications to thread subscribers */
			$tmp = db_all('SELECT u.email
					FROM {SQL_TABLE_PREFIX}thread_notify tn
					INNER JOIN {SQL_TABLE_PREFIX}users u ON tn.user_id=u.id AND (u.users_opt & 134217728) = 0
					INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id=2147483647 AND g1.resource_id='.$mtf->forum_id.'
					LEFT JOIN {SQL_TABLE_PREFIX}read r ON r.thread_id=tn.thread_id AND r.user_id=tn.user_id
					LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id=tn.user_id AND g2.resource_id='.$mtf->forum_id.'
					LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id='.$mtf->forum_id.' AND mm.user_id=u.id
				WHERE
					tn.thread_id='.$mtf->thread_id.' AND tn.user_id!='.(int)$mtf->poster_id.'
					'.($GLOBALS['FUD_OPT_3'] & 64 ? 'AND (r.msg_id='.$mtf->last_post_id.' OR (r.msg_id IS NULL AND '.$mtf->post_stamp.' > u.last_read))' : '').'
					AND ((COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 2) > 0 OR (u.users_opt & 1048576) > 0 OR mm.id IS NOT NULL)');
			$to = !$to ? $tmp : array_unique(array_merge($to, $tmp));
			$notify_type = 'thr';
		}
		if ($to) {
			send_notifications($to, $mtf->id, $mtf->subject, $mtf->alias, $notify_type, ($notify_type == 'thr' ? $mtf->thread_id : $mtf->forum_id), $mtf->frm_name, $mtf->forum_id);
		}

		// Handle Mailing List and/or Newsgroup syncronization.
		if (($mtf->nntp_id || $mtf->mlist_id) && !$mtf->mlist_msg_id) {
			fud_use('email_msg_format.inc', 1);

			$from = $mtf->poster_id ? reverse_fmt($mtf->real_name).' <'.$mtf->email.'>' : $GLOBALS['ANON_NICK'].' <'.$GLOBALS['NOTIFY_FROM'].'>';
			$body = $mtf->body . (($mtf->msg_opt & 1 && $mtf->sig) ? "\n--\n" . $mtf->sig : '');
			$body = plain_text($body, '{TEMPLATE: post_html_quote_start_p1}', '{TEMPLATE: post_html_quote_start_p2}', '{TEMPLATE: post_html_quote_end}');
			$mtf->subject = reverse_fmt($mtf->subject);

			if ($mtf->reply_to) {
				$replyto_id = q_singleval('SELECT mlist_msg_id FROM {SQL_TABLE_PREFIX}msg WHERE id='.$mtf->reply_to);
			} else {
				$replyto_id = 0;
			}

			if ($mtf->attach_cnt) {
				$r = uq("SELECT a.id, a.original_name, COALESCE(m.mime_hdr, 'application/octet-stream')
						FROM {SQL_TABLE_PREFIX}attach a
						LEFT JOIN {SQL_TABLE_PREFIX}mime m ON a.mime_type=m.id
						WHERE a.message_id=".$mtf->id." AND a.attach_opt=0");
				while ($ent = db_rowarr($r)) {
					$attach[$ent[1]] = file_get_contents($GLOBALS['FILE_STORE'].$ent[0].'.atch');
					if ($mtf->mlist_id) {
						$attach_mime[$ent[1]] = $ent[2];
					}
				}
				unset($r);
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
				$nntp->nntp_opt = $nntp_adm->nntp_opt;
				$nntp->login = $nntp_adm->login;
				$nntp->pass = $nntp_adm->pass;

				define('sql_p', '{SQL_TABLE_PREFIX}');

				$lock = $nntp->get_lock();
				$nntp->post_message($mtf->subject, $body, $from, $mtf->id, $replyto_id, $attach);
				$nntp->close_connection();
				$nntp->release_lock($lock);
			} else {
				fud_use('mlist_post.inc', true);
				
				$r = db_saq('SELECT name, additional_headers FROM {SQL_TABLE_PREFIX}mlist WHERE id='.$mtf->mlist_id);
				mail_list_post($r[0], $from, $mtf->subject, $body, $mtf->id, $replyto_id, $attach, $attach_mime, $r[1]);
			}
		}
	}
}

function write_body($data, &$len, &$offset, $fid)
{
	$MAX_FILE_SIZE = 2140000000;

	$len = strlen($data);
	$i = 1;

	db_lock('{SQL_TABLE_PREFIX}fl_'.$fid.' WRITE');

	$s = $fid * 10000;
	$e = $s + 100;
	
	while ($s < $e) {
		$fp = fopen($GLOBALS['MSG_STORE_DIR'].'msg_'.$s, 'ab');
		if (!$fp) {
			exit("FATAL ERROR: could not open message store for forum id#".$s."<br>\n");
		}
		fseek($fp, 0, SEEK_END);
		if (!($off = ftell($fp))) {
			$off = __ffilesize($fp);
		}
		if (!$off || ($off + $len) < $MAX_FILE_SIZE) {
			break;
		}
		fclose($fp);
		$s++;
	}

	if (fwrite($fp, $data) !== $len) {
		if ($fid) {
			db_unlock();
		}
		exit("FATAL ERROR: system has ran out of disk space<br>\n");
	}
	fclose($fp);

	db_unlock();

	if (!$off) {
		@chmod('msg_'.$s, ($GLOBALS['FUD_OPT_2'] & 8388608 ? 0600 : 0666));
	}
	$offset = $off;

	return $s;
}

function trim_html($str, $maxlen)
{
	$n = strlen($str);
	$ln = 0;
	$tree = array();
	for ($i = 0; $i < $n; $i++) {
		if ($str[$i] != '<') {
			$ln++;
			if ($ln > $maxlen) {
				break;
			}
			continue;
		}

		if (($p = strpos($str, '>', $i)) === false) {
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
	if ($tree) {
		foreach (array_reverse($tree) as $v) {
			$data .= '</'.$v.'>';
		}
	}

	return $data;
}

function make_email_message(&$body, &$obj, $iemail_unsub)
{
	$TITLE_EXTRA = $iemail_poll = $iemail_attach = '';
	if ($obj->poll_cache) {
		$pl = unserialize($obj->poll_cache);
		if (!empty($pl)) {
			foreach ($pl as $k => $v) {
				$length = ($v[1] && $obj->total_votes) ? round($v[1] / $obj->total_votes * 100) : 0;
				$iemail_poll .= '{TEMPLATE: iemail_poll_result}';
			}
			$iemail_poll = '{TEMPLATE: iemail_poll_tbl}';
		}
	}
	if ($obj->attach_cnt && $obj->attach_cache) {
		$atch = unserialize($obj->attach_cache);
		if (!empty($atch)) {
			foreach ($atch as $v) {
				$sz = $v[2] / 1024;
				$sz = $sz < 1000 ? number_format($sz, 2).'KB' : number_format($sz/1024, 2).'MB';
				$iemail_attach .= '{TEMPLATE: iemail_attach_entry}';
			}
			$iemail_attach = '{TEMPLATE: iemail_attach}';
		}
	}

	if ($GLOBALS['FUD_OPT_2'] & 32768 && defined('_rsid')) {
		$pfx = str_repeat('/', substr_count(_rsid, '/'));
	}

	// we need this for spam filters like SpamAssassin
	return str_replace('<script language="JavaScript" src="lib.js" type="text/javascript"></script>', '', '{TEMPLATE: iemail_body}');
}

function poll_cache_rebuild($poll_id)
{
	if (!$poll_id) {
		return;
	}

	$data = array();
	$c = uq('SELECT id, name, count FROM {SQL_TABLE_PREFIX}poll_opt WHERE poll_id='.$poll_id);
	while ($r = db_rowarr($c)) {
		$data[$r[0]] = array($r[1], $r[2]);
	}
	unset($c);
	
	if ($data) {
		return serialize($data);
	} else {
		return;
	}
}

function send_notifications($to, $msg_id, $thr_subject, $poster_login, $id_type, $id, $frm_name, $frm_id)
{
	if (!$to) {
		return;
	}

	$goto_url['email'] = '{FULL_ROOT}{ROOT}?t=rview&goto='.$msg_id.'#msg_'.$msg_id;
	$CHARSET = $GLOBALS['CHARSET'];
	if ($GLOBALS['FUD_OPT_2'] & 64) {
		$obj = db_sab("SELECT p.total_votes, p.name AS poll_name, m.reply_to, m.subject, m.id, m.post_stamp, m.poster_id, m.foff, m.length, m.file_id, u.alias, m.attach_cnt, m.attach_cache, m.poll_cache FROM {SQL_TABLE_PREFIX}msg m LEFT JOIN {SQL_TABLE_PREFIX}users u ON m.poster_id=u.id LEFT JOIN {SQL_TABLE_PREFIX}poll p ON m.poll_id=p.id WHERE m.id=".$msg_id." AND m.apr=1");

		if (!$obj->alias) { /* anon user */
			$obj->alias = htmlspecialchars($GLOBALS['ANON_NICK']);
		}

		$headers  = "MIME-Version: 1.0\r\n";
		if ($obj->reply_to) {
			$headers .= "In-Reply-To: ".$obj->reply_to."\r\n";
		}
		$headers .= "List-Id: ".$frm_id.".".(isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost')."\r\n";
		$split = get_random_value(128)                                                                            ;
		$headers .= "Content-Type: multipart/alternative;\n  boundary=\"------------" . $split . "\"\r\n";
		$boundry = "\r\n--------------" . $split . "\r\n";

		$pfx = '';
		if ($GLOBALS['FUD_OPT_2'] & 32768 && !empty($_SERVER['PATH_INFO'])) {
			if ($GLOBALS['FUD_OPT_1'] & 128) {
				$pfx .= '0/';
			}
			if ($GLOBALS['FUD_OPT_2'] & 8192) {
				$pfx .= '0/';
			}
		}

		$plain_text = read_msg_body($obj->foff, $obj->length, $obj->file_id);
		$iemail_unsub = html_entity_decode($id_type == 'thr' ? '{TEMPLATE: iemail_thread_unsub}' : '{TEMPLATE: iemail_forum_unsub}');

		$body_email = 	$boundry . "Content-Type: text/plain; charset=" . $CHARSET . "; format=flowed\r\nContent-Transfer-Encoding: 7bit\r\n\r\n" . html_entity_decode(strip_tags($plain_text)) . "\r\n\r\n" . html_entity_decode('{TEMPLATE: iemail_participate}') . ' ' . '{FULL_ROOT}{ROOT}?t=rview&th=' . $id . "\r\n" .
				$boundry . "Content-Type: text/html; charset=" . $CHARSET . "\r\nContent-Transfer-Encoding: 7bit\r\n\r\n" . make_email_message($plain_text, $obj, $iemail_unsub) . "\r\n" . substr($boundry, 0, -2) . "--\r\n";
	} else {
		$headers = "Content-Type: text/plain; charset={$CHARSET}\r\n";
	}

	$thr_subject = reverse_fmt($thr_subject);
	$poster_login = reverse_fmt($poster_login);

	if ($id_type == 'thr') {
		$subj = html_entity_decode('{TEMPLATE: iemail_thr_subject}');

		if (!isset($body_email)) {
			$unsub_url['email'] = '{FULL_ROOT}{ROOT}?t=rview&th='.$id.'&notify=1&opt=off';
			$body_email = html_entity_decode('{TEMPLATE: iemail_thr_bodyemail}');
		}
	} else if ($id_type == 'frm') {
		$frm_name = reverse_fmt($frm_name);

		$subj = html_entity_decode('{TEMPLATE: iemail_frm_subject}');

		if (!isset($body_email)) {
			$unsub_url['email'] = '{FULL_ROOT}{ROOT}?t=rview&unsub=1&frm_id='.$id;
			$body_email = html_entity_decode('{TEMPLATE: iemail_frm_bodyemail}');
		}
	}

	send_email($GLOBALS['NOTIFY_FROM'], $to, $subj, $body_email, $headers);
}
?>