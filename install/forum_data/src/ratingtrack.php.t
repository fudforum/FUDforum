<?php
/**
* copyright            : (C) 2001-2010 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

	define('plain_form', 1);

/*{PRE_HTML_PHP}*/

	/* only admins & moderators have access to this control panel */
	if (!_uid) {
		std_error('login');
	} if (!($usr->users_opt & (1048576|524288))) {
		std_error('access');
	}

	$th = isset($_GET['th']) ? (int)$_GET['th'] : 0;
	$ratingid = isset($_GET['ratingid']) ? (int)$_GET['ratingid'] : 0;
	if (!$th) {
		invl_inp_err();
	}

	$thr = db_sab('SELECT m.subject, t.forum_id, t.id FROM 
			{SQL_TABLE_PREFIX}thread t 
			INNER JOIN {SQL_TABLE_PREFIX}msg m ON t.root_msg_id=m.id 
			'.($is_a ? '' : 'INNER JOIN {SQL_TABLE_PREFIX}mod o ON o.user_id='._uid.' AND o.forum_id=t.forum_id').'
			WHERE t.id='.$th);
	if (!$thr) {
		invl_inp_err();
	}

	/* delete rating */
	if ($ratingid && sq_check(0, $usr->sq)) {
		q('DELETE FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id='.$th.' AND id = '.$ratingid);
		$rt = db_saq('SELECT count(*), ROUND(AVG(rating)) FROM {SQL_TABLE_PREFIX}thread_rate_track WHERE thread_id='.$th);
		q('UPDATE {SQL_TABLE_PREFIX}thread SET rating='.(int)$rt[1].', n_rating='.(int)$rt[0].' WHERE id='.$th);

		logaction(_uid, 'DELRATING', $th);
	}

/*{POST_HTML_PHP}*/

	$c = uq('SELECT u.alias, t.rating, t.id FROM {SQL_TABLE_PREFIX}thread_rate_track t INNER JOIN {SQL_TABLE_PREFIX}users u ON t.user_id = u.id WHERE t.thread_id = '.$thr->id);
	$table_data = '';
	while ($r = db_rowarr($c)) {
		$table_data .= '{TEMPLATE: ratingtrack_entry}';
	}
	unset($c);
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: RATINGTRACK_PAGE}
