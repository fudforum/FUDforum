<?php
/***************************************************************************
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: avatarsel.php.t,v 1.11 2004/03/18 00:34:28 hackie Exp $
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

	$galleries = array();
	$c = uq("SELECT DISTINCT(gallery) FROM {SQL_TABLE_PREFIX}avatar");
	while ($r = db_rowarr($c)) {
		$galleries[$r[0]] = htmlspecialchars($r[0]);
	}
	unset($c, $r);

	if (count($galleries) > 1) {
		$gal = isset($_POST['gal']) && isset($galleries[$_POST['gal']]) ? $_POST['gal'] : 'default';
		$select = tmpl_draw_select_opt(implode("\n", $galleries), implode("\n", array_keys($galleries)), $gal, '', '');
		$select = '{TEMPLATE: avatarsel_gal_sel}';
	} else {
		$gal = 'default';
		$select = '';
	}

	/* here we draw the avatar control */
	$icons_per_row = 5;
	$c = uq("SELECT id, descr, img FROM {SQL_TABLE_PREFIX}avatar WHERE gallery='".$gal."' ORDER BY id");
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