<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: imsg_edt.inc.t,v 1.21 2003/03/30 18:26:52 hackie Exp $
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
	
	function add_reply($reply_to, $th_id=NULL, $autoapprove=TRUE)
	{
		if ($reply_to) {
			$this->reply_to = $reply_to;
			$fd = db_saq('SELECT {SQL_TABLE_PREFIX}thread.forum_id,{SQL_TABLE_PREFIX}forum.message_threshold,{SQL_TABLE_PREFIX}forum.moderated FROM {SQL_TABLE_PREFIX}msg INNER JOIN {SQL_TABLE_PREFIX}thread ON {SQL_TABLE_PREFIX}msg.thread_id={SQL_TABLE_PREFIX}thread.id INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}thread.forum_id WHERE {SQL_TABLE_PREFIX}msg.id='.$reply_to);
		} else {
			$fd = db_saq('SELECT {SQL_TABLE_PREFIX}thread.forum_id,{SQL_TABLE_PREFIX}forum.message_threshold,{SQL_TABLE_PREFIX}forum.moderated FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}forum ON {SQL_TABLE_PREFIX}forum.id={SQL_TABLE_PREFIX}thread.forum_id WHERE {SQL_TABLE_PREFIX}thread.id='.$th_id);
		}
		
		return $this->add($fd[0], $fd[1], $fd[2], $autoapprove);
	}
	
	function add($forum_id, $message_threshold, $moderated, $autoapprove=TRUE)
	{
		if (!$this->attachment_id) {
			$this->attachment_id = 0;
		}
		
		if (!$this->post_stamp) {
			$this->post_stamp = __request_timestamp__;
		}

		$this->ip_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "'0.0.0.0'";
		$this->host_name = $GLOBALS['PUBLIC_RESOLVE_HOST'] == 'Y' ? get_host($this->ip_addr) : 'NULL';
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
			mlist_msg_id
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
			".strnull($this->mlist_msg_id)."
		)");

		if ((!empty($GLOBALS['MOD']) || is_perms($this->poster_id, $forum_id, 'STICKY')) && isset($_POST['thr_locked'])) {
			$thr_locked = $_POST['thr_locked'] == 'Y' ? 'Y' : 'N';
		} else {
			$thr_locked = 'N';
		}

		if (!$this->thread_id) { /* new thread */
			if ((!empty($GLOBALS['MOD']) || is_perms($this->poster_id, $forum_id, 'STICKY')) && isset($_POST['thr_ordertype'], $_POST['thr_orderexpiry'])) {
				if ($_POST['thr_ordertype'] != 'NONE' && ($_POST['thr_ordertype'] == 'ANNOUNCE' || $_POST['thr_ordertype'] == 'STICKY')) {
					$is_sticky = 'Y';
					$thr_ordertype = $_POST['thr_ordertype'];
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
			if (thr_locked == 'Y') {
				fud_thread::lock($this->thread_id);
			} else {
				fud_thread::unlock($this->thread_id);
			}
		}
		
		if ($autoapprove && $moderated == 'Y') {
			$this->approve(NULL, TRUE);
		}

		return $this->id;
	}
	
	function sync($id, $frm_id, $message_threshold)
	{
		if (!db_locked()) {
			db_lock('WRITE {SQL_TABLE_PREFIX}cat, WRITE {SQL_TABLE_PREFIX}forum, WRITE {SQL_TABLE_PREFIX}msg, WRITE {SQL_TABLE_PREFIX}thread, WRITE {SQL_TABLE_PREFIX}thread_view');
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
			icon=".strnull($this->icon)." 
		WHERE id=".$this->id);
		
		/* determine wether or not we should deal with locked & sticky stuff 
		 * current approach may seem a little redundant, but for (most) users who 
		 * do not have access to locking & sticky this eliminated a query.
		 */
		if (isset($_POST['thr_ordertype'], $_POST['thr_orderexpiry']) || isset($_POST['thr_locked'])) {
			$th_data = db_saq('SELECT ordertype, orderexpiry, locked, root_msg_id FROM {SQL_TABLE_PREFIX}thread WHERE id='.$this->thread_id);

			if (isset($_POST['thr_locked']) && $_POST['thr_locked'] != $th_data[2]) {
				/* confirm that user has ability to change lock status of the thread */
				if (!empty($GLOBALS['MOD']) || is_perms($this->poster_id, $frm->id, 'LOCK')) {
					$thr_locked = $_POST['thr_locked'];
				}	
			}

			if ($th_data[3] == $this->id && isset($_POST['thr_ordertype'], $_POST['thr_orderexpiry']) && ($_POST['thr_ordertype'] != $th_data[0] || $_POST['thr_orderexpiry'] != $th_data[1])) {
				/* confirm that user has ability to change sticky status of the thread */
				if (!empty($GLOBALS['MOD']) || is_perms($this->poster_id, $frm->id, 'STICKY')) {
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
	
	function export_vars($prefix)
	{	
		$GLOBALS[$prefix.'subject'] = $this->subject;
		$GLOBALS[$prefix.'body'] = $this->body;
		$GLOBALS[$prefix.'icon'] = $this->icon;
		$GLOBALS[$prefix.'show_sig'] = $this->show_sig;
	}
	
	function delete($rebuild_view=TRUE, $mid=0, $th_rm=0)
	{
		if (!db_locked()) {
			db_lock('WRITE {SQL_TABLE_PREFIX}thr_exchange, WRITE {SQL_TABLE_PREFIX}thread_view, WRITE {SQL_TABLE_PREFIX}level, WRITE {SQL_TABLE_PREFIX}forum, WRITE {SQL_TABLE_PREFIX}forum_read, WRITE {SQL_TABLE_PREFIX}thread, WRITE {SQL_TABLE_PREFIX}msg, WRITE {SQL_TABLE_PREFIX}attach, WRITE {SQL_TABLE_PREFIX}poll, WRITE {SQL_TABLE_PREFIX}poll_opt, WRITE {SQL_TABLE_PREFIX}poll_opt_track, WRITE {SQL_TABLE_PREFIX}users, WRITE {SQL_TABLE_PREFIX}thread_notify, WRITE {SQL_TABLE_PREFIX}msg_report, WRITE {SQL_TABLE_PREFIX}thread_rate_track');
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

		if ($this->poster_id && $del->approved == 'Y') {
			fud_user::set_post_count($this->poster_id, -1);
		}

		if (!$th_rm && $del->root_msg_id != $mid && $del->thread_lip == $mid) {
			$mid = (int) q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$del->thread_id." AND approved='Y' ORDER BY post_stamp DESC LIMIT 1");
			q('UPDATE {SQL_TABLE_PREFIX}thread SET last_post_id='.$mid.' WHERE id='.$del->thread_id);
		}

		if ($del->root_msg_id == $del->id && $del->approved == 'Y') {
			$mid = (int) q_singleval("SELECT last_post_id FROM {SQL_TABLE_PREFIX}thread INNER JOIN {SQL_TABLE_PREFIX}msg ON {SQL_TABLE_PREFIX}thread.last_post_id={SQL_TABLE_PREFIX}msg.id WHERE forum_id=".$v['forum_id']." AND {SQL_TABLE_PREFIX}msg.approved='Y' ORDER BY last_post_id DESC LIMIT 1");
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
	
	function approve($id=NULL, $unlock_safe=FALSE)
	{	
		if( !db_locked() ) {
			db_lock('WRITE {SQL_TABLE_PREFIX}thread_view, WRITE {SQL_TABLE_PREFIX}level, WRITE {SQL_TABLE_PREFIX}cat, WRITE {SQL_TABLE_PREFIX}users, WRITE {SQL_TABLE_PREFIX}forum, WRITE {SQL_TABLE_PREFIX}thread, WRITE {SQL_TABLE_PREFIX}msg');
			$ll = 1;
		}

		if ($id) {
			$this->get_by_id($id);
			$this->subject = addslashes($this->subject);
			$this->body = addslashes($this->body);
		}	

		if (empty($this->approved)) {
			$this->approved = q_singleval("SELECT approved FROM {SQL_TABLE_PREFIX}msg WHERE id=".$this->id);
		}

		if ($this->approved == 'Y') {
			return;
		}

		$thr = new fud_thread;
		$frm = new fud_forum;
			
		$thr->get_by_id($this->thread_id);
		$frm->get($thr->forum_id);
			
		q("UPDATE {SQL_TABLE_PREFIX}msg SET approved='Y' WHERE id=".$this->id);
			
		if ($this->poster_id) {
			fud_user::set_post_count($this->poster_id, 1, $this->id);
		}
		
		if ($thr->last_post_id <= $this->id) { 
			q("UPDATE {SQL_TABLE_PREFIX}thread SET last_post_id=".$this->id.", last_post_date=".$this->post_stamp." WHERE id=".$this->thread_id);
				
			if (q_singleval("SELECT last_post_id FROM {SQL_TABLE_PREFIX}forum WHERE id=".$thr->forum_id) < $this->id) {
				q("UPDATE {SQL_TABLE_PREFIX}forum SET last_post_id=".$this->id." WHERE id=".$thr->forum_id);
			}
		}	

		if( $thr->root_msg_id == $this->id ) {	/* new thread */
			q('UPDATE {SQL_TABLE_PREFIX}forum SET thread_count=thread_count+1 WHERE id='.$frm->id);
			rebuild_forum_view($thr->forum_id);
		} else {				/* reply to thread */
			$thr->inc_post_count(1);
			rebuild_forum_view($thr->forum_id, q_singleval("SELECT page FROM {SQL_TABLE_PREFIX}thread_view WHERE forum_id=".$thr->forum_id." AND thread_id=".$this->thread_id));
		}	
				
		$frm->inc_reply_count(1);

		if ($unlock_safe || isset($ll)) {
			db_unlock();
		}

		if ($GLOBALS['FORUM_SEARCH'] == 'Y') {
			index_text((preg_match("!Re: !i", $this->subject) ? '': $this->subject), $this->body, $this->id);
		}

		if ($thr->root_msg_id == $this->id) {
			send_notifications($frm->get_notify_list(intzero($this->poster_id)), $this->id, $thr->subject, ($GLOBALS['usr']->login?$GLOBALS['usr']->login:$GLOBALS['ANON_NICK']), 'frm', $frm->id, $frm->name);
		} else {
			send_notifications($thr->get_notify_list(intzero($this->poster_id)), $this->id, $thr->subject, ($GLOBALS['usr']->login?$GLOBALS['usr']->login:$GLOBALS['ANON_NICK']), 'thr', $thr->id);
		}

		// Handle Mailing List and/or Newsgroup syncronization.
		if (!$this->mlist_msg_id) {
			if (($mlist_id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}mlist WHERE forum_id=".$frm->id." AND allow_frm_post='Y'"))) {
				fud_use('email_msg_format.inc', true);
				fud_use('mlist_post.inc', true);
				
				$GLOBALS['CHARSET'] = '{TEMPLATE: imsg_CHARSET}';
				
				if ($this->poster_id) {
					$r = db_saq('SELECT alias,email,sig FROM {SQL_TABLE_PREFIX}users WHERE id='.$this->poster_id);
					$from = $r[0].' <'.$r[1].'>';
				} else {
				 	$from = $GLOBALS['ANON_NICK'].' <'.$GLOBALS['NOTIFY_FROM'].'>';
				}
				
				$body = stripslashes($this->body);
				if ($this->show_sig == 'Y' && !empty($r[3])) {
					$body .= "\n--\n".$r[3];
				}
				plain_text($body);
				
				if ($this->reply_to) {
					$replyto_id = q_singleval('SELECT mlist_msg_id FROM {SQL_TABLE_PREFIX}msg WHERE id='.$this->reply_to);
				} else {
					$replyto_id = 0;
				}
				
				if ($this->attach_cnt) {
					$r = q("SELECT {SQL_TABLE_PREFIX}attach.id, {SQL_TABLE_PREFIX}attach.original_name, {SQL_TABLE_PREFIX}mime.mime_hdr FROM {SQL_TABLE_PREFIX}attach INNER JOIN {SQL_TABLE_PREFIX}mime ON {SQL_TABLE_PREFIX}attach.mime_type={SQL_TABLE_PREFIX}mime.id WHERE message_id=".$this->id." AND private='N'");
					while ($ent = db_rowarr($r)) {
						$attach[$ent[1]][] = file_get_contents($GLOBALS['FILE_STORE'].$ent[0].'.atch');
						$attach[$ent[1]][] = $ent[2];
					}
					qf($r);
				} else {
					$attach = null;
				}
				
				$addr = q_singleval('SELECT name FROM {SQL_TABLE_PREFIX}mlist WHERE forum_id='.$frm->id);
				mail_list_post($addr, $from, $this->subject, $body, $this->id, $replyto_id, $attach, '');
			} else if (($nntp_id = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}nntp WHERE forum_id=".$frm->id." AND allow_frm_post='Y'"))) {
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

				if ($this->poster_id) {
					$r = db_saq('SELECT alias,email,sig FROM {SQL_TABLE_PREFIX}users WHERE id='.$this->poster_id);
					$from = $r[0].' <'.$r[1].'>';
				} else {
				 	$from = $GLOBALS['ANON_NICK'].' <'.$GLOBALS['NOTIFY_FROM'].'>';
				}
				
				$body = stripslashes($this->body);
				if ($this->show_sig == 'Y' && !empty($r[3])) {
					$body .= "\n--\n".$r[3];
				}
				
				if ($this->reply_to) {
					$replyto_id = q_singleval('SELECT mlist_msg_id FROM {SQL_TABLE_PREFIX}msg WHERE id='.$this->reply_to);
				} else {
					$replyto_id = 0;
				}
				
				if( $this->attach_cnt ) {
					$r = q("SELECT id, original_name FROM {SQL_TABLE_PREFIX}attach WHERE message_id=".$this->id." AND private='N'");
					while ($ent = db_rowarr($r)) {
						$attach[$ent[1]][] = file_get_contents($GLOBALS['FILE_STORE'].$ent[0].'.atch');
					}
					qf($r);
				} else {
					$attach = null;
				}
				
				$lock = $nntp->get_lock();
				$nntp->post_message($this->subject, $body, $from, $this->id, $replyto_id, $attach);
				$nntp->close_connection();
				$nntp->release_lock($lock);
			}
		}
	}
}

function flood_check()
{
	$check_time = __request_timestamp__-$GLOBALS['FLOOD_CHECK_TIME'];
	
	if (($v = q_singleval("SELECT post_stamp FROM {SQL_TABLE_PREFIX}msg WHERE ip_addr='".$_SERVER['REMOTE_ADDR']."' AND poster_id=".((_uid)?_uid:0)." AND post_stamp>".$check_time." ORDER BY post_stamp DESC LIMIT 1"))) {
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
	$i=1;
	while( $i<100 ) {
		$fp = fopen($GLOBALS["MSG_STORE_DIR"].'msg_'.$i, 'ab');
		flock($fp, LOCK_EX);
		if( !($off = ftell($fp)) ) $off = __ffilesize($fp);
		if( !$off || sprintf("%u", $off+$len)<$MAX_FILE_SIZE ) break;
		fclose($fp);
		$i++;
	}
	
	$len = fwrite($fp, $data);
	fclose($fp);
	
	if( !$off ) @chmod('msg_'.$i, ($GLOBALS['FILE_LOCK']=='Y'?0600:0666));
	
	if( $len == -1 ) exit("FATAL ERROR: system has ran out of disk space<br>\n");
	$offset = $off;
	
	return $i;
}

function trim_html($str, $maxlen)
{
	$n = strlen($str);
	$ln = 0;
	for ( $i=0; $i<$n; $i++ ) {
		if ( $str[$i] != '<' ) {
			$ln++;
			if( $ln > $maxlen ) break;
			continue;
		}
		
		if( ($p = strpos($str, '>', $i)) === FALSE ) break;
		
		for ( $k=$i; $k<$p; $k++ ) {
			switch ( $str[$k] ) 
			{
				case ' ':
				case "\r":
				case "\n":
				case "\t":
				case ">":
					break 2;
			}
		}
		
		if ( $str[$i+1] == '/' ) {
			$tagname = strtolower(substr($str, $i+2, $k-$i-2));	
			if( @end($tagindex[$tagname]) ) {
				$k = key($tagindex[$tagname]);
				unset($tagindex[$tagname][$k]);
				unset($tree[$k]);
			}	
		}
		else {
			$tagname = strtolower(substr($str, $i+1, $k-$i-1));
			switch ( $tagname ) 
			{
				case "br":
				case "img":
				case "meta":
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
	if ( is_array($tree) ) {
		$tree = array_reverse($tree);
		foreach($tree as $v ) $data .= '</'.$v.'>';
	}

	return $data;
}
?>