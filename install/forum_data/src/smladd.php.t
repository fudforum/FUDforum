<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: smladd.php.t,v 1.14 2005/02/27 02:44:26 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	$col_count = '{TEMPLATE: sml_per_row}' - 2;
	$col_pos = -1;

	include $FORUM_SETTINGS_PATH.'ps_cache';

	$sml_smiley_entry = $sml_smiley_row = '';
	foreach ($PS_SRC as $k => $v) {
		if ($col_pos++ > $col_count) {
			$sml_smiley_row .= '{TEMPLATE: sml_smiley_row}';
			$sml_smiley_entry = '';
			$col_pos = 0;
		}
		$sml_smiley_entry .= '{TEMPLATE: sml_smiley_entry}';
	}
	if ($col_pos > -1) {
		$sml_smiley_row .= '{TEMPLATE: sml_smiley_row}';
	} else {
		$sml_smiley_row = '{TEMPLATE: sml_no_smilies}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: SMLLIST_PAGE}
