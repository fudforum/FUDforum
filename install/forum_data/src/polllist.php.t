<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: polllist.php.t,v 1.18 2003/10/05 22:18:42 hackie Exp $
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

	ses_update_status($usr->sid, '{TEMPLATE: polllist_update}');

/*{POST_HTML_PHP}*/

	if (!isset($_GET['oby'])) {
		$_GET['oby'] = 'DESC';
	}
	if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
		$start = 0;
	}
	if (isset($_GET['uid']) && ($uid = (int)$_GET['uid'])) {
		$usr_lmt = ' p.owner='.$uid.' AND ';
	} else {
		$uid = $usr_lmt = '';
	}

	if ($_GET['oby'] == 'ASC') {
		$oby = 'ASC';
		$oby_rev_val = 'DESC';
	} else {
                $oby = 'DESC';
		$oby_rev_val = 'ASC';
	}

	$ttl = (int) q_singleval('SELECT count(*)
				FROM {SQL_TABLE_PREFIX}poll p
				INNER JOIN {SQL_TABLE_PREFIX}forum f ON p.forum_id=f.id
				INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=p.forum_id AND mm.user_id='._uid.'
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=p.forum_id
				LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=p.forum_id
				WHERE
					'.$usr_lmt.($usr->users_opt & 1048576 ? ' 1=1' : ' (mm.id IS NOT NULL OR ((CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) & 2) > 0)'));
	$poll_entries = $pager = '';
	if ($ttl) {
		$c = uq('SELECT
				p.owner, p.name, (CASE WHEN expiry_date = 0 THEN 0 ELSE (p.creation_date + p.expiry_date) END) AS poll_expiry_date, p.creation_date, p.id AS poid, p.max_votes, p.total_votes,
				u.alias, u.alias AS login, (u.last_visit + '.($LOGEDIN_TIMEOUT * 60).') AS last_visit, u.users_opt,
				m.id,
				t.thread_opt,
				'.($usr->users_opt & 1048576 ? '1' : 'mm.id').' AS md,
				pot.id AS cant_vote,
				(CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) AS gco
				FROM {SQL_TABLE_PREFIX}poll p
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=p.forum_id
				INNER JOIN {SQL_TABLE_PREFIX}forum f ON p.forum_id=f.id
				INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
				INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.poll_id=p.id
				INNER JOIN {SQL_TABLE_PREFIX}thread t ON m.thread_id=t.id
				LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=p.forum_id
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=p.forum_id AND mm.user_id='._uid.'
				LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=m.poster_id
				LEFT JOIN {SQL_TABLE_PREFIX}poll_opt_track pot ON pot.poll_id=p.id AND pot.user_id='._uid.'
				WHERE
					'.$usr_lmt.' '.($usr->users_opt & 1048576 ? '1=1' : '(mm.id IS NOT NULL OR ((CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) & 2) > 0)').' ORDER BY p.creation_date '.$oby.' LIMIT '.qry_limit($POLLS_PER_PAGE, $start));

		while ($obj = db_rowobj($c)) {
			if (!$obj->total_votes) {
				$obj->total_votes = '0';
			}
			$vote_lnk = '';
			if(!$obj->cant_vote && (!$obj->poll_expiry_date || $obj->poll_expiry_date < __request_timestamp__)) {
				if ($obj->md || ($obj->gco & 512 && (!($obj->thread_opt & 1) || $obj->gco & 4096))) {
					if (!$obj->max_votes || $obj->total_votes < $obj->max_votes) {
						$vote_lnk = '{TEMPLATE: vote_lnk}';
					}
				}
			}
			$view_res_lnk = $obj->total_votes ? '{TEMPLATE: poll_view_res_lnk}' : '';

			if ($obj->owner && (!($obj->users_opt & 32768) || $usr->users_opt & 1048576) && $FUD_OPT_2 & 32) {
				$online_indicator = $obj->last_visit > __request_timestamp__ ? '{TEMPLATE: polllist_online_indicator}' : '{TEMPLATE: polllist_offline_indicator}';
			} else {
				$online_indicator = '';
			}
			$poll_entries .= '{TEMPLATE: poll_entry}';
		}
		qf($c);

		if ($ttl > $POLLS_PER_PAGE) {
			if ($FUD_OPT_2 & 32768) {
				$pager = tmpl_create_pager($start, $POLLS_PER_PAGE, $ttl, '{ROOT}/pl/'.$uid.'/', '/' . $oby . '/' . _rsid);
			} else {
				$pager = tmpl_create_pager($start, $POLLS_PER_PAGE, $ttl, '{ROOT}?t=polllist&amp;oby='.$oby.'&amp;uid='.$uid);
			}
		}
	} else {
		$poll_entries = '{TEMPLATE: poll_no_polls}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: POLLLIST_PAGE}