<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: pmuserloc.php.t,v 1.26 2005/09/08 14:17:00 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	define('plain_form', 1);

/*{PRE_HTML_PHP}*/

	if (empty($_GET['js_redr'])) {
		exit;
	}

	if (!_uid) {
		std_error('login');
	} else if (!($FUD_OPT_1 & (8388608|4194304))) {
		std_error('disabled');
	}

/*{POST_HTML_PHP}*/

	$usr_login = isset($_GET['usr_login']) && is_string($_GET['usr_login']) ? trim($_GET['usr_login']) : '';
	$overwrite = isset($_GET['overwrite']) ? (int)$_GET['overwrite'] : 0;

	$js_redr = $_GET['js_redr'];
	switch ($js_redr) {
		case 'post_form.msg_to_list':
		case 'groupmgr.gr_member':
		case 'buddy_add.add_login':
			break;
		default:
			exit;
	}

	$find_user_data = '';
	if ($usr_login) {
		$c = uq("SELECT alias FROM {SQL_TABLE_PREFIX}users WHERE alias LIKE "._esc(char_fix(htmlspecialchars(addcslashes($usr_login.'%','\\'))))." AND id>1");
		$i = 0;
		while ($r = db_rowarr($c)) {
			if ($overwrite) {
				$retlink = 'javascript: window.opener.document.'.$js_redr.'.value=\''.addcslashes($r[0], "'\\").'\'; window.close();';
			} else {
				$retlink = 'javascript:
						if (!window.opener.document.'.$js_redr.'.value) {
							window.opener.document.'.$js_redr.'.value = \''.addcslashes($r[0], "'\\").'\';
						} else {
							window.opener.document.'.$js_redr.'.value = window.opener.document.'.$js_redr.'.value + \'; \' + \''.addcslashes($r[0], "'\\").'; \';
						}
					window.close();';
			}
			$find_user_data .= '{TEMPLATE: user_result_entry}';
			++$i;
		}
		unset($c);
		if (!$find_user_data) {
			$find_user_data = '{TEMPLATE: no_result_entry}';
		}
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: PMUSERLOC_PAGE}