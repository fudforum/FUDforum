<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: error.php.t,v 1.4 2003/04/02 15:39:11 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

/*{PRE_HTML_PHP}*/

	if (isset($_POST['ok'])) {
		check_return($ses->returnto);
	}
	$TITLE_EXTRA = ': {TEMPLATE: error_title}';
/*{POST_HTML_PHP}*/

	if (isset($ses->data['er_msg']) && isset($ses->data['err_t'])) {
		$error_message	= $ses->data['er_msg'];
		$error_title	= $ses->data['err_t'];
		$ses->sync_vars(TRUE);
	} else {
		$error_message	= '{TEMPLATE: error_invalidurl}';
		$error_title	= '{TEMPLATE: error_error}';
	}

/*{POST_PAGE_PHP_CODE}*/
?>
{TEMPLATE: ERROR_PAGE}