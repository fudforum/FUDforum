<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: index.php.t,v 1.31 2003/05/08 00:27:03 hackie Exp $
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

function set_collapse($id, $val)
{
	if (isset($GLOBALS['collapse'][$id])) {
		return;
	}
	$GLOBALS['collapse'][$id] = $val;
}

function reload_collapse($str)
{
	if (!($tok = strtok($str, '_'))) {
		return;
	}
	do {
		list($key, $val) = explode(':', $tok);
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
		$c_status = (empty($GLOBALS['collapse'][$id]) ? '1' : '0');

		if (isset($GLOBALS['collapse'][$id]) && ($p = strpos($c, $id . ':' . !$c_status)) !== FALSE) {
			$c[$p + strlen($id) + 1] = $c_status;
			return $c;
		} else {
			return $c . '_' . $id . ':' . $c_status;
		} 
	}	
}

function iscollapsed($id)
{
	if (!isset($GLOBALS['collapse'][$id])) {
		return;
	}
	return $GLOBALS['collapse'][$id];
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
	  7	cat.default_view, 
	  8	cat.allow_collapse, 
	  9	forum.cat_id,
	  10	forum.forum_icon
	  11	forum.id
	  12	forum.last_post_id
	  13	forum.moderators
	  14	forum.name
	  15	forum.descr
	  16	forum.post_count
	  17	forum.thread_count
	  18	forum_read.last_view
	  19	is_moderator
	  20	read perm
	*/
	$frmres = uq('SELECT 
				m.subject, m.id, m.post_stamp,
				u.id, u.alias,
				c.description, c.name, c.default_view, c.allow_collapse,
				f.cat_id, f.forum_icon, f.id, f.last_post_id, f.moderators, f.name, f.descr, f.post_count, f.thread_count,
				fr.last_view,
				mo.id AS mod,
				'.(_uid ? 'CASE WHEN g2.p_READ IS NULL THEN g1.p_READ ELSE g2.p_READ END AS p_READ' : 'g1.p_READ').'
		      FROM {SQL_TABLE_PREFIX}forum f
		      INNER JOIN {SQL_TABLE_PREFIX}cat c ON c.id=f.cat_id
		      INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? 2147483647 : 0).' AND g1.resource_id=f.id
		      LEFT JOIN {SQL_TABLE_PREFIX}msg m ON f.last_post_id=m.id
		      LEFT JOIN {SQL_TABLE_PREFIX}users u ON u.id=m.poster_id
		      LEFT JOIN {SQL_TABLE_PREFIX}forum_read fr ON fr.forum_id=f.id AND fr.user_id='._uid.'
		      LEFT JOIN {SQL_TABLE_PREFIX}mod mo ON mo.user_id='._uid.' AND mo.forum_id=f.id
		      '.(_uid ? 'LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g2.resource_id=f.id' : '').'
		      '.($usr->is_mod != 'A' ? 'WHERE mo.id IS NOT NULL OR '.(_uid ? 'CASE WHEN g2.p_VISIBLE IS NULL THEN g1.p_VISIBLE ELSE g2.p_VISIBLE END' : 'g1.p_VISIBLE').'=\'Y\'' : '').'
		      ORDER BY c.view_order, f.view_order');
		
	$post_count = $thread_count = $last_msg_id = $cat = 0;	
	while ($r = db_rowarr($frmres)) {
		if ($cat != $r[9]) {
			if ($r[8] == 'Y') {
				set_collapse($r[9], ($r[7] == 'COLLAPSED' ? 1 : 0));
				
				if (iscollapsed($r[9])) {
					$collapse_status = '{TEMPLATE: maximize_category}';
					$collapse_indicator = '{TEMPLATE: collapse_indicator_MAX}';
				} else {
					$collapse_status = '{TEMPLATE: minimize_category}';
					$collapse_indicator = '{TEMPLATE: collapse_indicator_MIN}';
				}
				
				$collapse_url = '{ROOT}?t=index&amp;c='.url_tog_collapse($r[9], $cs).'&amp;'._rsid;
				
				$forum_list_table_data .= '{TEMPLATE: index_category_allow_collapse_Y}';
			} else {
				$forum_list_table_data .= '{TEMPLATE: index_category_allow_collapse_N}';
			}
			$cat = $r[9];
		}
		
		if (iscollapsed($r[9])) {
			continue;
		}
		
		if ($r[10]) {
			$forum_icon = '{TEMPLATE: forum_icon}';
		} else {
			$forum_icon = '{TEMPLATE: no_forum_icon}';
		}
		
		$forum_link = '{ROOT}?t='.t_thread_view.'&amp;frm_id='.$r[11].'&amp;'._rsid;

		/* increase thread & post count */
		$post_count += $r[16];
		$thread_count += $r[17];

		if ($r[20] == 'N') { /* visible forum with no 'read' permission */
			$forum_list_table_data .= '{TEMPLATE: forum_with_no_view_perms}';
			continue;
		}

		/* code to determine the last post id for 'latest' forum message */
		if ($r[12] > $last_msg_id) {
			$last_msg_id = $r[12];
		}

		if (_uid && $r[18] < $r[2] && $usr->last_read < $r[2]) {
			$forum_read_indicator = '{TEMPLATE: forum_unread}';
		} else if (_uid) {
			$forum_read_indicator = '{TEMPLATE: forum_read}';
		} else {
			$forum_read_indicator = '{TEMPLATE: forum_no_indicator}';
		}

		if ($r[12]) {
			if ($r[3]) {
				$last_poster_profile = '{TEMPLATE: profile_link_user}';
			} else {
				$last_poster_profile = '{TEMPLATE: profile_link_anon}';
			}
			$last_post = '{TEMPLATE: last_post}';
		} else {
			$last_post = '{TEMPLATE: na}';
		}

		$moderators = '';
		if ($r[13] && ($mods = @unserialize($r[13]))) {
			foreach($mods as $k => $v) {
				$moderators .= '{TEMPLATE: profile_link_mod}';	
			}
		} else {
			$moderators = '{TEMPLATE: no_mod}';
		}
		
		$forum_list_table_data .= '{TEMPLATE: index_forum_entry}';
	}
	
	qf($frmres);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: INDEX_PAGE}
