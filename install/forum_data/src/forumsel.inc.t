<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: forumsel.inc.t,v 1.9 2003/04/30 19:51:05 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

function tmpl_create_forum_select($frm_id, $is_mod)
{
	$prev_cat_id = 0;
	$selection_options = '';

	if (!_uid) { /* anon user, we can optimize things quite a bit here */
		$c = q('SELECT f.id, f.name, c.name, c.id FROM {SQL_TABLE_PREFIX}group_cache g INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=g.resource_id AND g.user_id=0 INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id WHERE p_READ=\'Y\' ORDER BY c.view_order, f.view_order');
		while ($r = db_rowarr($c)) {
			if ($prev_cat_id != $r[3]) {
				$prev_cat_id = $r[3];
				$selection_options .= '{TEMPLATE: category_option}';	
			}
			$selected = $frm_id == $r[0] ? ' selected' : '';
			$selection_options .= '{TEMPLATE: forum_option}';
		}
		qf($c);

		return '{TEMPLATE: forum_select}';
	} else {
		$c = q('SELECT f.id, f.name, c.name, c.id, CASE WHEN '.$GLOBALS['usr']->last_read.' < m.post_stamp AND (fr.last_view IS NULL OR m.post_stamp > fr.last_view) THEN 1 ELSE 0 END AS reads
			FROM {SQL_TABLE_PREFIX}forum f 
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id 
			INNER JOIN {SQL_TABLE_PREFIX}msg m ON m.id=f.last_post_id
			'.($is_mod != 'A' ? 'INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.resource_id=f.id AND g1.user_id=2147483647 LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.resource_id=f.id AND g2.user_id='._uid : '').'
			LEFT JOIN {SQL_TABLE_PREFIX}forum_read fr ON fr.forum_id=f.id AND fr.user_id='._uid.'
			'.($is_mod != 'A' ? ' WHERE (CASE WHEN g2.id IS NULL THEN g1.p_READ ELSE g2.p_READ END)=\'Y\'' : '').'
			ORDER BY c.view_order, f.view_order');			

		while ($r = db_rowarr($c)) {
			if ($prev_cat_id != $r[3]) {
				$prev_cat_id = $r[3];
				$selection_options .= '{TEMPLATE: category_option}';	
			}
			$selected = $frm_id == $r[0] ? ' selected' : '';
			$selection_options .= $r[4] ? '{TEMPLATE: unread_forum_option}' : '{TEMPLATE: forum_option}';
		}
		qf($c);

		return '{TEMPLATE: forum_select}';		
	}
}

	$forum_select = tmpl_create_forum_select($frm->id, $usr->is_mod);
?>