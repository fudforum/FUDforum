<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: polllist.php.t,v 1.34 2005/07/06 14:39:22 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

/*{PRE_HTML_PHP}*/

	if (!empty($_GET['goto'])) {
		$pl_view = empty($_GET['vote']) ? 0 : (int)$_GET['goto'];
		$mid = q_singleval("SELECT id FROM {SQL_TABLE_PREFIX}msg WHERE poll_id=".(int)$_GET['goto']);
		/* PATH_INFO is handled via /pv/ */
		header("Location: {FULL_ROOT}{ROOT}?t=".d_thread_view."&goto=".$mid."&pl_view=".$pl_view."&"._rsidl.'#msg_'.$mid);
		return;
	}

	ses_update_status($usr->sid, '{TEMPLATE: polllist_update}');

/*{POST_HTML_PHP}*/

	if (!isset($_GET['oby'])) {
		$_GET['oby'] = 'DESC';
	}
	if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
		$start = 0;
	}
	if (isset($_GET['uid']) && ($uid = (int)$_GET['uid'])) {
		$usr_lmt = ' WHERE p.owner='.$uid;
	} else {
		$uid = $usr_lmt = '';
	}

	if (!$is_a) {
		$usr_lmt = $usr_lmt . ($usr_lmt ? ' AND ' : ' WHERE ') . ' (mm.id IS NOT NULL OR ((CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) & 2) > 0)';
	}

	if ($_GET['oby'] == 'ASC') {
		$oby = 'ASC';
		$oby_rev_val = 'DESC';
	} else {
                $oby = 'DESC';
		$oby_rev_val = 'ASC';
	}

	$poll_entries = '';
	$c = uq('SELECT /*!40000 SQL_CALC_FOUND_ROWS */
			p.owner, p.name, p.creation_date, p.id, p.max_votes, p.total_votes,
			u.alias, u.alias AS login, (u.last_visit + '.($LOGEDIN_TIMEOUT * 60).') AS last_visit, u.users_opt,
			'.($is_a ? '1' : 'mm.id').' AS md,
			(CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) AS gco
			FROM {SQL_TABLE_PREFIX}poll p
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=p.forum_id
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON p.forum_id=f.id
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
			LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=p.forum_id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=p.forum_id AND mm.user_id='._uid.'
			LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=p.owner
			LEFT JOIN {SQL_TABLE_PREFIX}poll_opt_track pot ON pot.poll_id=p.id AND pot.user_id='._uid.
			$usr_lmt.' ORDER BY p.creation_date '.$oby.' LIMIT '.qry_limit($POLLS_PER_PAGE, $start));

	while ($obj = db_rowobj($c)) {
		$view_res_lnk = $obj->total_votes ? '{TEMPLATE: poll_view_res_lnk}' : '';
		if (!$obj->total_votes) {
			$obj->total_votes = '0';
		}
		if ($obj->owner && (!($obj->users_opt & 32768) || $is_a) && $FUD_OPT_2 & 32) {
			$online_indicator = $obj->last_visit > __request_timestamp__ ? '{TEMPLATE: polllist_online_indicator}' : '{TEMPLATE: polllist_offline_indicator}';
		} else {
			$online_indicator = '';
		}
		$poll_entries .= '{TEMPLATE: poll_entry}';
	}
	unset($c);

	if (($ttl = (int) q_singleval("SELECT /*!40000 FOUND_ROWS(), */ -1")) < 0) {
		$ttl = (int) q_singleval('SELECT count(*)
				FROM {SQL_TABLE_PREFIX}poll p
				INNER JOIN {SQL_TABLE_PREFIX}forum f ON p.forum_id=f.id
				INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=p.forum_id AND mm.user_id='._uid.'
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=p.forum_id
				LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=p.forum_id'.$usr_lmt);
	}

	$pager = '';
	if ($ttl > $POLLS_PER_PAGE) {
		if ($FUD_OPT_2 & 32768) {
			$pager = tmpl_create_pager($start, $POLLS_PER_PAGE, $ttl, '{ROOT}/pl/'.$uid.'/', '/' . $oby . '/' . _rsid);
		} else {
			$pager = tmpl_create_pager($start, $POLLS_PER_PAGE, $ttl, '{ROOT}?t=polllist&amp;oby='.$oby.'&amp;uid='.$uid);
		}
	} else if (!$ttl) {
		$poll_entries = '{TEMPLATE: poll_no_polls}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: POLLLIST_PAGE}