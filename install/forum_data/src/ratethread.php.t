<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ratethread.php.t,v 1.2 2002/07/30 14:34:37 hackie Exp $
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
	
	if ( !empty($rate_thread_id) ) {
		$thr = new fud_thread;
		$frm = new fud_forum;
		$thr->get_by_id($rate_thread_id);
		$frm->get($thr->forum_id);
		if ( is_perms(_uid, $frm->id, 'VOTE') && is_numeric($HTTP_POST_VARS['th_rating_'.$rate_thread_id]) ) {
			if ( !$frm->is_moderator($usr->id) && $usr->is_mod != 'A' ) {
				std_error('access');
				exit();
			}
			$thr->adm_set_rating($HTTP_POST_VARS['th_rating_'.$rate_thread_id]);
		}
		else if( is_numeric($sel_vote) ) 
			$thr->register_thread_vote(_uid, $sel_vote);
	}
	check_return();
	exit();
?>