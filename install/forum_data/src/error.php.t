<?php
/***************************************************************************
*   copyright            : (C) 2001,2002 Advanced Internet Designs Inc.
*   email                : forum@prohost.org
*
*   $Id: error.php.t,v 1.3 2003/03/31 11:29:59 hackie Exp $
****************************************************************************
          
****************************************************************************
*
*	This program is free software; you can redistribute it and/or modify
*	it under the terms of the GNU General Public License as published by
*	the Free Software Foundation; either version 2 of the License, or
*	(at your option) any later version.
*
***************************************************************************/

	{PRE_HTML_PHP}
	
	if (isset($_POST['ok'])) {
		check_return();
	}
	$TITLE_EXTRA = ': {TEMPLATE: error_title}';
	{POST_HTML_PHP}
	
	if ($_REQUEST['err_id'] != $ses->getvar('err_id')) {
		$error_msg = '{TEMPLATE: error_invalidurl}';
		$error_title = '{TEMPLATE: error_error}';
	} else {
		$error_msg = $ses->getvar('er_msg');
		$error_title = $ses->getvar('err_t');
		$returnto = base64_decode($ses->getvar('ret_to'));
	}

	{POST_PAGE_PHP_CODE}
?>
{TEMPLATE: ERROR_PAGE}