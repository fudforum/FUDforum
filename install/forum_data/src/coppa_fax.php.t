<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: coppa_fax.php.t,v 1.13 2004/01/29 22:58:32 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

/*{PRE_HTML_PHP}*/

	/* this form is for printing, therefore it lacks any advanced layout */
	if (!__fud_real_user__) {
		if ($FUD_OPT_2 & 32768) {
			header('Location: {FULL_ROOT}{ROOT}/i/'._rsidl);
		} else {
			header('Location: {FULL_ROOT}{ROOT}?t=index&'._rsidl);
		}
		exit;
	}

	$name = q_singleval('SELECT name FROM {SQL_TABLE_PREFIX}users WHERE id='.__fud_real_user__);

	if (!($coppa_address = @file_get_contents($FORUM_SETTINGS_PATH.'coppa_maddress.msg'))) {
		$coppa_address = '';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: COPPAFAX_PAGE}