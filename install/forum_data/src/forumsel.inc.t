<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: forumsel.inc.t,v 1.24 2004/05/12 16:02:22 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

function tmpl_create_forum_select($frm_id, $mod)
{
	$prev_cat_id = 0;
	$selection_options = '';

	if (!isset($_GET['t']) || ($_GET['t'] != 'thread' && $_GET['t'] != 'threadt')) {
		$dest = t_thread_view;
	} else {
		$dest = $_GET['t'];
	}

	if (!_uid) { /* anon user, we can optimize things quite a bit here */
		$c = uq('SELECT f.id, f.name, c.name, c.id FROM {SQL_TABLE_PREFIX}group_cache g INNER JOIN {SQL_TABLE_PREFIX}fc_view v ON v.f=g.resource_id INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=g.resource_id INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id WHERE g.user_id=0 AND group_cache_opt>=1 AND (group_cache_opt & 1) > 0 ORDER BY v.id');
		while ($r = db_rowarr($c)) {
			if ($prev_cat_id != $r[3]) {
				$prev_cat_id = $r[3];
				$selection_options .= '{TEMPLATE: category_option}';
			}
			$selected = $frm_id == $r[0] ? ' selected' : '';
			$selection_options .= '{TEMPLATE: forum_option}';
		}
		unset($c);

		return '{TEMPLATE: forum_select}';
	} else {
		$c = uq('SELECT f.id, f.name, c.name, c.id, CASE WHEN '.$GLOBALS['usr']->last_read.' < m.post_stamp AND (fr.last_view IS NULL OR m.post_stamp > fr.last_view) THEN 1 ELSE 0 END AS reads
			FROM {SQL_TABLE_PREFIX}fc_view v
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=v.f
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=v.c
			LEFT JOIN {SQL_TABLE_PREFIX}msg m ON m.id=f.last_post_id
			'.($mod ? '' : 'LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.user_id='._uid.' AND mm.forum_id=f.id INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.resource_id=f.id AND g1.user_id=2147483647 LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.resource_id=f.id AND g2.user_id='._uid).'
			LEFT JOIN {SQL_TABLE_PREFIX}forum_read fr ON fr.forum_id=f.id AND fr.user_id='._uid.'
			'.($mod ? '' : ' WHERE mm.id IS NOT NULL OR ((CASE WHEN g2.id IS NULL THEN g1.group_cache_opt ELSE g2.group_cache_opt END) & 1) > 0').'
			ORDER BY v.id');

		while ($r = db_rowarr($c)) {
			if ($prev_cat_id != $r[3]) {
				$prev_cat_id = $r[3];
				$selection_options .= '{TEMPLATE: category_option}';
			}
			$selected = $frm_id == $r[0] ? ' selected' : '';
			$selection_options .= $r[4] ? '{TEMPLATE: unread_forum_option}' : '{TEMPLATE: forum_option}';
		}
		unset($c);

		return '{TEMPLATE: forum_select}';
	}
}

	$forum_select = tmpl_create_forum_select((isset($frm->forum_id) ? $frm->forum_id : $frm->id), $usr->users_opt & 1048576);
?>