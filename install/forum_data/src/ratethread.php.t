<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: ratethread.php.t,v 1.6 2003/10/01 21:51:52 hackie Exp $
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

	if (isset($_POST['rate_thread_id'], $_POST['sel_vote'])) {
		$th = (int) $_POST['rate_thread_id'];
		$rt = (int) $_POST['sel_vote'];

		/* determine if the user has permission to rate the thread */
		if (!q_singleval('SELECT t.id
				FROM {SQL_TABLE_PREFIX}thread t
				LEFT JOIN {SQL_TABLE_PREFIX}mod m ON t.forum_id=m.forum_id AND m.user_id='._uid.'
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? 2147483647 : 0).' AND g1.resource_id=t.forum_id
				'.(_uid ? ' LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=t.forum_id ' : '').'
				WHERE t.id='.$th.($usr->users_opt & 1048576 ? '' : ' AND (m.id IS NOT NULL OR (CASE WHEN g1.id IS NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) & 1024)')  . ' LIMIT 1')) {
			std_error('access');
		}

		db_lock('{SQL_TABLE_PREFIX}thread_rate_track WRITE, {SQL_TABLE_PREFIX}thread WRITE');
		if (!q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id='.$th.' AND user_id='._uid)) {
			q('INSERT INTO {SQL_TABLE_PREFIX}thread_rate_track (thread_id, user_id, stamp, rating) VALUES('.$th.', '._uid.', '.__request_timestamp__.', '.$rt.')');
			$rt = db_saq('SELECT count(*), ROUND(AVG(rating)) FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id='.$th);
			q('UPDATE {SQL_TABLE_PREFIX}thread SET rating='.(int)$rt[1].', n_rating='.(int)$rt[0].' WHERE id='.$th);
		}
		db_unlock();
	}
	check_return($usr->returnto);
?>