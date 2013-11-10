<?php
/**
* copyright            : (C) 2001-2013 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id$
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; version 2 of the License.
**/

/*{PRE_HTML_PHP}*/
	$TITLE_EXTRA = ': {TEMPLATE: coppa_conf}';

/*{POST_HTML_PHP}*/
	// Change this line if you want to increase the minimum age.
	$coppa = strtotime('-13 years');

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: COPPA_PAGE}
