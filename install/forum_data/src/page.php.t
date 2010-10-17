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

if (isset($_GET['id'])) {
	$page = db_sab('SELECT id, slug, title, body FROM {SQL_TABLE_PREFIX}pages WHERE id='. (int)$_GET['id']);
	$TITLE_EXTRA = ': '. $page->title;
} else {
	$page_list = '';
	$i = 0;
	$c = q('SELECT id, slug, title, body FROM {SQL_TABLE_PREFIX}pages');
	while ($r = db_rowobj($c)) {
		$page_list .= '{TEMPLATE: PAGE_LIST_ENTRY}';
		$i++;
	}
}

/*{POST_HTML_PHP}*/

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PAGE_PAGE}
