<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: index.php.t,v 1.56 2004/10/19 00:40:39 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

function reload_collapse($str)
{
	if (!($tok = strtok($str, '_'))) {
		return;
	}
	do {
		@list($key, $val) = explode(':', $tok);
		if ((int) $key) {
			$GLOBALS['collapse'][(int) $key] = (int) $val;
		}
	} while (($tok = strtok('_')));
}

function url_tog_collapse($id, $c)
{
	if (!isset($GLOBALS['collapse'][$id])) {
		return;
	}

	if (!$c) {
		return $id . ':'.(empty($GLOBALS['collapse'][$id]) ? '1' : '0');
	} else {
		$c_status = (empty($GLOBALS['collapse'][$id]) ? 1 : 0);

		if (isset($GLOBALS['collapse'][$id]) && ($p = strpos('_' . $c, '_' . $id . ':' . (int)!$c_status)) !== false) {
			$c[$p + strlen($id) + 1] = $c_status;
			return $c;
		} else {
			return $c . '_' . $id . ':' . $c_status;
		}
	}
}

	if (isset($_GET['c'])) {
		$cs = $_GET['c'];
		if (_uid && $cs != $usr->cat_collapse_status) {
			q("UPDATE {SQL_TABLE_PREFIX}users SET cat_collapse_status='".addslashes($cs)."' WHERE id="._uid);
		}
		reload_collapse($cs);
	} else if (_uid && $usr->cat_collapse_status) {
		$cs = $usr->cat_collapse_status;
		reload_collapse($cs);
	} else {
		$cs = '';
	}

	if (!_uid) {
		$mark_all_read = $welcome_message = '';
	} else {
		$welcome_message = '{TEMPLATE: welcome_message}';
		$mark_all_read = '{TEMPLATE: mark_all_read}';
	}

	ses_update_status($usr->sid, '{TEMPLATE: index_update}');

/*{POST_HTML_PHP}*/
	$TITLE_EXTRA = ': {TEMPLATE: index_title}';

	$forum_list_table_data = '';

	/* List of fetched fields & their ids
	  0	msg.subject,
	  1	msg.id AS msg_id,
	  2	msg.post_stamp,
	  3	users.id AS user_id,
	  4	users.alias
	  5	cat.description,
	  6	cat.name,
	  7	cat.cat_opt,
	  8	forum.cat_id,
	  9	forum.forum_icon
	  10	forum.id
	  11	forum.last_post_id
	  12	forum.moderators
	  13	forum.name
	  14	forum.descr
	  15	forum.post_count
	  16	forum.thread_count
	  17	forum_read.last_view
	  18	is_moderator
	  19	read perm
	*/
	$frmres = uq('SELECT
				m.subject, m.id, m.post_stamp,
				u.id, u.alias,
				c.description, c.name, c.cat_opt,
				f.cat_id, f.forum_icon, f.id, f.last_post_id, f.moderators, f.name, f.descr, f.post_count, f.thread_count,
				fr.last_view,
				mo.id AS md,
				'.(_uid ? 'CASE WHEN g2.group_cache_opt IS NULL THEN g1.group_cache_opt ELSE g2.group_cache_opt END AS group_cache_opt' : 'g1.group_cache_opt').',
				v.lvl, c.parent
			FROM {SQL_TABLE_PREFIX}fc_view v
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=v.f
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=v.c
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? 2147483647 : 0).' AND g1.resource_id=f.id
			LEFT JOIN {SQL_TABLE_PREFIX}msg m ON f.last_post_id=m.id
			LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=m.poster_id
			LEFT JOIN {SQL_TABLE_PREFIX}forum_read fr ON fr.forum_id=f.id AND fr.user_id='._uid.'
			LEFT JOIN {SQL_TABLE_PREFIX}mod mo ON mo.user_id='._uid.' AND mo.forum_id=f.id
			'.(_uid ? 'LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id' : '').'
			'.($usr->users_opt & 1048576 ? '' : 'WHERE mo.id IS NOT NULL OR ('.(_uid ? 'CASE WHEN g2.group_cache_opt IS NULL THEN g1.group_cache_opt ELSE g2.group_cache_opt END' : 'g1.group_cache_opt').' & 1)>0').' ORDER BY v.id');

	$post_count = $thread_count = $last_msg_id = $cat = 0;
	while ($r = db_rowarr($frmres)) {
		if ($cat != $r[8]) {
			if ($r[21] && !empty($GLOBALS['collapse'][$r[21]])) {
				$GLOBALS['collapse'][$r[8]] = 1;
				continue;
			}

			$r[7] = (int) $r[7];

			$tabw = $r[20] ?  $r[20] * '{TEMPLATE: cat_tab}' : '0';

			if ($r[7] & 1) {
				if (!isset($GLOBALS['collapse'][$r[8]])) {
					$GLOBALS['collapse'][$r[8]] = ($r[7] & 2 ? 0 : 1);
				}

				if (!empty($GLOBALS['collapse'][$r[8]])) {
					$collapse_status = '{TEMPLATE: maximize_category}';
					$collapse_indicator = '{TEMPLATE: collapse_indicator_MAX}';
				} else {
					$collapse_status = '{TEMPLATE: minimize_category}';
					$collapse_indicator = '{TEMPLATE: collapse_indicator_MIN}';
				}

				$forum_list_table_data .= '{TEMPLATE: index_category_allow_collapse_Y}';
			} else {
				$forum_list_table_data .= '{TEMPLATE: index_category_allow_collapse_N}';
			}
			$cat = $r[8];
		}

		if (!empty($GLOBALS['collapse'][$r[8]])) {
			continue;
		}

		if (!($r[19] & 2) && !($usr->users_opt & 1048576) && !$r[18]) { /* visible forum with no 'read' permission */
			$forum_list_table_data .= '{TEMPLATE: forum_with_no_view_perms}';
			continue;
		}

		/* increase thread & post count */
		$post_count += $r[15];
		$thread_count += $r[16];

		/* code to determine the last post id for 'latest' forum message */
		if ($r[11] > $last_msg_id) {
			$last_msg_id = $r[11];
		}

		$forum_icon = $r[9] ? '{TEMPLATE: forum_icon}' : '{TEMPLATE: no_forum_icon}';
		$forum_descr = $r[14] ? '{TEMPLATE: forum_descr}' : '';

		if (_uid && $r[17] < $r[2] && $usr->last_read < $r[2]) {
			$forum_read_indicator = '{TEMPLATE: forum_unread}';
		} else if (_uid) {
			$forum_read_indicator = '{TEMPLATE: forum_read}';
		} else {
			$forum_read_indicator = '{TEMPLATE: forum_no_indicator}';
		}

		if ($r[11]) {
			$last_poster_profile = $r[3] ? '{TEMPLATE: profile_link_user}' : '{TEMPLATE: profile_link_anon}';
			$last_post = '{TEMPLATE: last_post}';
		} else {
			$last_post = '{TEMPLATE: na}';
		}

		if ($r[12] && ($mods = @unserialize($r[12]))) {
			$moderators = '';
			foreach($mods as $k => $v) {
				$moderators .= '{TEMPLATE: profile_link_mod}';
			}
			$moderators = '{TEMPLATE: moderators}';
		} else {
			$moderators = '{TEMPLATE: no_mod}';
		}

		$forum_list_table_data .= '{TEMPLATE: index_forum_entry}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: INDEX_PAGE}
