<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: search.php.t,v 1.13 2003/04/14 18:49:51 hackie Exp $
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

	if ($FORUM_SEARCH != 'Y') {
		std_error('disabled');
	}
	if (!isset($_GET['start']) || !($start = (int)$_GET['start'])) {
		$start = 0;
	}

	$ppg = $usr->posts_ppg ? $usr->posts_ppg : $POSTS_PER_PAGE;
	$srch = isset($_GET['srch']) ? trim($_GET['srch']) : '';
	$forum_limiter = isset($_GET['forum_limiter']) ? $_GET['forum_limiter'] : '';
	$field = !isset($_GET['field']) ? 'subject' : ($_GET['field'] == 'subject' ? 'subject' : 'all');
	$search_logic = (isset($_GET['search_logic']) && $_GET['search_logic'] == 'OR') ? 'OR' : 'AND';
	$sort_order = (isset($_GET['sort_order']) && $_GET['sort_order'] == 'ASC') ? 'ASC' : 'DESC';

/*{POST_HTML_PHP}*/

	/* draw search engine selection boxes */
	if ($usr->is_mod != 'A') {
		$c = uq('SELECT f.id,f.name, c.id, c.name AS cat_name 
				FROM {SQL_TABLE_PREFIX}forum f 
				INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id 
				INNER JOIN {SQL_TABLE_PREFIX}group_cache g1 ON g1.user_id='.(_uid ? '2147483647' : '0').' AND g1.resource_id=f.id
				LEFT JOIN {SQL_TABLE_PREFIX}mod mm ON mm.forum_id=f.id AND mm.user_id='._uid.'
				LEFT JOIN {SQL_TABLE_PREFIX}group_cache g2 ON g2.user_id='._uid.' AND g1.resource_id=f.id
				WHERE mm.id IS NOT NULL OR (CASE WHEN g2.id IS NOT NULL THEN g2.p_READ ELSE g1.p_READ END)=\'Y\'
				ORDER BY c.view_order, f.view_order');
	} else {
		$c = uq('SELECT f.id, f.name, c.id, c.name FROM {SQL_TABLE_PREFIX}forum f INNER JOIN {SQL_TABLE_PREFIX}cat c ON f.cat_id=c.id ORDER BY c.view_order, f.view_order');
	}
	$old_cat = $forum_limit_data = '';
	while ($r = db_rowarr($c)) {
		if ($old_cat != $r[2]) {
			$selected = ('c'.$r[2] == $forum_limiter) ? ' selected' : '';
			$forum_limit_data .= '{TEMPLATE: forum_limit_cat_option}';
			$old_cat = $r[2];
			continue;
		}
		$selected = $r[0] == $forum_limiter ? ' selected' : '';
		$forum_limit_data .= '{TEMPLATE: forum_limit_frm_option}';
	}
	qf($c);
	/* user has no permissions to any forum, so as far as they are concerned the search is disabled */
	if (!$forum_limit_data) {
		std_error('disabled');	
	}

	$search_options = tmpl_draw_radio_opt('field', "all\nsubject", "{TEMPLATE: search_entire_msg}\n{TEMPLATE: search_subect_only}", $field, '{TEMPLATE: radio_button}', '{TEMPLATE: radio_button_selected}', '{TEMPLATE: radio_button_separator}');
	$logic_options = tmpl_draw_select_opt("AND\nOR", "{TEMPLATE: search_and}\n{TEMPLATE: search_or}", $search_logic, '{TEMPLATE: search_normal_option}', '{TEMPLATE: search_selected_option}');
	$sort_options = tmpl_draw_select_opt("DESC\nASC", "{TEMPLATE: search_desc_order}\n{TEMPLATE: search_asc_order}", $sort_order, '{TEMPLATE: search_normal_option}', '{TEMPLATE: search_selected_option}');

	$TITLE_EXTRA = ': {TEMPLATE: search_title}';

	ses_update_status($usr->sid, '{TEMPLATE: search_update}');

	$page_pager = '';
	if ($srch) {
		if (!($c =& fetch_search_cache($srch, $start, $ppg, $search_logic, $field, $sort_order, $forum_limiter, $total))) {
			$search_data = '{TEMPLATE: no_search_results}';
		} else {
			$i = 0;
			$search_data = '';
			while ($r = db_rowobj($c)) {
				$body = strip_tags(read_msg_body($r->foff, $r->length, $r->file_id));
				if (strlen($body) > 80) {
					$body = substr($body, 0, 80) . '...';
				}
				$poster_info = !empty($r->poster_id) ? '{TEMPLATE: registered_poster}' : '{TEMPLATE: unregistered_poster}';
				++$i;
				$search_data .= '{TEMPLATE: search_entry}';
			}
			qf($c);
			un_register_fps();
			$search_data = '{TEMPLATE: search_results}';
			$page_pager = tmpl_create_pager($start, $ppg, $total, '{ROOT}?t=search&amp;srch='.urlencode($srch).'&amp;field='.$field.'&amp;'._rsid.'&amp;search_logic='.$search_logic.'&amp;sort_order='.$sort_order.'&amp;forum_limiter='.$forum_limiter);
		}
	} else {
		$search_data = '';
	}
	
	
/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: SEARCH_PAGE}