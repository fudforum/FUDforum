<?php
/**
* copyright            : (C) 2001-2009 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

	$RSS = '{TEMPLATE: index_RSS}';
	$collapse = $usr->cat_collapse_status ? unserialize($usr->cat_collapse_status) : array();
	$cat_id = !empty($_GET['cat']) ? (int) $_GET['cat'] : 0;

	if ($cat_id && !empty($collapse[$cat_id])) {
		$collapse[$cat_id] = 0;
	}

	ses_update_status($usr->sid, '{TEMPLATE: index_update}');

	require $FORUM_SETTINGS_PATH . 'idx.inc';
	if (!isset($cidxc[$cat_id])) {
		$cat_id = 0;
	}

/*{POST_HTML_PHP}*/
	$TITLE_EXTRA = ': {TEMPLATE: index_title}';

	$cbuf = $forum_list_table_data = $cat_path = '';

	if ($cat_id) {
		$cid = $cat_id;
		while (($cid = $cidxc[$cid][4]) > 0) {
			$cat_path = '{TEMPLATE: idx_forum_path}' . $cat_path;
		}
		$cat_path = '{TEMPLATE: idx_cat_path}';
	}

	/* List of fetched fields & their ids
	  0	msg.subject,
	  1	msg.id AS msg_id,
	  2	msg.post_stamp,
	  3	users.id AS user_id,
	  4	users.alias
	  5	forum.cat_id,
	  6	forum.forum_icon
	  7	forum.id
	  8	forum.last_post_id
	  9	forum.moderators
	  10	forum.name
	  11	forum.descr
	  12	forum.url_redirect
	  13	forum.post_count
	  14	forum.thread_count
	  15	forum_read.last_view
	  16	is_moderator
	  17	read perm
	  18	is the category using compact view
	*/
	$c = uq('SELECT
				m.subject, m.id, m.post_stamp,
				u.id, u.alias,
				f.cat_id, f.forum_icon, f.id, f.last_post_id, f.moderators, f.name, f.descr, f.url_redirect, f.post_count, f.thread_count,
				'.(_uid ? 'fr.last_view, mo.id, COALESCE(g2.group_cache_opt, g1.group_cache_opt) AS group_cache_opt' : '0,0,g1.group_cache_opt').',
				c.cat_opt
			FROM {SQL_TABLE_PREFIX}fc_view v
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=v.c
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=v.f
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? 2147483647 : 0).' AND g1.resource_id=f.id
			LEFT JOIN {SQL_TABLE_PREFIX}msg m ON f.last_post_id=m.id
			LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=m.poster_id '.
			(_uid ? ' LEFT JOIN {SQL_TABLE_PREFIX}forum_read fr ON fr.forum_id=f.id AND fr.user_id='._uid.' LEFT JOIN {SQL_TABLE_PREFIX}mod mo ON mo.user_id='._uid.' AND mo.forum_id=f.id LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id' : '').
			((!$is_a || $cat_id) ?  ' WHERE ' : '') .
			($is_a ? '' : (_uid ? ' (mo.id IS NOT NULL OR ((COALESCE(g2.group_cache_opt, g1.group_cache_opt) & 1) > 0))' : ' ((g1.group_cache_opt & 1) > 0)')) .
			($cat_id ? ($is_a ? '' : ' AND ') . ' v.c IN('.implode(',', ($cf = $cidxc[$cat_id][5])).') ' : '').' ORDER BY v.id');

	$post_count = $thread_count = $last_msg_id = $cat = 0;
	while ($r = db_rowarr($c)) {
		/* increase thread & post count */
		$post_count += $r[13];
		$thread_count += $r[14];

		$cid = (int) $r[5];

		if ($cat != $cid) {
			if ($cbuf) { /* if previous category was using compact view, print forum row */
				if (empty($collapse[$i[4]])) { /* only show if parent is not collapsed as well */
					$forum_list_table_data .= '{TEMPLATE: idx_compact_forum_row}';
				}
				$cbuf = '';
			}

			while (list($k, $i) = each($cidxc)) {
				/* 2nd check ensures that we don't end up displaying categories without any children */ 
				if (($cat_id && !isset($cf[$k])) || ($cid != $k && $i[4] >= $cidxc[$cid][4])) {
					continue;
				}

				/* if parent category is collapsed, hide child category */
				if ($i[4] && !empty($collapse[$i[4]])) {
					$collapse[$k] = 1;
				}

				if ($i[3] & 1 && $k != $cat_id && !($i[3] & 4)) {
					if (!isset($collapse[$k])) {
						$collapse[$k] = !($i[3] & 2);
					}
					$forum_list_table_data .= '{TEMPLATE: index_category_allow_collapse_Y}';
				} else {
					if ($i[3] & 4) {
						++$i[0];
					}
					$forum_list_table_data .= '{TEMPLATE: index_category_allow_collapse_N}';
				}
			
				if ($k == $cid) {
					break;
				}
			}
			$cat = $cid;
		}

		/* compact view check */
		if ($r[18] & 4) {
			$cbuf .= '{TEMPLATE: idx_compact_forum_entry}';
			continue;
		}

		if (!($r[17] & 2) && !$is_a && !$r[16]) { /* visible forum with no 'read' permission */
			$forum_list_table_data .= '{TEMPLATE: forum_with_no_view_perms}';
			continue;
		}

		/* code to determine the last post id for 'latest' forum message */
		if ($r[8] > $last_msg_id) {
			$last_msg_id = $r[8];
		}

		if (!_uid) { /* anon user */
			$forum_read_indicator = '{TEMPLATE: forum_no_indicator}';
		} else if ($r[15] < $r[2] && $usr->last_read < $r[2]) {
			$forum_read_indicator = '{TEMPLATE: forum_unread}';
		} else {
			$forum_read_indicator = '{TEMPLATE: forum_read}';
		}

		if ($r[9] && ($mods = unserialize($r[9]))) {
			$moderators = '';	// List of forum modeators.
			$modcount = 0;		// Use singular or plural message form.
			foreach($mods as $k => $v) {
				$moderators .= '{TEMPLATE: profile_link_mod}';
				$modcount++;
			}
			$moderators = '{TEMPLATE: moderators}';
		} else {
			$moderators = '{TEMPLATE: no_mod}';
		}

		$forum_list_table_data .= '{TEMPLATE: index_forum_entry}';
	}
	unset($c);

	if ($cbuf) { /* if previous category was using compact view, print forum row */
		$forum_list_table_data .= '{TEMPLATE: idx_compact_forum_row}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: INDEX_PAGE}
