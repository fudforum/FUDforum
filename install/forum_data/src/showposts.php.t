<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: showposts.php.t,v 1.10 2003/04/30 19:51:05 hackie Exp $
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
	
	if (!isset($_GET['id']) || !($tmp = db_saq('SELECT id, alias, posted_msg_count, join_date FROM {SQL_TABLE_PREFIX}users WHERE id='.(int)$_GET['id']))) {
		invl_inp_err();
	} else {
		$uid = $tmp[0];
		$u_alias = $tmp[1];
		$u_pcount = $tmp[2];
		$u_reg_date = $tmp[3];
	}

/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: show_posts_by}';
	
	ses_update_status($usr->sid, '{TEMPLATE: showposts_update}');

	if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
		$start = 0;
	}
	
	if ($usr->is_mod != 'A') {
		$fids = implode(',', get_all_read_perms(_uid));
	}

	if (isset($_GET['so']) && !strcasecmp($_GET['so'], 'asc')) {
		$SORT_ORDER = 'ASC';
		$SORT_ORDER_R = 'DESC';
	} else {
		$SORT_ORDER = 'DESC';
		$SORT_ORDER_R = 'ASC';
	}
	
	$post_entry = '';
	if ($usr->is_mod == 'A' || $fids) {
		$qry_limit = $usr->is_mod != 'A' ? 'f.id IN ('.$fids.') AND ' : '';
	
		/* we need the total for the pager & we don't trust the user to pass it via GET or POST */
		$total = q_singleval("SELECT count(*) 
					FROM {SQL_TABLE_PREFIX}msg m 
					INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id 
					INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id 
					INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
					WHERE ".$qry_limit." m.approved='Y' AND m.poster_id=".$uid);
		
		$c = uq("SELECT 
				f.name, f.id, 
				m.subject, m.id, m.post_stamp 
			FROM {SQL_TABLE_PREFIX}msg m
			INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id 
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON t.forum_id=f.id
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
			WHERE ".$qry_limit." m.approved='Y' AND m.poster_id=".$uid." 
			ORDER BY m.post_stamp ".$SORT_ORDER." LIMIT ".qry_limit($THREADS_PER_PAGE, $start));
		
		while ($r = db_rowarr($c)) {
			$post_entry .= '{TEMPLATE: post_entry}';
		}
		qf($c);
	
		$pager = tmpl_create_pager($start, $THREADS_PER_PAGE, $total, '{ROOT}?t=showposts&amp;id='.$uid.'&amp;start='.$start.'&amp;'._rsid);
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: SHOWPOSTS_PAGE}