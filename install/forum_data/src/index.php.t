<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: index.php.t,v 1.64 2004/10/22 14:03:19 hackie Exp $
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

	$cat_id = !empty($_GET['cat']) ? (int) $_GET['cat'] : 0;

	if (!_uid) {
		$mark_all_read = $welcome_message = '';
	} else {
		$welcome_message = '{TEMPLATE: welcome_message}';
		$mark_all_read = '{TEMPLATE: mark_all_read}';
	}

	ses_update_status($usr->sid, '{TEMPLATE: index_update}');

	require $GLOBALS['FORUM_SETTINGS_PATH'] . 'idx.inc';

/*{POST_HTML_PHP}*/
	$TITLE_EXTRA = ': {TEMPLATE: index_title}';

	$forum_list_table_data = $cat_path = '';

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
	  12	forum.post_count
	  13	forum.thread_count
	  14	forum_read.last_view
	  15	is_moderator
	  16	read perm
	*/
	$c = uq('SELECT
				m.subject, m.id, m.post_stamp,
				u.id, u.alias,
				f.cat_id, f.forum_icon, f.id, f.last_post_id, f.moderators, f.name, f.descr, f.post_count, f.thread_count,
				fr.last_view,
				mo.id AS md,
				'.(_uid ? 'CASE WHEN g2.group_cache_opt IS NULL THEN g1.group_cache_opt ELSE g2.group_cache_opt END AS group_cache_opt' : 'g1.group_cache_opt').'
			FROM {SQL_TABLE_PREFIX}fc_view v
			INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=v.c
			INNER JOIN {SQL_TABLE_PREFIX}forum f ON f.id=v.f
			INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? 2147483647 : 0).' AND g1.resource_id=f.id
			LEFT JOIN {SQL_TABLE_PREFIX}msg m ON f.last_post_id=m.id
			LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=m.poster_id
			LEFT JOIN {SQL_TABLE_PREFIX}forum_read fr ON fr.forum_id=f.id AND fr.user_id='._uid.'
			LEFT JOIN {SQL_TABLE_PREFIX}mod mo ON mo.user_id='._uid.' AND mo.forum_id=f.id
			'.(_uid ? 'LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id' : '').'
			'.($usr->users_opt & 1048576 ? '' : 'WHERE mo.id IS NOT NULL OR ('.(_uid ? 'CASE WHEN g2.group_cache_opt IS NULL THEN g1.group_cache_opt ELSE g2.group_cache_opt END' : 'g1.group_cache_opt').' & 1)>0').' 
			'.($cat_id ? ' WHERE v.c IN('.implode(',', ($cf = $cidxc[$cat_id][5])).') ' : '').'
			ORDER BY v.id');

	$post_count = $thread_count = $last_msg_id = $cat = 0;
	while ($r = db_rowarr($c)) {
		/* increase thread & post count */
		$post_count += $r[12];
		$thread_count += $r[13];

		$cid = (int) $r[5];

		if ($cat != $cid) {
			while (list($k, $i) = each($cidxc)) {
				if ($cat_id && !isset($cf[$k])) {
					continue;
				}

				/* if parent category is collapsed, hide child category */
				if ($i[4] && !empty($collapse[$i[4]])) {
					$collapse[$k] = 1;
					$cat = $k;
					continue;
				}

				if ($i[3] & 1) {
					if (!isset($collapse[$k])) {
						$collapse[$k] = !($i[3] & 2);
					}

					if (!empty($collapse[$k])) {
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
			
				if ($k == $cid) {
					break;
				}
			}
			$cat = $cid;
		}

		if (!empty($collapse[$cid])) {
			continue;
		}

		if (!($r[16] & 2) && !($usr->users_opt & 1048576) && !$r[15]) { /* visible forum with no 'read' permission */
			$forum_list_table_data .= '{TEMPLATE: forum_with_no_view_perms}';
			continue;
		}

		/* code to determine the last post id for 'latest' forum message */
		if ($r[8] > $last_msg_id) {
			$last_msg_id = $r[8];
		}

		$forum_icon = $r[6] ? '{TEMPLATE: forum_icon}' : '{TEMPLATE: no_forum_icon}';

		if (_uid && $r[14] < $r[2] && $usr->last_read < $r[2]) {
			$forum_read_indicator = '{TEMPLATE: forum_unread}';
		} else if (_uid) {
			$forum_read_indicator = '{TEMPLATE: forum_read}';
		} else {
			$forum_read_indicator = '{TEMPLATE: forum_no_indicator}';
		}

		if ($r[8]) {
			$last_poster_profile = $r[3] ? '{TEMPLATE: profile_link_user}' : '{TEMPLATE: profile_link_anon}';
			$last_post = '{TEMPLATE: last_post}';
		} else {
			$last_post = '{TEMPLATE: na}';
		}

		if ($r[9] && ($mods = @unserialize($r[9]))) {
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
