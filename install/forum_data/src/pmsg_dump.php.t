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

	if (!($FUD_OPT_1 & 1024)) {
		error_dialog('{TEMPLATE: pm_err_nopm_title}', '{TEMPLATE: pm_err_nopm_msg}');
	}

	if (__fud_real_user__) {
		is_allowed_user($usr);
	} else {
		std_error('login');
	}

	$c = q('SELECT p.subject, p.post_stamp, p.foff, p.length, u.alias
		FROM {SQL_TABLE_PREFIX}pmsg p INNER JOIN {SQL_TABLE_PREFIX}users u ON p.ouser_id=u.id
		WHERE p.duser_id='. _uid .' AND p.fldr IN(1,2,3) ORDER BY p.id');

	$out = '';
	while ($obj = db_rowobj($c)) {
		$out .= '{TEMPLATE: pmsg_dump_msg_entry}';	
	}
	unset($c);

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PMSG_DUMP_PAGE}