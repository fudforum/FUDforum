<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: search_forum_sel.inc.t,v 1.24 2009/01/29 18:37:17 frank Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/* draw search engine selection boxes */
if ($is_a) {
	$c = uq('SELECT f.id, f.name, c.id FROM {SQL_TABLE_PREFIX}fc_view v INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=v.f INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id ORDER BY v.id');
} else {
	$c = uq('SELECT f.id, f.name, c.id
			FROM {SQL_TABLE_PREFIX}fc_view v
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=v.f
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=f.id
			LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.'
			LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id
			WHERE mm.id IS NOT NULL OR (COALESCE(g2.group_cache_opt, g1.group_cache_opt) & (1|262144)) >= (1|262144)
			ORDER BY v.id');
}
$oldc = $forum_limit_data = ''; $g = $f = array();
if ($forum_limiter) {
	if ($forum_limiter{0} != 'c') {
		$f[$forum_limiter] = 1;
	} else {
		$g[(int)ltrim($forum_limiter, 'c')] = 1;
	}
}

while ($r = db_rowarr($c)) {
	if ($oldc != $r[2]) {
		while (list($k, $i) = each($cat_cache)) {
			$forum_limit_data .= '{TEMPLATE: forum_limit_cat_option}';
			if ($k == $r[2]) {
				break;
			}
		}
		$oldc = $r[2];
	}
	$forum_limit_data .= '{TEMPLATE: forum_limit_frm_option}';
}
unset($c);

/* user has no permissions to any forum, so as far as they are concerned the search is disabled */
if (!$forum_limit_data) {
	std_error('disabled');
}

function trim_body($body)
{
	/* remove stuff in quotes */
	while (($p = strpos($body, '<table border="0" align="center" width="90%" cellpadding="3" cellspacing="1"><tr><td class="SmallText"><b>')) !== false) {
		if (($pos = strpos($body, '<br></td></tr></table>', $p)) === false) {
			$pos = strpos($body, '<br /></td></tr></table>', $p);
			if ($pos === false) {
				break;
			}
			$e = $pos + strlen('<br /></td></tr></table>');
		} else {
			$e = $pos + strlen('<br></td></tr></table>');
		}
		$body = substr($body, 0, $p) . substr($body, $e);
	}

	$body = strip_tags($body);
	if (strlen($body) > $GLOBALS['MNAV_MAX_LEN']) {
		$body = substr($body, 0, $GLOBALS['MNAV_MAX_LEN']) . '...';
	}
	return $body;
}
?>