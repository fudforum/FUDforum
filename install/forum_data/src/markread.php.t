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

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	if (_uid && sq_check(0, $usr->sq)) {
		if (!empty($_GET['id'])) {
			user_mark_forum_read(_uid, (int)$_GET['id'], $usr->last_read);
		} else if (!empty($_GET['cat'])) {
			/* Mark all forums inside a category and it's child categories. */
			require $FORUM_SETTINGS_PATH .'cat_cache.inc';

			if (!empty($cat_cache[(int)$_GET['cat']])) {
				$c = $cat_cache[(int)$_GET['cat']];

				$cids = array();
				/* Fetch all sub-categories if there are any. */
				if (!empty($c[2])) {
					$cids = $c[2];
				}
				$cids[] = (int)$_GET['cat'];

				$c = q('SELECT id FROM {SQL_TABLE_PREFIX}forum WHERE cat_id IN('. implode(',', $cids) .')');
				while ($r = db_rowarr($c)) {
					user_mark_forum_read(_uid, $r[0], $usr->last_read);
				}
			}
		} else {
			user_mark_all_read(_uid);
		}
	}

	check_return($usr->returnto);
?>