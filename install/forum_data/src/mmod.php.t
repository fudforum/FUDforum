<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: mmod.php.t,v 1.3 2002/07/06 13:38:22 hackie Exp $
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
	{POST_HTML_PHP}
	
	$thread = new fud_thread;
	$frm = new fud_forum;
		
	if( is_numeric($del) && !empty($del) ) {
		$msg = new fud_msg_edit;
		$msg->get_by_id($del);
		$th = $msg->thread_id;
	}
	else if( is_numeric($th) && !empty($th) ) {
		/* nop */
	}
	else check_return();
	
	$thread->get_by_id($th);
	$frm->get($thread->forum_id);
	
	if ( ($usr->is_mod == 'A' || $frm->is_moderator($usr->id)) ) $MOD=1;

	$GLOBALS['__RESOURCE_ID'] = $frm->id;

	if( !$MOD ) {
		if( $del && !is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'DEL' ) )
			check_return();
		else if( $lock && !is_perms(_uid, $GLOBALS['__RESOURCE_ID'], 'LOCK') ) 
			check_return();
		else if( !$lock && !$del ) 
			check_return();	
	}
	
	if ( empty($GLOBALS['HTTP_POST_VARS']['det_page']) ) {
		preg_match('/\?t=([A-Z0-9a-z_]+)(\&|$)/', $GLOBALS['HTTP_SERVER_VARS']['HTTP_REFERER'], $regs);
		$det_page = $regs[1];
	}
	
	if( !empty($del) ) {
		if( empty($confirm) ) {
			$ret = create_return();
			if( $msg->id != $thread->root_msg_id ) 
				$delete_msg = '{TEMPLATE: single_msg_delete}';
			else
				$delete_msg = '{TEMPLATE: thread_delete}';	

			exit('{TEMPLATE: delete_confirm_pg}');
		}
		
		if ( !empty($YES) ) {
			if( $thread->root_msg_id == $msg->id ) 
				logaction($usr->id, 'DELTHR', 0, '"'.addslashes($thread->subject).'" w/'.$thread->replies.' replies');
			else 
				logaction($usr->id, 'DELMSG', 0, addslashes($msg->subject));
				
			$msg->delete();
		}
		
		if ( $det_page == 'tree' || $det_page == 'msg' ) {
			if( $msg->id == $thread->root_msg_id && empty($NO) ) {
				header('Location: {ROOT}?t=thread&'._rsid.'&frm_id='.$frm->id);
				exit;
			}
			
			switch ( $det_page )
			{
				case 'tree':
					if( !$msg->reply_to ) 
						header('Location: {ROOT}?t=tree&'._rsid.'&th='.$thread->id);
					else 
						header('Location: {ROOT}?t=tree&'._rsid.'&th='.$thread->id.'&mid='.$msg->reply_to);
					exit;
					break;
				default:
					$count = !empty($usr->posts_ppg) ? $usr->posts_ppg : $GLOBALS['POSTS_PER_PAGE'];
					$pos = q_singleval("SELECT count(*) FROM {SQL_TABLE_PREFIX}msg WHERE thread_id=".$thread->id." AND id<=".$msg->id." AND approved='Y'");
					$start = (ceil(($pos/$count))-1)*$count;
					header('Location: {ROOT}?t=msg&th='.$thread->id.'&'._rsid.'&start='.$start);
					exit;
			}	
		}
	}
	else {
		if( !empty($GLOBALS["lock"]) ) {
			logaction($usr->id, 'THRLOCK', $thread->id);
			$thread->lock();
		}
		else {
			logaction($usr->id, 'THRUNLOCK', $thread->id);
			$thread->unlock();	
		}
	}
	check_return();
?>