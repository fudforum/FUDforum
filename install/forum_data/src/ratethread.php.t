<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ratethread.php.t,v 1.3 2003/04/09 14:11:42 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/
	
	if (isset($_POST['rate_thread_id'])) {
		$th = (int) $_POST['rate_thread_id'];
		if (isset($_POST['sel_vote'])) { /* regular rating */
			$rt = (int) $_POST['sel_vote'];
			/* determine if the user has permission to rate the thread */
			$perm = db_saq('SELECT m.id, '.(_uid ? '(CASE WHEN g2.id IS NOT NULL THEN g2.p_RATE ELSE g1.p_RATE END)' : 'g1.p_RATE').' AS p_rate 
				FROM {SQL_TABLE_PREFIX}thread t 
				LEFT JOIN {SQL_TABLE_PREFIX}mod m ON t.forum_id=m.forum_id AND m.user_id='._uid.'
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? 2147483647 : 0).' AND g1.resource_id=t.forum_id 
				'.(_uid ? ' LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id ' : '').'
				WHERE t.id='.$th);
			if (!$perm || ($usr->is_mod != 'A' && !$perm[0] && $perm[1] != 'Y')) {
				std_error('access');
			}
			
			db_lock('{SQL_TABLE_PREFIX}thread_rate_track WRITE, {SQL_TABLE_PREFIX}thread WRITE');
			if (!q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id='.$th.' AND user_id='._uid)) {
				q('INSERT INTO {SQL_TABLE_PREFIX}thread_rate_track (thread_id, user_id, stamp, rating) VALUES('.$th.', '._uid.', '.__request_timestamp__.', '.$rt.')');
				$rt = db_saq('SELECT count(*), ROUND(AVG(rating)) FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id='.$th);
				q('UPDATE {SQL_TABLE_PREFIX}thread SET rating='.(int)$rt[1].', n_rating='.(int)$rt[0].' WHERE id='.$th);
			}
			db_unlock();
		} else if (isset($_POST['th_rating_'.$th])) { /* admin/mod only rating */
			if ($usr->is_mod != 'A' && !q_singleval('SELECT m.id FROM {SQL_TABLE_PREFIX}thread t INNER JOIN {SQL_TABLE_PREFIX}mod m ON t.forum_id=m.forum_id AND m.user_id='._uid.' WHERE t.id='.$th)) {
				std_error('access');
			}
			q('UPDATE {SQL_TABLE_PREFIX}thread SET rating='.intnull($_POST['th_rating_'.$th]).' WHERE id='.$th);
		}
	}	
	check_return($usr->returnto);
?>