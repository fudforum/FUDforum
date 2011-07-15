<?php
/**
* copyright            : (C) 2001-2011 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/

if (isset($_GET['id'])) {
	// Show a page.
	if (is_numeric($_GET['id'])) {
	      $page = db_sab('SELECT id, slug, title, foff, length, page_opt FROM {SQL_TABLE_PREFIX}pages WHERE id='. (int)$_GET['id'] . ($is_a ? '' : ' AND '. q_bitand('page_opt', 1) .' = 1'));
	} else {
	      $page = db_sab('SELECT id, slug, title, foff, length, page_opt FROM {SQL_TABLE_PREFIX}pages WHERE slug='. _esc($_GET['id']) . ($is_a ? '' : ' AND '. q_bitand('page_opt', 1) .' = 1'));
	}

	$TITLE_EXTRA = ': '. $page->title;

	fud_use('page_adm.inc', true);
	$page->body = fud_page::read_page_body($page->foff, $page->length, ($page->page_opt & 3));
} else {
	// Show a list of pages.
	$page_list = '';
	$i = 0;
	$c = q('SELECT id, slug, title FROM {SQL_TABLE_PREFIX}pages WHERE '. q_bitand('page_opt', 1) .' = 1 AND '. q_bitand('page_opt', 2) .' = 2');
	while ($r = db_rowobj($c)) {
		$page_list .= '{TEMPLATE: page_list_entry}';
		$i++;
	}
}

/*{POST_HTML_PHP}*/

ses_update_status($usr->sid, '{TEMPLATE: page_update}');

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PAGE_PAGE}
