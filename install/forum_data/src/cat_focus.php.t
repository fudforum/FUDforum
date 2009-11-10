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
/*{POST_HTML_PHP}*/

	if (!_uid || empty($_POST['c'])) { // nothing to do for unregistered users or missing category id
		return;
	}

	if (!($c = q_singleval('SELECT id FROM {SQL_TABLE_PREFIX}cat WHERE id='.(int)$_POST['c']))) { // invalid category id
		return;
	}

	if (($cur_status = q_singleval('SELECT cat_collapse_status FROM {SQL_TABLE_PREFIX}users WHERE id='._uid))) {
		$cur_status = unserialize($cur_status);
	} else {
		$cur_status = array();
	}

	$cur_status[$c] = (int) !empty($_POST['on']);

	q('UPDATE {SQL_TABLE_PREFIX}users SET cat_collapse_status='.($cur_status ? _esc(serialize($cur_status)) : 'NULL').' WHERE id='._uid);
