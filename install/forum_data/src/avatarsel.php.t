<?php
/***************************************************************************
* copyright            : (C) 2001-2003 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: avatarsel.php.t,v 1.9 2003/11/14 10:50:18 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
***************************************************************************/

	define('plain_form', 1);

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	$TITLE_EXTRA = ': {TEMPLATE: avatar_sel_form}';

	/* here we draw the avatar control */
	$icons_per_row = 5;
	$c = uq('SELECT id, descr, img FROM {SQL_TABLE_PREFIX}avatar ORDER BY id');
	$avatars_data = '';
	$col = 0;
	while ($r = db_rowarr($c)) {
		if (!($col++ % $icons_per_row)) {
			$avatars_data .= '{TEMPLATE: row_separator}';
		}
		$avatars_data .= '{TEMPLATE: avatar_entry}';
	}

	if (!$avatars_data) {
		$avatars_data = '{TEMPLATE: no_avatars}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: AVATARSEL_PAGE}