<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: search_forum_sel.inc.t,v 1.4 2003/10/01 21:51:52 hackie Exp $
****************************************************************************

****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/* draw search engine selection boxes */
if ($usr->users_opt & 1048576) {
	$c = uq('SELECT f.id, f.name, c.id, c.name FROM {SQL_TABLE_PREFIX}forum f INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id ORDER BY c.view_order, f.view_order');
} else {
	$c = uq('SELECT f.id,f.name, c.id, c.name AS cat_name
			FROM {SQL_TABLE_PREFIX}forum f
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.'
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=f.id
			LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
			WHERE mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.group_cache_opt ELSE g1.group_cache_opt END) & 1
			ORDER BY c.view_order, f.view_order');
}
$old_cat = $forum_limit_data = '';
while ($r = db_rowarr($c)) {
	if ($old_cat != $r[2]) {
		$selected = ('c'.$r[2] == $forum_limiter) ? ' selected' : '';
		$forum_limit_data .= '{TEMPLATE: forum_limit_cat_option}';
		$old_cat = $r[2];
	}
	$selected = $r[0] == $forum_limiter ? ' selected' : '';
	$forum_limit_data .= '{TEMPLATE: forum_limit_frm_option}';
}
qf($c);
/* user has no permissions to any forum, so as far as they are concerned the search is disabled */
if (!$forum_limit_data) {
	std_error('disabled');
}

function trim_body($body)
{
	/* remove stuff in quotes */
	while (($p = strpos($body, '<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>')) !== false) {
		$e = strpos($body, '<br></td></tr></table>', $p) + strlen('<br></td></tr></table>');
		$body = substr($body, 0, $p) . substr($body, $e);
	}

	$body = strip_tags($body);
	if (strlen($body) > $GLOBALS['MNAV_MAX_LEN']) {
		$body = substr($body, 0, $GLOBALS['MNAV_MAX_LEN']) . '...';
	}
	return $body;
}
?>