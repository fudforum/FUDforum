<?php
/**
* copyright            : (C) 2001-2004 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: smladd.php.t,v 1.15 2005/10/18 15:51:00 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
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
