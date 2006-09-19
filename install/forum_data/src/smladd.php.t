<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: smladd.php.t,v 1.18 2006/09/19 14:37:55 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/

	include $FORUM_SETTINGS_PATH.'ps_cache';

	$smileys = '';
	foreach ($PS_SRC as $k => $v) {
		$smileys .= '{TEMPLATE: sml_smiley_row}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: SMLLIST_PAGE}
