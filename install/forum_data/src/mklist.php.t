<?php
/**
* copyright            : (C) 2001-2006 Advanced Internet Designs Inc.
* email                : forum@prohost.org
* $Id: mklist.php.t,v 1.21 2006/05/10 04:43:02 hackie Exp $
*
* This program is free software; you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
**/

	define('plain_form', 1);

/*{PRE_HTML_PHP}*/
/*{POST_HTML_PHP}*/
/*{POST_PAGE_PHP_CODE}*/

	if (!empty($_GET['tp']) && $_GET['tp'] == 'OL:1') {
		$def_list_type = '1';
	} else {
		$def_list_type = 'square';
	}
?>
{TEMPLATE: MKLIST_PAGE}