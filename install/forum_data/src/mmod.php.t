<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: mmod.php.t,v 1.8 2003/03/30 18:03:11 hackie Exp $
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
	{POST_HTML_PHP}
	
	if (isset($_REQUEST['del']) && (int)$_REQUEST['del']) {
		$_REQUEST['del'] = (int)$_REQUEST['del'];
		$msg = new fud_msg_edit;
		$msg->get_by_id($_REQUEST['del']);
		$th = $msg->thread_id;
	} else if (isset($_REQUEST['th']) && (int)$_REQUEST['th']) {
		$th = (int) $_REQUEST['th'];	
	} else {
		check_return($ses->returnto);
	}

	$thread = new fud_thread;
	$frm = new fud_forum;
	$thread->get_by_id($th);
	$frm->get($thread->forum_id);
	
	if (($usr->is_mod == 'A' || $frm->is_moderator(_uid))) {
		$MOD = 1;
	} else {
		if (isset($_REQUEST['del']) && !is_perms(_uid, $frm->id, 'DEL')) {
			check_return($ses->returnto);
		} else if (isset($_REQUEST['lock']) && !is_perms(_uid, $frm->id, 'LOCK')) {
			check_return($ses->returnto);
		} else {
			check_return($ses->returnto);
		}
	}
	
	if (!empty($_REQUEST['del'])) {
		if (empty($_POST['confirm'])) {
			if ($msg->id != $thread->root_msg_id) {
				$delete_msg = '{TEMPLATE: single_msg_delete}';
			} else {
				$delete_msg = '{TEMPLATE: thread_delete}';
			}

			?> {TEMPLATE: delete_confirm_pg} <?php 
			exit;
		}
		
		if (isset($_POST['YES'])) {
			if ($thread->root_msg_id == $msg->id) {
				logaction(_uid, 'DELTHR', 0, '"'.addslashes($thread->subject).'" w/'.$thread->replies.' replies');
			} else {
				logaction(_uid, 'DELMSG', 0, addslashes($msg->subject));
			}
			$msg->delete();
			if ($msg->id == $thread->root_msg_id) {
				header('Location: {ROOT}?t='.t_thread_view.'&'._rsidl.'&frm_id='.$frm->id);
				exit;
			}
		}
		
		if (d_thread_view == 'tree') {
			if (!$msg->reply_to) {
				header('Location: {ROOT}?t=tree&'._rsidl.'&th='.$thread->id);
			} else {
				header('Location: {ROOT}?t=tree&'._rsidl.'&th='.$thread->id.'&mid='.$msg->reply_to);
			}
		} else {
			$count = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;
			$pos = q_singelval('SELECT replies + 1 FROM {SQL_TABLE_PREFIX}thread WHERE id='.$thread->id);
			$start = (ceil((pos/$count))-1)*$count;
			header('Location: {ROOT}?t=msg&th='.$thread->id.'&'._rsidl.'&start='.$start);
		}
		exit;
	} else {
		if (isset($_REQUEST['lock'])) {
			logaction(_uid, 'THRLOCK', $thread->id);
			$thread->lock($thread->id);
		} else {
			logaction(_uid, 'THRUNLOCK', $thread->id);
			$thread->unlock($thread->id);	
		}
	}
	check_return($ses->returnto);
?>